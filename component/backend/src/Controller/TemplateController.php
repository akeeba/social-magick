<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Controller;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Mixin\ControllerEvents;
use Joomla\CMS\MVC\Controller\FormController;

/**
 * MVC Controller for the Template form (edit / add) view.
 *
 * @since 3.0.0
 */
class TemplateController extends FormController
{
	use ControllerEvents;

	protected $text_prefix = 'COM_SOCIALMAGICK_TEMPLATE';
}