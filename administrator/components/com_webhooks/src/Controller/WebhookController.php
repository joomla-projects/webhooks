<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Http\HttpFactory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Webhook form controller.
 *
 * @since  __DEPLOY_VERSION__
 */
class WebhookController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $text_prefix = 'COM_WEBHOOKS_WEBHOOK';

    /**
     * Verify a webhook endpoint via subscription handshake.
     *
     * Sends a GET request with a challenge token to the webhook URL.
     * If the endpoint returns the exact challenge, the webhook is marked as verified.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    public function verify()
    {
        if (!Session::checkToken('json')) {
            echo new JsonResponse(null, Text::_('JINVALID_TOKEN'), true);
            $this->app->close();

            return;
        }

        $id   = $this->input->getInt('id', 0);
        $user = $this->app->getIdentity();

        if (!$user->authorise('core.edit', 'com_webhooks.webhook.' . $id)
            && !$user->authorise('core.edit.own', 'com_webhooks.webhook.' . $id)) {
            echo new JsonResponse(null, Text::_('JERROR_ALERTNOAUTHOR'), true);
            $this->app->close();

            return;
        }

        $model = $this->getModel();
        $table = $model->getTable();

        if (!$table->load($id)) {
            echo new JsonResponse(null, Text::_('JLIB_APPLICATION_ERROR_ITEMNOTFOUND'), true);
            $this->app->close();

            return;
        }

        $token = bin2hex(random_bytes(32));
        $table->verify_token = $token;
        $table->store();

        $url = $table->url
            . (str_contains($table->url, '?') ? '&' : '?')
            . 'hub.challenge=' . urlencode($token)
            . '&hub.mode=subscribe';

        try {
            $timeout  = (int) ComponentHelper::getParams('com_webhooks')->get('request_timeout', 10);
            $http     = HttpFactory::getHttp();
            $response = $http->get($url, [], $timeout);
            $body     = trim($response->body);

            if ($body === $token) {
                $table->verified     = 1;
                $table->verify_token = null;
                $table->store();

                echo new JsonResponse(['verified' => true], Text::_('COM_WEBHOOKS_VERIFY_SUCCESS'));
            } else {
                $table->verify_token = null;
                $table->store();

                echo new JsonResponse(null, Text::_('COM_WEBHOOKS_VERIFY_FAILED'), true);
            }
        } catch (\Exception $e) {
            $table->verify_token = null;
            $table->store();

            echo new JsonResponse(null, Text::sprintf('COM_WEBHOOKS_VERIFY_ERROR', $e->getMessage()), true);
        }

        $this->app->close();
    }
}
