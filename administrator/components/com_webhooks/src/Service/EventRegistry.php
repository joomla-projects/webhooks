<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Service;

use Joomla\Event\EventInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Aggregates webhook event providers and maps Joomla events to webhook event names.
 *
 * Provides the 14 curated core events and allows plugins to register additional events
 * via the WebhookEventProviderInterface.
 *
 * @since  __DEPLOY_VERSION__
 */
class EventRegistry
{
    /**
     * Registered event providers.
     *
     * @var    WebhookEventProviderInterface[]
     * @since  __DEPLOY_VERSION__
     */
    private array $providers = [];

    /**
     * Cached event definitions.
     *
     * @var    ?array
     * @since  __DEPLOY_VERSION__
     */
    private ?array $cachedEvents = null;

    /**
     * Core Joomla event to webhook event mapping.
     *
     * Some Joomla events map to multiple webhook events depending on context
     * (e.g., onContentAfterSave maps to created or updated based on isNew flag).
     * Those are handled specially in resolveWebhookEventName().
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    private const CORE_EVENT_MAP = [
        'onContentAfterSave'       => ['content.article.created', 'content.article.updated'],
        'onContentAfterDelete'     => ['content.article.deleted'],
        'onContentChangeState'     => ['content.article.state_changed'],
        'onUserAfterSave'          => ['user.created', 'user.updated'],
        'onUserAfterDelete'        => ['user.deleted'],
        'onUserLogin'              => ['user.logged_in'],
        'onUserLogout'             => ['user.logged_out'],
        'onCategoryChangeState'    => ['category.state_changed'],
        'onSubmitContact'          => ['contact.form_submitted'],
        'onExtensionAfterInstall'  => ['extension.installed'],
        'onExtensionAfterUpdate'   => ['extension.updated'],
        'onExtensionAfterUninstall' => ['extension.uninstalled'],
    ];

    /**
     * Core webhook event definitions.
     *
     * @var    array
     * @since  __DEPLOY_VERSION__
     */
    private const CORE_EVENTS = [
        'content.article.created' => [
            'label'       => 'COM_WEBHOOKS_EVENT_ARTICLE_CREATED',
            'group'       => 'Content',
            'description' => 'COM_WEBHOOKS_EVENT_ARTICLE_CREATED_DESC',
        ],
        'content.article.updated' => [
            'label'       => 'COM_WEBHOOKS_EVENT_ARTICLE_UPDATED',
            'group'       => 'Content',
            'description' => 'COM_WEBHOOKS_EVENT_ARTICLE_UPDATED_DESC',
        ],
        'content.article.deleted' => [
            'label'       => 'COM_WEBHOOKS_EVENT_ARTICLE_DELETED',
            'group'       => 'Content',
            'description' => 'COM_WEBHOOKS_EVENT_ARTICLE_DELETED_DESC',
        ],
        'content.article.state_changed' => [
            'label'       => 'COM_WEBHOOKS_EVENT_ARTICLE_STATE_CHANGED',
            'group'       => 'Content',
            'description' => 'COM_WEBHOOKS_EVENT_ARTICLE_STATE_CHANGED_DESC',
        ],
        'user.created' => [
            'label'       => 'COM_WEBHOOKS_EVENT_USER_CREATED',
            'group'       => 'Users',
            'description' => 'COM_WEBHOOKS_EVENT_USER_CREATED_DESC',
        ],
        'user.updated' => [
            'label'       => 'COM_WEBHOOKS_EVENT_USER_UPDATED',
            'group'       => 'Users',
            'description' => 'COM_WEBHOOKS_EVENT_USER_UPDATED_DESC',
        ],
        'user.deleted' => [
            'label'       => 'COM_WEBHOOKS_EVENT_USER_DELETED',
            'group'       => 'Users',
            'description' => 'COM_WEBHOOKS_EVENT_USER_DELETED_DESC',
        ],
        'user.logged_in' => [
            'label'       => 'COM_WEBHOOKS_EVENT_USER_LOGGED_IN',
            'group'       => 'Users',
            'description' => 'COM_WEBHOOKS_EVENT_USER_LOGGED_IN_DESC',
        ],
        'user.logged_out' => [
            'label'       => 'COM_WEBHOOKS_EVENT_USER_LOGGED_OUT',
            'group'       => 'Users',
            'description' => 'COM_WEBHOOKS_EVENT_USER_LOGGED_OUT_DESC',
        ],
        'category.state_changed' => [
            'label'       => 'COM_WEBHOOKS_EVENT_CATEGORY_STATE_CHANGED',
            'group'       => 'Categories',
            'description' => 'COM_WEBHOOKS_EVENT_CATEGORY_STATE_CHANGED_DESC',
        ],
        'contact.form_submitted' => [
            'label'       => 'COM_WEBHOOKS_EVENT_CONTACT_FORM_SUBMITTED',
            'group'       => 'Contact',
            'description' => 'COM_WEBHOOKS_EVENT_CONTACT_FORM_SUBMITTED_DESC',
        ],
        'extension.installed' => [
            'label'       => 'COM_WEBHOOKS_EVENT_EXTENSION_INSTALLED',
            'group'       => 'Extensions',
            'description' => 'COM_WEBHOOKS_EVENT_EXTENSION_INSTALLED_DESC',
        ],
        'extension.updated' => [
            'label'       => 'COM_WEBHOOKS_EVENT_EXTENSION_UPDATED',
            'group'       => 'Extensions',
            'description' => 'COM_WEBHOOKS_EVENT_EXTENSION_UPDATED_DESC',
        ],
        'extension.uninstalled' => [
            'label'       => 'COM_WEBHOOKS_EVENT_EXTENSION_UNINSTALLED',
            'group'       => 'Extensions',
            'description' => 'COM_WEBHOOKS_EVENT_EXTENSION_UNINSTALLED_DESC',
        ],
    ];

    /**
     * Register an event provider.
     *
     * @param   WebhookEventProviderInterface  $provider  The provider to register.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function registerProvider(WebhookEventProviderInterface $provider): void
    {
        $this->providers[] = $provider;
        $this->cachedEvents = null;
    }

    /**
     * Get all registered webhook events (core + plugin-provided).
     *
     * @return  array  Event definitions keyed by webhook event name.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getEvents(): array
    {
        if ($this->cachedEvents !== null) {
            return $this->cachedEvents;
        }

        $this->cachedEvents = self::CORE_EVENTS;

        foreach ($this->providers as $provider) {
            foreach ($provider->getWebhookEvents() as $name => $definition) {
                $this->cachedEvents[$name] = $definition;
            }
        }

        return $this->cachedEvents;
    }

    /**
     * Get events grouped by their group name.
     *
     * @return  array  Groups as keys, arrays of event definitions as values.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getEventsByGroup(): array
    {
        $grouped = [];

        foreach ($this->getEvents() as $name => $definition) {
            $group = $definition['group'] ?? 'Other';
            $grouped[$group][$name] = $definition;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * Resolve a Joomla event name to a webhook event name.
     *
     * @param   string          $joomlaEventName  The Joomla event class or trigger name.
     * @param   EventInterface  $event            The event object for context.
     *
     * @return  ?string  The webhook event name, or null if not mapped.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function resolveWebhookEventName(string $joomlaEventName, EventInterface $event): ?string
    {
        if (!isset(self::CORE_EVENT_MAP[$joomlaEventName])) {
            return null;
        }

        $mapped = self::CORE_EVENT_MAP[$joomlaEventName];

        // Single mapping — straightforward
        if (\count($mapped) === 1) {
            return $mapped[0];
        }

        // onContentAfterSave / onUserAfterSave: distinguish new vs update
        if (\in_array($joomlaEventName, ['onContentAfterSave', 'onUserAfterSave'], true)) {
            $isNew = $event->getArgument('isNew', $event->getArgument(2, false));

            return $isNew ? $mapped[0] : $mapped[1];
        }

        return $mapped[0];
    }

    /**
     * Get all Joomla event names that should be subscribed to.
     *
     * @return  string[]  Array of Joomla event names.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getSubscribedJoomlaEvents(): array
    {
        return array_keys(self::CORE_EVENT_MAP);
    }

    /**
     * Find the provider that handles a given webhook event name.
     *
     * @param   string  $webhookEventName  The webhook event name.
     *
     * @return  ?WebhookEventProviderInterface  The provider, or null if core event.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getProviderForEvent(string $webhookEventName): ?WebhookEventProviderInterface
    {
        foreach ($this->providers as $provider) {
            $events = $provider->getWebhookEvents();

            if (isset($events[$webhookEventName])) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Check if a webhook event name is a known core event.
     *
     * @param   string  $webhookEventName  The webhook event name.
     *
     * @return  bool
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isCoreEvent(string $webhookEventName): bool
    {
        return isset(self::CORE_EVENTS[$webhookEventName]);
    }
}
