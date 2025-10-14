<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever;

defined('_JEXEC') || die();

use Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator\ImageGenerator;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemImageEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemParametersEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemTitleEvent;
use Exception;
use Joomla\Application\ApplicationInterface;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\Content\Administrator\Model\ArticleModel;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Input\Input;

/**
 * Utility class to retrieve configuration parameters taking into account merge rules.
 *
 * @since  1.0.0
 */
final class ParametersRetriever implements DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use ParamsFromRegistryTrait;
	use InheritanceAwareMergeTrait;
	use CategoryRetrievalTrait;

	/**
	 * Cached parameters per menu item
	 *
	 * @var   array[]
	 * @since 1.0.0
	 */
	private array $menuParameters = [];

	/**
	 * Cached parameters per article ID
	 *
	 * @since 1.0.0
	 */
	private array $articleParameters = [];

	/**
	 * Cached parameters **FOR ARTICLES** per category ID
	 *
	 * @since 1.0.0
	 */
	private array $categoryArticleParameters = [];

	/**
	 * Cached parameters **FOR THE CATEGORY** per category ID
	 *
	 * @since 1.0.0
	 */
	private array $categoryParameters = [];

	/**
	 * Article objects per article ID
	 *
	 * @var   array<object>
	 * @since 1.0.0
	 */
	private array $articlesById = [];

	/**
	 * The CMS application we're running under
	 *
	 * @var   ApplicationInterface|CMSApplication
	 * @since 2.0.0
	 */
	private ApplicationInterface|CMSApplication $application;

	/**
	 * A cached copy of com_content's ArticleModel
	 *
	 * @var   ArticleModel
	 * @since 3.0.0
	 */
	private ArticleModel $articleModel;

	/**
	 * Public constructor
	 *
	 * @param   ApplicationInterface|CMSApplication  $application  The application we're running under
	 * @param   DatabaseInterface                    $database
	 *
	 * @since   2.0.0
	 */
	public function __construct(ApplicationInterface|CMSApplication $application, DatabaseInterface $database)
	{
		$this->application = $application;

		$this->setDatabase($database);
	}

	/**
	 * Get the applicable OpenGraph parameters.
	 *
	 * There are two modes of operation.
	 *
	 * A. Request. Pass a menu item to `$activeMenuItem`. Both `$article` and `$category` are ignored.
	 * B. Specific Item. Pass null to `$activeMenuItem`, populate either or both `$article` and `$category`.
	 *
	 * The system plugin uses the first mode to generate OpenGraph images.
	 *
	 * @return  array
	 * @throws  \Exception
	 * @since   3.0.0
	 */
	public function getApplicableOGParameters(?MenuItem $activeMenuItem = null, ?Input $input = null): array
	{
		// Start with the hard-coded defaults, and their component Options overrides.
		$cParams = ComponentHelper::getParams('com_socialmagick');
		$params  = array_merge($this->defaultParameters, $cParams->toArray());

		// Apply item overrides
		PluginHelper::importPlugin('socialmagick');

		$event   = new ItemParametersEvent(arguments: [
			'params'   => $params,
			'menuitem' => $activeMenuItem,
			'input'    => $input,
		]);
		$results = $this->application->getDispatcher()->dispatch($event->getName(), $event) ?: [];

		foreach ($results as $result)
		{
			$params = $this->inheritanceAwareMerge($params, $result);
		}

		// Apply menu item overrides
		$params = $this->inheritanceAwareMerge($params, $this->getMenuParameters($activeMenuItem));

		/**
		 * Get the effective template ID.
		 *
		 * We are doing the following (first non-empty template ID found wins):
		 * * The `template` key from `$params`. Only set if there are (cascaded) overrides.
		 * * The default template ID set up in the extension.
		 * * The first published template's ID
		 * * Fallback to 0 which just uses some rather useless defaults.
		 */
		$imageGenerator            = new ImageGenerator($cParams, $this->getDatabase());
		$firstTemplateKey          = array_key_first($imageGenerator->getTemplates() ?? []);
		$configuredDefaultTemplate = $cParams->get('default_template', $firstTemplateKey) ?: null;
		$templateFromParams        = ($params['template'] ?? null) ?: null;
		$templateId                = $templateFromParams ?? $configuredDefaultTemplate ?? $firstTemplateKey ?? 0;

		$params['template'] = $templateId;

		return $params;
	}

	public function getOpenGraphImageGeneratorArguments(array $params, ?MenuItem $activeMenuItem = null, ?Input $input = null): array
	{
		global $socialMagickTemplate;

		// Get the applicable options
		$template   = $params['template'];
		$overrideOG = $params['override_og'] == 1;
		$templateId = $socialMagickTemplate ?? $template;

		// Get the text to render.
		$text = $this->getText($params, $activeMenuItem, $input);

		// Get the extra image location
		$cParams         = ComponentHelper::getParams('com_socialmagick');
		$imageGenerator  = new ImageGenerator($cParams, $this->getDatabase());
		$templateOptions = $imageGenerator->getTemplateOptions($templateId) ?: [];
		$enhancedParams  = $this->inheritanceAwareMerge($templateOptions, $params);
		$extraImage      = $this->getExtraImage($enhancedParams, $activeMenuItem, $input);

		// So, Joomla 4 adds some meta information to the image. Let's fix that.
		if (!empty($extraImage))
		{
			$extraImage = urldecode(HTMLHelper::cleanImageURL($extraImage)->url ?? '');
		}

		if (!empty($extraImage) && (!@file_exists($extraImage) || !@is_readable($extraImage)))
		{
			$extraImage = null;
		}

		return [
			'text'       => $text,
			'templateId' => $templateId,
			'extraImage' => $extraImage,
			'force'      => $overrideOG == 1,
		];
	}

	/**
	 * Get the SocialMagick parameters for a menu item.
	 *
	 * This is a hard-coded behaviour instead of using the onSocialMagickItemParameters event for a good reason. While
	 * the architecturally correct way would be using the onSocialMagickItemParameters event handler in
	 * plg_socialmagick_menus, the parameter cascading would rely on plugin order. If that plugin was published, say,
	 * before plg_socialmagick_articles we'd have the article parameters override the menu parameters which is wrong as
	 * per Joomla's default behavior since it was called Mambo! The only way to have the menu items cascade as the very
	 * last thing is to hard-code the behavior in this method.
	 *
	 * @param   MenuItem|null  $menuItem  The menu item object, if available.
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function getMenuParameters(?MenuItem $menuItem = null): array
	{
		// This only applies if plg_socialmagick_menus is enabled.
		if (!PluginHelper::isEnabled('socialmagick', 'menus'))
		{
			return [];
		}

		// The effective menu item ID.
		$id = $menuItem?->id ?? 0;

		// Return cached results quickly
		if (isset($this->menuParameters[$id]))
		{
			return $this->menuParameters[$id];
		}

		// If there is no menu item, or it's the wrong one, retrieve it from Joomla.
		if ((empty($menuItem) || ($menuItem->id != $id)) && $id > 0)
		{
			$menuItem = $this->application->getMenu()->getItem($id);
		}

		// Still no menu item? We return the default parameters.
		if (empty($menuItem) || ($menuItem->id != ($id)))
		{
			// This trick allows us to copy an array without creating a reference to the original.
			return $this->menuParameters[$id] = [];
		}

		return $this->menuParameters[$id] = $this->getParamsFromRegistry($menuItem->getParams());
	}

	/**
	 * Get the appropriate text for rendering on the auto-generated OpenGraph image.
	 *
	 * @return  string  The text to render in the auto-generated OpenGraph image.
	 *
	 * @since   3.0.0
	 */
	private function getText(array $params, ?MenuItem $activeMenuItem = null, ?Input $input = null): string
	{
		$customText = $params['custom_text'];
		$useArticle = $params['use_article'] == 1;
		$useTitle   = $params['use_title'] == 1;

		// 01. Try using a global variable used by template overrides
		global $socialMagickText;

		if (is_string($socialMagickText ?? null) && !empty(trim($socialMagickText)))
		{
			return trim($socialMagickText);
		}

		// 02. Explicitly entered custom text
		$customText = trim($customText ?? '');

		if (!empty($customText))
		{
			return $customText;
		}

		// 03. Item title (uses plugins)
		if ($useArticle)
		{
			PluginHelper::importPlugin('socialmagick');

			$event   = new ItemTitleEvent(arguments: [
				'params'   => $params,
				'menuitem' => $activeMenuItem,
				'input'    => $input,
			]);
			$results = $this->application->getDispatcher()->dispatch($event->getName(), $event)['result'] ?: [];
			$title   = array_reduce($results, fn($carry, $result) => $carry ?? $result, null);

			if (!empty($title))
			{
				return $title;
			}
		}

		// 04. Joomla! page title, if this feature is enabled.
		$isSite = $this->application->isClient('site');

		if ($isSite && $useTitle)
		{
			$menu        = $this->application->getMenu();
			$currentItem = $menu->getActive();

			return $currentItem->getParams()->get('page_title', $this->application->getDocument()->getTitle());
		}

		// I have found nothing. Return blank.
		return '';
	}

	/**
	 * Gets the additional image to apply to the article
	 *
	 * @return  string|null  The (hopefully relative) image path. NULL if no image is found or applicable.
	 *
	 * @since   3.0.0
	 */
	private function getExtraImage(array $params, ?MenuItem $activeMenuItem = null, ?Input $input = null): ?string
	{
		$imageSource = $params['image_source'] ?? 'none';
		$staticImage = $params['static_image'] ?? '';

		global $socialMagickImage;

		$customImage = trim($socialMagickImage ?? '');

		if (!empty($customImage))
		{
			return $customImage;
		}

		if (empty($imageSource) || $imageSource === 'none')
		{
			return null;
		}

		if ($imageSource === 'static')
		{
			return $staticImage;
		}

		// Get the content item image
		PluginHelper::importPlugin('socialmagick');

		$event   = new ItemImageEvent(arguments: [
			'params'   => $params,
			'menuitem' => $activeMenuItem,
			'input'    => $input,
		]);

		$results = $this->application->getDispatcher()->dispatch($event->getName(), $event)['result'] ?: [];
		$image   = array_reduce($results, fn($carry, $result) => $carry ?? $result, null);

		return $image;
	}
}