<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Service;

use Joomla\CMS\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Converts event data to JSON:API format for webhook delivery.
 *
 * Supports 'full' mode (complete entity) and 'minimal' mode (ID + changed fields only).
 *
 * @since  __DEPLOY_VERSION__
 */
class PayloadSerializer
{
    /**
     * Serialize event data into a JSON:API formatted payload.
     *
     * @param   string  $eventName    The webhook event name (e.g., 'content.article.created').
     * @param   array   $data         The raw event data.
     * @param   string  $payloadMode  'full' or 'minimal'.
     *
     * @return  string  JSON-encoded payload.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function serialize(string $eventName, array $data, string $payloadMode = 'full'): string
    {
        $type = $this->resolveType($eventName);
        $id   = (string) ($data['id'] ?? '0');

        $attributes = $payloadMode === 'minimal'
            ? $this->extractMinimalAttributes($data)
            : $this->extractFullAttributes($data);

        $payload = [
            'data' => [
                'type'       => $type,
                'id'         => $id,
                'attributes' => $attributes,
            ],
            'meta' => [
                'event'     => $eventName,
                'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c'),
                'site_url'  => Uri::root(),
            ],
        ];

        return json_encode($payload, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * Resolve the JSON:API type name from a webhook event name.
     *
     * @param   string  $eventName  The webhook event name.
     *
     * @return  string  The JSON:API type (e.g., 'articles', 'users').
     *
     * @since  __DEPLOY_VERSION__
     */
    private function resolveType(string $eventName): string
    {
        $parts = explode('.', $eventName);

        // Map first segment to JSON:API type names
        $typeMap = [
            'content'   => 'articles',
            'user'      => 'users',
            'category'  => 'categories',
            'contact'   => 'contacts',
            'extension' => 'extensions',
        ];

        return $typeMap[$parts[0]] ?? $parts[0];
    }

    /**
     * Fields that must never appear in outbound webhook payloads regardless of payload mode.
     *
     * @var    string[]
     * @since  __DEPLOY_VERSION__
     */
    private const SENSITIVE_FIELDS = [
        'id',            // promoted to top-level data.id
        'password',
        'secret',
        'otpKey',
        'otep',
        'api_token',
        'requireReset',
        'resetCount',
        'lastResetTime',
        'authProvider',
        'activation',
        'params',        // may contain 2FA configuration
        '_changed_fields', // internal bookkeeping key
    ];

    /**
     * Extract full attributes from event data.
     *
     * Removes internal/sensitive fields.
     *
     * @param   array  $data  Raw event data.
     *
     * @return  array  Cleaned attributes.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function extractFullAttributes(array $data): array
    {
        return $this->sanitizeAttributes($data);
    }

    /**
     * Extract minimal attributes (ID + changed fields only).
     *
     * If a '_changed_fields' key is present, only those fields are included.
     * Otherwise falls back to full attributes.
     *
     * @param   array  $data  Raw event data.
     *
     * @return  array  Minimal attributes.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function extractMinimalAttributes(array $data): array
    {
        if (!isset($data['_changed_fields']) || !\is_array($data['_changed_fields'])) {
            return $this->extractFullAttributes($data);
        }

        $changedFields = $data['_changed_fields'];
        $attributes = [];

        foreach ($changedFields as $field) {
            if (isset($data[$field])) {
                $attributes[$field] = $data[$field];
            }
        }

        return $this->sanitizeAttributes($attributes);
    }

    /**
     * Remove all sensitive fields from an attributes array.
     *
     * @param   array  $attributes  Raw attributes.
     *
     * @return  array  Sanitized attributes with sensitive keys removed.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function sanitizeAttributes(array $attributes): array
    {
        foreach (self::SENSITIVE_FIELDS as $field) {
            unset($attributes[$field]);
        }

        return $attributes;
    }
}
