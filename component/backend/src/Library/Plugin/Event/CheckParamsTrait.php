<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event;

\defined('_JEXEC') || die;

trait CheckParamsTrait
{
	private function checkParams(string $eventName, array $expectedKeys, array $arguments): void
	{
		$missing = array_diff($expectedKeys, array_keys($arguments));

		if (empty($missing))
		{
			return;
		}

		if (count($missing) == 1)
		{
			throw new \BadMethodCallException("Argument '{$missing[0]}' of event {$eventName} is required but has not been provided");
		}

		$missing = implode("', '", $missing);

		throw new \BadMethodCallException("Arguments {$missing} of event {$eventName} are required but have not been provided");
	}
}