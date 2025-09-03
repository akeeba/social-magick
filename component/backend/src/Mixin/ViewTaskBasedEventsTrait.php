<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Mixin;

defined('_JEXEC') || die;

/**
 * A trait for MVC Views which routes plugin events based on the name of the view and task you are executing.
 *
 * @since  3.0.0
 */
trait ViewTaskBasedEventsTrait
{
	use TriggerEventTrait;

	public function display($tpl = null)
	{
		$task = $this->getModel()->getState('task');

		$eventName = 'onBefore' . ucfirst($task);
		$this->triggerEvent($eventName, [&$tpl]);

		parent::display($tpl);

		$eventName = 'onAfter' . ucfirst($task);
		$this->triggerEvent($eventName, [&$tpl]);
	}
}