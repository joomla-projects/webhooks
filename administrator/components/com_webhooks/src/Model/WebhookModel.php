<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Webhook model.
 *
 * @since  __DEPLOY_VERSION__
 */
class WebhookModel extends AdminModel
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $text_prefix = 'COM_WEBHOOKS_WEBHOOK';

    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    public $typeAlias = 'com_webhooks.webhook';

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|boolean  A Form object on success, false on failure.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_webhooks.webhook', 'webhook', ['control' => 'jform', 'load_data' => $loadData]);

        if (!$this->canEditState((object) $data)) {
            $form->setFieldAttribute('state', 'disabled', 'true');
            $form->setFieldAttribute('state', 'filter', 'unset');
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since  __DEPLOY_VERSION__
     */
    protected function loadFormData()
    {
        $app  = Factory::getApplication();
        $data = $app->getUserState('com_webhooks.edit.webhook.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            if ($this->getState('webhook.id') == 0) {
                // Auto-generate secret for new webhooks
                $data->secret = bin2hex(random_bytes(32));
            }
        }

        $this->preprocessData('com_webhooks.webhook', $data);

        return $data;
    }

    /**
     * Prepare and sanitise the table prior to saving.
     *
     * @param   \Joomla\CMS\Table\Table  $table  The table object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    protected function prepareTable($table)
    {
        $date = Factory::getDate();
        $user = $this->getCurrentUser();

        if (empty($table->id)) {
            $table->created    = $date->toSql();
            $table->created_by = $user->id;

            // Auto-generate secret if not set
            if (empty($table->secret)) {
                $table->secret = bin2hex(random_bytes(32));
            }

            // Set ordering to the last item if not set
            if (empty($table->ordering)) {
                $db    = $this->getDatabase();
                $query = $db->createQuery()
                    ->select('MAX(' . $db->quoteName('ordering') . ')')
                    ->from($db->quoteName('#__webhooks'));

                $db->setQuery($query);
                $max = $db->loadResult();

                $table->ordering = $max + 1;
            }
        }

        $table->modified    = $date->toSql();
        $table->modified_by = $user->id;
    }
}
