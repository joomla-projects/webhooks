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
use Joomla\Component\Webhooks\Administrator\Service\PayloadSerializer;
use Joomla\Component\Webhooks\Administrator\Transport\WebhookMessage;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The webhooks API controller.
 *
 * @since  __DEPLOY_VERSION__
 */
class WebhooksController extends ApiController
{
    /**
     * The content type of the item.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $contentType = 'webhooks';

    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $default_view = 'webhooks';

    /**
     * Webhook list view with filter support.
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

        if (\array_key_exists('state', $apiFilterInfo)) {
            $this->modelState->set('filter.published', $filter->clean($apiFilterInfo['state'], 'INT'));
        }

        return parent::displayList();
    }

    /**
     * Send a test delivery for a webhook.
     *
     * @return  static  A BaseController object to support chaining.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function sendTest()
    {
        $id = $this->input->getInt('id');

        if (!$id) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_RECORD_NOT_FOUND'), 404);
        }

        $user = $this->app->getIdentity();

        if (!$user->authorise('webhooks.test', 'com_webhooks')) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
        }

        $model   = $this->getModel('Webhook', 'Administrator');
        $webhook = $model->getItem($id);

        if (!$webhook || !$webhook->id) {
            throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_RECORD_NOT_FOUND'), 404);
        }

        // Get the first subscribed event for the test payload
        $events = json_decode($webhook->events, true);
        $testEvent = !empty($events) ? $events[0] : 'content.article.created';

        // Build a sample test payload
        $serializer = new PayloadSerializer();
        $sampleData = [
            'id'    => 0,
            'title' => 'Test Webhook Delivery',
            'test'  => true,
        ];

        $payload = $serializer->serialize($testEvent, $sampleData, $webhook->payload_mode ?: 'full');

        // Create a test message and deliver it
        $message = new WebhookMessage(
            webhookId: (int) $webhook->id,
            eventName: $testEvent,
            payload: $payload,
            maxAttempts: 1,
        );

        // Enqueue and immediately process
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
                'type'       => 'webhooks',
                'id'         => (string) $webhook->id,
                'attributes' => [
                    'test_result'  => $result['success'] ? 'delivered' : 'failed',
                    'status_code'  => $result['status_code'],
                    'error'        => $result['error'],
                    'duration_ms'  => $result['duration_ms'],
                ],
            ],
        ]));

        $this->app->setHeader('Content-Type', 'application/vnd.api+json');

        return $this;
    }
}
