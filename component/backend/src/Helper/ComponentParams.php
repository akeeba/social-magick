<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * A helper to programmatically modify the component's parameters, including saving them.
 *
 * Joomla provides its own helper, but it only reads the component's parameters. It cannot save them.
 *
 * This helper addresses the missing functionality in Joomla's ComponentHelper.
 *
 * @since  3.0.0
 */
class ComponentParams
{
	/**
	 * Actually Save the params into the db
	 *
	 * @param   Registry  $params
	 *
	 * @since   9.0.0
	 */
	public static function save(Registry $params, string $option = 'com_socialmagick'): void
	{
		/** @var DatabaseDriver $db */
		$db   = JoomlaFactory::getContainer()->get(DatabaseInterface::class);
		$data = $params->toString('JSON');

		$sql = (method_exists($db, 'createQuery') ? $db->createQuery() : $db->getQuery(true))
			->update($db->qn('#__extensions'))
			->set($db->qn('params') . ' = ' . $db->q($data))
			->where($db->qn('element') . ' = :option')
			->where($db->qn('type') . ' = ' . $db->q('component'))
			->bind(':option', $option);

		$db->setQuery($sql);

		try
		{
			$db->execute();

			// The component parameters are cached. We just changed them. Therefore we MUST reset the system cache which holds them.
			CacheCleaner::clearCacheGroups(['_system'], [0, 1]);
		}
		catch (\Exception $e)
		{
			// Don't sweat if it fails
		}

		// Reset ComponentHelper's cache
		$refClass = new \ReflectionClass(ComponentHelper::class);
		$refProp  = $refClass->getProperty('components');

		$refProp->setAccessible(true);

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$components = $refClass->getStaticPropertyValue('components');
		}
		else
		{
			$components = $refProp->getValue();
		}

		$components['com_akeebabackup']->params = $params;

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$refClass->setStaticPropertyValue('components', $components);
		}
		else
		{
			$refProp->setValue($components);
		}

	}

}