<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension;

defined('_JEXEC') || die();

use Akeeba\Plugin\System\SocialMagick\Extension\Traits\ConditionalMetaTrait;
use Akeeba\Plugin\System\SocialMagick\Extension\Traits\DebugPlaceholderTrait;
use Akeeba\Plugin\System\SocialMagick\Extension\Traits\ImageGeneratorHelperTrait;
use Akeeba\Plugin\System\SocialMagick\Extension\Traits\OpenGraphImageTrait;
use Akeeba\Plugin\System\SocialMagick\Extension\Traits\ParametersRetrieverTrait;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Throwable;

/**
 * System plugin to automatically generate Open Graph images
 *
 * @since        1.0.0
 *
 * @noinspection PhpUnused
 */
class SocialMagick extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;
	use ConditionalMetaTrait;
	use DebugPlaceholderTrait;
	use OpenGraphImageTrait;
	use ImageGeneratorHelperTrait;
	use ParametersRetrieverTrait;
	use Feature\FormTabs;
	use Feature\Ajax;

	/**
	 * The com_content article ID being rendered, if applicable.
	 *
	 * @var   int|null
	 * @since 1.0.0
	 */
	protected ?int $article = null;

	/**
	 * The com_content category ID being rendered, if applicable.
	 *
	 * @var   int|null
	 * @since 1.0.0
	 */
	protected ?int $category = null;

	/**
	 * The placeholder variable to be replaced by the image link when Debug Link is enabled.
	 *
	 * @var  string
	 */
	protected string $debugLinkPlaceholder = '';

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterRender'              => 'onAfterRender',
			'onAjaxSocialmagick'         => 'onAjaxSocialmagick',
			'onBeforeRender'             => 'onBeforeRender',
			'onContentBeforeDisplay'     => 'onContentBeforeDisplay',
			'onContentBeforeSave'        => 'onContentBeforeSave',
			'onContentPrepareData'       => 'onContentPrepareData',
			'onContentPrepareForm'       => 'onContentPrepareForm',
			'onSocialMagickGetTemplates' => 'onSocialMagickGetTemplates',
		];
	}

	/**
	 * Returns all Social Magick templates known to the plugin
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @since        1.0.0
	 */
	public function onSocialMagickGetTemplates(Event $event): void
	{
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = $this->getHelper()->getTemplates();
		$event->setArgument('result', $result);
	}

	/**
	 * Runs before Joomla renders the HTML document.
	 *
	 * This is the main event where Social Magick evaluates whether to apply an Open Graph image to the document.
	 *
	 * @return  void
	 *
	 * @since        1.0.0
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUnused
	 */
	public function onBeforeRender(Event $event): void
	{
		// Is this plugin even supported?
		if (!$this->getHelper()->isAvailable())
		{
			return;
		}

		// Is this the frontend HTML application?
		if (!is_object($this->getApplication()) || !($this->getApplication() instanceof CMSApplication))
		{
			return;
		}

		if (!method_exists($this->getApplication(), 'isClient') || !$this->getApplication()->isClient('site'))
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

		// Try to get the active menu item
		try
		{
			//$menu        = AbstractMenu::getInstance('site');
			$menu        = $this->getApplication()->getMenu();
			$currentItem = $menu->getActive();
		}
		catch (Throwable $e)
		{
			return;
		}

		// Make sure there *IS* an active menu item.
		if (empty($currentItem))
		{
			return;
		}

		// Get the menu item parameters
		$params = $this->getParamsRetriever()->getMenuParameters($currentItem->id, $currentItem);

		/**
		 * In Joomla 4 when you access a /component/whatever URL you have the ItemID for the home page as the active
		 * item BUT the option parameter in the application is different. Let's detect that and get out if that's the
		 * case.
		 */
		$menuOption    = $currentItem->query['option'] ?? '';
		$currentOption = $this->getApplication()->input->getCmd('option', $menuOption);

		if (!empty($menuOption) && ($menuOption !== $currentOption))
		{
			$menuOption = $currentOption;
		}

		// Apply core content settings overrides, if applicable
		if ($menuOption == 'com_content')
		{
			$task        = $this->getApplication()->input->getCmd('task', $currentItem->query['task'] ?? '');
			$defaultView = '';

			if (strpos($task, '.') !== false)
			{
				[$defaultView,] = explode('.', $task);
			}

			$view = $this->getApplication()->input->getCmd('view', ($currentItem->query['view'] ?? '') ?: $defaultView);

			switch ($view)
			{
				case 'categories':
				case 'category':
					// Apply category overrides if applicable
					$category = $this->category ?: $this->getApplication()->input->getInt('id', $currentItem->query['id'] ?? null);

					if ($category)
					{
						$catParams = $this->getParamsRetriever()->getCategoryParameters($category);

						if ($catParams['override'] == 1)
						{
							$params = $catParams;
						}
					}

					$this->article  = null;
					$this->category = $category;
					break;

				case 'archive':
				case 'article':
				case 'featured':
					// Apply article overrides if applicable
					$article = $this->article ?: $this->getApplication()->input->getInt('id', $currentItem->query['id'] ?? null);

					if ($article)
					{
						$articleParams = $this->getParamsRetriever()->getArticleParameters($article);

						if ($articleParams['override'] == 1)
						{
							$params = $articleParams;
						}
					}

					$this->article  = $article;
					$this->category = null;

					break;
			}
		}

		// Apply default site-wide settings if applicable
		$templateKeys    = array_keys($this->getHelper()->getTemplates() ?? []);
		$defaultTemplate = count($templateKeys) ? array_shift($templateKeys) : '';

		$defaultPluginSettings = [
			'template'              => $defaultTemplate,
			'generate_images'       => 1,
			'og_title'              => 1,
			'og_title_custom'       => '',
			'og_description'        => 1,
			'og_description_custom' => '',
			'og_url'                => 1,
			'og_site_name'          => 1,
			'twitter_card'          => 2,
			'twitter_site'          => '',
			'twitter_creator'       => '',
			'fb_app_id'             => '',
		];

		foreach ($defaultPluginSettings as $key => $defaultValue)
		{
			$inheritValue = is_numeric($defaultValue) ? -1 : '';
			$paramsValue  = trim($params[$key]);
			$paramsValue  = is_numeric($paramsValue) ? ((int) $paramsValue) : $paramsValue;

			if ($paramsValue === $inheritValue)
			{
				$params[$key] = $this->params->get($key, $defaultValue);
			}
		}

		// Generate an Open Graph image, if applicable.
		if ($params['generate_images'] == 1)
		{
			$this->applyOGImage($params);
		}

		// Apply additional Open Graph tags
		$this->applyOpenGraphTags($params);
	}

	/**
	 * Runs when Joomla is about to display an article. Used to save some useful article parameters.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onContentBeforeDisplay(Event $event): void
	{
		[$context, $row, $params] = array_values($event->getArguments());
		$result = $event->getArgument('result') ?: [];
		$result = is_array($result) ? $result : [$result];

		/**
		 * When Joomla is rendering an article in a Newsflash module it uses the same context as rendering an article
		 * through com_content (com_content.article). However, we do NOT want the newsflash articles to override the
		 * Social Magick settings!
		 *
		 * This is an ugly hack around this problem. It's based on the observation that the newsflash module is passing
		 * its own module options in the $params parameter to this event. As a result it has the `moduleclass_sfx` key
		 * defined, whereas this key does not exist when rendering an article through com_content.
		 */
		if (($params instanceof Registry) && $params->exists('moduleclass_sfx'))
		{
			return;
		}

		if (!in_array($context, ['com_content.article', 'com_content.category', 'com_content.categories']))
		{
			return;
		}

		switch ($context)
		{
			case 'com_content.article':
			case 'com_content.category':
				$this->article = $row->id;
				break;

			case 'com_content.categories':
				$this->category = $row->id;
		}

		// Save the article/category, images and fields for later use
		if ($context == 'com_content.categories')
		{
			$this->category = $row->id;
		}
		else
		{
			$this->article = $row->id;
		}

		// Add the debug link if necessary
		if ($this->params->get('debuglink', 0) == 1)
		{
			$result[] = $this->getDebugLinkPlaceholder();

			$event->setArgument('result', $result);
		}
	}

	/**
	 * Runs after rendering the document but before outputting it to the browser.
	 *
	 * Used to add the OpenGraph declaration to the document head and apply the debug image link.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @since        1.0.0
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onAfterRender(Event $event): void
	{
		if ($this->params->get('add_og_declaration', '1') == 1)
		{
			$this->addOgPrefixToHtmlDocument();
		}

		if ($this->params->get('debuglink', 0) == 1)
		{
			$this->replaceDebugImagePlaceholder();
		}

	}
}