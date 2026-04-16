<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Webhooks\Administrator\View\Logs\HtmlView $this */

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('table.columns')
    ->useScript('multiselect');

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
?>
<form action="<?php echo Route::_('index.php?option=com_webhooks&view=logs'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="logList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_WEBHOOKS_LOGS_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col">
                                    <?php echo Text::_('COM_WEBHOOKS_HEADING_WEBHOOK'); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_WEBHOOKS_HEADING_EVENT', 'a.event_name', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo Text::_('COM_WEBHOOKS_HEADING_URL'); ?>
                                </th>
                                <th scope="col" class="w-5 text-center">
                                    <?php echo Text::_('COM_WEBHOOKS_HEADING_STATUS_CODE'); ?>
                                </th>
                                <th scope="col" class="w-5 text-center">
                                    <?php echo Text::_('COM_WEBHOOKS_HEADING_RESULT'); ?>
                                </th>
                                <th scope="col" class="w-5 text-center d-none d-md-table-cell">
                                    <?php echo Text::_('COM_WEBHOOKS_HEADING_DURATION'); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_FIELD_CREATED_LABEL', 'a.created', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->items as $i => $item) : ?>
                                <tr class="row<?php echo $i % 2; ?>">
                                    <td class="text-center">
                                        <?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->event_name); ?>
                                    </td>
                                    <td>
                                        <?php echo $this->escape($item->webhook_title ?: Text::_('COM_WEBHOOKS_LOG_DELETED_WEBHOOK')); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $this->escape($item->event_name); ?></span>
                                    </td>
                                    <td class="small d-none d-md-table-cell text-truncate" style="max-width: 150px;">
                                        <?php echo $this->escape($item->url); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($item->status_code) : ?>
                                            <span class="badge bg-<?php echo $item->success ? 'success' : 'danger'; ?>"><?php echo (int) $item->status_code; ?></span>
                                        <?php else : ?>
                                            <span class="badge bg-warning"><?php echo Text::_('COM_WEBHOOKS_LOG_NO_RESPONSE'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($item->success) : ?>
                                            <span class="icon-check text-success" aria-hidden="true"></span>
                                            <span class="visually-hidden"><?php echo Text::_('COM_WEBHOOKS_FILTER_SUCCESS'); ?></span>
                                        <?php else : ?>
                                            <span class="icon-times text-danger" aria-hidden="true"></span>
                                            <span class="visually-hidden"><?php echo Text::_('COM_WEBHOOKS_FILTER_FAILED'); ?></span>
                                            <?php if ($item->error_message) : ?>
                                                <div class="small text-danger"><?php echo $this->escape($item->error_message); ?></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center d-none d-md-table-cell">
                                        <?php echo $item->duration_ms !== null ? $item->duration_ms . ' ms' : '-'; ?>
                                    </td>
                                    <td>
                                        <?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC6')); ?>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <?php echo $item->id; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <?php echo $this->filterForm->renderControlFields(); ?>
            </div>
        </div>
    </div>
</form>
