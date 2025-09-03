<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Mixin;

defined('_JEXEC') || die;

/**
 * A quick hack to get an object's properties.
 *
 * Normally, you'd have to use Reflection which is slow. Like, glacially slow. This hack hinges on the fact that when
 * you cast an object to array non-public properties have a NULL byte prefixed to their name, which is the key to the
 * associative array. Filtering for strings starting with null bytes is **orders of magnitude** faster than using
 * Reflection. Now you know.
 *
 * @since  3.0.0
 */
trait GetPropertiesAwareTrait
{
	/**
	 * Convert the object to an array.
	 *
	 * This is a **FAR** more efficient way to do things than the crap used by Joomla!. PHP always adds a NULL byte in
	 * front of private properties' names when casting an object to array. We exploit this quirk to filter out private
	 * properties without using the slow-as-molasses PHP Reflection.
	 *
	 * @param  bool  $public
	 *
	 * @return array
	 *
	 * @since  7.3.0
	 */
	public function getProperties($public = true)
	{
		$asArray = (array) $this;

		if (!$public)
		{
			return $asArray;
		}

		return array_filter($asArray, fn($x) => !empty($x) && !is_numeric($x) && ord(substr($x, 0, 1)) !== 0, ARRAY_FILTER_USE_KEY);
	}
}