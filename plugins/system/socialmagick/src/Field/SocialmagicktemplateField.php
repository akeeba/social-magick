<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Field;

defined('_JEXEC') || die();

use Akeeba\Component\SocialMagick\Administrator\Extension\SocialMagickComponent;
use Akeeba\Component\SocialMagick\Administrator\Model\TemplatesModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Select a SocialMagick template
 *
 * @package      Joomla\CMS\Form\Field
 *
 * @since        1.0.0
 * @noinspection PhpUnused
 */
class SocialmagicktemplateField extends ListField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $type = 'Socialmagicktemplate';

	protected function getOptions()
	{
		/** @var SocialMagickComponent $extension */
		$extension = Factory::getApplication()->bootComponent('com_socialmagick');
		/** @var TemplatesModel $templatesModel */
		$templatesModel = $extension->getMVCFactory()->createModel('Templates', 'Administrator', ['ignore_request' => true]);
		$templates      = $templatesModel->listEnabledTemplates();

		$options = array_map(fn($k, $templateName) => HTMLHelper::_('select.option', $k, $templateName), array_keys($templates), array_values($templates));
		$options = array_merge(parent::getOptions() ?? [], $options);

		if (empty($options))
		{
			return [
				'' => Text::_('PLG_SYSTEM_SOCIALMAGICK_FORM_COMMON_TEMPLATE_NONE_EXISTS'),
			];
		}

		return $options;
	}
}