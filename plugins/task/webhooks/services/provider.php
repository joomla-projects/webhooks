<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.Webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Plugin\Task\Webhooks\Extension\Webhooks;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(Webhooks::class, function (Container $container) {
                $plugin = new Webhooks(
                    (array) PluginHelper::getPlugin('task', 'webhooks')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
