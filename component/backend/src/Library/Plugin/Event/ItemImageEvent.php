<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event;

\defined('_JEXEC') || die;

use Joomla\CMS\Event\Result\ResultTypeStringAware;

/**
 * Concrete event class for SocialMagic plugins to return the current item's image.
 *
 * @since  3.0.0
 */
final class ItemImageEvent extends AbstractItemDataEvent
{
	use ResultTypeStringAware;

	/** @inheritDoc */
	public function __construct(string $name = 'onSocialMagickItemImage', array $arguments = [])
	{
		parent::__construct($name, $arguments);
	}
}