<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Transport;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Database-backed webhook message transport.
 *
 * Uses the #__webhook_queue table with a Compare-And-Swap (CAS) pattern in
 * markProcessing() for concurrent-safe message claiming.
 *
 * @since  __DEPLOY_VERSION__
 */
class DatabaseTransport implements WebhookTransportInterface
{
    use DatabaseAwareTrait;

    /**
     * Enqueue a message for delivery.
     *
     * @param   WebhookMessage  $message  The message to enqueue.
     *
     * @return  string|int  The inserted queue item ID.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function enqueue(WebhookMessage $message): string|int
    {
        $db  = $this->getDatabase();
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $record = (object) [
            'webhook_id'     => $message->webhookId,
            'event_name'     => $message->eventName,
            'payload'        => $message->payload,
            'status'         => WebhookMessage::STATUS_PENDING,
            'attempts'       => 0,
            'next_attempt_at' => $now,
            'locked_at'      => null,
            'created'        => $now,
        ];

        $db->insertObject('#__webhook_queue', $record, 'id');

        $message->id = (int) $record->id;

        return $message->id;
    }

    /**
     * Fetch pending messages ready for delivery.
     *
     * Returns candidate messages; actual claiming is done by markProcessing()
     * using a CAS pattern. Also picks up messages with stale locks (older than 5 minutes).
     *
     * @param   int  $limit  Maximum number of messages to fetch.
     *
     * @return  WebhookMessage[]  Array of pending messages.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function fetchPending(int $limit): array
    {
        $db             = $this->getDatabase();
        $now            = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $staleLockSecs  = (int) ComponentHelper::getParams('com_webhooks')->get('queue_lock_timeout', 60);
        $staleThreshold = (new \DateTimeImmutable('-' . $staleLockSecs . ' seconds', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__webhook_queue'))
            ->where('((' . $db->quoteName('status') . ' = :pending AND ' . $db->quoteName('next_attempt_at') . ' <= :now)'
                . ' OR (' . $db->quoteName('status') . ' = :processing AND ' . $db->quoteName('locked_at') . ' <= :stale))')
            ->order($db->quoteName('created') . ' ASC')
            ->bind(':pending', $statusPending, ParameterType::INTEGER)
            ->bind(':processing', $statusProcessing, ParameterType::INTEGER)
            ->bind(':now', $now)
            ->bind(':stale', $staleThreshold)
            ->setLimit($limit);

        $statusPending    = WebhookMessage::STATUS_PENDING;
        $statusProcessing = WebhookMessage::STATUS_PROCESSING;

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $messages = [];

        foreach ($rows as $row) {
            $messages[] = new WebhookMessage(
                id: (int) $row->id,
                webhookId: (int) $row->webhook_id,
                eventName: $row->event_name,
                payload: $row->payload,
                attempts: (int) $row->attempts,
                status: (int) $row->status,
                nextRetryAt: $row->next_attempt_at ? new \DateTimeImmutable($row->next_attempt_at, new \DateTimeZone('UTC')) : null,
            );
        }

        return $messages;
    }

    /**
     * Mark a message as currently being processed.
     *
     * @param   string|int  $id        The queue item ID.
     * @param   string      $lockedBy  Identifier for the lock holder (unused in DB transport, timestamp used instead).
     *
     * @return  bool  True if the lock was acquired.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markProcessing(string|int $id, string $lockedBy): bool
    {
        $db             = $this->getDatabase();
        $now            = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $staleLockSecs  = (int) ComponentHelper::getParams('com_webhooks')->get('queue_lock_timeout', 60);
        $staleThreshold = (new \DateTimeImmutable('-' . $staleLockSecs . ' seconds', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__webhook_queue'))
            ->set($db->quoteName('status') . ' = :status')
            ->set($db->quoteName('locked_at') . ' = :now')
            ->where($db->quoteName('id') . ' = :id')
            ->where('('
                . '(' . $db->quoteName('status') . ' = :pendingStatus AND ' . $db->quoteName('next_attempt_at') . ' <= :now2)'
                . ' OR '
                . '(' . $db->quoteName('status') . ' = :processingStatus AND ' . $db->quoteName('locked_at') . ' <= :stale)'
                . ')')
            ->bind(':status', $statusProcessing, ParameterType::INTEGER)
            ->bind(':now', $now)
            ->bind(':id', $id, ParameterType::INTEGER)
            ->bind(':pendingStatus', $statusPending, ParameterType::INTEGER)
            ->bind(':now2', $now)
            ->bind(':processingStatus', $statusProcessingGuard, ParameterType::INTEGER)
            ->bind(':stale', $staleThreshold);

        $statusProcessing      = WebhookMessage::STATUS_PROCESSING;
        $statusPending         = WebhookMessage::STATUS_PENDING;
        $statusProcessingGuard = WebhookMessage::STATUS_PROCESSING;

        $db->setQuery($query);
        $db->execute();

        return $db->getAffectedRows() > 0;
    }

    /**
     * Mark a message as successfully delivered.
     *
     * @param   string|int  $id  The queue item ID.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markDelivered(string|int $id): bool
    {
        return $this->updateStatus($id, WebhookMessage::STATUS_DELIVERED);
    }

    /**
     * Mark a message as failed, optionally scheduling a retry.
     *
     * @param   string|int               $id           The queue item ID.
     * @param   ?\DateTimeInterface       $nextRetryAt  When to retry, or null.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markFailed(string|int $id, ?\DateTimeInterface $nextRetryAt): bool
    {
        $db = $this->getDatabase();

        $nextAttempt = $nextRetryAt ? $nextRetryAt->format('Y-m-d H:i:s') : null;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__webhook_queue'))
            ->set($db->quoteName('status') . ' = :status')
            ->set($db->quoteName('attempts') . ' = ' . $db->quoteName('attempts') . ' + 1')
            ->set($db->quoteName('locked_at') . ' = NULL')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':status', $statusFailed, ParameterType::INTEGER)
            ->bind(':id', $id, ParameterType::INTEGER);

        $statusFailed = WebhookMessage::STATUS_FAILED;

        if ($nextAttempt !== null) {
            $query->set($db->quoteName('next_attempt_at') . ' = :next')
                ->bind(':next', $nextAttempt);

            // Reset to pending so it will be picked up again
            $statusFailed = WebhookMessage::STATUS_PENDING;
        }

        $db->setQuery($query);
        $db->execute();

        return $db->getAffectedRows() > 0;
    }

    /**
     * Mark a message as dead (permanently failed).
     *
     * @param   string|int  $id  The queue item ID.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markDead(string|int $id): bool
    {
        return $this->updateStatus($id, WebhookMessage::STATUS_DEAD);
    }

    /**
     * Purge old messages by status and age.
     *
     * @param   \DateTimeInterface  $olderThan  Purge messages older than this date.
     * @param   array               $statuses   Array of status values to purge.
     *
     * @return  int  Number of purged messages.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function purge(\DateTimeInterface $olderThan, array $statuses): int
    {
        if (empty($statuses)) {
            return 0;
        }

        $db        = $this->getDatabase();
        $threshold = $olderThan->format('Y-m-d H:i:s');

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__webhook_queue'))
            ->where($db->quoteName('created') . ' < :threshold')
            ->whereIn($db->quoteName('status'), $statuses)
            ->bind(':threshold', $threshold);

        $db->setQuery($query);
        $db->execute();

        return $db->getAffectedRows();
    }

    /**
     * Update a queue item's status and clear its lock.
     *
     * @param   string|int  $id      The queue item ID.
     * @param   int         $status  The new status.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function updateStatus(string|int $id, int $status): bool
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__webhook_queue'))
            ->set($db->quoteName('status') . ' = :status')
            ->set($db->quoteName('locked_at') . ' = NULL')
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':status', $status, ParameterType::INTEGER)
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query);
        $db->execute();

        return $db->getAffectedRows() > 0;
    }
}
