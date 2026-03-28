<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Transport;

use Psr\Container\ContainerInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Interface for plugins that provide alternative webhook transport backends.
 *
 * Plugins in the 'webhooks' group can implement this interface to supply
 * a custom transport (Redis, AMQP, SQS, etc.) instead of the default
 * DatabaseTransport.
 *
 * @since  __DEPLOY_VERSION__
 */
interface WebhookTransportProviderInterface
{
    /**
     * Get the transport instance.
     *
     * @param   ContainerInterface  $container  The component service container.
     *
     * @return  WebhookTransportInterface  The transport implementation.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getTransport(ContainerInterface $container): WebhookTransportInterface;
}
