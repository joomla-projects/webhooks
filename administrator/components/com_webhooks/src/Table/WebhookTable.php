<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Table;

use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Webhook table class.
 *
 * @since  __DEPLOY_VERSION__
 */
class WebhookTable extends Table
{
    /**
     * Indicates that columns fully support the NULL value in the database.
     *
     * @var    boolean
     * @since  __DEPLOY_VERSION__
     */
    protected $_supportNullValue = true;

    /**
     * Constructor.
     *
     * @param   DatabaseInterface     $db          Database connector object.
     * @param   ?DispatcherInterface  $dispatcher  Event dispatcher for this table.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_webhooks.webhook';

        parent::__construct('#__webhooks', 'id', $db, $dispatcher);

        $this->created  = Factory::getDate()->toSql();
        $this->modified = Factory::getDate()->toSql();

        $this->setColumnAlias('published', 'state');
    }

    /**
     * Overloaded check function.
     *
     * @return  boolean  True if the object is ok.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        // Check for valid title
        if (trim($this->title) === '') {
            $this->setError('COM_WEBHOOKS_ERROR_TITLE_REQUIRED');

            return false;
        }

        // Generate alias from title if empty
        if (trim($this->alias) === '') {
            $this->alias = $this->title;
        }

        $this->alias = ApplicationHelper::stringURLSafe($this->alias);

        if (trim(str_replace('-', '', $this->alias)) === '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        // Check for valid URL
        if (trim($this->url) === '') {
            $this->setError('COM_WEBHOOKS_ERROR_URL_REQUIRED');

            return false;
        }

        // Validate webhook URL to prevent SSRF
        if (!$this->isAllowedUrl($this->url)) {
            $this->setError('COM_WEBHOOKS_ERROR_URL_NOT_ALLOWED');

            return false;
        }

        // Ensure events is a valid JSON array
        if (empty($this->events)) {
            $this->events = '[]';
        } else {
            $decoded = json_decode($this->events, true);

            if (!is_array($decoded)) {
                $this->setError('COM_WEBHOOKS_ERROR_EVENTS_INVALID_JSON');

                return false;
            }
        }

        // Ensure params is valid JSON
        if (empty($this->params)) {
            $this->params = '{}';
        }

        // Ensure conditions is a valid JSON array
        if (empty($this->conditions)) {
            $this->conditions = '[]';
        } else {
            $decoded = json_decode($this->conditions, true);

            if (!is_array($decoded)) {
                $this->setError('COM_WEBHOOKS_ERROR_CONDITIONS_INVALID_JSON');

                return false;
            }
        }

        return true;
    }

    /**
     * Validate a webhook URL against an allowlist of schemes and a blocklist of private/reserved IP ranges.
     *
     * Accepts only http:// and https:// URLs whose resolved IP address is not a private,
     * loopback, link-local, or cloud-metadata address.
     *
     * @param   string  $url  The URL to validate.
     *
     * @return  bool  True if the URL is safe to use as a webhook target.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function isAllowedUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);

        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = $parsed['host'] ?? '';

        if ($host === '') {
            return false;
        }

        // Resolve the hostname to an IP address
        $ip = gethostbyname($host);

        // If resolution fails gethostbyname returns the original hostname unchanged
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            // Could not resolve; reject to be safe
            return false;
        }

        return !$this->isPrivateOrReservedIp($ip);
    }

    /**
     * Check whether an IP address falls within a private, loopback, link-local,
     * or otherwise reserved range that must not be reachable from webhook deliveries.
     *
     * @param   string  $ip  An IPv4 or IPv6 address.
     *
     * @return  bool  True if the address is private or reserved.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function isPrivateOrReservedIp(string $ip): bool
    {
        // Use PHP's built-in flags to detect private and reserved ranges
        $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

        if (filter_var($ip, FILTER_VALIDATE_IP, $flags) === false) {
            return true;
        }

        // Additionally block cloud metadata endpoints not covered by the flags above
        $blockedIps = [
            '169.254.169.254', // AWS / GCP / Azure IMDS
            '100.100.100.200', // Alibaba Cloud IMDS
        ];

        // Merge in any administrator-configured extra blocked IPs (valid IPv4/IPv6 only)
        $extraParam = ComponentHelper::getParams('com_webhooks')->get('extra_blocked_ips', '');
        $extraIps   = array_filter(
            array_map('trim', explode("\n", $extraParam)),
            static fn(string $entry): bool => $entry !== '' && filter_var($entry, FILTER_VALIDATE_IP) !== false
        );
        $blockedIps = array_merge($blockedIps, $extraIps);

        if (in_array($ip, $blockedIps, true)) {
            return true;
        }

        return false;
    }

    /**
     * Overloaded store function.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function store($updateNulls = true)
    {
        return parent::store($updateNulls);
    }

    /**
     * Reset the consecutive failure counter for a webhook.
     *
     * @param   int  $id  The webhook ID.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function resetConsecutiveFailures(int $id): bool
    {
        $db = $this->getDbo();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__webhooks'))
            ->set($db->quoteName('consecutive_failures') . ' = 0')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        return true;
    }

    /**
     * Increment the consecutive failure counter for a webhook.
     *
     * @param   int  $id  The webhook ID.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function incrementConsecutiveFailures(int $id): bool
    {
        $db = $this->getDbo();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__webhooks'))
            ->set($db->quoteName('consecutive_failures') . ' = ' . $db->quoteName('consecutive_failures') . ' + 1')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        return true;
    }

    /**
     * Read the current consecutive failure count for a webhook from the database.
     *
     * Always performs a fresh SELECT so concurrent updates are reflected.
     *
     * @param   int  $id  The webhook ID.
     *
     * @return  int  The current consecutive failure count, or 0 if the record is not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getConsecutiveFailures(int $id): int
    {
        $db = $this->getDbo();

        $query = $db->getQuery(true)
            ->select($db->quoteName('consecutive_failures'))
            ->from($db->quoteName('#__webhooks'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);
        $value = $db->loadResult();

        return $value !== null ? (int) $value : 0;
    }

    /**
     * Reset the circuit breaker state for a webhook.
     *
     * Clears consecutive failures, disabled_at, and half-open flag atomically.
     *
     * @param   int  $id  The webhook ID.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function resetCircuitBreaker(int $id): bool
    {
        $db = $this->getDbo();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__webhooks'))
            ->set($db->quoteName('consecutive_failures') . ' = 0')
            ->set($db->quoteName('disabled_at') . ' = NULL')
            ->set($db->quoteName('circuit_breaker_half_open') . ' = 0')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        return true;
    }

    /**
     * Set or clear the half-open circuit breaker flag.
     *
     * When transitioning from half-open back to open (on failure), also
     * updates disabled_at to now to restart the cooldown period.
     *
     * @param   int   $id        The webhook ID.
     * @param   bool  $halfOpen  True to set half-open, false to clear.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setHalfOpen(int $id, bool $halfOpen): bool
    {
        $db    = $this->getDbo();
        $value = $halfOpen ? 1 : 0;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__webhooks'))
            ->set($db->quoteName('circuit_breaker_half_open') . ' = :halfOpen')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':halfOpen', $value, ParameterType::INTEGER)
            ->bind(':id', $id, ParameterType::INTEGER);

        // When setting half-open, guard against race: only set if currently 0
        if ($halfOpen) {
            $zero = 0;
            $query->where($db->quoteName('circuit_breaker_half_open') . ' = :currentValue')
                ->bind(':currentValue', $zero, ParameterType::INTEGER);
        } else {
            // When clearing half-open (failure), restart cooldown by updating disabled_at
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
            $query->set($db->quoteName('disabled_at') . ' = :now')
                ->bind(':now', $now);
        }

        $db->setQuery($query);
        $db->execute();

        // Return true only if a row was actually affected
        return $db->getAffectedRows() > 0;
    }

    /**
     * Set the disabled_at timestamp to now (without changing state).
     *
     * Used by cooldown mode to mark when the circuit breaker first tripped.
     *
     * @param   int  $id  The webhook ID.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function setDisabledAt(int $id): bool
    {
        $db  = $this->getDbo();
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__webhooks'))
            ->set($db->quoteName('disabled_at') . ' = :now')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':now', $now)
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        return true;
    }

    /**
     * Disable a webhook via circuit breaker by setting state=0 and recording the disabled_at timestamp.
     *
     * @param   int  $id  The webhook ID.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function disableByCircuitBreaker(int $id): bool
    {
        $db  = $this->getDbo();
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__webhooks'))
            ->set($db->quoteName('state') . ' = 0')
            ->set($db->quoteName('disabled_at') . ' = :now')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':now', $now)
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        return true;
    }
}
