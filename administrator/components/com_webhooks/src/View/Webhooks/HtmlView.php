<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_webhooks
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Webhooks\Administrator\View\Webhooks;

use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\MVC\View\ListView;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * View class for a list of webhooks.
 *
 * @since  __DEPLOY_VERSION__
 */
class HtmlView extends ListView
{
    /**
     * The help link for the view.
     *
     * @var    string
     * @since  __DEPLOY_VERSION__
     */
    protected $helpLink = 'Webhooks';

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @since  __DEPLOY_VERSION__
     */
    public function __construct(array $config)
    {
        if (empty($config['option'])) {
            $config['option'] = 'com_webhooks';
        }

        $config['toolbar_icon'] = 'link';

        parent::__construct($config);
    }

    /**
     * Prepare view data.
     *
     * @return  void
     *
     * @since  __DEPLOY_VERSION__
     */
    protected function initializeView()
    {
        parent::initializeView();

        $this->canDo = ContentHelper::getActions('com_webhooks');
    }
}
