<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\View\Template;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Mixin\ViewLoadAnyTemplateTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ViewToolbarTrait;
use Akeeba\Component\SocialMagick\Administrator\Model\TemplateModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * MVC View for the Template form (add / create) view
 *
 * @since 3.0.0
 */
class HtmlView extends BaseHtmlView
{
	use ViewLoadAnyTemplateTrait;
	use ViewToolbarTrait;

	/**
	 * The Joomla form used to generate the controls
	 *
	 * @var Form
	 */
	public $form;

	public function display($tpl = null)
	{
		/** @var TemplateModel $model */
		$model = $this->getModel();

		$this->form      = $model->getForm();

		// Push translations
		Text::script('JNO', true);
		Text::script('JYES', true);

		$this->addToolbar();

		parent::display($tpl);
	}

	protected function addToolbar()
	{
		Factory::getApplication()->getInput()->set('hidemainmenu', true);

		$isNew = empty($this->item->id);

		ToolbarHelper::title(Text::_('COM_SOCIALMAGICK_TITLE_TEMPLATE_' . ($isNew ? 'ADD' : 'EDIT')), 'icon-socialmagick');

		ToolbarHelper::apply('template.apply');

		$toolbarButtons = [];

		// If not checked out, can save the item.
		$toolbarButtons[] = ['save', 'template.save'];
		$toolbarButtons[] = ['save2new', 'template.save2new'];

		ToolbarHelper::saveGroup(
			$toolbarButtons,
			'btn-success'
		);

		ToolbarHelper::inlinehelp();

		ToolbarHelper::cancel('template.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');

		//ToolbarHelper::help(null, false, 'https://www.akeeba.com/documentation/socialmagick/template.html');
	}
}