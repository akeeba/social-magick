<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Field;

defined('_JEXEC') || die();

use Akeeba\Component\SocialMagick\Administrator\Library\JoomlaMedia\MediaProviderHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Joomla list field to select a media filesystem adapter.
 *
 * @since  3.0.0
 */
class MediaAdaptersField extends ListField
{
	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	protected function getOptions()
	{
		$options = parent::getOptions();

		$app                 = Factory::getApplication();
		$cParams             = ComponentHelper::getParams('com_socialmagick');
		$mediaProviderHelper = new MediaProviderHelper($app, $cParams);
		$providers           = $mediaProviderHelper->getProviders();

		foreach ($providers as $provider)
		{
			$displayName = $provider->getDisplayName();
			$adapters    = $mediaProviderHelper->getAdapters($provider->getID());

			foreach ($adapters as $adapter)
			{
				$options[] = HTMLHelper::_(
					'select.option', $provider->getID() . '-' . $adapter->getAdapterName(), $displayName . ' – ' . $adapter->getAdapterName()
				);
			}
		}

		return $options;
	}
}