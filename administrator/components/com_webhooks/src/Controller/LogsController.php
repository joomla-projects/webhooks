<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Webhook logs list controller.
 *
 * @since  __DEPLOY_VERSION__
 */
class LogsController extends AdminController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $text_prefix = 'COM_WEBHOOKS_LOGS';

    /**
     * Method to get a model object, loading it if required.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel  The model.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getModel($name = 'Log', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Purge old log entries.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function purge()
    {
        $this->checkToken();

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.delete', 'com_webhooks')) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED'), 'error');
            $this->setRedirect('index.php?option=com_webhooks&view=logs');

            return;
        }

        /** @var \Joomla\Component\Webhooks\Administrator\Model\LogsModel $model */
        $model = $this->getModel('Logs');
        $count = $model->purge();

        $this->setMessage(Text::sprintf('COM_WEBHOOKS_LOGS_PURGED', $count));
        $this->setRedirect('index.php?option=com_webhooks&view=logs');
    }
}
