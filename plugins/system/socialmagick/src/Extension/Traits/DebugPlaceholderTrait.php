<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension\Traits;

\defined('_JEXEC') || die;

use Joomla\CMS\User\UserHelper;

trait DebugPlaceholderTrait
{
	/**
	 * The placeholder variable to be replaced by the image link when Debug Link is enabled.
	 *
	 * @var  string
	 */
	protected string $debugLinkPlaceholder;

	/**
	 * Get a random, unique placeholder for the debug OpenGraph image link
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	private function getDebugLinkPlaceholder(): string
	{
		return $this->debugLinkPlaceholder ??= '{' . UserHelper::genRandomPassword(32) . '}';
	}

}