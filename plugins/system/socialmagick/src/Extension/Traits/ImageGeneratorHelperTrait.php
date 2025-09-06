<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension\Traits;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator\ImageGenerator;
use Joomla\CMS\Component\ComponentHelper;

trait ImageGeneratorHelperTrait
{
	/**
	 * The ImageGenerator object instance.
	 *
	 * @var   ImageGenerator
	 * @since 1.0.0
	 */
	protected ImageGenerator $imageGeneratorHelper;

	/**
	 * Returns the ImageGenerator object instance.
	 *
	 * @return ImageGenerator
	 */
	protected function getImageGenerator(): ImageGenerator
	{
		return $this->imageGeneratorHelper ??= call_user_func(function () {
			$helper = new ImageGenerator(
				ComponentHelper::getComponent('com_socialmagick', true)->getParams(),
				$this->getDatabase()
			);
			/** @noinspection PhpParamsInspection */
			$helper->setApplication($this->getApplication());

			return $helper;
		});
	}
}