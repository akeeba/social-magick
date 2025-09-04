<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension\Traits;

\defined('_JEXEC') || die;

use \Akeeba\Plugin\System\SocialMagick\Library\ImageGenerator;

trait ImageGeneratorHelperTrait
{
	/**
	 * The ImageGenerator instance used throughout the plugin
	 *
	 * @var   ImageGenerator|null
	 * @since 1.0.0
	 */
	protected ?ImageGenerator $helper = null;

	protected function getHelper(): ?ImageGenerator
	{
		$this->helper ??= call_user_func(function () {
			$helper = new ImageGenerator($this->params, $this->getDatabase());
			/** @noinspection PhpParamsInspection */
			$helper->setApplication($this->getApplication());

			return $helper;
		});

		return $this->helper;
	}
}