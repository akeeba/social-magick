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
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Event\Result\ResultTypeStringAware;

/**
 * Concrete event class for SocialMagic plugins to return custom SocialMagick forms when editing menu items.
 *
 * @since  3.0.0
 */
final class MenuItemFormEvent extends AbstractImmutableEvent implements ResultAwareInterface
{
	use ResultAware;
	use ResultTypeStringAware;
	use CheckParamsTrait;

	/**
	 * Concrete event constructor.
	 *
	 * This event takes the following arguments:
	 * * `formdata` The com_menus form data when editing a menu item.
	 *
	 * @param   string  $name
	 * @param   array   $arguments
	 * @since   3.0.0
	 */
	public function __construct(string $name = 'onSocialMagickMenuItemForm', array $arguments = [])
	{
		$this->checkParams($name, ['formdata'], $arguments);

		parent::__construct($name, $arguments);
	}

	/**
	 * Retrieves the `formdata` argument.
	 *
	 * @return  array|object|null
	 * @since   3.0.0
	 */
	public function getFormData(): array|object|null
	{
		return $this->arguments['formdata'];
	}

	/**
	 * Called when setting the `formdata` argument. Implicitly performs typecheck.
	 *
	 * @param   array|object|null  $value  The value we're trying to set.
	 *
	 * @return  array|object|null
	 * @since   3.0.0
	 */
	protected function onSetFormdata(array|object|null $value): array|object|null
	{
		return $value;
	}
}