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
use Joomla\CMS\Form\Form;

/**
 * Concrete event class for SocialMagick plugins to inject an OpenGraph form.
 *
 * @since  3.0.0
 */
final class FormInjectedEvent extends AbstractImmutableEvent implements ResultAwareInterface
{
	use ResultAware;
	use ResultTypeStringAware;
	use CheckParamsTrait;

	/**
	 * Concrete event constructor.
	 *
	 * This event takes the following arguments:
	 * * `form` The component item's edit form.
	 *
	 * @param   string  $name
	 * @param   array   $arguments
	 * @since   3.0.0
	 */
	public function __construct(string $name = 'onSocialMagickFormInjected', array $arguments = [])
	{
		$this->checkParams($name, ['form'], $arguments);

		parent::__construct($name, $arguments);
	}

	/**
	 * Retrieves the `form` argument.
	 *
	 * @return  Form
	 * @since   3.0.0
	 */
	public function getForm(): Form
	{
		return $this->arguments['form'];
	}

	/**
	 * Called when setting the `form` argument. Implicitly performs typecheck.
	 *
	 * @param   Form  $value  The value we're trying to set.
	 *
	 * @return  Form
	 * @since   3.0.0
	 */
	protected function onSetForm(Form $value): Form
	{
		return $value;
	}
}