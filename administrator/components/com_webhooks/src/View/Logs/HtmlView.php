<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\View\Logs;

use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Component\Webhooks\Administrator\Model\LogsModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * View class for a list of webhook delivery logs.
 *
 * @since  __DEPLOY_VERSION__
 */
class HtmlView extends BaseHtmlView
{
    /**
     * An array of items.
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    protected $items;

    /**
     * The pagination object.
     *
     * @var    \Joomla\CMS\Pagination\Pagination
     * @since  __DEPLOY_VERSION__
     */
    protected $pagination;

    /**
     * The model state.
     *
     * @var    \Joomla\Registry\Registry
     * @since  __DEPLOY_VERSION__
     */
    protected $state;

    /**
     * Form object for search filters.
     *
     * @var    \Joomla\CMS\Form\Form
     * @since  __DEPLOY_VERSION__
     */
    public $filterForm;

    /**
     * The active search filters.
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    public $activeFilters;

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
        /** @var LogsModel $model */
        $model               = $this->getModel();
        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if (\count($errors = $model->getErrors())) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    protected function addToolbar(): void
    {
        $canDo   = ContentHelper::getActions('com_webhooks');
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_WEBHOOKS_LOGS_TITLE'), 'list');

        if ($canDo->get('core.delete')) {
            $toolbar->delete('logs.delete')
                ->text('JTOOLBAR_DELETE')
                ->message('JGLOBAL_CONFIRM_DELETE')
                ->listCheck(true);

            $toolbar->standardButton('purge', 'COM_WEBHOOKS_TOOLBAR_PURGE', 'logs.purge')
                ->icon('icon-trash')
                ->listCheck(false);
        }

        if ($canDo->get('core.admin')) {
            $toolbar->preferences('com_webhooks');
        }

        $toolbar->help('Webhooks:_Logs');
    }
}
