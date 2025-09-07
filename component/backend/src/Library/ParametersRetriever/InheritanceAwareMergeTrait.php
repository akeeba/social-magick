<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever;

trait InheritanceAwareMergeTrait
{
	/**
	 * Merges the `$overrides` parameters into the `$source` parameters, aware of the inheritance rules.
	 *
	 * @param   array  $source     Source parameters.
	 * @param   array  $overrides  Array of possible overrides.
	 *
	 * @return  array
	 * @since   3.0.0
	 */
	private function inheritanceAwareMerge(array $source, array $overrides): array
	{
		$overrideImageParams = isset($overrides['override']) && $overrides['override'] == 1;

		if (empty($overrides))
		{
			return $source;
		}

		$temp = [];

		$temp['override'] = $overrideImageParams || ($source['override'] ?? null) == 1 ? 1 : 0;;

		foreach ($source as $key => $value)
		{
			// Start by using the source value
			$temp[$key] = $value;

			// If there is no override value under this key, well, we're done.
			if (!isset($overrides[$key]))
			{
				continue;
			}

			// Ignore override and og_override; I have already handled that.
			if (in_array($key, ['override'], true))
			{
				continue;
			}

			// Is it a valid override?
			$isOGKey = str_starts_with($key, 'og_') || str_starts_with($key, 'twitter_') || str_starts_with($key, 'fb_');

			if (!$isOGKey && !$overrideImageParams)
			{
				continue;
			}

			// Does this override anything, or does it basically say "nah, use the default"?
			$newValue = $overrides[$key];

			if ($newValue === '' || $newValue < 0)
			{
				continue;
			}

			// Okay, that's an override alright!
			$temp[$key] = $newValue;
		}

		return $temp;
	}

}