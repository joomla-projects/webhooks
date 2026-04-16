<?php

/**
 * @package     Joomla.API
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Api\View\Webhooks;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The webhooks API view.
 *
 * @since  __DEPLOY_VERSION__
 */
class JsonapiView extends BaseApiView
{
    /**
     * The fields to render item in the documents.
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    protected $fieldsToRenderItem = [
        'id',
        'title',
        'alias',
        'url',
        'events',
        'conditions',
        'payload_mode',
        'batch_mode',
        'retry_strategy',
        'retry_count',
        'retry_interval',
        'circuit_breaker_mode',
        'circuit_breaker_threshold',
        'consecutive_failures',
        'disabled_at',
        'verbose_logging',
        'state',
        'access',
        'created',
        'created_by',
        'modified',
        'modified_by',
        'checked_out',
        'checked_out_time',
        'ordering',
        'params',
    ];

    /**
     * The fields to render items in the documents.
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    protected $fieldsToRenderList = [
        'id',
        'title',
        'alias',
        'url',
        'events',
        'payload_mode',
        'batch_mode',
        'consecutive_failures',
        'state',
        'ordering',
        'created',
    ];
}
