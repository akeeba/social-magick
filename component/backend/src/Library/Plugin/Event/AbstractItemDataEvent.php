<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event;

\defined('_JEXEC') || die;

use Joomla\CMS\Event\AbstractImmutableEvent;
use Joomla\CMS\Event\Result\ResultAware;
use Joomla\CMS\Event\Result\ResultTypeStringAware;
use Joomla\CMS\Menu\MenuItem;
use Joomla\Input\Input;
use Joomla\Registry\Registry;

/**
 * Concrete event class for SocialMagic plugins to return data from the currently displayed item.
 *
 * @since  3.0.0
 */
abstract class AbstractItemDataEvent extends AbstractImmutableEvent
{
	use ResultAware;
	use CheckParamsTrait;

	/**
	 * Concrete event constructor.
	 *
	 * This event takes the following arguments:
	 * * `params` The OPenGraph generation parameters.
	 * * `menuitem` The active Joomla menu item.
	 * * `input` The Joomla Input object.
	 *
	 * @param   string  $name
	 * @param   array   $arguments
	 * @since   3.0.0
	 */
	public function __construct(string $name, array $arguments = [])
	{
		$this->checkParams($name, ['params', 'menuitem', 'input'], $arguments);

		parent::__construct($name, $arguments);
	}

	/**
	 * Retrieves the `params` argument. This is the OpenGraph parameters.
	 *
	 * @return  Registry
	 * @since   3.0.0
	 */
	public function getParams(): Registry
	{
		return $this->arguments['menuitem'];
	}

	/**
	 * Retrieves the `menuitem` argument. This is the active menu item.
	 *
	 * @return  MenuItem|null
	 * @since   3.0.0
	 */
	public function getMenuItem(): ?MenuItem
	{
		return $this->arguments['menuitem'];
	}

	/**
	 * Retrieves the `input` argument. This is the Joomla input object.
	 *
	 * @return  Input|null
	 * @since   3.0.0
	 */
	public function getInput(): ?Input
	{
		return $this->arguments['input'];
	}

	/**
	 * Called when setting the `params` argument. Implicitly performs typecheck.
	 *
	 * @param   Registry|string|array|object  $value  The value we're trying to set.
	 *
	 * @return  Registry
	 * @since   3.0.0
	 */
	protected function onSetParams(string|array|object $value): Registry
	{
		if ($value instanceof Registry)
		{
			return $value;
		}

		return new Registry($value);
	}

	/**
	 * Called when setting the `menuitem` argument. Implicitly performs typecheck.
	 *
	 * @param   MenuItem|null  $value  The value we're trying to set.
	 *
	 * @return  MenuItem|null
	 * @since   3.0.0
	 */
	protected function onSetMenuitem(?MenuItem $value): ?MenuItem
	{
		return $value;
	}

	/**
	 * Called when setting the `input` argument. Implicitly performs typecheck.
	 *
	 * @param   Input|null  $value  The value we're trying to set.
	 *
	 * @return  Input|null
	 * @since   3.0.0
	 */
	protected function onSetInput(?Input $value): ?Input
	{
		return $value;
	}
}