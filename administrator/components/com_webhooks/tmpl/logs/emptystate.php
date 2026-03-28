<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

/** @var \Joomla\Component\Webhooks\Administrator\View\Logs\HtmlView $this */

$displayData = [
    'textPrefix' => 'COM_WEBHOOKS_LOGS',
    'formURL'    => 'index.php?option=com_webhooks&view=logs',
    'icon'       => 'icon-list',

    'controlFields' => $this->filterForm->renderControlFields(),
];

echo LayoutHelper::render('joomla.content.emptystate', $displayData);
