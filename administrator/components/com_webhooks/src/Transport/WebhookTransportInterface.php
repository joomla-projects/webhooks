<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Transport;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Interface for webhook message transport backends.
 *
 * Default implementation uses the database (#__webhook_queue table).
 * Third-party plugins can provide alternative transports (Redis, RabbitMQ, etc.)
 * by registering their implementation in the component container.
 *
 * @since  __DEPLOY_VERSION__
 */
interface WebhookTransportInterface
{
    /**
     * Enqueue a message for delivery.
     *
     * @param   WebhookMessage  $message  The message to enqueue.
     *
     * @return  string|int  The queue item ID.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function enqueue(WebhookMessage $message): string|int;

    /**
     * Fetch pending messages ready for delivery.
     *
     * @param   int  $limit  Maximum number of messages to fetch.
     *
     * @return  WebhookMessage[]  Array of pending messages.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function fetchPending(int $limit): array;

    /**
     * Mark a message as currently being processed (lock it).
     *
     * @param   string|int  $id        The queue item ID.
     * @param   string      $lockedBy  Identifier for the lock holder.
     *
     * @return  bool  True if the lock was acquired.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markProcessing(string|int $id, string $lockedBy): bool;

    /**
     * Mark a message as successfully delivered.
     *
     * @param   string|int  $id  The queue item ID.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markDelivered(string|int $id): bool;

    /**
     * Mark a message as failed, optionally scheduling a retry.
     *
     * @param   string|int               $id           The queue item ID.
     * @param   ?\DateTimeInterface       $nextRetryAt  When to retry, or null if no retry.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markFailed(string|int $id, ?\DateTimeInterface $nextRetryAt): bool;

    /**
     * Mark a message as dead (permanently failed, no more retries).
     *
     * @param   string|int  $id  The queue item ID.
     *
     * @return  bool  True on success.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function markDead(string|int $id): bool;

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
    public function purge(\DateTimeInterface $olderThan, array $statuses): int;
}
