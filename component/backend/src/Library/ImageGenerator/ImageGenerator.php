<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator;

defined('_JEXEC') || die();

use Akeeba\Component\SocialMagick\Administrator\Library\FileDistributor\FileDistributor;
use Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator\Adapter\AdapterInterface;
use Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator\Adapter\GDAdapter;
use Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator\Adapter\ImagickAdapter;
use DateInterval;
use Exception;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;
use Throwable;

/**
 * Automatic OpenGraph image generator.
 *
 * @since       1.0.0
 */
final class ImageGenerator implements DatabaseAwareInterface
{
	use DatabaseAwareTrait;

	/**
	 * The CMS application we are running under
	 *
	 * @var   CMSApplication
	 * @since 2.0.0
	 */
	private CMSApplication $app;

	/**
	 * Is this plugin in Development Mode? In this case the images are forcibly generated.
	 *
	 * @since 1.0.0
	 */
	private bool $devMode = false;

	/**
	 * OpenGraph image templates, loaded from the database
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private array $templates = [];

	/**
	 * Path relative to the site's root where the generated images will be saved
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	private string $outputFolder = '';

	/**
	 * The image renderer we'll be using
	 *
	 * @var   AdapterInterface
	 * @since 1.0.0
	 */
	private AdapterInterface $renderer;

	/**
	 * Number of subfolder levels for generated images
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	private int $folderLevels = 0;

	/**
	 * Old image threshold, in days
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	private int $oldImageThreshold = 0;

	/**
	 * Should I delete old images if the image I am asked to generate already exists?
	 *
	 * @since 1.0.0
	 */
	private bool $autoDeleteOldImages = false;

	/**
	 * ImageGenerator constructor.
	 *
	 * @param   Registry  $cParams  The component parameters. Used to set up internal properties.
	 *
	 * @since   1.0.0
	 */
	public function __construct(Registry $cParams, DatabaseInterface $db)
	{
		$this->setDatabase($db);
		$this->devMode             = $cParams->get('devmode', 0) == 1;
		$this->outputFolder        = $cParams->get('output_folder', 'images/og-generated') ?: 'images/og-generated';
		$this->folderLevels        = $cParams->get('folder_levels', 0);
		$this->oldImageThreshold   = $cParams->get('old_images_after', 180);
		$this->autoDeleteOldImages = $cParams->get('pseudo_cron', '1') == 1;

		$rendererType = $cParams->get('library', 'auto');
		$textDebug    = $cParams->get('textdebug', '0') == 1;
		$quality      = 100 - $cParams->get('quality', '95');

		$this->loadImageTemplates();

		switch ($rendererType)
		{
			case 'imagick':
				$this->renderer = new ImagickAdapter($quality, $textDebug);
				break;

			case 'gd':
				$this->renderer = new GDAdapter($quality, $textDebug);
				break;

			case 'auto':
			default:
				$this->renderer = new ImagickAdapter($quality, $textDebug);

				if (!$this->renderer->isSupported())
				{
					$this->renderer = new GDAdapter($quality, $textDebug);
				}

				break;
		}
	}

	/**
	 * Generates an OpenGraph image given set parameters, and sets appropriate meta tags.
	 *
	 * @param   string       $text        Test to overlay on image.
	 * @param   int          $templateId  Preset template ID.
	 * @param   string|null  $extraImage  Additional image to layer below template.
	 * @param   bool         $force       Should I override an already set OpenGraph image?
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function applyOGImage(string $text, int $templateId, ?string $extraImage = null, bool $force = false): void
	{
		// Don't try if the server requirements are not met
		if (!$this->isAvailable())
		{
			return;
		}

		// Make sure we have a front-end, HTML document — otherwise OG images are pointless
		try
		{
			$app      = $this->app;
			$document = $app->getDocument();
		}
		catch (Throwable $e)
		{
			return;
		}

		if (!($document instanceof HtmlDocument))
		{
			return;
		}

		// Only run if there's not already an OpenGraph image set or if we're told to forcibly apply one
		$ogImage = $document->getMetaData('og:image');

		if (!empty($ogImage) && !$force)
		{
			return;
		}

		// Try to generate (or get an already generated) image
		try
		{
			$templateOptions = $this->getTemplateOptions($templateId);

			$imageData      = $this->getOGImage($text, $templateOptions, $extraImage);
			$imageURL       = $imageData['imageURL'];
			$templateHeight = $imageData['height'];
			$templateWidth  = $imageData['width'];
		}
		catch (Exception $e)
		{
			return;
		}

		if (empty($imageURL))
		{
			// There was an error generating the image. We can't set up the OpenGraph properties.
			return;
		}

		// Set the page metadata
		$document->setMetaData('og:image', $imageURL, 'property');
		$document->setMetaData('og:image:alt', stripcslashes($text), 'property');
		$document->setMetaData('og:image:height', $templateHeight, 'property');
		$document->setMetaData('og:image:width', $templateWidth, 'property');
	}

	/**
	 * Returns the array of parsed templates.
	 *
	 * This is used by the plugin to create an event that returns the templates, used by teh custom XML form field which
	 * allows the user to select a template in menu items.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function getTemplates(): array
	{
		return $this->templates;
	}

	/**
	 * Are all the requirements met to automatically generate OpenGraph images?
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	public function isAvailable(): bool
	{
		return $this->renderer->isSupported();
	}

	/**
	 * Returns the normalised template options for a given template ID.
	 *
	 * @param   int  $templateId  The template ID.
	 *
	 * @return  array
	 * @since   3.0.0
	 */
	public function getTemplateOptions(int $templateId): array
	{
		return array_merge([
			'base-image'        => '',
			'template-w'        => 1200,
			'template-h'        => 630,
			'base-color'        => '#000000',
			'base-color-alpha'  => 1,
			'overlay_text'      => 1,
			'text-font'         => '',
			'font-size'         => 24,
			'text-color'        => '#ffffff',
			'text-width'        => 1200,
			'text-height'       => 630,
			'text-align'        => 'left',
			'text-y-center'     => 1,
			'text-y-adjust'     => 0,
			'text-y-absolute'   => 0,
			'text-x-center'     => 1,
			'text-x-adjust'     => 0,
			'text-x-absolute'   => 0,
			'use-article-image' => 0,
			'image-z'           => 'under',
			'image-cover'       => 1,
			'image-width'       => 1200,
			'image-height'      => 630,
			'image-x'           => 0,
			'image-y'           => 0,
		], $this->templates[$templateId] ?? []);
	}

	/**
	 * Returns the generated OpenGraph image and its information.
	 *
	 * Note: We accept template options instead of a template ID to make previewing an as-yet-unsaved template possible.
	 * The preview only needs to pass some sample text, the current form data cast as an array, and a sample image to
	 * generate an image which can be used to preview the OpenGraph results. Simple, eh?
	 *
	 * @param   string       $text             The text to render
	 * @param   array        $templateOptions  The options of the OpenGraph image template
	 * @param   string|null  $extraImage       The location of the extra image to render
	 *
	 * @return  array{imageURL: string, width: int, height: int}
	 *
	 * @since  1.0.0
	 */
	public function getOGImage(string $text, array $templateOptions, ?string $extraImage): array
	{
		$templateWidth  = $templateOptions['template-w'] ?? 1200;
		$templateHeight = $templateOptions['template-h'] ?? 630;

		// Get the generated image filename and URL
		$outputFolder     = trim($this->outputFolder, '/\\');
		$outputFolder     = str_replace('\\', '/', $outputFolder);
		$filename         = Path::clean(sprintf("%s/%s/%s.png",
			JPATH_ROOT,
			$outputFolder,
			md5($text . serialize($templateOptions) . ($extraImage ?? '') . $this->renderer->getOptionsKey())
		));
		$filename         = FileDistributor::ensureDistributed(dirname($filename), basename($filename), $this->folderLevels);
		$realRelativePath = ltrim(substr($filename, strlen(JPATH_ROOT)), '/');
		$imageUrl         = Uri::base() . $realRelativePath;

		// Update the image's last access date
		$this->hitImage(basename($filename, '.png'));

		// If the file exists return early
		if (@file_exists($filename) && !$this->devMode)
		{
			$mediaVersion = ApplicationHelper::getHash(@filemtime($filename));

			return [
				'imageURL' => $imageUrl . '?' . $mediaVersion,
				'width'    => $templateWidth,
				'height'   => $templateHeight,
			];
		}

		// Create the folder if it doesn't already exist
		$imageOutputFolder = dirname($filename);

		if (!@is_dir($imageOutputFolder) && !@mkdir($imageOutputFolder, 0777, true))
		{
			Folder::create($imageOutputFolder);
		}

		try
		{
			$this->renderer->makeImage($text, $templateOptions, $filename, $extraImage);
		}
		catch (Exception $e)
		{
			return [
				'imageURL' => null,
				'width'    => 0,
				'height'   => 0,
			];
		}

		$mediaVersion = ApplicationHelper::getHash(@filemtime($filename));

		return [
			'imageURL' => $imageUrl . '?' . $mediaVersion,
			'width'    => $templateWidth,
			'height'   => $templateHeight,
		];
	}

	/**
	 * Update the last access date/time stamp for an image.
	 *
	 * This only works when we are asked to apply the OpenGraph image. If you have Joomla caching turned on, this will
	 * only be called once every caching period.
	 *
	 * @param   string  $hash
	 *
	 * @since   1.0.0
	 */
	public function hitImage(string $hash): void
	{
		try
		{
			$db    = $this->getDatabase();
			$jNow  = new Date();
			$query = $db->getQuery(true)
				->insert($db->qn('#__socialmagick_images'))
				->columns([$db->qn('hash'), $db->qn('last_access')])
				->values(implode(',', [
					$db->q($hash), $db->q($jNow->toSql()),
				]));
		}
		catch (Exception $e)
		{
			// Something broke in Joomla. Nevermind.
			return;
		}

		try
		{
			$db->setQuery($query)->execute();

			return;
		}
		catch (Exception $e)
		{
			// We probably need to just run an update. Let's try that.
		}

		$query = $db->getQuery(true)
			->update($db->qn('#__socialmagick_images'))
			->set($db->qn('last_access') . ' = ' . $db->q($jNow->toSql()))
			->where($db->qn('hash') . ' = ' . $db->q($hash));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// DB error? No problem. Just go ahead.
		}
	}

	/**
	 * Deletes generated OpenGraph images older than this many days
	 *
	 * @param   int  $days     Minimum time since the last access time to warrant image deletion.
	 * @param   int  $maxTime  Maximum execution time, in seconds
	 *
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function deleteOldImages(int $days, int $maxTime = 5): void
	{
		if ($days === 0)
		{
			return;
		}

		$deleted      = [];
		$start        = microtime(true);
		$outputFolder = str_replace('\\', '/', trim($this->outputFolder, '/\\'));
		$maxTime      = min($maxTime, 1);

		while (true)
		{
			// Get a batch of old images
			$oldImages = $this->getOldImages($days, 50);

			// No images? We are done.
			if (empty($oldImages))
			{
				break;
			}

			// Process each old image record
			foreach ($oldImages as $hash)
			{
				// If we have already deleted 50 images return. Prevents the delete SQL query from becoming unwieldy.
				if (count($deleted) >= 50)
				{
					break 2;
				}

				// We ran out of time. Return now.
				if (microtime(true) - $start >= $maxTime)
				{
					break 2;
				}

				// Get the correct path for the image file to delete
				$filename = FileDistributor::ensureDistributed($outputFolder, $hash . '.png', $this->folderLevels);

				// No such file. Mark it as already deleted and move on.
				if (!@file_exists($filename))
				{
					$deleted[] = $hash;

					continue;
				}

				// Try (very hard) to delete the old iamge file
				$unlinked = true;

				if (!@unlink($filename))
				{
					$unlinked = @unlink($filename);
				}

				// Add positively deleted images to the list of image records to delete.
				if (!$unlinked)
				{
					continue;
				}

				$deleted[] = $hash;
			}
		}

		// Delete records or removed images, if necessary
		if (empty($deleted))
		{
			return;
		}

		try
		{
			$db    = $this->getDatabase();
			$query = $db->getQuery(true)
				->delete('#__socialmagick_images')
				->where($db->qn('hash') . ' IN(' . implode(',', array_map([$db, 'q'], $deleted)) . ')');
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// Shouldn't happen but I know better than to be an optimist when it comes to building software.
		}
	}

	/**
	 * Set the CMS application object
	 *
	 * @param   CMSApplication  $app
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function setApplication(CMSApplication $app): void
	{
		$this->app = $app;
	}

	/**
	 * Get image hashes older than this many days
	 *
	 * @param   int  $days       Number of days since last access
	 * @param   int  $maxImages  Maximum number of images to return in a single operation
	 *
	 * @return  array  The image hashes fulfilling the criteria
	 *
	 * @throws Exception
	 * @since  1.0.0
	 */
	private function getOldImages(int $days = 180, int $maxImages = 50): array
	{
		$db    = $this->getDatabase();
		$jNow  = new Date();
		$jThen = $jNow->sub(new DateInterval(sprintf('P%dD', $days)));
		$query = $db->getQuery(true)
			->select($db->qn('hash'))
			->from('#__socialmagick_images')
			->where($db->qn('last_access') . ' <= ' . $db->q($jThen->toSql()))
			->setLimit(min($maxImages, 1));

		return $db->setQuery($query)->loadColumn() ?? [];
	}

	/**
	 * Load the image templates from the database
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	private function loadImageTemplates(): void
	{
		$this->templates = [];

		/** @var DatabaseDriver $db */
		$db    = $this->getDatabase();
		$query = $db->createQuery()
			->select([
				$db->quoteName('id'),
				$db->quoteName('params'),
			])
			->from($db->quoteName('#__socialmagick_templates'))
			->where($db->quoteName('enabled') . ' = 1');

		try
		{
			$this->templates = array_map(
				fn($params) => json_decode($params, true),
				$db->setQuery($query)->loadAssocList('id', 'params')
			);
		}
		catch (Throwable)
		{
			// Swallow the exception; we just have no templates.
		}
	}
}