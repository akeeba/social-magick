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
 * System plugin to automatically generate OpenGraph images
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
			'onAfterRender'          => 'onAfterRender',
			'onAjaxSocialmagick'     => 'onAjaxSocialmagick',
			'onBeforeRender'         => 'onBeforeRender',
			'onContentBeforeDisplay' => 'onContentBeforeDisplay',
			'onContentBeforeSave'    => 'onContentBeforeSave',
			'onContentPrepareData'   => 'onContentPrepareData',
			'onContentPrepareForm'   => 'onContentPrepareForm',
		];
	}

	/**
	 * Runs before Joomla renders the HTML document.
	 *
	 * This is the main event where Social Magick evaluates whether to apply an OpenGraph image to the document.
	 *
	 * @return  void
	 *
	 * @since        1.0.0
	 * @noinspection PhpUnusedParameterInspection
	 * @noinspection PhpUnused
	 */
	public function onBeforeRender(Event $event): void
	{
		// Should I proceed?
		if (!$this->shouldProcessOpenGraph())
		{
			return;
		}

		// Get the active menu item.
		$app            = $this->getApplication();
		$activeMenuItem = $app->getMenu()->getActive();

		// Populate $this->article and $this->content, if they exist
		$this->handleCoreContentMenuItems();

		// Start with the plugin parameters
		$parametersRetriever = $this->getParamsRetriever();
		$params              = array_merge($parametersRetriever->getDefaultParameters(), $this->params->toArray());

		if ($this->article)
		{
			$extraParams = $parametersRetriever->getArticleParameters($this->article);
			$params      = $parametersRetriever->inheritanceAwareMerge($params, $extraParams);
		}
		elseif ($this->category)
		{
			$extraParams = $parametersRetriever->getCategoryParameters($this->category);
			$params      = $parametersRetriever->inheritanceAwareMerge($params, $extraParams);
		}

		// Get the menu item parameters and cascade them.
		$paramsFromMenu = $parametersRetriever->getMenuParameters($activeMenuItem->id, $activeMenuItem);
		$params         = $parametersRetriever->inheritanceAwareMerge($params, $paramsFromMenu);

		/**
		 * Get the effective template ID.
		 *
		 * We are doing the following (first non-empty template ID found wins):
		 * * The `template` key from `$params`. Only set if there are (cascaded) overrides.
		 * * The default template ID set up in the extension.
		 * * The first published template's ID
		 * * Fallback to 0 which just uses some rather useless defaults.
		 */
		$firstTemplateKey          = array_key_first($this->getHelper()->getTemplates() ?? []);
		$configuredDefaultTemplate = $this->params->get('default_template', $firstTemplateKey) ?: null;
		$templateFromParams        = ($params['template'] ?? null) ?: null;
		$templateId                = $templateFromParams ?? $configuredDefaultTemplate ?? $firstTemplateKey ?? 0;

		$params['template'] = $templateId;

		// Generate an OpenGraph image if supported and if we are requested to do so.
		if ($this->getHelper()->isAvailable() && $params['generate_images'] == 1)
		{
			$this->applyOGImage($params);
		}

		// Apply additional OpenGraph tags
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

	private function handleCoreContentMenuItems(): void
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
		$app            = $this->getApplication();
		$activeMenuItem = $app->getMenu()->getActive();
		$menuOption     = $activeMenuItem->query['option'] ?? '';
		$currentOption  = $app->getInput()->getCmd('option', $menuOption);

		if (!empty($menuOption) && ($menuOption !== $currentOption))
		{
			$menuOption = $currentOption;
		}

		// Reset the found article and category objects.
		$this->article  = null;
		$this->category = null;

		// We can only handle com_content menu items here.
		if ($menuOption != 'com_content')
		{
			return;
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
				$this->category = ($this->category ?: $app->getInput()->getInt('id', $activeMenuItem->query['id'] ?? null));
				break;

			case 'archive':
			case 'article':
			case 'featured':
				// Apply article overrides if applicable
				$this->article = ($this->article ?: $app->getInput()->getInt('id', $activeMenuItem->query['id'] ?? null));

				break;
		}
	}

	/**
	 * Determine whether to proceed with processing the OpenGraph data for this page.
	 *
	 * It asserts the following conditions:
	 *
	 * * We are in the frontend (site) application.
	 * * There is an active menu item.
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	private function shouldProcessOpenGraph(): bool
	{
		// Is this the frontend HTML application?
		try
		{
			$app = $this->getApplication();

			if (
				!is_object($app)
				|| !($app instanceof CMSApplication)
				|| !$app->isClient('site')
				|| $app->getDocument()->getType() != 'html'
			)
			{
				return false;
			}
		}
		catch (Throwable)
		{
			return false;
		}

		// Try to get the active menu item
		try
		{
			$activeMenuItem = $app->getMenu()->getActive();
		}
		catch (Throwable)
		{
			return false;
		}

		// Make sure there *IS* an active menu item.
		if (empty($activeMenuItem))
		{
			return false;
		}

		return true;
	}
}