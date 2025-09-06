<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\OpenGraphTags;

use Joomla\Application\ApplicationInterface;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Document\HtmlDocument;
use Throwable;

final class OGTagsHelper
{
	public function __construct(private readonly ApplicationInterface $application)
	{
	}

	/**
	 * Adds the `prefix="og: http://ogp.me/ns#"` declaration to the `<html>` root tag.
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	public function addOgPrefixToHtmlDocument(): void
	{
		// Make sure I am in the front-end, and I'm doing HTML output
		/** @var SiteApplication $app */
		$app = $this->application;

		if (!$app instanceof SiteApplication)
		{
			return;
		}

		try
		{
			if ($app->getDocument()->getType() !== 'html')
			{
				return;
			}
		}
		catch (Throwable)
		{
			return;
		}

		$html = $app->getBody();

		$hasDeclaration = function (string $html): bool {
			$detectPattern = '/<html.*prefix\s?="(.*)\s?:(.*)".*>/iU';
			$count         = preg_match_all($detectPattern, $html, $matches);

			if ($count === 0)
			{
				return false;
			}

			for ($i = 0; $i < $count; $i++)
			{
				if (trim($matches[1][$i]) == 'og')
				{
					return true;
				}
			}

			return false;
		};

		if ($hasDeclaration($html))
		{
			return;
		}

		$replacePattern = '/<html(.*)>/iU';

		/** @noinspection HttpUrlsUsage */
		$app->setBody(preg_replace($replacePattern, '<html$1 prefix="og: http://ogp.me/ns#">', $html, 1));
	}

	/**
	 * Apply OpenGraph tags
	 *
	 * @param   array  $params  Applicable parameters for OpenGraph tag generation.
	 *
	 * @return  void
	 * @since   2.0.0
	 */
	public function applyOpenGraphTags(array $params): void
	{
		$this->applyOpenGraphTitle($params);
		$this->applyOpenGraphDescription($params);
		$this->applyOpenGraphURL($params);
		$this->applyOpenGraphSiteName($params);
		$this->applyFacebookAppID($params);
		$this->applyTwitterTags($params);
	}

	/**
	 * Apply an HTML meta-attribute if it doesn't already exist.
	 *
	 * @param   string  $name       The name of the meta to add.
	 * @param   mixed   $value      The value of the meta to apply.
	 * @param   string  $attribute  The meta-attribute, default is 'property', could also be 'name'
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function conditionallyApplyMeta(string $name, $value, string $attribute = 'property'): void
	{
		/** @var HtmlDocument $doc */
		$doc      = $this->application->getDocument();
		$existing = $doc->getMetaData($name, $attribute);

		if (!empty($existing))
		{
			return;
		}

		$doc->setMetaData($name, $value, $attribute);
	}

	/**
	 * Apply the `og:title` meta tag.
	 *
	 * @param   array  $params  The applicable OpenGraph parameters.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applyOpenGraphTitle(array $params): void
	{
		// Apply OpenGraph Title
		switch ($params['og_title'] ?? 1)
		{
			case 0:
				break;

			case 1:
				$this->conditionallyApplyMeta('og:title', $this->application->getDocument()->getTitle());
				break;

			case 2:
				$this->conditionallyApplyMeta('og:title', $params['og_title_custom'] ?? $this->application->getDocument()->getTitle());
				break;
		}
	}

	/**
	 * Apply the `og:description` meta tag.
	 *
	 * @param   array  $params  The applicable OpenGraph parameters.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applyOpenGraphDescription(array $params): void
	{
		// Apply OpenGraph Description
		switch ($params['og_description'] ?? 1)
		{
			case 0:
				break;

			case 1:
				$this->conditionallyApplyMeta('og:description', $this->application->getDocument()->getDescription());
				break;

			case 2:
				$this->conditionallyApplyMeta('og:description', $params['og_description_custom'] ?? $this->application->getDocument()->getDescription());
				break;
		}
	}

	/**
	 * Apply the `og:url` meta tag.
	 *
	 * @param   array  $params  The applicable OpenGraph parameters.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applyOpenGraphURL(array $params): void
	{
		// Apply OpenGraph URL
		if (($params['og_url'] ?? 1) == 1)
		{
			$this->conditionallyApplyMeta('og:url', $this->application->getDocument()->getBase());
		}
	}

	/**
	 * Apply the `og:site_name` meta tag.
	 *
	 * @param   array  $params  The applicable OpenGraph parameters.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applyOpenGraphSiteName(array $params): void
	{
		// Apply OpenGraph Site Name
		if (($params['og_site_name'] ?? 1) == 1)
		{
			$this->conditionallyApplyMeta('og:site_name', $this->application->get('sitename', ''));
		}
	}

	/**
	 * Apply the `fb:app_id` meta tag.
	 *
	 * @param   array  $params  The applicable OpenGraph parameters.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applyFacebookAppID(array $params): void
	{
		// Apply Facebook App ID
		$fbAppId = trim($params['fb_app_id'] ?? '');

		if (!empty($fbAppId))
		{
			$this->conditionallyApplyMeta('fb:app_id', $fbAppId);
		}
	}

	/**
	 * Apply the `twitter:*` meta tags.
	 *
	 * @param   array  $params  The applicable OpenGraph parameters.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applyTwitterTags(array $params): void
	{
		// Apply Twitter options, of there is a Twitter card type
		$twitterCard    = trim($params['twitter_card'] ?? '');
		$twitterSite    = trim($params['twitter_site'] ?? '');
		$twitterCreator = trim($params['twitter_creator'] ?? '');

		switch ($twitterCard)
		{
			case 0:
				// Nothing further to do with Twitter.
				return;

			case 1:
				$this->conditionallyApplyMeta('twitter:card', 'summary', 'name');
				break;

			case 2:
				$this->conditionallyApplyMeta('twitter:card', 'summary_large_image', 'name');
				break;
		}

		if (!empty($twitterSite))
		{
			$twitterSite = (substr($twitterSite, 0, 1) == '@') ? $twitterSite : ('@' . $twitterSite);
			$this->conditionallyApplyMeta('twitter:site', $twitterSite, 'name');
		}

		if (!empty($twitterCreator))
		{
			$twitterCreator = (substr($twitterCreator, 0, 1) == '@') ? $twitterCreator : ('@' . $twitterCreator);
			$this->conditionallyApplyMeta('twitter:creator', $twitterCreator, 'name');
		}

		// Transcribe OpenGraph properties to Twitter meta
		/** @var HtmlDocument $doc */
		$doc = $this->application->getDocument();

		$transcribes = [
			'title'       => $doc->getMetaData('og:title', 'property'),
			'description' => $doc->getMetaData('og:description', 'property'),
			'image'       => $doc->getMetaData('og:image', 'property'),
			'image:alt'   => $doc->getMetaData('og:image:alt', 'property'),
		];

		foreach ($transcribes as $key => $value)
		{
			$value = trim($value ?? '');

			if (empty($value))
			{
				continue;
			}

			$this->conditionallyApplyMeta('twitter:' . $key, $value, 'name');
		}
	}
}