<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension\Traits;

\defined('_JEXEC') || die;

use Exception;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Throwable;

trait OpenGraphImageTrait
{
	/**
	 * Get the appropriate text for rendering on the auto-generated OpenGraph image
	 *
	 * @param   string|null  $customText  Any custom text the admin has entered for this menu item/
	 * @param   bool         $useArticle  Should I do a fallback to the core content article's title, if one exists?
	 * @param   bool         $useTitle    Should I do a fallback to the Joomla page title?
	 *
	 * @return  string  The text to render oin the auto-generated OpenGraph image.
	 *
	 * @since   1.0.0
	 */
	private function getText(?string $customText = null, bool $useArticle = false, bool $useTitle = false): string
	{
		// 01. Try using a global variable used by template overrides
		global $socialMagickText;

		if (isset($socialMagickText) && is_string($socialMagickText) && !empty(trim($socialMagickText)))
		{
			return trim($socialMagickText);
		}

		// 02. Explicitly entered custom text
		$customText = trim($customText ?? '');

		if (!empty($customText))
		{
			return $customText;
		}

		// 03. Core content article title, if one exists and this feature is enabled.
		if ($useArticle)
		{
			$title = '';

			if ($this->article)
			{
				$article = $this->getParamsRetriever()->getArticleById($this->article);
				$title   = empty($article) ? '' : ($article->title ?? '');
			}
			elseif ($this->category)
			{
				$category = $this->getParamsRetriever()->getCategoryById($this->category);
				$title    = empty($category) ? '' : ($category->title ?? '');
			}

			$title = trim($title);

			if (!empty($title))
			{
				return $title;
			}
		}

		// 04. Joomla! page title, if this feature is enabled
		if ($useTitle)
		{
			$menu        = $this->getApplication()->getMenu();
			$currentItem = $menu->getActive();

			return $currentItem->getParams()->get('page_title', $this->getApplication()->getDocument()->getTitle());
		}

		// I have found nothing. Return blank.
		return '';
	}

	/**
	 * Gets the additional image to apply to the article
	 *
	 * @param   string|null  $imageSource  The image source type: `none`, `intro`, `fulltext`, `custom`.
	 * @param   string|null  $imageField   The name of the Joomla! Custom Field when `$imageSource` is `custom`.
	 * @param   string|null  $staticImage  A static image definition
	 *
	 * @return  string|null  The (hopefully relative) image path. NULL if no image is found or applicable.
	 *
	 * @since   1.0.0
	 */
	private function getExtraImage(?string $imageSource, ?string $imageField, ?string $staticImage): ?string
	{
		/** @noinspection PhpUndefinedFieldInspection */
		$customImage = trim(@$this->getApplication()->socialMagickImage ?? '');

		if (!empty($customImage))
		{
			return $customImage;
		}

		if (empty($imageSource))
		{
			return null;
		}

		$contentObject = null;
		$jcFields      = [];
		$articleImages = [];

		if ($this->article)
		{
			$contentObject = $this->getParamsRetriever()->getArticleById($this->article);
		}
		elseif ($this->category)
		{
			$contentObject = $this->getParamsRetriever()->getCategoryById($this->category);
		}

		if (!empty($contentObject))
		{
			// Decode custom fields
			$jcFields = $contentObject->jcfields ?? [];

			if (is_string($jcFields))
			{
				$jcFields = @json_decode($jcFields, true);
			}

			$jcFields = is_array($jcFields) ? $jcFields : [];

			// Decode images
			$articleImages = $contentObject->images ?? ($contentObject->params ?? []);
			$articleImages = is_string($articleImages) ? @json_decode($articleImages, true) : $articleImages;
			$articleImages = is_array($articleImages) ? $articleImages : [];
		}

		switch ($imageSource)
		{
			default:
			case 'none':
				return null;

			case 'static':
				return $staticImage;

			case 'fullintro':
				return $this->getExtraImage('fulltext', $imageField, $staticImage)
					?? $this->getExtraImage('intro', $imageField, $staticImage);

			case 'introfull':
				return $this->getExtraImage('intro', $imageField, $staticImage)
					?? $this->getExtraImage('fulltext', $imageField, $staticImage);

			case 'customfullintro':
				return $this->getExtraImage('custom', $imageField, $staticImage)
					?? $this->getExtraImage('fulltext', $imageField, $staticImage)
					?? $this->getExtraImage('intro', $imageField, $staticImage);

			case 'customintrofull':
				return $this->getExtraImage('custom', $imageField, $staticImage)
					?? $this->getExtraImage('intro', $imageField, $staticImage)
					?? $this->getExtraImage('fulltext', $imageField, $staticImage);

			case 'intro':
			case 'fulltext':
			case 'category':
				return empty($articleImages)
					? null :
					(($articleImages['image_' . $imageSource] ?? $articleImages['image'] ?? null) ?: null);

			case 'custom':
				if (empty($jcFields) || empty($imageField))
				{
					return null;
				}

				foreach ($jcFields as $fieldInfo)
				{
					if ($fieldInfo->name != $imageField)
					{
						continue;
					}

					$rawvalue = $fieldInfo->rawvalue ?? '';
					$value    = @json_decode($rawvalue, true);


					if (empty($value) && is_string($rawvalue))
					{
						return $rawvalue;
					}

					if (empty($value) || !is_array($value))
					{
						return null;
					}

					return trim($value['imagefile'] ?? '') ?: null;
				}

				return null;
		}
	}

	/**
	 * Replace the debug image placeholder with a link to the OpenGraph image.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function replaceDebugImagePlaceholder(): void
	{
		// Make sure I am in the front-end, and I'm doing HTML output
		/** @var SiteApplication $app */
		$app = $this->getApplication();

		if (!is_object($app) || !($app instanceof SiteApplication))
		{
			return;
		}

		try
		{
			if ($this->getApplication()->getDocument()->getType() != 'html')
			{
				return;
			}
		}
		catch (Throwable $e)
		{
			return;
		}

		$imageLink = ($this->getApplication()->getDocument()->getMetaData('og:image') ?: $this->getApplication()->getDocument()->getMetaData('twitter:image')) ?: '';

		$this->loadLanguage('com_socialmagick', JPATH_ADMINISTRATOR);

		$message = Text::_('COM_SOCIALMAGICK_LBL_DEBUGLINK_MESSAGE');

		if ($message == 'COM_SOCIALMAGICK_LBL_DEBUGLINK_MESSAGE')
		{
			/** @noinspection HtmlUnknownTarget */
			$message = "<a href=\"%s\" target=\"_blank\">Preview OpenGraph Image</a>";
		}

		$message = $imageLink ? sprintf($message, $imageLink) : '';

		$app->setBody(str_replace($this->getDebugLinkPlaceholder(), $message, $app->getBody()));
	}

	/**
	 * Generate (if necessary) and apply the OpenGraph image
	 *
	 * @param   array  $params  Applicable menu parameters, with any overrides already taken into account
	 *
	 * @return  void
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	private function applyOGImage(array $params): void
	{
		// Get the applicable options
		$template    = $params['template'];
		$customText  = $params['custom_text'];
		$useArticle  = $params['use_article'] == 1;
		$useTitle    = $params['use_title'] == 1;
		$imageSource = $params['image_source'];
		$imageField  = $params['image_field'];
		$staticImage = $params['static_image'] ?: '';
		$overrideOG  = $params['override_og'] == 1;

		// Get the text to render.
		$text = $this->getText($customText, $useArticle, $useTitle);

		$templates      = $this->getImageGenerator()->getTemplates();
		$templateParams = $templates[$template] ?? [];

		// If there is no text AND I am supposed to use overlay text I will not try to generate an image.
		if (empty($text) && ($templateParams['overlay_text'] ?? 1))
		{
			return;
		}

		// Get the extra image location
		$extraImage = $this->getExtraImage($imageSource, $imageField, $staticImage);

		// So, Joomla 4 adds some meta information to the image. Let's fix that.
		if (!empty($extraImage))
		{
			$extraImage = urldecode(HTMLHelper::cleanImageURL($extraImage)->url ?? '');
		}

		if (!is_null($extraImage) && (!@file_exists($extraImage) || !@is_readable($extraImage)))
		{
			$extraImage = null;
		}

		/** @noinspection PhpUndefinedFieldInspection */
		$template = trim(@$this->getApplication()->socialMagickTemplate ?? '') ?: $template;

		// Generate (if necessary) and apply the OpenGraph image
		$this->getImageGenerator()->applyOGImage($text, (int) $template, $extraImage, $overrideOG);
	}

}