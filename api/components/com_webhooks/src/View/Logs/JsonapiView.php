<?php

/**
 * @package     Joomla.API
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Api\View\Logs;

use Joomla\CMS\MVC\View\JsonApiView as BaseApiView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The webhook logs API view.
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
        'webhook_id',
        'queue_id',
        'event_name',
        'url',
        'status_code',
        'success',
        'error_message',
        'duration_ms',
        'request_headers',
        'request_body',
        'response_headers',
        'response_body',
        'created',
    ];

    /**
     * The fields to render items in the documents.
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    protected $fieldsToRenderList = [
        'id',
        'webhook_id',
        'event_name',
        'url',
        'status_code',
        'success',
        'error_message',
        'duration_ms',
        'created',
        'webhook_title',
    ];
}
