<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\JoomlaMedia;

defined('_JEXEC') || die;

use Exception;
use Joomla\Application\ApplicationInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Media\Administrator\Adapter\AdapterInterface;
use Joomla\Component\Media\Administrator\Event\MediaProviderEvent;
use Joomla\Component\Media\Administrator\Exception\FileNotFoundException;
use Joomla\Component\Media\Administrator\Exception\ProviderAccountNotFoundException;
use Joomla\Component\Media\Administrator\Provider\ProviderInterface;
use Joomla\Component\Media\Administrator\Provider\ProviderManager;
use Joomla\Registry\Registry;

/**
 * Integration with Joomla's Media Manager storage providers and adapters.
 *
 * @since  3.0.0
 */
final class MediaProviderHelper
{
	/**
	 * The manager object for media file adapters.
	 *
	 * @var   ProviderManager
	 * @since 3.0.0
	 */
	private ProviderManager $providerManager;

	/**
	 * The default adapter name.
	 *
	 * @var    string|null
	 * @since  3.0.0
	 */
	private ?string $defaultAdapterName;

	/**
	 * Constructor.
	 *
	 * @param   ApplicationInterface  $app  The Joomla! application object.
	 * @param   Registry              $componentParams
	 *
	 * @throws Exception
	 * @since   3.0.0
	 */
	public function __construct(ApplicationInterface $app, private readonly Registry $componentParams)
	{
		$this->providerManager = $this->createProviderManager($app);
	}

	/**
	 * Returns all filesystem providers known to Joomla!.
	 *
	 * @return  array<ProviderInterface>
	 * @since   3.0.0
	 */
	public function getProviders(): array
	{
		return $this->getProviderManager()->getProviders();
	}

	/**
	 * Returns all adapter. If a $providerID is specified, it returns all adapters for this provider only.
	 *
	 * @param   string|null  $providerID
	 *
	 * @return  array<AdapterInterface>
	 * @throws Exception
	 * @since   3.0.0
	 */
	public function getAdapters(?string $providerID = null): array
	{
		$providers = $providerID === null
			? $this->getProviderManager()->getProviders()
			: [$this->getProvider($providerID)];

		$ret = [];

		foreach ($providers as $provider)
		{
			$ret = array_merge($ret, $provider->getAdapters());
		}

		return $ret;
	}

	/**
	 * Returns a provider object. Providers correspond to enabled `filesystem` plugins.
	 *
	 * @param   string  $id
	 *
	 * @return  ProviderInterface
	 * @throws Exception
	 * @since   3.0.0
	 */
	public function getProvider(string $id): ProviderInterface
	{
		return $this->getProviderManager()->getProvider($id);
	}

	/**
	 * Returns an adapter object. Adapters correspond to configuration sets of each enabled `filesystem` plugin.
	 *
	 * @param   string  $name
	 *
	 * @return  AdapterInterface
	 * @throws Exception
	 * @since   3.0.0
	 */
	public function getAdapter(string $name): AdapterInterface
	{
		return $this->getProviderManager()->getAdapter($name);
	}

	/**
	 * Returns the default adapter name.
	 *
	 * @return  string|null
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function getDefaultAdapterName(): ?string
	{
		if (isset($this->defaultAdapterName))
		{
			return $this->defaultAdapterName;
		}

		try
		{
			$configuredFilePath = ComponentHelper::getParams('com_media')->get('file_path', 'files');
			$adapter            = $this->getAdapter('local-' . $configuredFilePath);
		}
		catch (ProviderAccountNotFoundException)
		{
			$localFilesystemAdapters = array_values($this->getProviderManager()->getProvider('local')->getAdapters());
			$adapter                 = $localFilesystemAdapters[0] ?? null;
		}

		if ($adapter === null)
		{
			return null;
		}

		return $this->defaultAdapterName = 'local-' . $adapter->getAdapterName();
	}

	/**
	 * Splits a media file path to an adapter name and relative path into this adapter.
	 *
	 * @param   string  $path
	 *
	 * @return  array{adapter: string, path: string}
	 *
	 * @throws Exception
	 * @since   3.0.0
	 */
	public function splitMediaPath(string $path): array
	{
		$defaultAdapter = $this->getDefaultAdapterName();

		if ($defaultAdapter === null)
		{
			throw new \RuntimeException('No media adapters found. Your Joomla! Media Manager is broken.');
		}

		$parts = explode(':', $path, 2);

		if (count($parts) < 2)
		{
			array_unshift($parts, $defaultAdapter);
		}

		$adapterName = array_shift($parts);

		return [
			'adapter' => $adapterName,
			'path'    => implode('/', $parts),
		];
	}

	/**
	 * Returns a Joomla media URI to the configured adapter and subdirectory for the given filename.
	 *
	 * @param   string  $fileName  The filename to get the Joomla media URI for.
	 *
	 * @return  string
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function makeMediaURI(string $fileName): string
	{
		$adapter  = $this->componentParams
			->get('autoimage_adapter', $this->getDefaultAdapterName());
		$basePath = '/' . trim(
				$this->componentParams->get('autoimage_subdirectory', ''),
				'/'
			);
		$basePath .= '/';

		return $adapter . ':' . $basePath . $fileName;
	}

	/**
	 * Ensures that the directory structure for a given media URI exists.
	 *
	 * This method creates all necessary directories in the specified media URI path
	 * if they do not already exist.
	 *
	 * @param   string  $mediaUri  The Joomla media URI for which the directory structure should be ensured.
	 *
	 * @return  void
	 * @throws  Exception  If there is an error creating a directory.
	 * @since   3.0.0
	 */
	public function ensureDirectory(string $mediaUri): void
	{
		$uriInfo   = $this->splitMediaPath($mediaUri);
		$adapter   = $this->getAdapter($uriInfo['adapter']);
		$directory = trim(dirname($uriInfo['path']), '/');

		if (empty($directory))
		{
			return;
		}

		$pathParts = explode('/', $uriInfo['path']);
		$curDir    = '';

		while (!empty($pathParts))
		{
			$name = array_shift($pathParts);

			try
			{
				$adapter->createFolder($name, $curDir);
			}
			catch (Exception)
			{
				// This will throw if the folder exists.
			}

			$curDir = ltrim($curDir . '/' . $name, '/');
		}
	}

	/**
	 * Create or update an image file.
	 *
	 * The image data is ALWAYS converted to PNG.
	 *
	 * @param   string       $imageBlob  The image data to write. It is converted to PNG.
	 * @param   string|null  $mediaURI   The Joomla media URI to save to. NULL for a random filename.
	 *
	 * @return  string  The actual media URI used (the adapter may have changed the filename).
	 * @throws  \Random\RandomException
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function saveImage(string $imageBlob, ?string $mediaURI = null): string
	{
		if (empty($mediaURI))
		{
			$format   = $this->componentParams->get('imagetype', 'jpg') ?: 'jpg';
			$format   = in_array($format, ['png', 'jpg', 'webp']) ? $format : 'jpg';
			$mediaURI = $this->makeMediaURI(md5(random_bytes(32)) . '.' . $format);
		}

		[$importantPart,] = explode('?', $mediaURI);
		[$importantPart,] = explode('#', $importantPart);
		$parts  = explode('.', $importantPart);
		$format = array_pop($parts);
		$format = in_array($format, ['png', 'jpg', 'webp']) ? $format : 'jpg';

		self::ensureDirectory(dirname($mediaURI));

		$uriInfo   = $this->splitMediaPath($mediaURI);
		$adapter   = $this->getAdapter($uriInfo['adapter']);
		$fullPath  = $uriInfo['path'];
		$directory = dirname($fullPath);
		$fileName  = basename($fullPath);
		$imageBlob = $this->convertToImageFormat($imageBlob, $format);
		$exists    = true;

		try
		{
			$adapter->getFile($uriInfo['path']);
		}
		catch (FileNotFoundException)
		{
			$exists = false;
		}

		if ($exists)
		{
			$adapter->updateFile($fileName, $directory, $imageBlob);
		}
		else
		{
			$fileName = $adapter->createFile($fileName, $directory, $imageBlob);
		}

		return $uriInfo['adapter'] . ':' . $directory . (!empty($directory) ? '/' : '') . $fileName;
	}

	/**
	 * Converts a Joomla! Media Manager image URI to something we can insert into a media field.
	 *
	 * Basically, it takes this:
	 * local-images:/some/thing/or/another.jpg
	 * and converts it to this:
	 * images/some/thing/or/another.jpg#joomlaImage://local-images/some/thing/or/another.jpg
	 *
	 * It's an image URL relative to the site's root with a fragment which has YET ANOTHER Media Manager image URI
	 * format. Joomla. Consistency. Pick one. We have three (four with the nominal absolute URL) different ways to
	 * express and image, and we have to use the CORRECT one for each use case. Because screw the 3PDs trying to make
	 * their extensions work with this mess, that's why.
	 *
	 * @param   string  $mediaUri
	 *
	 * @return  string
	 * @throws  FileNotFoundException
	 * @since   3.0.0
	 */
	public function mediaUriToMediaFieldWeirdUriThing(string $mediaUri): string
	{
		// I have local-images:/some/thing/or/another.jpg
		// I want images/some/thing/or/another.jpg#joomlaImage://local-images/some/thing/or/another.jpg

		$mediaInfo = $this->splitMediaPath($mediaUri);
		$adapter   = $this->getAdapter($mediaInfo['adapter']);
		$path      = $mediaInfo['path'];
		$absoluteUrl = $adapter->getUrl($path);
		$rootUrl = Uri::root(false);

		$url = str_starts_with($absoluteUrl, $rootUrl) ? substr($absoluteUrl, strlen($rootUrl)) : $absoluteUrl;
		$suffix = '#joomlaImage://' . $adapter->getAdapterName() . '/' . ltrim($path, '/');

		if (!str_contains($url, '#'))
		{
			$url .= $suffix;
		}

		return $url;
	}

	/**
	 * Return a provider manager.
	 *
	 * @return  ProviderManager
	 * @since   3.0.0
	 */
	private function getProviderManager(): ProviderManager
	{
		return $this->providerManager;
	}

	/**
	 * Creates a new instance of com_media's ProviderManager object.
	 *
	 * @param   ApplicationInterface|null  $app  The Joomla! application object.
	 *
	 * @return  ProviderManager
	 * @throws Exception
	 * @since   3.0.0
	 */
	private function createProviderManager(?ApplicationInterface $app = null): ProviderManager
	{
		PluginHelper::importPlugin('filesystem');
		$app   ??= Factory::getApplication();
		$event = new MediaProviderEvent(
			'onSetupProviders',
			[
				'context'         => 'AdapterManager',
				'providerManager' => new ProviderManager(),
			]
		);

		$app->getDispatcher()->dispatch($event->getName(), $event);

		return $event->getProviderManager();
	}

	/**
	 * Converts an image to PNG format using either GD or Imagick.
	 *
	 * @param   string  $imageData    Binary image data.
	 * @param   string  $imageFormat  One of png, jpg, webp.
	 * @param   bool    $throw        Throw exceptions?
	 *
	 * @return string|null Binary PNG data. NULL if $throw is false, and the conversion fails.
	 * @since   3.0.0
	 */
	private function convertToImageFormat(string $imageData, string $imageFormat = 'jpg', bool $throw = true
	): ?string
	{
		// Check if we have any supported image libraries
		$hasGd      = $this->hasPhpGd();
		$hasImagick = $this->hasPhpImagick();

		if (!$hasGd && !$hasImagick)
		{
			if (!$throw)
			{
				return null;
			}

			throw new \RuntimeException(
				sprintf(
					'Your PHP %s does not have either the gd or the imagick extension installed.',
					PHP_VERSION_ID
				)
			);
		}

		$imageFormat = strtolower($imageFormat);
		$imageFormat = in_array($imageFormat, ['png', 'jpg', 'webp']) ? $imageFormat : 'jpg';

		// Try Imagick first
		if ($hasImagick)
		{
			try
			{
				$imagick = new \Imagick();
				$imagick->readImageBlob($imageData);

				if (!in_array($imagick->getImageFormat(), ['WEBP', 'PNG', 'JPEG', 'GIF', 'BMP']))
				{
					throw new \RuntimeException('Invalid image format');
				}

				$imagick->setImageFormat($imageFormat);

				switch ($imageFormat)
				{
					case 'webp':
						$imagick->setOption('webp:lossless', 'true');
						break;

					case 'png':
						$imagick->setCompressionQuality(100);
						$imagick->setOption('png:compression-level', 9);
						break;

					case 'jpg':
						$imagick->setImageCompressionQuality(80);
						break;
				}

				$outputImageData = $imagick->getImageBlob();
				$imagick->clear();

				return $outputImageData;
			}
			catch (\Throwable)
			{
				// Fall through to try GD
			}
		}

		// Try GD
		if ($hasGd)
		{
			$image = @imagecreatefromstring($imageData);

			if ($image === false)
			{
				throw new \RuntimeException('Invalid image data');
			}

			imagepalettetotruecolor($image);

			ob_start();

			switch ($imageFormat)
			{
				case 'png':
				default:
					imagealphablending($image, true);
					imagesavealpha($image, true);
					imagepng($image, null, 9);
					break;

				case 'jpg':
					imagejpeg($image, null, 80);
					break;

				case 'webp':
					$quality = defined('IMG_WEBP_LOSSLESS') ? IMG_WEBP_LOSSLESS : 100;

					imagealphablending($image, true);
					imagesavealpha($image, true);
					imagewebp($image, null, $quality);
					break;
			}

			$outputImageData = ob_get_clean();

			imagedestroy($image);

			return $outputImageData;
		}

		if (!$throw)
		{
			return null;
		}

		throw new \RuntimeException('Failed to convert image data');
	}

	/**
	 * Checks if the PHP GD library is available.
	 *
	 * @return  bool  Returns true if the PHP GD library is installed, and the required functions exist.
	 * @since   3.0.0
	 */
	private function hasPhpGd(): bool
	{
		return function_exists('imagecreatefromstring')
			&& function_exists('imagepng')
			&& function_exists('imagedestroy');
	}

	/**
	 * Checks if the PHP Imagick extension is available.
	 *
	 * @return  bool  Returns true if the PHP Imagick extension is installed, and the Imagick class exists.
	 * @since   3.0.0
	 */
	private function hasPhpImagick(): bool
	{
		return class_exists('Imagick');
	}
}