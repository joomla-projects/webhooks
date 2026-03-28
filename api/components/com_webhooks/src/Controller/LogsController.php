<?php

/**
 * @package     Joomla.API
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Api\Controller;

use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\Component\Webhooks\Administrator\Extension\WebhooksComponent;
use Joomla\Component\Webhooks\Administrator\Transport\WebhookMessage;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The webhook logs API controller.
 *
 * @since  __DEPLOY_VERSION__
 */
class LogsController extends ApiController
{
    /**
     * The content type of the item.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $contentType = 'webhooklogs';

    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $default_view = 'logs';

    /**
     * Log list view with filter support.
     *
     * @return  static  A BaseController object to support chaining.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function displayList()
    {
        if (!$this->app->getIdentity()->authorise('core.manage', 'com_webhooks')) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $apiFilterInfo = $this->input->get('filter', [], 'array');
        $filter        = InputFilter::getInstance();

        // Filter by webhook ID from route parameter or filter parameter
        $routeWebhookId = $this->input->getInt('id');

        if ($routeWebhookId) {
            $this->modelState->set('filter.webhook_id', $routeWebhookId);
        } elseif (\array_key_exists('webhook_id', $apiFilterInfo)) {
            $this->modelState->set('filter.webhook_id', $filter->clean($apiFilterInfo['webhook_id'], 'INT'));
        }

        if (\array_key_exists('event_name', $apiFilterInfo)) {
            $this->modelState->set('filter.event_name', $filter->clean($apiFilterInfo['event_name'], 'STRING'));
        }

        if (\array_key_exists('success', $apiFilterInfo)) {
            $this->modelState->set('filter.success', $filter->clean($apiFilterInfo['success'], 'INT'));
        }

        return parent::displayList();
    }

    /**
     * Replay a past delivery.
     *
     * @return  static  A BaseController object to support chaining.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function replay()
    {
        $webhookId = $this->input->getInt('id');
        $logId     = $this->input->getInt('logId');

        if (!$webhookId || !$logId) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_RECORD_NOT_FOUND'), 404);
        }

        $user = $this->app->getIdentity();

        if (!$user->authorise('webhooks.replay', 'com_webhooks')) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        // Load the log entry via table
        /** @var \Joomla\Component\Webhooks\Administrator\Table\WebhookLogTable $logTable */
        $logTable = $this->getModel('Log', 'Administrator')->getTable('WebhookLog');
        $logTable->load($logId);

        if (!$logTable->id || (int) $logTable->webhook_id !== $webhookId) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_RECORD_NOT_FOUND'), 404);
        }

        // Load the webhook
        $model   = $this->getModel('Webhook', 'Administrator');
        $webhook = $model->getItem($webhookId);

        if (!$webhook || !$webhook->id) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_RECORD_NOT_FOUND'), 404);
        }

        // Get the original payload from the log entry
        $payload = $logTable->request_body;

        if (empty($payload)) {
            // If verbose logging was off, we can't replay the exact payload
            throw new \RuntimeException(Text::_('COM_WEBHOOKS_ERROR_NO_PAYLOAD_FOR_REPLAY'), 400);
        }

        // Create a new message and deliver it
        $message = new WebhookMessage(
            webhookId: (int) $webhook->id,
            eventName: $logTable->event_name,
            payload: $payload,
            maxAttempts: 1,
        );

        /** @var WebhooksComponent $component */
        $component       = $this->app->bootComponent('com_webhooks');
        $transport       = $component->getTransport();
        $deliveryService = $component->getDeliveryService();

        $transport->enqueue($message);
        $result = $deliveryService->deliver($message, $webhook);

        if ($result['success']) {
            $transport->markDelivered($message->id);
        } else {
            $transport->markDead($message->id);
        }

        $this->app->getDocument()->setBuffer(json_encode([
            'data' => [
                'type'       => 'webhooklogs',
                'id'         => (string) $logId,
                'attributes' => [
                    'replay_result' => $result['success'] ? 'delivered' : 'failed',
                    'status_code'   => $result['status_code'],
                    'error'         => $result['error'],
                    'duration_ms'   => $result['duration_ms'],
                ],
            ],
        ]));

        $this->app->setHeader('Content-Type', 'application/vnd.api+json');

        return $this;
    }
}
