<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Webservices.webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\WebServices\Webhooks\Extension;

use Joomla\CMS\Event\Application\BeforeApiRouteEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\Router\Route;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Web Services adapter for com_webhooks.
 *
 * Registers API routes for webhook CRUD, logs, test delivery, and replay.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Webhooks extends CMSPlugin implements SubscriberInterface
{
    /**
     * @inheritDoc
     *
     * @return  array
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeApiRoute' => 'onBeforeApiRoute',
        ];
    }

    /**
     * Registers com_webhooks API routes.
     *
     * @param   BeforeApiRouteEvent  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onBeforeApiRoute(BeforeApiRouteEvent $event): void
    {
        $router = $event->getRouter();

        // CRUD: v1/webhooks
        $router->createCRUDRoutes(
            'v1/webhooks',
            'webhooks',
            ['component' => 'com_webhooks']
        );

        // Read-only: v1/webhooks/logs
        $router->addRoute(
            new Route(
                ['GET'],
                'v1/webhooks/logs',
                'logs.displayList',
                [],
                ['component' => 'com_webhooks']
            )
        );

        // Filter logs by webhook: v1/webhooks/:id/logs
        $router->addRoute(
            new Route(
                ['GET'],
                'v1/webhooks/:id/logs',
                'logs.displayList',
                ['id' => '(\d+)'],
                ['component' => 'com_webhooks']
            )
        );

        // Send test delivery: POST v1/webhooks/:id/test
        $router->addRoute(
            new Route(
                ['POST'],
                'v1/webhooks/:id/test',
                'webhooks.sendTest',
                ['id' => '(\d+)'],
                ['component' => 'com_webhooks']
            )
        );

        // Replay delivery: POST v1/webhooks/:id/replay/:logId
        $router->addRoute(
            new Route(
                ['POST'],
                'v1/webhooks/:id/replay/:logId',
                'logs.replay',
                ['id' => '(\d+)', 'logId' => '(\d+)'],
                ['component' => 'com_webhooks']
            )
        );
    }
}
