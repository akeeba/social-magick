<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Akeeba\Plugin\SocialMagick\Categories\Extension\Categories;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface {
	public function register(Container $container)
	{
		// SocialMagick plugins cannot work without the SocialMagick component installed and enabled.
		if (!ComponentHelper::getComponent('com_socialmagick', true)->enabled)
		{
			return;
		}

		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$config  = (array) PluginHelper::getPlugin('socialmagick', 'categories');

				if (version_compare(JVERSION, '5.999.999', 'gt'))
				{
					$plugin  = new Categories($config);
				}
				else
				{
					$subject = $container->get(DispatcherInterface::class);
					$plugin  = new Categories($subject, $config);
				}

				$plugin->setApplication(Factory::getApplication());
				$plugin->setDatabase($container->get(DatabaseInterface::class));

				return $plugin;
			}
		);
	}
};
