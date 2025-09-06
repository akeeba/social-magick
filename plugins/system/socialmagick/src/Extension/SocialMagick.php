<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension;

defined('_JEXEC') || die();

use Akeeba\Component\SocialMagick\Administrator\Library\OpenGraphTags\OGTagsHelper;
use Akeeba\Plugin\System\SocialMagick\Extension\Traits\DebugPlaceholderTrait;
use Akeeba\Plugin\System\SocialMagick\Extension\Traits\ImageGeneratorHelperTrait;
use Akeeba\Plugin\System\SocialMagick\Extension\Traits\ParametersRetrieverTrait;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
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
	use DebugPlaceholderTrait;
	use ImageGeneratorHelperTrait;
	use ParametersRetrieverTrait;
	use Feature\FormTabs;
	use Feature\Ajax;

	/**
	 * Does the system meet the requirements for using this plugin?
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	private bool $enabled;

	public function __construct($config = [])
	{
		parent::__construct($config);
	}

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
	 * @param   Event  $event
	 *
	 * @return  void
	 * @throws  \Exception
	 * @since   1.0.0
	 */
	public function onBeforeRender(Event $event): void
	{
		// Should I proceed?
		if (!$this->isEnabled())
		{
			return;
		}

		$parametersRetriever = $this->getParamsRetriever();
		$imageGenerator      = $this->getImageGenerator();
		$ogTagsHelper        = new OGTagsHelper($this->getApplication());

		$activeMenuItem = $this->getApplication()->getMenu()->getActive();
		$params         = $parametersRetriever->getApplicableOGParameters($activeMenuItem);

		// Generate an OpenGraph image if supported and if we are requested to do so.
		if ($params['generate_images'] == 1 && $imageGenerator->isAvailable())
		{
			$arguments = $parametersRetriever->getOpenGraphImageGeneratorArguments($params);

			$imageGenerator->applyOGImage(...$arguments);
		}

		// Apply additional OpenGraph tags
		$ogTagsHelper->applyOpenGraphTags($params);
	}

	/**
	 * Runs when Joomla is about to display an article or category.
	 *
	 * TODO Remove me, and come up with a different way to preview the OpenGraph image. Maybe a floating button?
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function onContentBeforeDisplay(Event $event): void
	{
		if (!$this->isEnabled())
		{
			return;
		}

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

		if (!in_array($context, ['com_content.article', 'com_content.category', 'com_content.categories'], true))
		{
			return;
		}

		// Add the debug link if necessary
		$cParams = ComponentHelper::getParams('com_socialmagick');

		if ($cParams->get('debuglink', 0) != 1)
		{
			return;
		}

		$result[] = $this->getDebugLinkPlaceholder();

		$event->setArgument('result', $result);
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
		if (!$this->isEnabled())
		{
			return;
		}

		$ogTagsHelper = new OGTagsHelper($this->getApplication());
		$cParams      = ComponentHelper::getParams('com_socialmagick');
		if ($cParams->get('add_og_declaration', 1))
		{
			$ogTagsHelper->addOgPrefixToHtmlDocument();
		}

		// TODO Remove me or replace me.
		if ($cParams->get('debuglink', 0))
		{
			$this->replaceDebugImagePlaceholder();
		}
	}

	/**
	 * Does the environment meet the requirements to generate OpenGraph tags and images?
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	private function isEnabled(): bool
	{
		if (isset($this->enabled))
		{
			return $this->enabled;
		}

		$this->enabled = false;

		try
		{
			// Is com_socialmagick installed and enabled on this site?
			if (!ComponentHelper::getComponent('com_socialmagick', true)?->enabled)
			{
				return false;
			}

			// Is this the frontend HTML application?
			$app = $this->getApplication();

			if (!($app instanceof SiteApplication) || $app->getDocument()->getType() != 'html')
			{
				return false;
			}

			// Make sure there *IS* an active menu item.
			$activeMenuItem = $app->getMenu()->getActive();

			if (empty($activeMenuItem))
			{
				return false;
			}
		}
		catch (Throwable)
		{
			return false;
		}

		return $this->enabled = true;
	}
}