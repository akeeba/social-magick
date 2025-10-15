<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\SocialMagick\Categories\Extension;

defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator\ImageGenerator;
use Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever\CategoryRetrievalTrait;
use Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever\ExtraImageFetchTrait;
use Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever\InheritanceAwareMergeTrait;
use Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever\ParamsFromRegistryTrait;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\AbstractPlugin;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemDescriptionEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemImageEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemParametersEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemTitleEvent;
use Joomla\CMS\Menu\MenuItem;
use Joomla\Input\Input;
use function defined;

final class Categories extends AbstractPlugin
{
	use ParamsFromRegistryTrait;
	use InheritanceAwareMergeTrait;
	use CategoryRetrievalTrait;
	use ExtraImageFetchTrait;

	public function __construct($config = [])
	{
		$this->supportedComponent = 'com_categories';
		$this->itemDataKey        = 'params';

		parent::__construct($config);
	}

	/** @inheritDoc */
	public function onSocialMagickItemImage(ItemImageEvent $event): void
	{
		$menuItem   = $event->getMenuItem();
		$input      = $event->getInput();
		$params     = $event->getParams();
		$categoryId = $this->getCategoryIdFromMenuItem($menuItem, $input);

		if (empty($categoryId))
		{
			return;
		}

		$contentObject = $this->getCategoryById($categoryId);

		if (empty($contentObject))
		{
			return;
		}

		$template = $params->get('template');

		if (empty($template))
		{
			return;
		}

		$templateOptions = (new ImageGenerator($this->getComponentParams(), $this->getDatabase()))
			->getTemplateOptions($template);
		$imageSource     = $templateOptions['image_source'] ?? 'customfullintro';
		$customFieldName = $templateOptions['image_field'] ?? 'ogimage';

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
		$menuItem   = $event->getMenuItem();
		$input      = $event->getInput();
		$params     = $event->getParams();
		$categoryId = $this->getCategoryIdFromMenuItem($menuItem, $input);

		if (empty($categoryId))
		{
			return;
		}

		$contentObject = $this->getCategoryById($categoryId);

		if (empty($contentObject))
		{
			return;
		}

		$template = $params->get('template');

		if (empty($template))
		{
			return;
		}

		$templateOptions = (new ImageGenerator($this->getComponentParams(), $this->getDatabase()))
			->getTemplateOptions($template);
		$useArticle      = ($templateOptions['use_article'] ?? 1) == 1;

		if (!$useArticle)
		{
			return;
		}

		$event->addResult($contentObject->title);
	}

	/** @inheritDoc */
	public function onSocialMagickItemDescription(ItemDescriptionEvent $event): void
	{
		$menuItem   = $event->getMenuItem();
		$input      = $event->getInput();
		$params     = $event->getParams();
		$categoryId = $this->getCategoryIdFromMenuItem($menuItem, $input);

		if (empty($categoryId))
		{
			return;
		}

		$contentObject = $this->getCategoryById($categoryId);

		if (empty($contentObject))
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
		$menuItem   = $event->getMenuItem();
		$input      = $event->getInput();
		$categoryId = $this->getCategoryIdFromMenuItem($menuItem, $input);

		if (empty($categoryId))
		{
			return;
		}

		$event->addResult($this->getCategoryParameters($categoryId));
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

			case 'customcategory':
				return $this->getExtraImage('custom', $contentObject)
					?? $this->getExtraImage('category', $contentObject);

			case 'category':
				return $this->getImageFromItem($contentObject, 'params', 'image');

			case 'custom':
				return $this->getImageFromCustomField($contentObject, $customFieldName);
		}
	}


	/**
	 * Get the numeric category ID from the Joomla menu item, and the current request.
	 *
	 * @param   MenuItem|null  $activeMenuItem  The Joomla! menu item to handle.
	 * @param   Input|null     $input           The Joomla! input object
	 *
	 * @return  int|null
	 * @since   3.0.0
	 */
	private function getCategoryIdFromMenuItem(?MenuItem $activeMenuItem = null, ?Input $input = null): ?int
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
			'categories', 'category' => $input?->getInt('id', $activeMenuItem->query['id'] ?? null),
			default => null,
		};
	}
}