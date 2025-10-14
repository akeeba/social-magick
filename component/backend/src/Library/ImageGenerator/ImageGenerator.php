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
	private int $folderLevels = 1;

	/**
	 * The image format to use for generated images.
	 *
	 * @var   string
	 * @since 3.0.0
	 */
	private string $imageType;

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
		$this->devMode      = $cParams->get('devmode', 0) == 1;
		$this->outputFolder = 'media/com_socialmagick/generated';
		$this->imageType    = $cParams->get('imagetype', 'jpg') ?: 'jpg';

		// Make sure the output folder exists
		if (!@is_dir($this->outputFolder))
		{
			Folder::create($this->outputFolder);
		}

		$rendererType = $cParams->get('library', 'auto');
		$textDebug    = $cParams->get('textdebug', '0') == 1;
		$quality  = $cParams->get('quality', '95');

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
	 * @param   string       $text        Text to overlay on the image.
	 * @param   int          $templateId  Preset template ID.
	 * @param   string|null  $extraImage  An additional image to layer below template.
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
			$imageData      = $this->createOGImage($text, $templateId, $extraImage);
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
	 * Try to generate, or get an already generated, OpenGraph image.
	 *
	 * @param   string       $text        Text to overlay on the image.
	 * @param   int          $templateId  Preset template ID.
	 * @param   string|null  $extraImage  An additional image to layer below template.
	 *
	 * @return  array{imageURL: string|null, height: int|null, width: int|null}|null[]
	 * @since   3.0.0
	 */
	public function createOGImage(string $text, int $templateId, ?string $extraImage = null): array
	{
		// Try to generate (or get an already generated) image
		try
		{
			return $this->getOGImage(
				$text,
				$this->getTemplateOptions($templateId),
				$extraImage
			);
		}
		catch (Exception)
		{
			return [
				'imageURL' => null,
				'height'   => null,
				'width'    => null,
			];
		}
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
		$filename         = Path::clean(sprintf("%s/%s/%s.%s",
			JPATH_ROOT,
			$outputFolder,
			md5($text . serialize($templateOptions) . ($extraImage ?? '') . $this->renderer->getOptionsKey()),
			$this->imageType
		));
		$filename         = FileDistributor::ensureDistributed(dirname($filename), basename($filename), $this->folderLevels);
		$realRelativePath = ltrim(substr($filename, strlen(JPATH_ROOT)), '/');
		$imageUrl         = Uri::root() . $realRelativePath;

		// If the file exists return early
		if (@file_exists($filename) && !$this->devMode)
		{
			/**
			 * Update the image file's modification and last access time.
			 *
			 * Why not just modify the just access time? On many production servers, especially those using solid state
			 * drives, the server administrators mount filesystems with the `noatime` or `relatime` flag. This does not
			 * update the last access time of the file either at all (`noatime`), or if the last access time is within
			 * the last 24 hours to reduce disk I/O.
			 *
			 * While `relatime` would work for our caching purposes, the fact that there are still servers out there
			 * using the legacy `noatime` means that we cannot rely on last access time. Thus, we have to update the
			 * last modification time even though it's technically wrong.
			 */
			touch($filename);

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