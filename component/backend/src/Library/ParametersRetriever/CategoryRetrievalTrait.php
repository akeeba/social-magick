<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever;

\defined('_JEXEC') || die;

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\Component\Categories\Administrator\Model\CategoryModel;
use Joomla\Registry\Registry;

trait CategoryRetrievalTrait
{
	/**
	 * Category objects per category ID
	 *
	 * @var   array<object>
	 * @since 3.0.0
	 */
	private array $categoriesById = [];

	/**
	 * A cached copy of com_content's CategoryModel
	 *
	 * @var   CategoryModel
	 * @since 3.0.0
	 */
	private CategoryModel $categoryModel;

	/**
	 * Get the category object given a category ID.
	 *
	 * @param   int|null  $id  The category ID.
	 *
	 * @return  object|null
	 *
	 * @since   3.0.0
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
		if (isset($this->application))
		{
			$app = $this->application;
		}
		else
		{
			$app = $this->getApplication();
		}

		$factory = $app->bootComponent('com_categories')->getMVCFactory();

		return $this->categoryModel ??= $factory
			->createModel('Category', 'Administrator', ['ignore_request' => true]);
	}

	/**
	 * Get the SocialMagick parameters for the category itself.
	 *
	 * If the category does not define an override, we walk through all of its parent categories until we find an
	 * override or reach a top level category.
	 *
	 * @param   int   $id        The category ID.
	 *
	 * @return  array
	 *
	 * @since   3.0.0
	 */
	private function getCategoryParameters(int $id, string $prefix = 'category_'): array
	{
		// Return cached results quickly
		if (isset($this->categoryParameters[$prefix][$id]))
		{
			return $this->categoryParameters[$prefix][$id];
		}

		$category = $this->getCategoryById($id);

		// Get parameters recursing all the way to the root category.
		$parentCategory = $this->getParentCategory($id);
		$parentParams   = empty($parentCategory) ? [] : $this->getCategoryParameters($parentCategory->id, $prefix);
		$catParams      = $this->getParamsFromRegistry(new Registry($category->params), 'socialmagick.' . $prefix);

		return $this->categoryParameters[$prefix][$id] = $this->inheritanceAwareMerge($parentParams, $catParams);
	}
}