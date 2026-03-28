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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Generates HMAC signature and timestamp headers for webhook deliveries.
 *
 * Produces:
 * - X-Webhook-Signature: sha256={hmac}
 * - X-Webhook-Timestamp: {unix_timestamp}
 *
 * The signed material is `{timestamp}.{payload}` so that a captured signature
 * cannot be replayed with a different timestamp.
 *
 * @since  __DEPLOY_VERSION__
 */
class HmacSigner
{
    /**
     * Minimum acceptable secret length (bytes).
     */
    private const MIN_SECRET_LENGTH = 32;

    /**
     * Sign a payload with the given secret.
     *
     * @param   string  $payload  The JSON request body.
     * @param   string  $secret   The webhook's HMAC secret.
     *
     * @return  array  Associative array of headers to include in the request.
     *
     * @throws  \InvalidArgumentException  If the secret is shorter than MIN_SECRET_LENGTH bytes.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function sign(string $payload, string $secret): array
    {
        if (\strlen($secret) < self::MIN_SECRET_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Webhook HMAC secret must be at least %d bytes.', self::MIN_SECRET_LENGTH)
            );
        }

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        return [
            'X-Webhook-Signature' => 'sha256=' . $signature,
            'X-Webhook-Timestamp' => (string) $timestamp,
        ];
    }

    /**
     * Verify a signature against a payload and secret.
     *
     * @param   string  $payload    The JSON request body.
     * @param   string  $secret     The webhook's HMAC secret.
     * @param   string  $signature  The signature from the X-Webhook-Signature header (with sha256= prefix).
     * @param   int     $timestamp  The timestamp from the X-Webhook-Timestamp header.
     * @param   int     $tolerance  Maximum age in seconds. Pass -1 (default) to use the
     *                              signature_tolerance component configuration parameter.
     *
     * @return  bool  True if the signature is valid and the timestamp is fresh.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function verify(string $payload, string $secret, string $signature, int $timestamp, int $tolerance = -1): bool
    {
        if ($tolerance < 0) {
            $tolerance = (int) ComponentHelper::getParams('com_webhooks')->get('signature_tolerance', 300);
        }

        // Check freshness
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        // Strip the sha256= prefix
        $hash = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        return hash_equals($expected, $hash);
    }
}
