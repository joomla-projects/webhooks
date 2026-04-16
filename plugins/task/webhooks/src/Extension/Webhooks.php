<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.Webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Task\Webhooks\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Component\Webhooks\Administrator\Extension\WebhooksComponent;
use Joomla\Component\Webhooks\Administrator\Transport\WebhookMessage;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Task plugin for webhook queue processing and log cleanup.
 *
 * Provides two scheduled task routines:
 * - processQueue: Processes pending webhook deliveries
 * - cleanupLogs: Purges old delivery logs and completed queue items
 *
 * @since  __DEPLOY_VERSION__
 */
final class Webhooks extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * @var    string[]
     * @since  __DEPLOY_VERSION__
     */
    protected const TASKS_MAP = [
        'webhooks.processQueue' => [
            'langConstPrefix' => 'PLG_TASK_WEBHOOKS_PROCESS_QUEUE',
            'form'            => 'processqueue_params',
            'method'          => 'processQueue',
        ],
        'webhooks.cleanupLogs' => [
            'langConstPrefix' => 'PLG_TASK_WEBHOOKS_CLEANUP_LOGS',
            'form'            => 'cleanuplogs_params',
            'method'          => 'cleanupLogs',
        ],
    ];

    /**
     * @var    boolean
     * @since  __DEPLOY_VERSION__
     */
    protected $autoloadLanguage = true;

    /**
     * @inheritDoc
     *
     * @return  string[]
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Process the webhook delivery queue.
     *
     * @param   ExecuteTaskEvent  $event  The task event.
     *
     * @return  int  Task status code.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function processQueue(ExecuteTaskEvent $event): int
    {
        $params    = $event->getArgument('params');
        $batchSize = (int) ($params->batch_size ?? 50);

        try {
            /** @var WebhooksComponent $component */
            $component       = $this->getApplication()->bootComponent('com_webhooks');
            $deliveryService = $component->getDeliveryService();

            $result = $deliveryService->processQueue($batchSize);

            $this->logTask(
                sprintf(
                    'Processed %d messages: %d delivered, %d failed',
                    $result['processed'],
                    $result['delivered'],
                    $result['failed']
                )
            );

            return TaskStatus::OK;
        } catch (\Exception $e) {
            $this->logTask('Queue processing failed: ' . $e->getMessage(), 'error');

            return TaskStatus::KNOCKOUT;
        }
    }

    /**
     * Clean up old delivery logs and completed queue items.
     *
     * @param   ExecuteTaskEvent  $event  The task event.
     *
     * @return  int  Task status code.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function cleanupLogs(ExecuteTaskEvent $event): int
    {
        $params        = $event->getArgument('params');
        $retentionDays = (int) ($params->retention_days ?? 30);

        try {
            /** @var WebhooksComponent $component */
            $component = $this->getApplication()->bootComponent('com_webhooks');
            $threshold = (new \DateTimeImmutable('-' . $retentionDays . ' days', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

            // Purge old logs via model
            $logsModel   = $component->getMVCFactory()->createModel('Logs', 'Administrator');
            $logsDeleted = $logsModel->purgeOlderThan($threshold);

            // Purge old completed/dead queue items via transport
            $transport   = $component->getTransport();
            $olderThan   = new \DateTimeImmutable('-' . $retentionDays . ' days', new \DateTimeZone('UTC'));
            $queuePurged = $transport->purge($olderThan, [WebhookMessage::STATUS_DELIVERED, WebhookMessage::STATUS_DEAD]);

            $this->logTask(
                sprintf(
                    'Cleanup complete: %d logs deleted, %d queue items purged',
                    $logsDeleted,
                    $queuePurged
                )
            );

            return TaskStatus::OK;
        } catch (\Exception $e) {
            $this->logTask('Log cleanup failed: ' . $e->getMessage(), 'error');

            return TaskStatus::KNOCKOUT;
        }
    }
}
