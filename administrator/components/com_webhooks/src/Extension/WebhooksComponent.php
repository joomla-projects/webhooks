<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Extension;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\Webhooks\Administrator\Service\CircuitBreaker;
use Joomla\Component\Webhooks\Administrator\Service\DeliveryService;
use Joomla\Component\Webhooks\Administrator\Service\EventRegistry;
use Joomla\Component\Webhooks\Administrator\Service\HmacSigner;
use Joomla\Component\Webhooks\Administrator\Service\PayloadSerializer;
use Joomla\Component\Webhooks\Administrator\Service\QueueManager;
use Joomla\Component\Webhooks\Administrator\Table\WebhookLogTable;
use Joomla\Component\Webhooks\Administrator\Table\WebhookTable;
use Joomla\Component\Webhooks\Administrator\Transport\DatabaseTransport;
use Joomla\Component\Webhooks\Administrator\Transport\WebhookTransportInterface;
use Joomla\Component\Webhooks\Administrator\Transport\WebhookTransportProviderInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Http\HttpFactory;
use Psr\Container\ContainerInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Component class for com_webhooks
 *
 * @since  __DEPLOY_VERSION__
 */
class WebhooksComponent extends MVCComponent implements BootableExtensionInterface
{
    /**
     * The component's service container, set during boot.
     *
     * @var    ?ContainerInterface
     * @since  __DEPLOY_VERSION__
     */
    private ?ContainerInterface $serviceContainer = null;

    /**
     * Booting the extension. Registers custom services in the container.
     *
     * @param   ContainerInterface  $container  The container
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function boot(ContainerInterface $container)
    {
        $this->serviceContainer = $container;

        $container->set(
            WebhookTransportInterface::class,
            function (ContainerInterface $container): WebhookTransportInterface {
                $app = Factory::getApplication();

                foreach (PluginHelper::getPlugin('webhooks') as $plugin) {
                    try {
                        $instance = $app->bootPlugin($plugin->name, 'webhooks');

                        if ($instance instanceof WebhookTransportProviderInterface) {
                            return $instance->getTransport($container);
                        }
                    } catch (\Throwable $e) {
                        // Skip broken plugins; fall through to default
                    }
                }

                // Default: database transport
                $transport = new DatabaseTransport();
                $transport->setDatabase($container->get(DatabaseInterface::class));

                return $transport;
            }
        );

        $container->set(
            EventRegistry::class,
            function (ContainerInterface $container) {
                return new EventRegistry();
            }
        );

        $container->set(
            HmacSigner::class,
            function (ContainerInterface $container) {
                return new HmacSigner();
            }
        );

        $container->set(
            PayloadSerializer::class,
            function (ContainerInterface $container) {
                return new PayloadSerializer();
            }
        );

        $container->set(
            WebhookTable::class,
            function (ContainerInterface $container) {
                return new WebhookTable($container->get(DatabaseInterface::class));
            }
        );

        $container->set(
            WebhookLogTable::class,
            function (ContainerInterface $container) {
                return new WebhookLogTable($container->get(DatabaseInterface::class));
            }
        );

        $container->set(
            CircuitBreaker::class,
            function (ContainerInterface $container) {
                return new CircuitBreaker($container->get(WebhookTable::class));
            }
        );

        $container->set(
            QueueManager::class,
            function (ContainerInterface $container) {
                $manager = new QueueManager(
                    $container->get(WebhookTransportInterface::class),
                    $container->get(PayloadSerializer::class)
                );
                $manager->setDatabase($container->get(DatabaseInterface::class));

                return $manager;
            }
        );

        $container->set(
            DeliveryService::class,
            function (ContainerInterface $container) {
                return new DeliveryService(
                    $container->get(WebhookTransportInterface::class),
                    $container->get(HmacSigner::class),
                    $container->get(CircuitBreaker::class),
                    HttpFactory::getHttp(),
                    $container->get(WebhookTable::class),
                    $container->get(WebhookLogTable::class)
                );
            }
        );
    }

    /**
     * Dispatch a webhook event: find matching subscriptions, evaluate conditions, and enqueue messages.
     *
     * @param   string  $webhookEventName  The webhook event name (e.g., 'content.article.created').
     * @param   array   $eventData         The raw event data.
     *
     * @return  int  Number of messages enqueued.
     *
     * @since   __DEPLOY_VERSION__
     */
    public function dispatch(string $webhookEventName, array $eventData): int
    {
        return $this->serviceContainer->get(QueueManager::class)->dispatch($webhookEventName, $eventData);
    }

    /**
     * Flush all accumulated batched webhook messages to the transport.
     *
     * @return  int  Number of messages flushed.
     *
     * @since   __DEPLOY_VERSION__
     */
    public function flushBatched(): int
    {
        return $this->serviceContainer->get(QueueManager::class)->flushBatched();
    }

    /**
     * Get the event registry service.
     *
     * @return  EventRegistry
     *
     * @since   __DEPLOY_VERSION__
     */
    public function getEventRegistry(): EventRegistry
    {
        return $this->serviceContainer->get(EventRegistry::class);
    }

    /**
     * Get the delivery service.
     *
     * @return  DeliveryService
     *
     * @since   __DEPLOY_VERSION__
     */
    public function getDeliveryService(): DeliveryService
    {
        return $this->serviceContainer->get(DeliveryService::class);
    }

    /**
     * Get the webhook transport.
     *
     * @return  WebhookTransportInterface
     *
     * @since   __DEPLOY_VERSION__
     */
    public function getTransport(): WebhookTransportInterface
    {
        return $this->serviceContainer->get(WebhookTransportInterface::class);
    }
}
