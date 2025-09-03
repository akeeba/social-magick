<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Model\UpgradeModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Adapter\PackageAdapter;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;

/**
 * Akeeba Release System package extension installation script file.
 *
 * @see https://docs.joomla.org/Manifest_files#Script_file
 * @see UpgradeModel
 * @noinspection PhpUnused
 */
class Pkg_SocialmagickInstallerScript extends InstallerScript
{
	/**
	 * @var   DatabaseDriver|DatabaseInterface|null
	 */
	protected DatabaseDriver|DatabaseInterface|null $dbo;

	protected $allowDowngrades = true;

	protected $minimumPhp = '8.1.0';

	protected $minimumJoomla = '5.3.0';

	/**
	 * Called after any type of installation / uninstallation action.
	 *
	 * @param   string          $type    Which action is happening (install|uninstall|discover_install|update)
	 * @param   PackageAdapter  $parent  The object responsible for running this script
	 *
	 * @return  bool
	 * @since   9.0.0
	 */
	public function postflight(string $type, PackageAdapter $parent): bool
	{
		$this->setDboFromAdapter($parent);

		// Do not run on uninstall.
		if ($type === 'uninstall')
		{
			return true;
		}

		// Forcibly create the autoload_psr4.php file afresh.
		if (class_exists(JNamespacePsr4Map::class))
		{
			try
			{
				$nsMap = new JNamespacePsr4Map();

				@clearstatcache(JPATH_CACHE . '/autoload_psr4.php');

				if (function_exists('opcache_invalidate'))
				{
					@opcache_invalidate(JPATH_CACHE . '/autoload_psr4.php');
				}

				@clearstatcache(JPATH_CACHE . '/autoload_psr4.php');
				$nsMap->create();

				if (function_exists('opcache_invalidate'))
				{
					@opcache_invalidate(JPATH_CACHE . '/autoload_psr4.php');
				}

				$nsMap->load();
			}
			catch (Throwable)
			{
				// In case of failure, just try to delete the old autoload_psr4.php file.
				if (function_exists('opcache_invalidate'))
				{
					@opcache_invalidate(JPATH_CACHE . '/autoload_psr4.php');
				}

				@unlink(JPATH_CACHE . '/autoload_psr4.php');
				@clearstatcache(JPATH_CACHE . '/autoload_psr4.php');
			}
		}

		$this->invalidateFiles();

		// Run the post-upgrade code
		$model = $this->getUpgradeModel();

		if (empty($model))
		{
			return true;
		}

		return $model->postflight($type, $parent);
	}

	/**
	 * Get the UpgradeModel of the installed component
	 *
	 * @return  UpgradeModel|null  The upgrade Model. NULL if it cannot be loaded.
	 * @since   9.0.0
	 */
	private function getUpgradeModel(): ?UpgradeModel
	{
		// Make sure the latest version of the Model file will be loaded, regardless of the opcache state.
		$filePath = JPATH_ADMINISTRATOR . '/components/com_socialmagick/src/Model/UpgradeModel.php';

		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($filePath = JPATH_ADMINISTRATOR . '/components/com_socialmagick/src/Model/UpgradeModel.php', true);
		}

		// Can I please load the model?
		if (!class_exists('\Akeeba\Component\SocialMagick\Administrator\Model\UpgradeModel'))
		{
			if (!file_exists($filePath) || !is_readable($filePath))
			{
				return null;
			}

			include_once $filePath;
		}

		if (!class_exists('\Akeeba\Component\SocialMagick\Administrator\Model\UpgradeModel'))
		{
			return null;
		}

		try
		{
			$upgradeModel = new UpgradeModel();
		}
		catch (Throwable)
		{
			return null;
		}

		if (method_exists($upgradeModel, 'setDatabase'))
		{
			$upgradeModel->setDatabase($this->dbo ?? Factory::getContainer()->get(DatabaseInterface::class));
		}
		elseif (method_exists($upgradeModel, 'setDbo'))
		{
			$upgradeModel->setDbo($this->dbo ?? Factory::getContainer()->get(DatabaseInterface::class));
		}

		if (method_exists($upgradeModel, 'init'))
		{
			$upgradeModel->init();
		}

		return $upgradeModel;
	}

	/**
	 * Set the database object from the installation adapter, if possible
	 *
	 * @param   InstallerAdapter|mixed  $adapter  The installation adapter, hopefully.
	 *
	 * @return  void
	 * @since   7.1.0
	 */
	private function setDboFromAdapter(mixed $adapter): void
	{
		$this->dbo = null;

		if (class_exists(InstallerAdapter::class) && ($adapter instanceof InstallerAdapter))
		{
			/**
			 * If this is Joomla 4.2+, the adapter has a protected getDatabase() method which we can access with the
			 * magic property $adapter->db. On Joomla 4.1 and lower this is not available. So, we have to first figure
			 * out if we can actually use the magic property...
			 */

			try
			{
				$refObj = new ReflectionObject($adapter);

				if ($refObj->hasMethod('getDatabase'))
				{
					$this->dbo = $adapter->db;

					return;
				}
			}
			catch (Throwable)
			{
				// If something breaks, we will fall through
			}
		}

		$this->dbo = Factory::getContainer()->get(DatabaseInterface::class);
	}

	private function invalidateFiles(): void
	{
		$extensionsFromPackage = $this->invF_getExtensionsFromManifest($this->invF_getManifestXML(__CLASS__));

		foreach ($extensionsFromPackage as $element)
		{
			if (str_starts_with($element, 'plg_'))
			{
				[$dummy, $folder, $plugin] = explode('_', $element);

				$paths = [
					sprintf('%s/%s/%s/services', JPATH_PLUGINS, $folder, $plugin),
					sprintf('%s/%s/%s/src', JPATH_PLUGINS, $folder, $plugin),
				];
			}
			elseif (str_starts_with($element, 'com_'))
			{
				$paths = [
					sprintf('%s/components/%s/services', JPATH_ADMINISTRATOR, $element),
					sprintf('%s/components/%s/src', JPATH_ADMINISTRATOR, $element),
					sprintf('%s/components/%s/src', JPATH_SITE, $element),
					sprintf('%s/components/%s/src', JPATH_API, $element),
				];
			}
			elseif (str_starts_with($element, 'mod_'))
			{
				$paths = [
					sprintf('%s/modules/%s/services', JPATH_ADMINISTRATOR, $element),
					sprintf('%s/modules/%s/src', JPATH_ADMINISTRATOR, $element),
					sprintf('%s/modules/%s/services', JPATH_SITE, $element),
					sprintf('%s/modules/%s/src', JPATH_SITE, $element),
				];
			}
			else
			{
				continue;
			}

			foreach ($paths as $path)
			{
				$this->invF_recursiveClearCache($path);
			}
		}

		$this->invF_clearFileInOPCache(JPATH_CACHE . '/autoload_psr4.php');
	}

	private function invF_getManifestXML($class): ?SimpleXMLElement
	{
		// Get the package element name
		$myPackage = strtolower(str_replace('InstallerScript', '', $class));

		// Get the package's manifest file
		$filePath = JPATH_MANIFESTS . '/packages/' . $myPackage . '.xml';

		if (!@file_exists($filePath) || !@is_readable($filePath))
		{
			return null;
		}

		$xmlContent = @file_get_contents($filePath);

		if (empty($xmlContent))
		{
			return null;
		}

		return new SimpleXMLElement($xmlContent);
	}

	private function invF_xmlNodeToExtensionName(SimpleXMLElement $fileField): ?string
	{
		$type = (string) $fileField->attributes()->type;
		$id   = (string) $fileField->attributes()->id;

		switch ($type)
		{
			case 'component':
			case 'file':
			case 'library':
				$extension = $id;
				break;

			case 'plugin':
				$group     = (string) $fileField->attributes()->group ?? 'system';
				$extension = 'plg_' . $group . '_' . $id;
				break;

			case 'module':
				$client    = (string) $fileField->attributes()->client ?? 'site';
				$extension = (($client != 'site') ? 'a' : '') . $id;
				break;

			default:
				$extension = null;
				break;
		}

		return $extension;
	}

	private function invF_getExtensionsFromManifest(?SimpleXMLElement $xml): array
	{
		if (empty($xml))
		{
			return [];
		}

		$extensions = [];

		foreach ($xml->xpath('//files/file') as $fileField)
		{
			$extensions[] = $this->invF_xmlNodeToExtensionName($fileField);
		}

		return array_filter($extensions);
	}

	private function invF_clearFileInOPCache(string $file): bool
	{
		static $hasOpCache = null;

		if (is_null($hasOpCache))
		{
			$hasOpCache = ini_get('opcache.enable')
			              && function_exists('opcache_invalidate')
			              && (!ini_get('opcache.restrict_api')
			                  || stripos(
				                     realpath($_SERVER['SCRIPT_FILENAME']), ini_get('opcache.restrict_api')
			                     ) === 0);
		}

		if ($hasOpCache && (strtolower(substr($file, -4)) === '.php'))
		{
			$ret = opcache_invalidate($file, true);

			@clearstatcache($file);

			return $ret;
		}

		return false;
	}

	private function invF_recursiveClearCache(string $path): void
	{
		if (!@is_dir($path))
		{
			return;
		}

		/** @var DirectoryIterator $file */
		foreach (new DirectoryIterator($path) as $file)
		{
			if ($file->isDot() || $file->isLink())
			{
				continue;
			}

			if ($file->isDir())
			{
				$this->invF_recursiveClearCache($file->getPathname());

				continue;
			}

			if (!$file->isFile())
			{
				continue;
			}

			$this->invF_clearFileInOPCache($file->getPathname());
		}
	}
}
