<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Component\Webhooks\Administrator\Transport\WebhookMessage;
use Joomla\Component\Webhooks\Administrator\Transport\WebhookTransportInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Finds matching webhook subscriptions for events, evaluates conditions, and enqueues messages.
 *
 * For 'individual' mode: enqueues immediately per matching webhook.
 * For 'batched' mode: accumulates in memory, call flushBatched() on onBeforeRespond.
 *
 * @since  __DEPLOY_VERSION__
 */
class QueueManager
{
    use DatabaseAwareTrait;

    /**
     * Accumulated batched events (webhookId => messages[]).
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    private array $batchBuffer = [];

    /**
     * Constructor.
     *
     * @param   WebhookTransportInterface  $transport   The message transport.
     * @param   PayloadSerializer          $serializer  The payload serializer.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private WebhookTransportInterface $transport,
        private PayloadSerializer $serializer,
    ) {
    }

    /**
     * Dispatch an event: find matching webhooks, evaluate conditions, and enqueue messages.
     *
     * @param   string  $webhookEventName  The webhook event name (e.g., 'content.article.created').
     * @param   array   $eventData         The raw event data.
     *
     * @return  int  Number of messages enqueued.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function dispatch(string $webhookEventName, array $eventData): int
    {
        $webhooks = $this->findMatchingWebhooks($webhookEventName);

        if (empty($webhooks)) {
            return 0;
        }

        $enqueued = 0;

        foreach ($webhooks as $webhook) {
            // Only verified webhooks receive deliveries
            if ((int) $webhook->verified !== 1) {
                continue;
            }

            if (!$this->evaluateConditions($webhook, $eventData)) {
                continue;
            }

            $payload = $this->serializer->serialize(
                $webhookEventName,
                $eventData,
                $webhook->payload_mode ?: 'full'
            );

            $maxPayloadBytes = (int) ComponentHelper::getParams('com_webhooks')->get('max_payload_size_kb', 1024) * 1024;

            if (\strlen($payload) > $maxPayloadBytes) {
                // Payload too large — skip this delivery to prevent queue and memory abuse
                continue;
            }

            $message = new WebhookMessage(
                webhookId: (int) $webhook->id,
                eventName: $webhookEventName,
                payload: $payload,
                maxAttempts: (int) ($webhook->retry_count ?: 5),
            );

            if ($webhook->batch_mode === 'batched') {
                $this->batchBuffer[(int) $webhook->id][] = $message;
            } else {
                $this->transport->enqueue($message);
                $enqueued++;
            }
        }

        return $enqueued;
    }

    /**
     * Flush all accumulated batched messages to the transport.
     *
     * Should be called on onBeforeRespond.
     *
     * @return  int  Number of messages flushed.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function flushBatched(): int
    {
        $flushed = 0;

        foreach ($this->batchBuffer as $messages) {
            foreach ($messages as $message) {
                $this->transport->enqueue($message);
                $flushed++;
            }
        }

        $this->batchBuffer = [];

        return $flushed;
    }

    /**
     * Find active webhook subscriptions that match the given event name.
     *
     * @param   string  $webhookEventName  The webhook event name.
     *
     * @return  object[]  Array of webhook records.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function findMatchingWebhooks(string $webhookEventName): array
    {
        $db = $this->getDatabase();

        $eventJson = json_encode($webhookEventName);

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__webhooks'))
            ->where($db->quoteName('state') . ' = 1')
            ->where('JSON_CONTAINS(' . $db->quoteName('events') . ', :event)')
            ->bind(':event', $eventJson);

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    /**
     * Evaluate field conditions against event data.
     *
     * Conditions JSON format: [{"field": "catid", "operator": "eq", "value": "5"}, ...]
     * Supported operators: eq, neq, gt, lt, gte, lte, in, contains
     *
     * All conditions must match (AND logic).
     *
     * @param   object  $webhook    The webhook record.
     * @param   array   $eventData  The event data to check against.
     *
     * @return  bool  True if all conditions match (or no conditions set).
     *
     * @since  __DEPLOY_VERSION__
     */
    private function evaluateConditions(object $webhook, array $eventData): bool
    {
        if (empty($webhook->conditions)) {
            return true;
        }

        $conditions = json_decode($webhook->conditions, true);

        // Fail-closed: malformed JSON must never allow unintended delivery
        if ($conditions === null && json_last_error() !== \JSON_ERROR_NONE) {
            return false;
        }

        if (empty($conditions) || !\is_array($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            $field    = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'eq';
            $expected = $condition['value'] ?? '';

            if ($field === '' || !array_key_exists($field, $eventData)) {
                return false;
            }

            $actual = $eventData[$field];

            if (!$this->matchCondition($actual, $operator, $expected)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition.
     *
     * @param   mixed   $actual    The actual field value.
     * @param   string  $operator  The comparison operator.
     * @param   mixed   $expected  The expected value.
     *
     * @return  bool  True if the condition matches.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function matchCondition(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'eq'       => (string) $actual === (string) $expected,
            'neq'      => (string) $actual !== (string) $expected,
            'gt'       => (float) $actual > (float) $expected,
            'lt'       => (float) $actual < (float) $expected,
            'gte'      => (float) $actual >= (float) $expected,
            'lte'      => (float) $actual <= (float) $expected,
            'in'       => \in_array((string) $actual, array_map('trim', explode(',', (string) $expected)), true),
            'contains' => str_contains((string) $actual, (string) $expected),
            default    => false,
        };
    }
}
