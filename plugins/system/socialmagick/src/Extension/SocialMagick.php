<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension;

defined('_JEXEC') || die();

use Akeeba\Component\SocialMagick\Administrator\Library\OpenGraphTags\OGTagsHelper;
use Akeeba\Plugin\System\SocialMagick\Extension\Traits\ImageGeneratorHelperTrait;
use Akeeba\Plugin\System\SocialMagick\Extension\Traits\ParametersRetrieverTrait;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
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
	use ImageGeneratorHelperTrait;
	use ParametersRetrieverTrait;
	use Feature\FormTabs;

	/**
	 * Does the system meet the requirements for using this plugin?
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	private bool $enabled;

	private string $previewHtml;

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return [
			'onAfterRender'        => 'onAfterRender',
			'onBeforeRender'       => 'onBeforeRender',
			'onContentBeforeSave'  => 'onContentBeforeSave',
			'onContentPrepareData' => 'onContentPrepareData',
			'onContentPrepareForm' => 'onContentPrepareForm',
		];
	}

	/**
	 * Runs before Joomla renders the HTML document.
	 *
	 * This is the main event where SocialMagick evaluates whether to apply an OpenGraph image to the document.
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
		$activeMenuItem      = $this->getApplication()->getMenu()->getActive();
		$input               = $this->getApplication()->input;
		$ogTagsHelper        = new OGTagsHelper($this->getApplication());

		// Get the applicable parameters
		$params = $parametersRetriever->getApplicableOGParameters($activeMenuItem, $input);

		// Generate an OpenGraph image if supported and if we are requested to do so.
		if ($params['generate_images'] == 1 && $imageGenerator->isAvailable())
		{
			$cParams   = ComponentHelper::getParams('com_socialmagick');
			$arguments = $parametersRetriever->getOpenGraphImageGeneratorArguments($params, $activeMenuItem, $input);

			$imageGenerator->applyOGImage(...$arguments);

			if ($cParams->get('debuglink', 0))
			{
				$this->preparePreviewButton();
			}
		}

		// Apply additional OpenGraph tags
		$ogTagsHelper->applyOpenGraphTags($params);
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

		if (!empty($this->previewHtml ?? ''))
		{
			$body = $this->getApplication()->getBody();
			$body = str_replace('</body', $this->previewHtml . '</body', $body);
			$this->getApplication()->setBody($body);
		}
	}

	/**
	 * Prepares the OpenGraph preview button.
	 *
	 * This loads all the necessary CSS and JavaScript, and stashes the OpenGraph preview HTML in the `previewHtml`
	 * property. The HTML needs to be pushed to the bottom of the document in the onAfterRender event handler.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function preparePreviewButton(): void
	{
		/**
		 * @var HtmlDocument    $document Joomla's HTML document
		 * @var WebAssetManager $wam      The WebAssetManager
		 */
		$document = $this->getApplication()->getDocument();
		$wam      = $document->getWebAssetManager();

		// Load JavaScript and CSS
		$wam->getRegistry()->addExtensionRegistryFile('plg_system_socialmagick');
		$wam->usePreset('plg_system_socialmagick.preview');

		// Load the language
		$this->loadLanguage('plg_system_socialmagick', JPATH_ADMINISTRATOR);

		// Get the OpenGraph preview information
		$imageLink   = $document->getMetaData('og:image', 'property')
			?: $document->getMetaData('twitter:image', 'property')
				?: null;
		$title       = $document->getMetaData('og:title', 'property')
			?: $document->getTitle()
				?: $this->getApplication()->get('sitename');
		$description = $document->getMetaData('og:description', 'property')
			?: $document->getDescription()
				?: $this->getApplication()->get('MetaDesc')
					?: '';

		// Load the HTML template
		@ob_start();
		@include_once PluginHelper::getLayoutPath('system', 'socialmagick', 'preview');
		$this->previewHtml = @ob_get_clean() ?: '';
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

	/**
	 * Returns the first non-empty event result, if any, after calling the event.
	 *
	 * @param   Event  $event  The event to call.
	 *
	 * @return  mixed
	 * @since   3.0.0
	 */
	private function getFirstNonEmptyEventResult(Event $event): mixed
	{
		PluginHelper::importPlugin('socialmagick');

		$results = $this->getApplication()->getDispatcher()->dispatch($event->getName(), $event)['result'];

		return array_reduce($results ?: [], fn($carry, $result) => $carry ?? $result, null);
	}
}