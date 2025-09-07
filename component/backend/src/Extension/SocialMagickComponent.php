<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Extension;

defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Service\Html\SocialMagick;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Psr\Container\ContainerInterface;

/**
 * Extension class for SocialMagick.
 *
 * Sets up basic services, and registers our HTML helper.
 *
 * @since  3.0.0
 */
class SocialMagickComponent extends MVCComponent implements
	BootableExtensionInterface
{
	use HTMLRegistryAwareTrait;

	private static $hasRegisteredHandler = false;

	public function boot(ContainerInterface $container)
	{
		$this->getRegistry()->register('socialmagick', new SocialMagick());
	}
}