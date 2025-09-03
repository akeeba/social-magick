<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Service\Html;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * The HTML helper for the component.
 *
 * @since  3.0.0
 */
class SocialMagick
{
	public static function formatDate($date, $local = true, $dateFormat = null)
	{
		$date = clone Factory::getDate($date, 'GMT');

		if ($local)
		{
			$app  = Factory::getApplication();
			$user = $app->getIdentity();
			$zone = $user->getParam('timezone', $app->get('offset', 'UTC'));
			$tz   = new \DateTimeZone($zone);
			$date->setTimezone($tz);
		}

		$dateFormat = $dateFormat ?: (Text::_('DATE_FORMAT_LC5') . ' T');

		return $date->format($dateFormat, $local);
	}
}