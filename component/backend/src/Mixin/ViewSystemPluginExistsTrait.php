<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Mixin;

\defined('_JEXEC') || die;

use Joomla\CMS\Plugin\PluginHelper;

/**
 * A trait for MVC Views which populates parameters indicating if the SocialMagick plugin exists and is enabled.
 *
 * @since  3.0.0
 */
trait ViewSystemPluginExistsTrait
{
	/**
	 * Does the system plugin exist?
	 *
	 * @var  bool
	 */
	public bool $pluginExists = false;

	/**
	 * Is the system plugin enabled?
	 *
	 * @var  bool
	 */
	public bool $pluginActive = false;

	private function populateSystemPluginExists()
	{
		$this->pluginExists = @is_dir(JPATH_ROOT . '/plugins/system/socialmagick');

		if (!$this->pluginExists)
		{
			return;
		}

		$this->pluginActive = PluginHelper::isEnabled('system', 'socialmagick');
	}
}