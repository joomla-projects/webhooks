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
use Joomla\CMS\Router\Route;

/** @var \Joomla\Component\Webhooks\Administrator\View\Webhook\HtmlView $this */

/** @var \Joomla\CMS\WebAsset\WebAssetManager $wa */
$wa = $this->getDocument()->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

if ((int) $this->item->id > 0 && (int) $this->item->verified !== 1) {
    $wa->useScript('com_webhooks.admin-webhook-verify');
    $this->getDocument()->addScriptOptions('com_webhooks-verify', [
        'webhookId'        => (int) $this->item->id,
        'url'              => Route::_('index.php?option=com_webhooks&task=webhook.verify&format=json', false),
        'textVerifying'    => Text::_('COM_WEBHOOKS_VERIFYING'),
        'textVerified'     => Text::_('COM_WEBHOOKS_VERIFIED'),
        'textErrorGeneric' => Text::_('COM_WEBHOOKS_VERIFY_ERROR_GENERIC'),
    ]);
}

?>

<form action="<?php echo Route::_('index.php?option=com_webhooks&layout=edit&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="webhook-form" aria-label="<?php echo Text::_('COM_WEBHOOKS_WEBHOOK_' . ((int) $this->item->id === 0 ? 'NEW' : 'EDIT'), true); ?>" class="form-validate">

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'details', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'details', Text::_('COM_WEBHOOKS_FIELDSET_DETAILS')); ?>
        <div class="row">
            <div class="col-lg-9">
                <?php echo $this->form->renderField('title'); ?>
                <?php echo $this->form->renderField('alias'); ?>
                <?php echo $this->form->renderField('url'); ?>
                <?php if ((int) $this->item->id > 0 && (int) $this->item->verified !== 1) : ?>
                    <div id="webhook-verify-container" class="mb-3">
                        <button type="button" id="btn-verify-endpoint" class="btn btn-warning">
                            <span class="icon-shield" aria-hidden="true"></span>
                            <?php echo Text::_('COM_WEBHOOKS_ACTION_VERIFY'); ?>
                        </button>
                    </div>
                <?php elseif ((int) $this->item->id > 0 && (int) $this->item->verified === 1) : ?>
                    <div id="webhook-verify-container" class="mb-3">
                        <span class="badge bg-success"><?php echo Text::_('COM_WEBHOOKS_VERIFIED'); ?></span>
                    </div>
                <?php endif; ?>
                <?php echo $this->form->renderField('secret'); ?>
                <?php echo $this->form->renderField('events'); ?>
                <?php echo $this->form->renderField('payload_mode'); ?>
                <?php echo $this->form->renderField('batch_mode'); ?>
            </div>
            <div class="col-lg-3">
                <?php echo $this->form->renderField('state'); ?>
                <?php echo $this->form->renderField('access'); ?>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'conditions', Text::_('COM_WEBHOOKS_FIELDSET_CONDITIONS')); ?>
            <fieldset id="fieldset-conditions" class="options-form">
                <legend><?php echo Text::_('COM_WEBHOOKS_FIELDSET_CONDITIONS'); ?></legend>
                <div>
                    <?php echo $this->form->renderFieldset('conditions'); ?>
                </div>
            </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'delivery', Text::_('COM_WEBHOOKS_FIELDSET_DELIVERY')); ?>
            <fieldset id="fieldset-delivery" class="options-form">
                <legend><?php echo Text::_('COM_WEBHOOKS_FIELDSET_DELIVERY'); ?></legend>
                <div>
                    <?php echo $this->form->renderFieldset('delivery'); ?>
                </div>
            </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'circuitbreaker', Text::_('COM_WEBHOOKS_FIELDSET_CIRCUIT_BREAKER')); ?>
            <fieldset id="fieldset-circuitbreaker" class="options-form">
                <legend><?php echo Text::_('COM_WEBHOOKS_FIELDSET_CIRCUIT_BREAKER'); ?></legend>
                <div>
                    <?php echo $this->form->renderFieldset('circuitbreaker'); ?>
                </div>
            </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'logging', Text::_('COM_WEBHOOKS_FIELDSET_LOGGING')); ?>
            <fieldset id="fieldset-logging" class="options-form">
                <legend><?php echo Text::_('COM_WEBHOOKS_FIELDSET_LOGGING'); ?></legend>
                <div>
                    <?php echo $this->form->renderFieldset('logging'); ?>
                </div>
            </fieldset>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'publishing', Text::_('JGLOBAL_FIELDSET_PUBLISHING')); ?>
        <div class="row">
            <div class="col-md-6">
                <fieldset id="fieldset-publishingdata" class="options-form">
                    <legend><?php echo Text::_('JGLOBAL_FIELDSET_PUBLISHING'); ?></legend>
                    <div>
                        <?php echo $this->form->renderField('ordering'); ?>
                        <?php echo $this->form->renderField('created'); ?>
                        <?php echo $this->form->renderField('created_by'); ?>
                        <?php echo $this->form->renderField('modified'); ?>
                        <?php echo $this->form->renderField('modified_by'); ?>
                    </div>
                </fieldset>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <?php echo $this->form->renderControlFields(); ?>
</form>
