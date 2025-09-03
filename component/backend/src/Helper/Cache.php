<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Helper;

// Protect from unauthorized access
defined('_JEXEC') or die();

use Joomla\CMS\Filter\InputFilter as JFilterInput;
use Joomla\Filesystem\File as JFile;
use Joomla\Filesystem\Folder as JFolder;
use Joomla\Registry\Registry as JRegistry;

/**
 * Handles the caching of lengthy database operations, irrespective of Joomla!'s cache status.
 *
 * @since 3.0.0
 */
class Cache
{
	/** @var  string  Absolute path to the cache directory */
	private $cachePath = null;

	/** @var  string  The configured cache domain */
	private $domain = null;

	/** @var  int  Last cache update timestamp */
	private $lastUpdate = null;

	/** @var  \Joomla\Registry\Registry  A registry object holding the cached data */
	private $registry = null;

	/** @var  bool  Can I cache data? */
	private $hasCache = null;

	/**
	 * Public constructor
	 *
	 * @param   string  $domain  The cache domain
	 * @param   int     $ttl     Cache time in seconds, default 900 seconds (15 minutes)
	 */
	public function __construct(string $domain = 'cpanelstats', int $ttl = 900)
	{
		// Get the domain
		$filter       = JFilterInput::getInstance();
		$domain       = $filter->clean($domain, 'CMD');
		$this->domain = $domain;

		// Get the cache paths
		$this->cachePath = JPATH_CACHE . '/com_socialmagick/' . $this->domain . '.ini';

		// Create a new registry
		$this->registry = new JRegistry();

		// Load the registry
		$this->hasCache = true;

		try
		{
			$folderExists = @is_dir(dirname($this->cachePath));
		}
		catch (\Exception $e)
		{
			return false;
		}

		if ($folderExists)
		{
			try
			{
				$fileExists = @is_file($this->cachePath);
			}
			catch (\Exception $e)
			{
				$fileExists = false;
			}

			if ($fileExists)
			{
				$this->lastUpdate = @filemtime($this->cachePath);

				if ($this->lastUpdate === false)
				{
					$this->lastUpdate = 0;
				}

				if ($this->lastUpdate != 0)
				{
					$now = time();

					if ($this->lastUpdate > ($now - $ttl))
					{
						// Only loads cache if its age is at least $ttl seconds since now
						$this->registry->loadFile($this->cachePath, 'INI');
					}
				}
			}
			else
			{
				$this->lastUpdate = 0;
			}
		}
		else
		{
			$this->lastUpdate = 0;

			try
			{
				$result = JFolder::create(dirname($this->cachePath));
			}
			catch (\Exception $e)
			{
				$result = false;
			}

			if (!$result)
			{
				$this->hasCache = false;
			}
		}
	}

	/**
	 * Get a cached value
	 *
	 * @param   string  $key      The key to get
	 * @param   mixed   $default  The default value to return if the cache key doesn't exist
	 *
	 * @return mixed|null
	 */
	public function getValue(string $key, $default = null)
	{
		if (!$this->hasCache)
		{
			return $default;
		}

		return $this->registry->get($key, $default);
	}

	/**
	 * Set a cached value
	 *
	 * @param   string  $key    The key to set
	 * @param   mixed   $value  The value to store under the specified key
	 *
	 * @return  void
	 */
	public function setValue(string $key, $value): void
	{
		if (!$this->hasCache)
		{
			return;
		}

		$this->registry->set($key, $value);
	}

	/**
	 * Saves the cache data to disk
	 *
	 * @return  void
	 */
	public function save(): void
	{
		if (!$this->hasCache)
		{
			return;
		}

		$serialized = $this->registry->toString('INI');

		try
		{
			JFile::write($this->cachePath, $serialized);
		}
		catch (\Exception $e)
		{
			// Swallow.
		}
	}
}
