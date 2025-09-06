<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever;

defined('_JEXEC') || die();

use Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator\ImageGenerator;
use Exception;
use Joomla\Application\ApplicationInterface;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Menu\MenuItem;
use Joomla\Component\Categories\Administrator\Model\CategoryModel;
use Joomla\Component\Content\Administrator\Model\ArticleModel;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Utility class to retrieve configuration parameters taking into account merge rules.
 *
 * @since  1.0.0
 */
final class ParametersRetriever implements DatabaseAwareInterface
{
	use DatabaseAwareTrait;

	/**
	 * Default Social Magick parameters for menu items, categories and articles
	 *
	 * @since 1.0.0
	 */
	private array $defaultParameters = [
		'override'              => '0',
		'generate_images'       => '-1',
		'template'              => '',
		'custom_text'           => '',
		'use_article'           => '1',
		'use_title'             => '1',
		'image_source'          => 'fulltext',
		'image_field'           => '',
		'override_og'           => '0',
		'og_title'              => '-1',
		'og_title_custom'       => '',
		'og_description'        => '-1',
		'og_description_custom' => '',
		'og_url'                => '-1',
		'og_site_name'          => '-1',
		'static_image'          => '',
		'twitter_card'          => '-1',
		'twitter_site'          => '',
		'twitter_creator'       => '',
		'fb_app_id'             => '',
	];

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
	 * Category objects per category ID
	 *
	 * @var   array<object>
	 * @since 1.0.0
	 */
	private array $categoriesById = [];

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
	 * A cached copy of com_content's CategoryModel
	 *
	 * @var   CategoryModel
	 * @since 3.0.0
	 */
	private CategoryModel $categoryModel;

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
	public function getApplicableOGParameters(?MenuItem $activeMenuItem = null, ?int $article = null, ?int $category = null): array
	{
		// Populate the article and content from the request.
		if ($activeMenuItem !== null && $article === null && $category === null)
		{
			[$article, $category] = $this->getArticleAndCategoryFromMenuItemOrRequest($activeMenuItem);
		}


		// Start with the hard-coded defaults and their component Options overrides.
		$cParams = ComponentHelper::getParams('com_socialmagick');
		$params  = array_merge($this->defaultParameters, $cParams->toArray());

		// Apply article and/or category overrides
		if ($article)
		{
			$extraParams = $this->getArticleParameters($article);
			$params      = $this->inheritanceAwareMerge($params, $extraParams);
		}
		elseif ($category)
		{
			$extraParams = $this->getCategoryParameters($category);
			$params      = $this->inheritanceAwareMerge($params, $extraParams);
		}

		// Apply menu item overrides
		$paramsFromMenu = $this->getMenuParameters($activeMenuItem->id, $activeMenuItem);
		$params         = $this->inheritanceAwareMerge($params, $paramsFromMenu);

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

		// Finally, add the article and category IDs to the parameters array
		$params['socialmagick_article_id']  = $article;
		$params['socialmagick_category_id'] = $category;

		return $params;
	}

	public function getOpenGraphImageGeneratorArguments(array $params): array
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
		$article     = $this->getArticleById($params['socialmagick_article_id'] ?? null);
		$category    = $this->getCategoryById($params['socialmagick_category_id'] ?? null);

		// Get the text to render.
		$text = $this->getText($customText, $useArticle, $useTitle, $article, $category);

		// Get the extra image location
		$extraImage = $this->getExtraImage($imageSource, $imageField, $staticImage, $article, $category);

		// So, Joomla 4 adds some meta information to the image. Let's fix that.
		if (!empty($extraImage))
		{
			$extraImage = urldecode(HTMLHelper::cleanImageURL($extraImage)->url ?? '');
		}

		if (!is_null($extraImage) && (!@file_exists($extraImage) || !@is_readable($extraImage)))
		{
			$extraImage = null;
		}

		global $socialMagickTemplate;

		return [
			'text'       => $text,
			'templateId' => $socialMagickTemplate ?? $template,
			'extraImage' => $extraImage,
			'force'      => $overrideOG == 1,
		];
	}

	/**
	 * Returns an article record given an article ID.
	 *
	 * @param   int|null  $id  The article ID.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getArticleById(?int $id): ?object
	{
		if (isset($this->articlesById[$id]))
		{
			return $this->articlesById[$id];
		}

		try
		{
			$this->articlesById[$id] = $id === null
				? null
				: ($this->getArticleModel()->getItem($id) ?: null);
		}
		catch (Exception)
		{
			$this->articlesById[$id] = null;
		}

		return $this->articlesById[$id];
	}

	/**
	 * Get the category object given a category ID.
	 *
	 * @param   int|null  $id  The category ID.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getCategoryById(?int $id): ?object
	{
		if (isset($this->categoriesById[$id]))
		{
			return $this->categoriesById[$id];
		}

		try
		{
			$this->categoriesById[$id] = $id === null
				? null
				: ($this->getCategoryModel()->getItem($id) ?: null);
		}
		catch (Exception $e)
		{
			$this->categoriesById[$id] = null;
		}

		return $this->categoriesById[$id];
	}

	private function getArticleAndCategoryFromMenuItemOrRequest(?MenuItem $activeMenuItem = null): array
	{
		/**
		 * Make sure we have the correct menu item.
		 *
		 * In Joomla 4 and later versions, when you access a `/component/something` URL you get the ItemID for the home
		 * page item as your active menu item. However, the `option` parameter in the application's global input object
		 * points to a different component. We detect this discrepancy and place the correct `option` to the $menuOption
		 * variable. Namely:
		 *
		 * - Accessing a regular menu item: you get the `option` from the menu item's `query` array.
		 * - Accessing an ad-hoc component menu item: you get the `option` from the application's global input object.
		 */
		$app           = $this->application;
		$menuOption    = $activeMenuItem->query['option'] ?? '';
		$currentOption = $app->getInput()->getCmd('option', $menuOption);

		if (!empty($menuOption) && ($menuOption !== $currentOption))
		{
			$menuOption = $currentOption;
		}

		// Reset the found article and category objects.
		$article  = null;
		$category = null;

		// We can only handle com_content menu items here.
		if ($menuOption != 'com_content')
		{
			return [null, null];
		}

		$task        = $app->getInput()->getCmd('task', $activeMenuItem->query['task'] ?? '');
		$defaultView = '';

		if (strpos($task, '.') !== false)
		{
			[$defaultView,] = explode('.', $task);
		}

		$view = $app->getInput()->getCmd('view', ($activeMenuItem->query['view'] ?? '') ?: $defaultView);

		switch ($view)
		{
			case 'categories':
			case 'category':
				$category = ($category ?: $app->getInput()->getInt('id', $activeMenuItem->query['id'] ?? null));
				break;

			case 'archive':
			case 'article':
			case 'featured':
				// Apply article overrides if applicable
				$article = ($article ?: $app->getInput()->getInt('id', $activeMenuItem->query['id'] ?? null));

				break;
		}

		return [$article, $category];
	}

	/**
	 * Merges the `$overrides` parameters into the `$source` parameters, aware of the inheritance rules.
	 *
	 * @param   array  $source     Source parameters.
	 * @param   array  $overrides  Array of possible overrides.
	 *
	 * @return  array
	 * @since   3.0.0
	 */
	private function inheritanceAwareMerge(array $source, array $overrides): array
	{
		$overrideImageParams = isset($overrides['override']) && $overrides['override'] == 1;

		if (empty($overrides))
		{
			return $source;
		}

		$temp = [];

		$temp['override'] = $overrideImageParams || ($source['override'] ?? null) == 1 ? 1 : 0;;

		foreach ($source as $key => $value)
		{
			// Start by using the source value
			$temp[$key] = $value;

			// If there is no override value under this key, well, we're done.
			if (!isset($overrides[$key]))
			{
				continue;
			}

			// Ignore override and og_override; I have already handled that.
			if (in_array($key, ['override'], true))
			{
				continue;
			}

			// Is it a valid override?
			$isOGKey = str_starts_with($key, 'og_') || str_starts_with($key, 'twitter_') || str_starts_with($key, 'fb_');

			if (!$isOGKey && !$overrideImageParams)
			{
				continue;
			}

			// Does this override anything, or does it basically say "nah, use the default"?
			$newValue = $overrides[$key];

			if ($newValue === '' || $newValue < 0)
			{
				continue;
			}

			// Okay, that's an override alright!
			$temp[$key] = $newValue;
		}

		return $temp;
	}

	/**
	 * Get the Social Magick parameters for a menu item.
	 *
	 * @param   int            $id        Menu item ID
	 * @param   MenuItem|null  $menuItem  The menu item object, if available.
	 *
	 * @return  array
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function getMenuParameters(int $id, ?MenuItem $menuItem = null): array
	{
		// Return cached results quickly
		if (isset($this->menuParameters[$id]))
		{
			return $this->menuParameters[$id];
		}

		// If there is no menu item, or it's the wrong one, retrieve it from Joomla.
		if (empty($menuItem) || ($menuItem->id != $id))
		{
			$menuItem = $this->application->getMenu()->getItem($id);
		}

		// Still no menu item? We return the default parameters.
		if (empty($menuItem) || ($menuItem->id != $id))
		{
			// This trick allows us to copy an array without creating a reference to the original.
			return $this->menuParameters[$id] = array_merge([], $this->defaultParameters);
		}

		return $this->menuParameters[$id] = $this->getParamsFromRegistry($menuItem->getParams());
	}

	/**
	 * Get the Social Magick parameters for an article.
	 *
	 * If the article doesn't define an override for the Social Magick parameters we check its category. If the category
	 * doesn't define an override we walk through all of its parent categories until we find an override or reach a top
	 * level category.
	 *
	 * @param   int   $id       The article ID.
	 * @param   null  $article  The article object, if you have it, thank you.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function getArticleParameters(int $id, $article = null): array
	{
		// Return cached results quickly.
		if (isset($this->articleParameters[$id]))
		{
			return $this->articleParameters[$id];
		}

		// If we were given an invalid article object, I need to find a new one.
		if (empty($article) || !is_object($article) || ($article->id != $id))
		{
			$article = $this->getArticleById($id);
		}

		// Get the article parameters from the category, and from the article itself.
		$catParams     = $this->getCategoryArticleParameters($article->catid);
		$articleParams = $this->getParamsFromRegistry(new Registry($article->attribs));

		// Return article parameters by merging the parameters coming from categories and the article itself.
		return $this->articleParameters[$id] = $this->inheritanceAwareMerge($catParams, $articleParams);
	}

	/**
	 * Get the Social Magick parameters for the articles contained in a category.
	 *
	 * If the category does not define an override we walk through all of its parent categories until we find an
	 * override or reach a top level category.
	 *
	 * @param   int   $id        The category ID.
	 * @param   null  $category  The category object, if you have it, thank you.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function getCategoryArticleParameters(int $id, $category = null): array
	{
		// Return cached results quickly
		if (isset($this->categoryArticleParameters[$id]))
		{
			return $this->categoryArticleParameters[$id];
		}

		// Get the category object
		if (empty($category) || !is_object($category) || ($category->id != $id))
		{
			$category = $this->getCategoryById($id);
		}

		// Get parameters recursing all the way to the root category.
		$parentCategory = $this->getParentCategory($id);
		$parentParams   = empty($parentCategory) ? [] : $this->getCategoryArticleParameters($parentCategory->id);
		$catParams      = $this->getParamsFromRegistry(new Registry($category->params), 'socialmagick.article_');

		return $this->categoryArticleParameters[$id] = $this->inheritanceAwareMerge($parentParams, $catParams);
	}

	/**
	 * Get the Social Magick parameters for the category itself.
	 *
	 * If the category does not define an override, we walk through all of its parent categories until we find an
	 * override or reach a top level category.
	 *
	 * @param   int   $id        The category ID.
	 * @param   null  $category  The category object, if you have it, thank you.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function getCategoryParameters(int $id, $category = null): array
	{
		// Return cached results quickly
		if (isset($this->categoryParameters[$id]))
		{
			return $this->categoryParameters[$id];
		}

		if (empty($category) || !is_object($category) || ($category->id != $id))
		{
			$category = $this->getCategoryById($id);
		}

		// Get parameters recursing all the way to the root category.
		$parentCategory = $this->getParentCategory($id);
		$parentParams   = empty($parentCategory) ? [] : $this->getCategoryParameters($parentCategory->id);
		$catParams      = $this->getParamsFromRegistry(new Registry($category->params), 'socialmagick.category_');

		return $this->categoryParameters[$id] = $this->inheritanceAwareMerge($parentParams, $catParams);
	}

	/**
	 * Retrieves the article model instance.
	 *
	 * This method ensures the article model is initialised and returned. If the model
	 * is not already set, it will be created using the MVC factory with a specific configuration.
	 *
	 * @return  ArticleModel  The article model instance.
	 * @throws  Exception
	 * @since   3.0.0
	 */
	private function getArticleModel(): ArticleModel
	{
		$factory = $this->application->bootComponent('com_content')->getMVCFactory();

		return $this->articleModel ??= $factory->createModel('Article', 'Administrator', ['ignore_request' => true]);
	}

	/**
	 * Retrieves the Category model instance, creating it if not already instantiated.
	 *
	 * This method uses the MVC factory to create a model for the Category within the Administrator context,
	 * with request parameters ignored during the creation process.
	 *
	 * @return  CategoryModel  The category model instance.
	 * @throws  Exception
	 * @since   3.0.0
	 */
	private function getCategoryModel(): CategoryModel
	{
		$factory = $this->application->bootComponent('com_categories')->getMVCFactory();

		return $this->categoryModel ??= $factory
			->createModel('Category', 'Administrator', ['ignore_request' => true]);
	}

	/**
	 * Retrieve the parameters from a Registry object, respecting the default values set at the top of the class.
	 *
	 * @param   Registry  $params     The Joomla Registry object which contains our parameters namespaced.
	 * @param   string    $namespace  The Joomla Registry namespace for our parameters
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function getParamsFromRegistry(Registry $params, string $namespace = 'socialmagick.'): array
	{
		$parsedParameters = [];

		foreach ($this->defaultParameters as $key => $defaultValue)
		{
			$parsedParameters[$key] = $params->get($namespace . $key, $defaultValue);
		}

		return $parsedParameters;
	}

	/**
	 * Get the parent category object given a child's category ID
	 *
	 * @param   int  $childId
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	private function getParentCategory(int $childId): ?object
	{
		/** @var CategoryModel $childCategory */
		$childCategory = $this->getCategoryById($childId);

		if (empty($childCategory))
		{
			return null;
		}

		$parentId = $childCategory->parent_id;

		if ($parentId <= 0)
		{
			return null;
		}

		return $this->getCategoryById($parentId);
	}

	/**
	 * Get the appropriate text for rendering on the auto-generated OpenGraph image
	 *
	 * @param   string|null  $customText  Any custom text the admin has entered for this menu item/
	 * @param   bool         $useArticle  Should I do a fallback to the core content article's title, if one exists?
	 * @param   bool         $useTitle    Should I do a fallback to the Joomla page title?
	 * @param   object|null  $article     The article object displayed on the page.
	 * @param   object|null  $category    The category object displayed on the page.
	 *
	 * @return  string  The text to render in the auto-generated OpenGraph image.
	 *
	 * @since   1.0.0
	 */
	private function getText(?string $customText = null, bool $useArticle = false, bool $useTitle = false, ?object $article = null, ?object $category = null): string
	{
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

		// 03. Core content article title, if one exists and this feature is enabled.
		if ($useArticle)
		{
			$title = trim($category?->title ?? $article?->title ?? '');

			if (!empty($title))
			{
				return $title;
			}
		}

		// 04. Joomla! page title, if this feature is enabled
		if ($useTitle)
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
	 * @param   string|null  $imageSource  The image source type: `none`, `intro`, `fulltext`, `custom`.
	 * @param   string|null  $imageField   The name of the Joomla! Custom Field when `$imageSource` is `custom`.
	 * @param   string|null  $staticImage  A static image definition
	 * @param   object|null  $article      The article object displayed on the page.
	 * @param   object|null  $category     The category object displayed on the page.
	 *
	 * @return  string|null  The (hopefully relative) image path. NULL if no image is found or applicable.
	 *
	 * @since   1.0.0
	 */
	private function getExtraImage(?string $imageSource, ?string $imageField, ?string $staticImage, ?object $article = null, ?object $category = null): ?string
	{
		global $socialMagickImage;

		$customImage = trim($socialMagickImage ?? '');

		if (!empty($customImage))
		{
			return $customImage;
		}

		if (empty($imageSource))
		{
			return null;
		}

		// Get the applicable content object
		$contentObject = $category ?? $article ?? null;

		// Decode custom fields
		$jcFields = $contentObject?->jcfields ?? [];

		if (is_string($jcFields))
		{
			$jcFields = @json_decode($jcFields, true);
		}

		$jcFields = is_array($jcFields) ? $jcFields : [];

		// Decode images
		$articleImages = $contentObject?->images ?? ($contentObject?->params ?? []);
		$articleImages = is_string($articleImages) ? @json_decode($articleImages, true) : $articleImages;
		$articleImages = is_array($articleImages) ? $articleImages : [];

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
}