<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Model;

use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Methods supporting a list of webhook delivery log records.
 *
 * @since  __DEPLOY_VERSION__
 */
class LogsModel extends ListModel
{
    /**
     * Constructor.
     *
     * @param   array                 $config   An optional associative array of configuration settings.
     * @param   ?MVCFactoryInterface  $factory  The factory.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null)
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'webhook_id', 'a.webhook_id',
                'event_name', 'a.event_name',
                'success', 'a.success',
                'status_code', 'a.status_code',
                'duration_ms', 'a.duration_ms',
                'created', 'a.created',
                'webhook_title',
            ];
        }

        parent::__construct($config, $factory);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  \Joomla\Database\QueryInterface
     *
     * @since  __DEPLOY_VERSION__
     */
    protected function getListQuery()
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select(
            $this->getState(
                'list.select',
                [
                    $db->quoteName('a.id'),
                    $db->quoteName('a.webhook_id'),
                    $db->quoteName('a.event_name'),
                    $db->quoteName('a.url'),
                    $db->quoteName('a.status_code'),
                    $db->quoteName('a.success'),
                    $db->quoteName('a.error_message'),
                    $db->quoteName('a.duration_ms'),
                    $db->quoteName('a.created'),
                ]
            )
        )
            ->from($db->quoteName('#__webhook_logs', 'a'));

        // Join webhooks table for title
        $query->select($db->quoteName('w.title', 'webhook_title'))
            ->join('LEFT', $db->quoteName('#__webhooks', 'w'), $db->quoteName('w.id') . ' = ' . $db->quoteName('a.webhook_id'));

        // Filter by webhook
        $webhookId = $this->getState('filter.webhook_id');

        if (is_numeric($webhookId)) {
            $webhookId = (int) $webhookId;
            $query->where($db->quoteName('a.webhook_id') . ' = :webhookid')
                ->bind(':webhookid', $webhookId, ParameterType::INTEGER);
        }

        // Filter by event name
        $eventName = $this->getState('filter.event_name');

        if (!empty($eventName)) {
            $query->where($db->quoteName('a.event_name') . ' = :eventname')
                ->bind(':eventname', $eventName);
        }

        // Filter by success
        $success = $this->getState('filter.success');

        if (is_numeric($success)) {
            $success = (int) $success;
            $query->where($db->quoteName('a.success') . ' = :success')
                ->bind(':success', $success, ParameterType::INTEGER);
        }

        // Filter by search
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $searchId = (int) substr($search, 3);
                $query->where($db->quoteName('a.id') . ' = :searchid')
                    ->bind(':searchid', $searchId, ParameterType::INTEGER);
            } else {
                $search = '%' . str_replace(' ', '%', trim($search)) . '%';
                $query->where(
                    '(' . $db->quoteName('a.event_name') . ' LIKE :search1'
                    . ' OR ' . $db->quoteName('a.url') . ' LIKE :search2'
                    . ' OR ' . $db->quoteName('w.title') . ' LIKE :search3)'
                )
                    ->bind(':search1', $search)
                    ->bind(':search2', $search)
                    ->bind(':search3', $search);
            }
        }

        // Add the list ordering clause
        $orderCol  = $this->state->get('list.ordering', 'a.id');
        $orderDirn = $this->state->get('list.direction', 'DESC');

        if (!in_array($orderCol, $this->filter_fields, true)) {
            $orderCol = 'a.id';
        }

        $orderDirn = strtoupper($orderDirn) === 'ASC' ? 'ASC' : 'DESC';

        $query->order($db->escape($orderCol) . ' ' . $orderDirn);

        return $query;
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string  A store id.
     *
     * @since  __DEPLOY_VERSION__
     */
    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.webhook_id');
        $id .= ':' . $this->getState('filter.event_name');
        $id .= ':' . $this->getState('filter.success');

        return parent::getStoreId($id);
    }

    /**
     * Method to auto-populate the model state.
     *
     * @param   string  $ordering   An optional ordering field.
     * @param   string  $direction  An optional direction (asc|desc).
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    protected function populateState($ordering = 'a.id', $direction = 'desc')
    {
        parent::populateState($ordering, $direction);
    }

    /**
     * Purge all log entries up to the current time.
     *
     * @return  int  The number of deleted log records.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function purge(): int
    {
        $db  = $this->getDatabase();
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__webhook_logs'))
            ->where($db->quoteName('created') . ' <= :now')
            ->bind(':now', $now);

        $db->setQuery($query);
        $db->execute();

        return $db->getAffectedRows();
    }

    /**
     * Purge log entries older than a given threshold date.
     *
     * @param   string  $threshold  The cutoff date in 'Y-m-d H:i:s' format (UTC).
     *
     * @return  int  The number of deleted log records.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function purgeOlderThan(string $threshold): int
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__webhook_logs'))
            ->where($db->quoteName('created') . ' < :threshold')
            ->bind(':threshold', $threshold);

        $db->setQuery($query);
        $db->execute();

        return $db->getAffectedRows();
    }
}
