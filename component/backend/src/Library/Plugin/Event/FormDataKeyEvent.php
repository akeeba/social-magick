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

/**
 * Concrete event class for SocialMagick plugins to return the form key used to save SocialMagick data.
 *
 * @since  3.0.0
 */
final class FormDataKeyEvent extends AbstractImmutableEvent
{
	use ResultAware;
	use ResultTypeStringAware;
	use CheckParamsTrait;

	/**
	 * Concrete event constructor.
	 *
	 * This event takes the following arguments:
	 * * `context` The form context.
	 *
	 * @param   string  $name
	 * @param   array   $arguments
	 * @since   3.0.0
	 */
	public function __construct(string $name = 'onSocialMagickFormDataKey', array $arguments = [])
	{
		$this->checkParams($name, ['context'], $arguments);

		parent::__construct($name, $arguments);
	}

	/**
	 * Retrieves the `context` argument.
	 *
	 * @return  string
	 * @since   3.0.0
	 */
	public function getContext(): string
	{
		return $this->arguments['context'];
	}

	/**
	 * Called when setting the `context` argument. Implicitly performs typecheck.
	 *
	 * @param   string  $value  The value we're trying to set.
	 *
	 * @return  string
	 * @since   3.0.0
	 */
	protected function onSetContext(string $value): string
	{
		return $value;
	}
}