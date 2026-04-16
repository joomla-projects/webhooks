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
 * Value object representing a webhook message in the queue.
 *
 * @since  __DEPLOY_VERSION__
 */
class WebhookMessage
{
    /**
     * Queue status constants.
     *
     * @since  __DEPLOY_VERSION__
     */
    public const STATUS_PENDING    = 0;
    public const STATUS_PROCESSING = 1;
    public const STATUS_DELIVERED  = 2;
    public const STATUS_FAILED     = 3;
    public const STATUS_DEAD       = 4;

    /**
     * Constructor.
     *
     * @param   string|int|null  $id            Queue item ID (null for new messages).
     * @param   int              $webhookId     The webhook subscription ID.
     * @param   string           $eventName     The webhook event name.
     * @param   string           $payload       Serialized JSON:API payload.
     * @param   int              $maxAttempts   Maximum delivery attempts.
     * @param   int              $attempts      Current number of attempts.
     * @param   int              $status        Current status.
     * @param   ?\DateTimeInterface  $nextRetryAt  When to retry next.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        public string|int|null $id = null,
        public int $webhookId = 0,
        public string $eventName = '',
        public string $payload = '',
        public int $maxAttempts = 5,
        public int $attempts = 0,
        public int $status = self::STATUS_PENDING,
        public ?\DateTimeInterface $nextRetryAt = null,
    ) {
    }
}
