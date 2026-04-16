<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\View\Webhook;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Webhooks\Administrator\Model\WebhookModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * View to edit a webhook.
 *
 * @since  __DEPLOY_VERSION__
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The Form object.
     *
     * @var    Form
     * @since  __DEPLOY_VERSION__
     */
    protected $form;

    /**
     * The active item.
     *
     * @var    object
     * @since  __DEPLOY_VERSION__
     */
    protected $item;

    /**
     * The model state.
     *
     * @var    object
     * @since  __DEPLOY_VERSION__
     */
    protected $state;

    /**
     * Display the view.
     *
     * @param   string  $tpl  The name of the template file to parse.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     *
     * @throws  \Exception
     */
    public function display($tpl = null): void
    {
        /** @var WebhookModel $model */
        $model       = $this->getModel();
        $this->form  = $model->getForm();
        $this->item  = $model->getItem();
        $this->state = $model->getState();

        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        $this->form->addControlField('task');

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     *
     * @throws  \Exception
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $user       = $this->getCurrentUser();
        $userId     = $user->id;
        $isNew      = ($this->item->id == 0);
        $checkedOut = !(\is_null($this->item->checked_out) || $this->item->checked_out == $userId);
        $canDo      = ContentHelper::getActions('com_webhooks');
        $toolbar    = $this->getDocument()->getToolbar();

        ToolbarHelper::title(
            $isNew ? Text::_('COM_WEBHOOKS_MANAGER_WEBHOOK_NEW') : Text::_('COM_WEBHOOKS_MANAGER_WEBHOOK_EDIT'),
            'link'
        );

        if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
            $toolbar->apply('webhook.apply');
        }

        $saveGroup = $toolbar->dropdownButton('save-group');

        $saveGroup->configure(
            function (Toolbar $childBar) use ($checkedOut, $canDo, $isNew) {
                if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
                    $childBar->save('webhook.save');

                    if ($canDo->get('core.create')) {
                        $childBar->save2new('webhook.save2new');
                    }
                }

                if (!$isNew && $canDo->get('core.create')) {
                    $childBar->save2copy('webhook.save2copy');
                }
            }
        );

        if (empty($this->item->id)) {
            $toolbar->cancel('webhook.cancel', 'JTOOLBAR_CANCEL');
        } else {
            $toolbar->cancel('webhook.cancel');
        }

        $toolbar->divider();
        $toolbar->help('Webhooks:_Edit');
    }
}
