<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Library;

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Menu\MenuItem;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\Categories\Administrator\Model\CategoryModel;
use Joomla\Component\Content\Site\Model\ArticleModel;
use Joomla\Registry\Registry;

defined('_JEXEC') || die();

final class ParametersRetriever
{
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
	 * @var   array
	 * @since 1.0.0
	 */
	private $articlesById = [];

	/**
	 * Category objects per category ID
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $categoriesById = [];

	/**
	 * The CMS application we're running under
	 *
	 * @var   CMSApplication
	 * @since 2.0.0
	 */
	private CMSApplication $application;

	/**
	 * Joomla's com_content MVC Factory
	 *
	 * @var   MVCFactoryInterface
	 * @since 3.0.0
	 */
	private MVCFactoryInterface $mvcFactory;

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
	 * @param   CMSApplication  $application  The application we're running under
	 *
	 * @since   2.0.0
	 */
	public function __construct(CMSApplication $application)
	{
		$this->application = $application;
	}

	/**
	 * Merges the `$overrides` parameters into the `$source` parameters, aware of the inheritance rules.
	 *
	 * @param   array  $source
	 * @param   array  $overrides
	 *
	 * @return array
	 */
	public function inheritanceAwareMerge(array $source, array $overrides): array
	{
		$overrideImageParams = isset($overrides['override']) && $overrides['override'] == 1;
		$overrideOGParams    = isset($overrides['og_override']) && $overrides['og_override'] == 1;

		if (!$overrideImageParams && !$overrideOGParams)
		{
			return $source;
		}

		$temp = [];

		$temp['override'] = $overrideImageParams || ($source['override'] ?? null) == 1 ? 1 : 0;;
		$temp['og_override'] = $overrideOGParams || ($source['og_override'] ?? null) == 1 ? 1 : 0;;

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
			if (in_array($key, ['override', 'og_override'], true))
			{
				continue;
			}

			// Is it a valid override?
			$isOGKey = str_starts_with($key, 'og_');

			if (
				($isOGKey && !$overrideOGParams)
				|| (!$isOGKey && !$overrideImageParams)
			)
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
	public function getMenuParameters(int $id, ?MenuItem $menuItem = null): array
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
	public function getArticleParameters(int $id, $article = null): array
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
	public function getCategoryArticleParameters(int $id, $category = null): array
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
	public function getCategoryParameters(int $id, $category = null): array
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
	 * Retrieve the default parameters for the application or component.
	 *
	 * This method returns an array containing the default configuration parameters.
	 *
	 * @return  array The default parameters.
	 * @since   3.0.0
	 */
	public function getDefaultParameters(): array
	{
		return $this->defaultParameters;
	}

	/**
	 * Returns an article record given an article ID ID.
	 *
	 * @param   int  $id  The article ID
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getArticleById(int $id): ?object
	{
		if (isset($this->articlesById[$id]))
		{
			return $this->articlesById[$id];
		}

		try
		{
			$this->articlesById[$id] = $this->getArticleModel()->getItem($id) ?: null;
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
	 * @param   int  $id  The category ID
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	public function getCategoryById(int $id): ?object
	{
		if (isset($this->categoriesById[$id]))
		{
			return $this->categoriesById[$id];
		}

		try
		{
			$this->categoriesById[$id] = $this->getCategoryModel()->getItem($id) ?: null;
		}
		catch (Exception $e)
		{
			$this->categoriesById[$id] = null;
		}

		return $this->categoriesById[$id];
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
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return $this->articleModel ??= $this->getMVCFactory()
			->createModel('Article', 'Administrator', ['ignore_request' => true]);
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
		/** @noinspection PhpIncompatibleReturnTypeInspection */
		return $this->categoryModel ??= $this->getMVCFactory()
			->createModel('Category', 'Administrator', ['ignore_request' => true]);
	}

	/**
	 * Returns a cached copy of com_content's MVC factory.
	 *
	 * @return  MVCFactoryInterface
	 * @since   3.0.0
	 */
	private function getMVCFactory(): MVCFactoryInterface
	{
		return $this->mvcFactory ??= $this->application->bootComponent('com_content')->getMVCFactory();
	}

	/**
	 * Retrieve the parameters from a Registry object, respecting the default values set at the top of the class.
	 *
	 * @param   Registry  $params     The Joomla Registry object which contains our parameters namespaced.
	 * @param   string    $namespace  The Joomla Registry namespace for our parameters
	 *
	 * @return array
	 *
	 * @since 1.0.0
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
}