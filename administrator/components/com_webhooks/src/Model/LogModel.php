<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Model;

use Joomla\CMS\MVC\Model\AdminModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Webhook log model (for delete/purge support).
 *
 * @since  __DEPLOY_VERSION__
 */
class LogModel extends AdminModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $text_prefix = 'COM_WEBHOOKS_LOG';

    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    public $typeAlias = 'com_webhooks.log';

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data.
     *
     * @return  \Joomla\CMS\Form\Form|boolean  A Form object on success, false on failure.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getForm($data = [], $loadData = true)
    {
        return false;
    }
}
