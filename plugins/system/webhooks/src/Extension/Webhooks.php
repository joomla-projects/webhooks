<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Webhooks\Extension;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Webhooks\Administrator\Extension\WebhooksComponent;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * System plugin for capturing Joomla events and dispatching them to the webhook queue.
 *
 * Subscribes to all 14 curated Joomla events. Each handler maps the Joomla event
 * to a webhook event name, extracts payload data, and calls WebhooksComponent::dispatch().
 * For batched mode webhooks, accumulated events are flushed via onBeforeRespond.
 *
 * @since  __DEPLOY_VERSION__
 */
final class Webhooks extends CMSPlugin implements SubscriberInterface
{
    /**
     * @inheritDoc
     *
     * @return  array
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentAfterSave'        => 'onContentAfterSave',
            'onContentAfterDelete'      => 'onContentAfterDelete',
            'onContentChangeState'      => 'onContentChangeState',
            'onUserAfterSave'           => 'onUserAfterSave',
            'onUserAfterDelete'         => 'onUserAfterDelete',
            'onUserLogin'               => 'onUserLogin',
            'onUserLogout'              => 'onUserLogout',
            'onCategoryChangeState'     => 'onCategoryChangeState',
            'onSubmitContact'           => 'onSubmitContact',
            'onExtensionAfterInstall'   => 'onExtensionAfterInstall',
            'onExtensionAfterUpdate'    => 'onExtensionAfterUpdate',
            'onExtensionAfterUninstall' => 'onExtensionAfterUninstall',
            'onBeforeRespond'           => 'onBeforeRespond',
        ];
    }

    /**
     * Handle content after save (article created or updated).
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentAfterSave(EventInterface $event): void
    {
        try {
            $context = $event->getArgument('context', $event->getArgument(0, ''));

            if ($context !== 'com_content.article' && $context !== 'com_content.form') {
                return;
            }

            $article = $event->getArgument('subject', $event->getArgument(1, null));
            $isNew   = $event->getArgument('isNew', $event->getArgument(2, false));

            if (!$article || !\is_object($article)) {
                return;
            }

            $webhookEvent = $isNew ? 'content.article.created' : 'content.article.updated';
            $this->dispatchWebhookEvent($webhookEvent, (array) $article);
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Handle content after delete.
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentAfterDelete(EventInterface $event): void
    {
        try {
            $context = $event->getArgument('context', $event->getArgument(0, ''));

            if ($context !== 'com_content.article') {
                return;
            }

            $article = $event->getArgument('subject', $event->getArgument(1, null));

            if (!$article || !\is_object($article)) {
                return;
            }

            $this->dispatchWebhookEvent('content.article.deleted', (array) $article);
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Handle content state change.
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onContentChangeState(EventInterface $event): void
    {
        try {
            $context = $event->getArgument('context', $event->getArgument(0, ''));

            if ($context !== 'com_content.article') {
                return;
            }

            $pks   = $event->getArgument('pks', $event->getArgument(1, []));
            $value = $event->getArgument('value', $event->getArgument(2, 0));

            foreach ((array) $pks as $pk) {
                $this->dispatchWebhookEvent('content.article.state_changed', [
                    'id'    => $pk,
                    'state' => $value,
                ]);
            }
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Handle user after save (created or updated).
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onUserAfterSave(EventInterface $event): void
    {
        try {
            $user  = $event->getArgument('subject', $event->getArgument(0, []));
            $isNew = $event->getArgument('isNew', $event->getArgument(1, false));

            if (empty($user)) {
                return;
            }

            $data = \is_object($user) ? (array) $user : $user;
            $webhookEvent = $isNew ? 'user.created' : 'user.updated';

            $this->dispatchWebhookEvent($webhookEvent, $data);
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Handle user after delete.
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onUserAfterDelete(EventInterface $event): void
    {
        try {
            $user = $event->getArgument('subject', $event->getArgument(0, []));

            if (empty($user)) {
                return;
            }

            $data = \is_object($user) ? (array) $user : $user;

            $this->dispatchWebhookEvent('user.deleted', $data);
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Handle user login.
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onUserLogin(EventInterface $event): void
    {
        try {
            $user = $event->getArgument('subject', $event->getArgument(0, []));

            $this->dispatchWebhookEvent('user.logged_in', \is_array($user) ? $user : (array) $user);
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Handle user logout.
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onUserLogout(EventInterface $event): void
    {
        try {
            $user = $event->getArgument('subject', $event->getArgument(0, []));

            $this->dispatchWebhookEvent('user.logged_out', \is_array($user) ? $user : (array) $user);
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Handle category state change.
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onCategoryChangeState(EventInterface $event): void
    {
        try {
            $extension = $event->getArgument('extension', $event->getArgument(0, ''));
            $pks       = $event->getArgument('pks', $event->getArgument(1, []));
            $value     = $event->getArgument('value', $event->getArgument(2, 0));

            foreach ((array) $pks as $pk) {
                $this->dispatchWebhookEvent('category.state_changed', [
                    'id'        => $pk,
                    'extension' => $extension,
                    'state'     => $value,
                ]);
            }
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Handle contact form submission.
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onSubmitContact(EventInterface $event): void
    {
        try {
            $contact = $event->getArgument('subject', $event->getArgument(0, null));
            $data    = $event->getArgument('data', $event->getArgument(1, []));

            $payload = \is_array($data) ? $data : (array) $data;

            if ($contact && \is_object($contact)) {
                $payload['contact_id']   = $contact->id ?? null;
                $payload['contact_name'] = $contact->name ?? null;
            }

            $this->dispatchWebhookEvent('contact.form_submitted', $payload);
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Handle extension after install.
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onExtensionAfterInstall(EventInterface $event): void
    {
        try {
            $installer = $event->getArgument('installer', $event->getArgument(0, null));
            $eid       = $event->getArgument('eid', $event->getArgument(1, 0));

            $this->dispatchWebhookEvent('extension.installed', [
                'id'   => $eid,
                'type' => $installer ? $installer->get('manifest')->attributes()->type ?? '' : '',
                'name' => $installer ? (string) $installer->get('manifest')->name ?? '' : '',
            ]);
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Handle extension after update.
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onExtensionAfterUpdate(EventInterface $event): void
    {
        try {
            $installer = $event->getArgument('installer', $event->getArgument(0, null));
            $eid       = $event->getArgument('eid', $event->getArgument(1, 0));

            $this->dispatchWebhookEvent('extension.updated', [
                'id'   => $eid,
                'type' => $installer ? $installer->get('manifest')->attributes()->type ?? '' : '',
                'name' => $installer ? (string) $installer->get('manifest')->name ?? '' : '',
            ]);
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Handle extension after uninstall.
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onExtensionAfterUninstall(EventInterface $event): void
    {
        try {
            $installer = $event->getArgument('installer', $event->getArgument(0, null));
            $eid       = $event->getArgument('eid', $event->getArgument(1, 0));
            $result    = $event->getArgument('result', $event->getArgument(2, true));

            if (!$result) {
                return;
            }

            $this->dispatchWebhookEvent('extension.uninstalled', [
                'id'   => $eid,
                'type' => $installer ? $installer->get('manifest')->attributes()->type ?? '' : '',
                'name' => $installer ? (string) $installer->get('manifest')->name ?? '' : '',
            ]);
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Flush batched webhook events before the response is sent.
     *
     * @param   EventInterface  $event  The event object.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function onBeforeRespond(EventInterface $event): void
    {
        try {
            /** @var WebhooksComponent $component */
            $component = $this->getApplication()->bootComponent('com_webhooks');
            $component->flushBatched();
        } catch (\Throwable $e) {
            // Never break the parent operation
        }
    }

    /**
     * Dispatch a webhook event via the WebhooksComponent public API.
     *
     * @param   string  $webhookEventName  The webhook event name.
     * @param   array   $payload           The event payload data.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    private function dispatchWebhookEvent(string $webhookEventName, array $payload): void
    {
        /** @var WebhooksComponent $component */
        $component = $this->getApplication()->bootComponent('com_webhooks');
        $component->dispatch($webhookEventName, $payload);
    }
}
