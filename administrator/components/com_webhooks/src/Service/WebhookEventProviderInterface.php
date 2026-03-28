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
 * Interface for plugins to register webhook events.
 *
 * Plugins implement this to provide custom webhook event definitions,
 * payload extraction logic, schemas, and sample payloads.
 *
 * @since  __DEPLOY_VERSION__
 */
interface WebhookEventProviderInterface
{
    /**
     * Return array of event definitions.
     *
     * Format: ['event.name' => ['label' => '...', 'group' => '...', 'description' => '...']]
     *
     * @return  array  Event definitions keyed by webhook event name.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getWebhookEvents(): array;

    /**
     * Extract payload data from an event object.
     *
     * @param   string          $eventName  The webhook event name.
     * @param   EventInterface  $event      The Joomla event object.
     *
     * @return  ?array  Payload data array, or null if this provider doesn't handle the event.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPayloadForEvent(string $eventName, EventInterface $event): ?array;

    /**
     * Return JSON Schema array describing the payload for a given event.
     *
     * @param   string  $eventName  The webhook event name.
     *
     * @return  array  JSON Schema definition.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getPayloadSchema(string $eventName): array;

    /**
     * Generate a sample payload for test delivery.
     *
     * @param   string  $eventName  The webhook event name.
     *
     * @return  array  Sample payload data.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getSamplePayload(string $eventName): array;
}
