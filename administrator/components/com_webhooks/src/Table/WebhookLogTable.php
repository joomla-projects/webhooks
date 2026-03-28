<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Table;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Webhook log table class.
 *
 * @since  __DEPLOY_VERSION__
 */
class WebhookLogTable extends Table
{
    /**
     * Indicates that columns fully support the NULL value in the database.
     *
     * @var    boolean
     * @since  __DEPLOY_VERSION__
     */
    protected $_supportNullValue = true;

    /**
     * Constructor.
     *
     * @param   DatabaseInterface     $db          Database connector object.
     * @param   ?DispatcherInterface  $dispatcher  Event dispatcher for this table.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_webhooks.log';

        parent::__construct('#__webhook_logs', 'id', $db, $dispatcher);
    }
}
