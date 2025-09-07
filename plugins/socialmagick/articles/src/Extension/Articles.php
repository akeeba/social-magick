<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\SocialMagick\Articles\Extension;

defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever\CategoryRetrievalTrait;
use Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever\ExtraImageFetchTrait;
use Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever\InheritanceAwareMergeTrait;
use Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever\ParamsFromRegistryTrait;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\AbstractPlugin;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemDescriptionEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemImageEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemParametersEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemTitleEvent;
use Exception;
use Joomla\CMS\Menu\MenuItem;
use Joomla\Component\Content\Administrator\Model\ArticleModel;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use function defined;

final class Articles extends AbstractPlugin
{
	use ParamsFromRegistryTrait;
	use InheritanceAwareMergeTrait;
	use CategoryRetrievalTrait;
	use ExtraImageFetchTrait;

	/**
	 * Cached parameters per article ID
	 *
	 * @since 3.0.0
	 */
	private array $articleParameters = [];

	/**
	 * Article objects per article ID
	 *
	 * @var   array<object>
	 * @since 3.0.0
	 */
	private array $articlesById = [];

	/**
	 * Cached parameters **FOR ARTICLES** per category ID
	 *
	 * @since 3.0.0
	 */
	private array $categoryArticleParameters = [];

	/**
	 * A cached copy of com_content's ArticleModel
	 *
	 * @var   ArticleModel
	 * @since 3.0.0
	 */
	private ArticleModel $articleModel;

	public function __construct($config = [])
	{
		$this->supportedComponent    = 'com_content';
		$this->itemInjectedForms     = [
			'com_content.article'                => 'socialmagick_article',
			'com_categories.categorycom_content' => 'socialmagick_category',
		];
		$this->itemDataKey           = 'attribs';
		$this->menuItemInjectedForms = [
			'categories' => 'socialmagick_menu_category',
			'category'   => 'socialmagick_menu_category',
			'*'          => 'socialmagick_menu_article',
		];

		parent::__construct($config);
	}

	/** @inheritDoc */
	public function onSocialMagickItemImage(ItemImageEvent $event): void
	{
		$menuItem  = $event->getMenuItem();
		$input     = $event->getInput();
		$params    = $event->getParams();
		$articleId = $this->getArticleIdFromMenuItem($menuItem, $input);

		if (empty($articleId))
		{
			return;
		}

		$contentObject = $this->getArticleById($articleId);

		if (empty($contentObject))
		{
			return;
		}

		$defaultImageSource     = $this->params->get('image_source', 'customfullintro');
		$defaultCustomFieldName = $this->params->get('image_field', 'ogimage');
		$imageSource            = $params->get('image_source', '') ?: $defaultImageSource;
		$customFieldName        = $params->get('image_field', '') ?: $defaultCustomFieldName;

		$extraImage = $this->getExtraImage($imageSource, $contentObject, $customFieldName);

		if (!$extraImage)
		{
			return;
		}

		$event->addResult($extraImage);
	}

	/** @inheritDoc */
	public function onSocialMagickItemTitle(ItemTitleEvent $event): void
	{
		$menuItem  = $event->getMenuItem();
		$input     = $event->getInput();
		$params    = $event->getParams();
		$articleId = $this->getArticleIdFromMenuItem($menuItem, $input);

		if (empty($articleId))
		{
			return;
		}

		$contentObject = $this->getArticleById($articleId);

		if (empty($contentObject))
		{
			return;
		}

		$defaultUseArticle = $this->params->get('use_article', 1);
		$useArticle        = $params->get('use_article', -1);
		$useArticle        = $useArticle < 0 ? $defaultUseArticle : $useArticle;

		if (!$useArticle)
		{
			return;
		}

		$event->addResult($contentObject->title);
	}

	/** @inheritDoc */
	public function onSocialMagickItemDescription(ItemDescriptionEvent $event): void
	{
		$menuItem  = $event->getMenuItem();
		$input     = $event->getInput();
		$params    = $event->getParams();
		$articleId = $this->getArticleIdFromMenuItem($menuItem, $input);

		if (empty($articleId))
		{
			return;
		}

		$contentObject = $this->getArticleById($articleId);

		if (empty($contentObject))
		{
			return;
		}

		$defaultUseArticleDesc = $this->params->get('use_article_description', 1);
		$useArticleDesc        = $params->get('use_article_description', -1);
		$useArticleDesc        = $useArticleDesc < 0 ? $defaultUseArticleDesc : $useArticleDesc;

		if (!$useArticleDesc)
		{
			return;
		}

		$metaDesc = trim($contentObject->metadesc ?: '');

		if (empty($metaDesc))
		{
			return;
		}

		$event->addResult($metaDesc);
	}

	/** @inheritDoc */
	public function onSocialMagickItemParameters(ItemParametersEvent $event): void
	{
		$menuItem  = $event->getMenuItem();
		$input     = $event->getInput();
		$articleId = $this->getArticleIdFromMenuItem($menuItem, $input);

		if (empty($articleId))
		{
			return;
		}

		$event->addResult($this->getArticleParameters($articleId));
	}

	/**
	 * Gets the additional image to apply to the article
	 *
	 * @param   string|null  $imageSource      The image source type
	 * @param   object|null  $contentObject    The content item where the image data and custom fields are.
	 * @param   string|null  $customFieldName  The name of the Joomla! Custom Field when `$imageSource` is `custom`.
	 *
	 * @return  string|null  The (hopefully relative) image path. NULL if no image is found or applicable.
	 *
	 * @since   3.0.0
	 */
	private function getExtraImage(?string $imageSource, ?object $contentObject = null, ?string $customFieldName = null): ?string
	{
		switch ($imageSource)
		{
			default:
				return null;

			case 'fullintro':
				return $this->getExtraImage('fulltext', $contentObject)
					?? $this->getExtraImage('intro', $contentObject);

			case 'introfull':
				return $this->getExtraImage('intro', $contentObject)
					?? $this->getExtraImage('fulltext', $contentObject);

			case 'customfullintro':
				return $this->getExtraImage('custom', $contentObject)
					?? $this->getExtraImage('fulltext', $contentObject)
					?? $this->getExtraImage('intro', $contentObject);

			case 'customintrofull':
				return $this->getExtraImage('custom', $contentObject)
					?? $this->getExtraImage('intro', $contentObject)
					?? $this->getExtraImage('fulltext', $contentObject);

			case 'intro':
			case 'fulltext':
				return $this->getImageFromItem($contentObject, 'images', 'image_' . $imageSource);

			case 'custom':
				return $this->getImageFromCustomField($contentObject, $customFieldName);
		}
	}

	/**
	 * Returns an article record given an article ID.
	 *
	 * @param   int|null  $id  The article ID.
	 *
	 * @return  object|null
	 *
	 * @since   3.0.0
	 */
	private function getArticleById(?int $id): ?object
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
	 * Get the numeric article ID from the Joomla menu item, and the current request.
	 *
	 * @param   MenuItem|null  $activeMenuItem  The Joomla! menu item to handle.
	 * @param   Input|null     $input           The Joomla! input object
	 *
	 * @return  int|null
	 * @since   3.0.0
	 */
	private function getArticleIdFromMenuItem(?MenuItem $activeMenuItem = null, Input $input = null): ?int
	{
		if (empty($activeMenuItem))
		{
			return null;
		}

		$menuOption    = $activeMenuItem?->query['option'] ?? '';
		$currentOption = $input?->getCmd('option', $menuOption) ?? $menuOption;

		if (!empty($menuOption) && ($menuOption !== $currentOption))
		{
			$menuOption = $currentOption;
		}

		// We can only handle com_content menu items here.
		if ($menuOption != 'com_content')
		{
			return null;
		}

		$task        = $input?->getCmd('task', $activeMenuItem->query['task'] ?? '')
			?? $activeMenuItem->query['task'];
		$defaultView = '';

		if (strpos($task, '.') !== false)
		{
			[$defaultView,] = explode('.', $task);
		}

		$view = $input?->getCmd('view', ($activeMenuItem->query['view'] ?? '') ?: $defaultView);

		return match ($view)
		{
			'archive', 'article', 'featured' => $input?->getInt('id', $activeMenuItem->query['id'] ?? null),
			default => null,
		};
	}

	/**
	 * Get the SocialMagick parameters for an article.
	 *
	 * @param   int  $id  The article ID.
	 *
	 * @return  array
	 *
	 * @since   3.0.0
	 */
	private function getArticleParameters(int $id): array
	{
		// Return cached results quickly.
		if (isset($this->articleParameters[$id]))
		{
			return $this->articleParameters[$id];
		}

		$article = $this->getArticleById($id);

		// Get the article parameters from the category, and from the article itself.
		$catParams     = $this->getCategoryParameters($article->catid, 'article_');
		$articleParams = $this->getParamsFromRegistry(new Registry($article->attribs));

		// Return article parameters by merging the parameters coming from categories and the article itself.
		return $this->articleParameters[$id] = $this->inheritanceAwareMerge($catParams, $articleParams);
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
		$factory = $this->getApplication()->bootComponent('com_content')->getMVCFactory();

		return $this->articleModel ??= $factory->createModel('Article', 'Administrator', ['ignore_request' => true]);
	}
}