<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever;

use Joomla\Registry\Registry;

\defined('_JEXEC') || die;

trait ParamsFromRegistryTrait
{
	/**
	 * Default SocialMagick parameters for menu items, categories and articles
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
		'image_source'          => '',
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
	 * Retrieve the parameters from a Registry object, respecting the default values set at the top of the class.
	 *
	 * @param   Registry  $params     The Joomla Registry object which contains our parameters namespaced.
	 * @param   string    $namespace  The Joomla Registry namespace for our parameters
	 *
	 * @return  array
	 *
	 * @since   1.0.0
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

}