<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Service;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;
use Joomla\Component\Webhooks\Administrator\Table\WebhookTable;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Tracks consecutive delivery failures and triggers circuit breaker actions.
 *
 * In 'disable' mode: sets webhook state=0 and disabled_at when threshold reached.
 * In 'cooldown' mode: progressively delays next delivery attempt.
 *
 * @since  __DEPLOY_VERSION__
 */
class CircuitBreaker
{
    /**
     * Constructor.
     *
     * @param   WebhookTable  $table  The webhook table instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(private WebhookTable $table)
    {
    }

    /**
     * Record a successful delivery, resetting the failure counter and circuit breaker state.
     *
     * @param   int  $webhookId  The webhook subscription ID.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function recordSuccess(int $webhookId): void
    {
        $this->table->resetCircuitBreaker($webhookId);
    }

    /**
     * Record a delivery failure, incrementing the failure counter.
     *
     * If the threshold is reached, takes action based on the circuit breaker mode.
     * If the webhook was in half-open state, clears the flag and restarts cooldown.
     *
     * @param   int     $webhookId  The webhook subscription ID.
     * @param   object  $webhook    The webhook record (needs circuit_breaker_mode, circuit_breaker_threshold).
     *
     * @return  bool  True if the circuit breaker was tripped (threshold reached).
     *
     * @since  __DEPLOY_VERSION__
     */
    public function recordFailure(int $webhookId, object $webhook): bool
    {
        $this->table->incrementConsecutiveFailures($webhookId);

        // If webhook was in half-open state, clear flag and restart cooldown
        if ((int) ($webhook->circuit_breaker_half_open ?? 0) === 1) {
            $this->table->setHalfOpen($webhookId, false);
        }

        // Re-read the count from DB after the UPDATE to avoid using a stale cached value
        // that could cause a race condition when multiple workers process the same webhook.
        $newCount = $this->table->getConsecutiveFailures($webhookId);

        if ($newCount < (int) $webhook->circuit_breaker_threshold) {
            return false;
        }

        // Threshold reached — take action based on mode
        if ($webhook->circuit_breaker_mode === 'disable') {
            $this->table->disableByCircuitBreaker($webhookId);
            $this->logCircuitBreakerAction($webhookId, 'disable', $newCount);
        } elseif ($webhook->circuit_breaker_mode === 'cooldown' && $webhook->disabled_at === null) {
            // First time hitting threshold in cooldown mode — record disabled_at to start cooldown
            $this->table->setDisabledAt($webhookId);
            $this->logCircuitBreakerAction($webhookId, 'cooldown', $newCount);
        }

        return true;
    }

    /**
     * Write an audit log entry when the circuit breaker trips for a webhook.
     *
     * @param   int     $webhookId  The webhook subscription ID.
     * @param   string  $mode       The circuit breaker mode ('disable' or 'cooldown').
     * @param   int     $failures   The consecutive failure count that triggered the action.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    private function logCircuitBreakerAction(int $webhookId, string $mode, int $failures): void
    {
        Log::add(
            sprintf(
                'Circuit breaker tripped for webhook #%d (mode: %s, consecutive failures: %d).',
                $webhookId,
                $mode,
                $failures
            ),
            Log::WARNING,
            'com_webhooks'
        );
    }

    /**
     * Check if a webhook's circuit breaker is currently open (tripped).
     *
     * For 'disable' mode: open when state=0 and disabled_at is set.
     * For 'cooldown' mode: supports CLOSED -> OPEN -> HALF-OPEN -> CLOSED/OPEN transitions.
     *   - If cooldown has not expired: circuit is open.
     *   - If cooldown expired and not half-open: transitions to half-open (allows one probe).
     *   - If cooldown expired and already half-open: circuit is open (probe already dispatched).
     *
     * @param   object  $webhook  The webhook record.
     *
     * @return  bool  True if the circuit is open and delivery should be skipped.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function isOpen(object $webhook): bool
    {
        // Disable mode: open when disabled by circuit breaker
        if ($webhook->circuit_breaker_mode === 'disable' && (int) $webhook->state === 0 && $webhook->disabled_at !== null) {
            return true;
        }

        // Cooldown mode: half-open state machine
        if ($webhook->circuit_breaker_mode === 'cooldown') {
            $failures  = (int) $webhook->consecutive_failures;
            $threshold = (int) $webhook->circuit_breaker_threshold;

            if ($failures >= $threshold && $webhook->disabled_at !== null) {
                $cooldownDelay = $this->getCooldownDelay($webhook);
                $disabledAt    = new \DateTimeImmutable($webhook->disabled_at, new \DateTimeZone('UTC'));
                $cooldownEnd   = $disabledAt->modify('+' . $cooldownDelay . ' seconds');
                $now           = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

                if ($now < $cooldownEnd) {
                    // Cooldown has not expired — circuit is open
                    return true;
                }

                if ((int) ($webhook->circuit_breaker_half_open ?? 0) === 0) {
                    // Cooldown expired, not yet half-open — attempt to transition to half-open
                    // setHalfOpen returns false if another worker won the race
                    if ($this->table->setHalfOpen((int) $webhook->id, true)) {
                        return false;
                    }

                    // Another worker already claimed the half-open probe
                    return true;
                }

                // Already half-open — probe already dispatched, block further deliveries
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate cooldown delay based on consecutive failures.
     *
     * Uses exponential backoff: base_interval * 2^(failures / threshold).
     *
     * @param   object  $webhook  The webhook record.
     *
     * @return  int  Additional delay in seconds (0 if no cooldown).
     *
     * @since  __DEPLOY_VERSION__
     */
    public function getCooldownDelay(object $webhook): int
    {
        if ($webhook->circuit_breaker_mode !== 'cooldown') {
            return 0;
        }

        $failures  = (int) $webhook->consecutive_failures;
        $threshold = (int) $webhook->circuit_breaker_threshold;

        if ($failures < $threshold) {
            return 0;
        }

        // Progressive cooldown: doubles for each multiple of threshold exceeded
        $multiplier = (int) floor($failures / $threshold);
        $maxDelay   = (int) ComponentHelper::getParams('com_webhooks')->get('max_retry_delay', 3600);

        return min($maxDelay, (int) pow(2, $multiplier) * 60);
    }
}
