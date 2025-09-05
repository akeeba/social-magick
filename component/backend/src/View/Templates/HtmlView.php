<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\View\Templates;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Mixin\ViewBehavioursTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ViewListLimitFixTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ViewLoadAnyTemplateTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ViewSystemPluginExistsTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ViewTableUITrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ViewTaskBasedEventsTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ViewToolbarTrait;
use Akeeba\Component\SocialMagick\Administrator\Model\TemplatesModel;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Button\DropdownButton;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * MVC View for the Templates list view
 *
 * @since 3.0.0
 */
class HtmlView extends BaseHtmlView
{
	use ViewLoadAnyTemplateTrait;
	use ViewTaskBasedEventsTrait;
	use ViewSystemPluginExistsTrait;
	use ViewTableUITrait;
	use ViewToolbarTrait;
	use ViewListLimitFixTrait;
	use ViewBehavioursTrait;

	/**
	 * The search tools form
	 *
	 * @var    Form
	 * @since  3.0.0
	 */
	public Form $filterForm;

	/**
	 * The active search filters
	 *
	 * @var    array
	 * @since  3.0.0
	 */
	public array $activeFilters = [];

	/**
	 * An array of items
	 *
	 * @var    array
	 * @since  3.0.0
	 */
	protected array|false $items = [];

	/**
	 * The pagination object
	 *
	 * @var    Pagination
	 * @since  3.0.0
	 */
	protected Pagination $pagination;

	/**
	 * The model state
	 *
	 * @var    Registry
	 * @since  3.0.0
	 */
	protected Registry $state;

	/**
	 * Is this view an Empty State
	 *
	 * @var   boolean
	 * @since 3.0.0
	 */
	private bool $isEmptyState = false;

	/** @inheritDoc */
	public function display($tpl = null)
	{
		/** @var TemplatesModel $model */
		$model = $this->getModel();
		$this->fixListLimitPastTotal($model);
		$this->items         = $model->getItems();
		$this->pagination    = $model->getPagination();
		$this->state         = $model->getState();
		$this->filterForm    = $model->getFilterForm();
		$this->activeFilters = $model->getActiveFilters();

		// Check for errors.
		if (method_exists($this->getModel(), 'getErrors'))
		{
			/** @noinspection PhpDeprecationInspection */
			$errors = $this->getModel()->getErrors();

			if (is_countable($errors) && count($errors))
			{
				throw new GenericDataException(implode("\n", $errors), 500);
			}
		}

		if (!\count($this->items) && $this->isEmptyState = $this->getModel()->getIsEmptyState())
		{
			$this->setLayout('emptystate');
		}

		$this->populateSystemPluginExists();

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Configures the toolbar with required buttons based on user permissions.
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   3.0.0
	 */
	private function addToolbar(): void
	{
		$user = Factory::getApplication()->getIdentity();

		// Get the toolbar object instance
		$toolbar = $this->getToolbarCompat();

		ToolbarHelper::title(sprintf(Text::_('COM_SOCIALMAGICK_TITLE_TEMPLATES')), 'icon-socialmagick');

		$canCreate    = $user->authorise('core.create', 'com_socialmagick');
		$canDelete    = $user->authorise('core.delete', 'com_socialmagick');
		$canEditState = $user->authorise('core.edit.state', 'com_socialmagick');

		{
			$toolbar
				->addNew('template.add');
		}

		if ($canDelete || $canEditState || $canCreate)
		{
			/** @var DropdownButton $dropdown */
			$dropdown = $toolbar->dropdownButton('status-group')
				->text('JTOOLBAR_CHANGE_STATUS')
				->toggleSplit(false)
				->icon('icon-ellipsis-h')
				->buttonClass('btn btn-action')
				->listCheck(true);

			$childBar = $dropdown->getChildToolbar();

			if ($canCreate)
			{
				$childBar->standardButton('copy', 'COM_SOCIALMAGICK_COMMON_LBL_COPY_LABEL')
					->icon('fa fa-copy')
					->task('templates.copy')
					->listCheck(true);
			}

			if ($canDelete)
			{
				$childBar->delete('templates.delete')
					->message('JGLOBAL_CONFIRM_DELETE')
					->listCheck(true);
			}
		}

		ToolbarHelper::preferences('com_socialmagick');

		//ToolbarHelper::help(null, false, 'https://www.akeeba.com/documentation/socialmagick/templates.html');
	}


}