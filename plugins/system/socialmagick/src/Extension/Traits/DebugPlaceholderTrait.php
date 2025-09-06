<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension\Traits;

\defined('_JEXEC') || die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserHelper;
use Throwable;

trait DebugPlaceholderTrait
{
	/**
	 * The placeholder variable to be replaced by the image link when Debug Link is enabled.
	 *
	 * @var  string
	 */
	protected string $debugLinkPlaceholder;

	/**
	 * Get a random, unique placeholder for the debug OpenGraph image link
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	private function getDebugLinkPlaceholder(): string
	{
		return $this->debugLinkPlaceholder ??= '{' . UserHelper::genRandomPassword(32) . '}';
	}

	/**
	 * Replace the debug image placeholder with a link to the OpenGraph image.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function replaceDebugImagePlaceholder(): void
	{
		// Make sure I am in the front-end, and I'm doing HTML output
		/** @var SiteApplication $app */
		$app = $this->getApplication();

		if (!is_object($app) || !($app instanceof SiteApplication))
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
		catch (Throwable)
		{
			return;
		}

		$imageLink = ($this->getApplication()->getDocument()->getMetaData('og:image') ?: $this->getApplication()->getDocument()->getMetaData('twitter:image')) ?: '';

		$this->loadLanguage('com_socialmagick', JPATH_ADMINISTRATOR);

		$message = Text::_('COM_SOCIALMAGICK_LBL_DEBUGLINK_MESSAGE');

		if ($message == 'COM_SOCIALMAGICK_LBL_DEBUGLINK_MESSAGE')
		{
			/** @noinspection HtmlUnknownTarget */
			$message = "<a href=\"%s\" target=\"_blank\">Preview OpenGraph Image</a>";
		}

		$message = $imageLink ? sprintf($message, $imageLink) : '';

		$app->setBody(str_replace($this->getDebugLinkPlaceholder(), $message, $app->getBody()));
	}
}