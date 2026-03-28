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
use Joomla\Component\Webhooks\Administrator\Table\WebhookLogTable;
use Joomla\Component\Webhooks\Administrator\Table\WebhookTable;
use Joomla\Component\Webhooks\Administrator\Transport\WebhookMessage;
use Joomla\Component\Webhooks\Administrator\Transport\WebhookTransportInterface;
use Joomla\Http\Http;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Fetches pending queue messages, sends HTTP POST requests, logs results, handles retries.
 *
 * @since  __DEPLOY_VERSION__
 */
class DeliveryService
{
    /**
     * Response headers that are always redacted before being written to the log.
     * Admins may append additional headers via the extra_redacted_headers config param.
     */
    private const BASE_REDACTED_HEADERS = [
        'x-webhook-signature',
        'authorization',
    ];
    /**
     * Constructor.
     *
     * @param   WebhookTransportInterface  $transport       The message transport.
     * @param   HmacSigner                 $signer          The HMAC signer.
     * @param   CircuitBreaker             $circuitBreaker  The circuit breaker.
     * @param   Http                       $http            The HTTP client.
     * @param   WebhookTable               $webhookTable    The webhook table instance.
     * @param   WebhookLogTable            $logTable        The webhook log table instance.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(
        private WebhookTransportInterface $transport,
        private HmacSigner $signer,
        private CircuitBreaker $circuitBreaker,
        private Http $http,
        private WebhookTable $webhookTable,
        private WebhookLogTable $logTable,
    ) {
    }

    /**
     * Process the queue: fetch pending messages and attempt delivery.
     *
     * @param   int  $batchSize  Maximum number of messages to process.
     *
     * @return  array  Summary: ['processed' => int, 'delivered' => int, 'failed' => int]
     *
     * @since  __DEPLOY_VERSION__
     */
    public function processQueue(int $batchSize = 50): array
    {
        $messages = $this->transport->fetchPending($batchSize);

        $summary = ['processed' => 0, 'delivered' => 0, 'failed' => 0];

        foreach ($messages as $message) {
            if (!$this->transport->markProcessing($message->id, gethostname() . ':' . getmypid())) {
                // Another worker claimed this message
                continue;
            }

            $summary['processed']++;

            $webhook = $this->loadWebhook($message->webhookId);

            if (!$webhook || (int) $webhook->state !== 1) {
                $this->transport->markDead($message->id);
                $summary['failed']++;

                continue;
            }

            if ($this->circuitBreaker->isOpen($webhook)) {
                $this->transport->markFailed($message->id, null);
                $summary['failed']++;

                continue;
            }

            $result = $this->deliver($message, $webhook);

            if ($result['success']) {
                $this->transport->markDelivered($message->id);
                $this->circuitBreaker->recordSuccess($message->webhookId);
                $summary['delivered']++;
            } else {
                $this->handleFailure($message, $webhook, $result);
                $this->circuitBreaker->recordFailure($message->webhookId, $webhook);
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * Deliver a single message to its webhook endpoint.
     *
     * @param   WebhookMessage  $message  The queue message.
     * @param   object          $webhook  The webhook record.
     *
     * @return  array  Result: ['success' => bool, 'status_code' => ?int, 'error' => ?string,
     *                          'duration_ms' => int, 'response_headers' => ?string, 'response_body' => ?string]
     *
     * @since  __DEPLOY_VERSION__
     */
    public function deliver(WebhookMessage $message, object $webhook): array
    {
        $params    = ComponentHelper::getParams('com_webhooks');
        $timeout   = (int) $params->get('request_timeout', 10);
        $userAgent = $params->get('user_agent', 'Joomla-Webhooks/1.0');

        $signatureHeaders = $this->signer->sign($message->payload, $webhook->secret);

        $headers = array_merge(
            [
                'Content-Type'       => 'application/vnd.api+json',
                'User-Agent'         => $userAgent,
                'X-Webhook-Event'    => $message->eventName,
                'X-Webhook-Delivery'    => (string) $message->id,
                'X-Webhook-Delivery-Id' => (string) $message->id,
            ],
            $signatureHeaders
        );

        $startTime = microtime(true);

        try {
            $response   = $this->http->post($webhook->url, $message->payload, $headers, $timeout);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $statusCode = $response->code;
            $success    = $statusCode >= 200 && $statusCode < 300;

            $result = [
                'success'          => $success,
                'status_code'      => $statusCode,
                'error'            => $success ? null : 'HTTP ' . $statusCode,
                'duration_ms'      => $durationMs,
                'response_headers' => $this->truncate($this->formatHeaders($response->headers)),
                'response_body'    => $this->truncate($response->body),
            ];
        } catch (\Exception $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            $result = [
                'success'          => false,
                'status_code'      => null,
                'error'            => $this->sanitizeErrorMessage($e),
                'duration_ms'      => $durationMs,
                'response_headers' => null,
                'response_body'    => null,
            ];
        }

        $this->logDelivery($message, $webhook, $headers, $result);

        return $result;
    }

    /**
     * Handle a failed delivery: calculate retry time or mark as dead.
     *
     * @param   WebhookMessage  $message  The failed message.
     * @param   object          $webhook  The webhook record.
     * @param   array           $result   The delivery result including response_headers.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    private function handleFailure(WebhookMessage $message, object $webhook, array $result): void
    {
        $newAttempts = $message->attempts + 1;
        $maxAttempts = (int) ($webhook->retry_count ?: 5);

        if ($newAttempts >= $maxAttempts) {
            $this->transport->markDead($message->id);

            return;
        }

        $nextRetry = $this->calculateNextRetry($webhook, $newAttempts);

        // Add cooldown delay if circuit breaker is in cooldown mode
        $cooldownDelay = $this->circuitBreaker->getCooldownDelay($webhook);

        if ($cooldownDelay > 0) {
            $nextRetry = $nextRetry->modify('+' . $cooldownDelay . ' seconds');
        }

        // Respect Retry-After header from the endpoint
        $retryAfter = $this->parseRetryAfter($result['response_headers'] ?? '');

        if ($retryAfter !== null) {
            $retryAfterTime = new \DateTimeImmutable('+' . $retryAfter . ' seconds', new \DateTimeZone('UTC'));

            if ($retryAfterTime > $nextRetry) {
                $nextRetry = $retryAfterTime;
            }
        }

        $this->transport->markFailed($message->id, $nextRetry);
    }

    /**
     * Parse a Retry-After header value from raw response headers.
     *
     * Supports integer-seconds (e.g. "120") and HTTP-date (RFC 7231 IMF-fixdate) formats.
     * Returns delta seconds, capped at 86400 (24 hours) to prevent abuse.
     *
     * @param   string  $rawHeaders  The raw response headers string.
     *
     * @return  ?int  Delta seconds until retry, or null if absent/unparseable.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function parseRetryAfter(string $rawHeaders): ?int
    {
        if ($rawHeaders === '') {
            return null;
        }

        // Search for Retry-After header (case-insensitive)
        if (!preg_match('/^Retry-After:\s*(.+)$/mi', $rawHeaders, $matches)) {
            return null;
        }

        $value = trim($matches[1]);

        // Integer seconds format
        if ($value !== '' && ctype_digit($value)) {
            $seconds = min((int) $value, 86400);

            return $seconds > 0 ? $seconds : null;
        }

        // HTTP-date format (RFC 7231 IMF-fixdate)
        $date = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'));

        if ($date === false) {
            return null;
        }

        $now   = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $delta = $date->getTimestamp() - $now->getTimestamp();

        if ($delta <= 0) {
            return null;
        }

        return min($delta, 86400);
    }

    /**
     * Calculate the next retry time based on the webhook's retry strategy.
     *
     * @param   object  $webhook   The webhook record.
     * @param   int     $attempt   The attempt number (1-based).
     *
     * @return  \DateTimeImmutable  The next retry time.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function calculateNextRetry(object $webhook, int $attempt): \DateTimeImmutable
    {
        $baseInterval = (int) ($webhook->retry_interval ?: 60);
        $now          = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $maxDelay = (int) ComponentHelper::getParams('com_webhooks')->get('max_retry_delay', 3600);

        if ($webhook->retry_strategy === 'fixed') {
            $delay = $baseInterval;
        } else {
            // Exponential backoff: base * 2^(attempt-1), capped at max_retry_delay
            $delay = min($maxDelay, $baseInterval * (int) pow(2, $attempt - 1));
        }

        return $now->modify('+' . $delay . ' seconds');
    }

    /**
     * Log a delivery attempt using the WebhookLogTable.
     *
     * @param   WebhookMessage  $message  The queue message.
     * @param   object          $webhook  The webhook record.
     * @param   array           $headers  The request headers sent.
     * @param   array           $result   The delivery result.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    private function logDelivery(WebhookMessage $message, object $webhook, array $headers, array $result): void
    {
        $now     = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $verbose = (int) $webhook->verbose_logging;

        $this->logTable->reset();
        $this->logTable->webhook_id       = $message->webhookId;
        $this->logTable->queue_id         = $message->id;
        $this->logTable->event_name       = $message->eventName;
        $this->logTable->url              = $webhook->url;
        $this->logTable->status_code      = $result['status_code'];
        $this->logTable->success          = $result['success'] ? 1 : 0;
        $this->logTable->error_message    = $result['error'];
        $this->logTable->duration_ms      = $result['duration_ms'];
        $this->logTable->request_headers  = $verbose ? $this->formatHeaders($this->redactHeaders($headers)) : null;
        $this->logTable->request_body     = $verbose ? $message->payload : null;
        $this->logTable->response_headers = $verbose ? $result['response_headers'] : null;
        $this->logTable->response_body    = $verbose ? $result['response_body'] : null;
        $this->logTable->created          = $now;

        $this->logTable->store();
    }

    /**
     * Load a webhook record by ID using the WebhookTable.
     *
     * @param   int  $webhookId  The webhook ID.
     *
     * @return  ?object  The webhook record, or null if not found.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function loadWebhook(int $webhookId): ?object
    {
        $this->webhookTable->reset();

        if (!$this->webhookTable->load($webhookId)) {
            return null;
        }

        return (object) $this->webhookTable->getProperties();
    }

    /**
     * Redact sensitive header values before writing them to the log.
     *
     * Header names listed in REDACTED_HEADERS have their value replaced with '****'.
     *
     * @param   array  $headers  The request headers array.
     *
     * @return  array  Headers with sensitive values redacted.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function redactHeaders(array $headers): array
    {
        $extraParam   = ComponentHelper::getParams('com_webhooks')->get('extra_redacted_headers', '');
        $extraHeaders = array_filter(array_map('strtolower', array_map('trim', explode("\n", $extraParam))));
        $allRedacted  = array_unique(array_merge(self::BASE_REDACTED_HEADERS, $extraHeaders));

        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), $allRedacted, true)) {
                $headers[$name] = '****';
            }
        }

        return $headers;
    }

    /**
     * Truncate a string to the configured max_log_body_size_kb limit.
     *
     * @param   string|null  $value  The value to truncate.
     *
     * @return  string|null  Truncated value, or null if input is null.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function truncate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $maxBytes = (int) ComponentHelper::getParams('com_webhooks')->get('max_log_body_size_kb', 64) * 1024;

        if (\strlen($value) <= $maxBytes) {
            return $value;
        }

        return substr($value, 0, $maxBytes) . '[truncated]';
    }

    /**
     * Produce a safe, generic error message for log storage.
     *
     * Logs only the exception class name to prevent leaking internal paths,
     * library versions, or network topology via exception messages.
     *
     * @param   \Throwable  $e  The exception.
     *
     * @return  string  A sanitized error description.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function sanitizeErrorMessage(\Throwable $e): string
    {
        return 'Delivery failed: ' . \get_class($e);
    }

    /**
     * Format headers array as a string for logging.
     *
     * @param   array|object  $headers  The headers.
     *
     * @return  string  Formatted headers string.
     *
     * @since  __DEPLOY_VERSION__
     */
    private function formatHeaders(array|object $headers): string
    {
        $lines = [];

        foreach ((array) $headers as $name => $value) {
            if (\is_array($value)) {
                $value = implode(', ', $value);
            }

            $lines[] = $name . ': ' . $value;
        }

        return implode("\r\n", $lines);
    }
}
