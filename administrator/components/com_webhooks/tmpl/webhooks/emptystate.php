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

/** @var \Joomla\Component\Webhooks\Administrator\View\Webhooks\HtmlView $this */

$displayData = [
    'textPrefix' => 'COM_WEBHOOKS',
    'formURL'    => 'index.php?option=com_webhooks&view=webhooks',
    'icon'       => 'icon-link',

    'controlFields' => $this->filterForm->renderControlFields(),
];

$user = $this->getCurrentUser();

if ($user->authorise('core.create', 'com_webhooks')) {
    $displayData['createURL'] = 'index.php?option=com_webhooks&task=webhook.add';
}

echo LayoutHelper::render('joomla.content.emptystate', $displayData);
