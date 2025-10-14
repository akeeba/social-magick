<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\Plugin;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\FormDataKeyEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\FormInjectedEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemDescriptionEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemImageEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemParametersEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemTitleEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\MenuItemFormEvent;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;

abstract class AbstractPlugin extends CMSPlugin implements SubscriberInterface, DatabaseAwareInterface
{
	use DatabaseAwareTrait;

	/**
	 * The name of the component supported by this plugin.
	 *
	 * Used when injecting forms to the menu item being edited.
	 *
	 * @var    string|null
	 * @since  3.0.0
	 */
	protected ?string $supportedComponent = null;

	/**
	 * The forms we will be injecting when editing menu items, keyed by the form context.
	 *
	 * The keys are form context such as 'com_foobar.item', 'com_foobar.*', or 'com_foobar.item*'
	 *
	 * The values are the names of the files in the plugin's `form` directory WITHOUT the `.xml` extension.
	 *
	 * @var    array|null
	 * @since  3.0.0
	 */
	protected ?array $itemInjectedForms = null;

	/**
	 * The key in the item's edit form (and, consequently, the database table) storing JSON data.
	 *
	 * This is where SocialMagick parameters are saved into, under the `socialmagick` key.
	 *
	 * For most components using the standard Joomla conventions it is `params`, which is our default. One notable
	 * exception is articles (com_content) which, for legacy reasons going back to Mambo, use the key `attribs` instead.
	 *
	 * @var    string
	 * @since  3.0.0
	 */
	protected string $itemDataKey = 'params';

	/**
	 * The name of the forms with custom SocialMagick parameters to inject when editing a menu item.
	 *
	 * The keys are view names. Use `*` for a catch-all.
	 *
	 * The values are the names of the form files in the plugin's `form` directory WITHOUT the `.xml` extension.
	 *
	 * @var    array<string>|null
	 * @since  3.0.0
	 */
	protected ?array $menuItemInjectedForms = null;

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return [
			'onSocialMagickMenuItemForm'    => 'onSocialMagickMenuItemForm',
			'onSocialMagickFormInjected'    => 'onSocialMagickFormInjected',
			'onSocialMagickFormDataKey'     => 'onSocialMagickFormDataKey',
			'onSocialMagickItemParameters'  => 'onSocialMagickItemParameters',
			'onSocialMagickItemImage'       => 'onSocialMagickItemImage',
			'onSocialMagickItemTitle'       => 'onSocialMagickItemTitle',
			'onSocialMagickItemDescription' => 'onSocialMagickItemDescription',
		];
	}

	/**
	 * Returns the item's parameters to SocialMagick for parameters cascading purposes.
	 *
	 * @param   ItemParametersEvent  $event  The event we received from Joomla.
	 *
	 * @return  void  Use the event's `addResult()` method instead.
	 * @since   3.0.0
	 */
	abstract public function onSocialMagickItemParameters(ItemParametersEvent $event): void;

	/**
	 * Returns the item's image to SocialMagick.
	 *
	 * @param   ItemImageEvent  $event  The event we received from Joomla.
	 *
	 * @return  void  Use the event's `addResult()` method instead.
	 * @since   3.0.0
	 */
	abstract public function onSocialMagickItemImage(ItemImageEvent $event): void;

	/**
	 * Returns the item's image to SocialMagick.
	 *
	 * @param   ItemTitleEvent  $event  The event we received from Joomla.
	 *
	 * @return  void  Use the event's `addResult()` method instead.
	 * @since   3.0.0
	 */
	abstract public function onSocialMagickItemTitle(ItemTitleEvent $event): void;

	/**
	 * Returns the item's image to SocialMagick.
	 *
	 * @param   ItemDescriptionEvent  $event  The event we received from Joomla.
	 *
	 * @return  void  Use the event's `addResult()` method instead.
	 * @since   3.0.0
	 */
	abstract public function onSocialMagickItemDescription(ItemDescriptionEvent $event): void;

	/**
	 * Returns a custom form to inject to a menu item being edited.
	 *
	 * @param   MenuItemFormEvent  $event
	 *
	 * @return  void
	 *
	 * @since        3.0.0
	 * @noinspection PhpUnused
	 */
	public function onSocialMagickMenuItemForm(MenuItemFormEvent $event): void
	{
		if (empty($this->menuItemInjectedForms) || empty($this->supportedComponent))
		{
			return;
		}

		$formData = $event->getFormData();

		if (!$this->isMenuItemForComponent($formData, [$this->supportedComponent]))
		{
			return;
		}

		foreach ($this->menuItemInjectedForms as $matchView => $formToLoad)
		{
			if (!$this->isMenuItemForView($formData, $matchView))
			{
				continue;
			}

			// Load language files
			$this->loadLanguage('com_socialmagick');
			$this->loadLanguage('plg_' . $this->_type . '_' . $this->_name);

			// Add this plugin's `form` directory into the global form path source
			Form::addFormPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/form');

			// Tell the SocialMagick plugin to add our custom form into the menu item's display form.
			$event->addResult($formToLoad);
		}
	}

	/**
	 * Returns a custom form to inject to a component item being edited.
	 *
	 * @param   FormInjectedEvent  $event
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	public function onSocialMagickFormInjected(FormInjectedEvent $event): void
	{
		/** @var Form $form */
		$form               = $event->getForm();
		$currentFormContext = $form->getName();

		// Check frontend editing and user groups
		if ($this->limitedByFrontendEditing() || $this->limitedByUserGroups())
		{
			return;
		}

		// Try to match a form context
		foreach ($this->itemInjectedForms ?? [] as $searchContext => $formToLoad)
		{
			if (
				$currentFormContext === $searchContext
				|| (str_ends_with($searchContext, '*') && str_starts_with($currentFormContext, rtrim($searchContext, '*')))
			)
			{
				// Special case: `com_categories.category*` contexts require checking plg_socialmagick_categories.
				if (str_starts_with($currentFormContext, 'com_categories.category') && !$this->canInjectIntoCategoryForm())
				{
					return;
				}

				// Load language files
				$this->loadLanguage('com_socialmagick');
				$this->loadLanguage('plg_' . $this->_type . '_' . $this->_name);

				// Add this plugin's `form` directory into the global form path source
				Form::addFormPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/form');

				// Tell the SocialMagick plugin to add our custom form into the menu item's display form.
				$event->addResult($formToLoad);

				return;
			}
		}
	}

	/**
	 * Handles the form data key for SocialMagick during the item form processing.
	 *
	 * You may want to override this method for more fine-grained context matching. The default behaviour is to match
	 * any context whose name starts with the supported component set in the plugin. For more complex components this
	 * may not be enough, or desirable.
	 *
	 * @param   FormDataKeyEvent  $event  The event received during form data key processing.
	 *
	 * @return  void  Uses the event's `addResult()` method to set the form data key.
	 *
	 * @since        3.0.0
	 * @noinspection PhpUnused
	 */
	public function onSocialMagickFormDataKey(FormDataKeyEvent $event): void
	{
		if (!str_starts_with($event->getContext(), $this->supportedComponent . '.'))
		{
			return;
		}

		$event->addResult($this->itemDataKey);
	}

	/**
	 * Checks if the com_menus form data says we're editing a menu item for an allowed component.
	 *
	 * @param   array|object  $formData         The com_menus form data.
	 * @param   array         $validComponents  Allowed components to show an OpenGraph tab for.
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	protected function isMenuItemForComponent(array|object $formData, array $validComponents): bool
	{
		// Normalise the form data
		if (is_array($formData))
		{
			$formData = (object) $formData;
		}

		if ($formData instanceof Registry)
		{
			$formData = (object) $formData->toArray();
		}

		// This must be a link to a component, obviously.
		if (($formData->type ?? '') != 'component')
		{
			return false;
		}

		if (isset($formData->params) && is_array($formData->params) && in_array($formData->params['option'] ?? '', $validComponents, true))
		{
			return true;
		}

		if (isset($formData->request) && is_array($formData->request) && in_array($formData->request['option'] ?? '', $validComponents, true))
		{
			return true;
		}

		// 'index.php?option=com_ats&view=latest'
		$link = $formData->link ?? '';

		if (!empty($link))
		{
			$uri = new Uri($link);

			if (in_array($uri->getVar('option', ''), $validComponents, true))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the com_menus form data says we're editing a menu item for the specified view.
	 *
	 * @param   array|object  $formData  The com_menus form data.
	 * @param   string        $view      The view to check. Can end in '*' for partial match.
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	protected function isMenuItemForView(array|object $formData, string $view): bool
	{
		$isMatch = function ($item) use ($view) {
			if (!str_ends_with($view, '*'))
			{
				return $item === $view;
			}

			return str_starts_with($item, rtrim($view, '*'));
		};

		// Normalise the form data
		if (is_array($formData))
		{
			$formData = (object) $formData;
		}

		// This must be a link to a component, obviously.
		if (($formData->type ?? '') != 'component')
		{
			return false;
		}

		// Catch-all case: matches anything.
		if ($view === '*')
		{
			return true;
		}

		// Legacy `params`, should not exist in Joomla 5+.
		if (isset($formData->params) && is_array($formData->params) && $isMatch($formData->params['view']))
		{
			return true;
		}

		// Request array, should be the default in Joomla 5+.
		if (isset($formData->request) && is_array($formData->request) && $isMatch($formData->request['view']))
		{
			return true;
		}

		// Parses the link, it's in the format 'index.php?option=com_ats&view=latest'. Should not be required in J5+.
		$link = $formData->link ?? '';

		if (!empty($link))
		{
			$uri = new Uri($link);

			if ($isMatch($uri->getVar('view', '')))
			{
				return true;
			}
		}

		return false;
	}


	/**
	 * Like `in_array()`, but allows the array to include glob strings in the form `foo*`.
	 *
	 * @param   string  $item   The item to search.
	 * @param   array   $array  The array to search it in.
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	private function inArrayGlob(string $item, array $array)
	{
		// First, we do a quick, exact string search to save time in most cases.
		if (in_array($item, $array, true))
		{
			return true;
		}

		$array = array_filter($array, fn($x) => str_ends_with($x, '*'));

		if (empty($array))
		{
			return false;
		}

		$array = array_map(fn($x) => rtrim($x, '*'), $array);

		foreach ($array as $search)
		{
			if (str_starts_with($item, $search))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Are we limited by frontend editing?
	 *
	 * When we detect the frontend of the site, AND the “Allow Frontend Editing of OpenGraph Settings” setting is
	 * disabled, we will not display the OpenGraph settings tab. Super Users are not limited by this setting.
	 *
	 * Plugins must implement the `edititem_frontend` plugin parameter. If the parameter does not exist, we assume
	 * frontend editing is implicitly allowed.
	 *
	 * @param   Registry|null  $params  The plugin parameters.
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	private function limitedByFrontendEditing(?Registry $params = null): bool
	{
		$params     ??= $this->params;
		$isFrontend = $this->getApplication()?->isClient('site');

		if ($isFrontend !== true)
		{
			return false;
		}

		// Is this a Super User? Super Users are allowed to do EVERYTHING.
		if ($this->getApplication()?->getIdentity()?->authorise('core.admin'))
		{
			return false;
		}

		if ($params->get('edititem_frontend', 1))
		{
			return false;
		}

		return true;
	}

	/**
	 * Are we limited by user groups?
	 *
	 * If there are one or more groups selected in “Limit OpenGraph Settings To Users Of These Groups”, and the user
	 * does not belong to any of them, we will not display the OpenGraph settings tab. Super Users are not limited by
	 * this setting.
	 *
	 * Plugins must implement the `edititem_groups` plugin parameter. If the parameter does not exist, we assume no
	 * user group limits should be applied.
	 *
	 * @param   Registry|null  $params  The plugin parameters.
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	private function limitedByUserGroups(?Registry $params = null): bool
	{
		$params            ??= $this->params;
		$allowedUserGroups = $params->get('edititem_groups', []);
		$allowedUserGroups = is_string($allowedUserGroups) ? explode(',', $allowedUserGroups) : $allowedUserGroups;

		if (empty($allowedUserGroups))
		{
			return false;
		}

		$user     = $this->getApplication()?->getIdentity();
		$myGroups = $user?->getAuthorisedGroups();

		if (empty($myGroups))
		{
			// This would mean that there is no application identity user i.e. it's not the front- or backend.
			return true;
		}

		// Is this a Super User? Super Users are allowed to do EVERYTHING.
		if ($user?->authorise('core.admin'))
		{
			return false;
		}

		// Check the user groups
		foreach ($myGroups as $myGroup)
		{
			if (in_array($myGroup, $allowedUserGroups, true))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Can we inject a form into the categories edit page?
	 *
	 * Component integration plugins are responsible for injecting their own, specialised parameters forms when a
	 * category of this specific component is being edited. For example, plg_socialmagick_articles is responsible for
	 * injecting the OpenGraph form when editing a com_content category in com_categories.
	 *
	 * However, loading the data in these forms and saving the data from these forms is handled by a different plugin,
	 * plg_socialmagick_categories. Therefore, we need to check with that plugin's parameters to make sure it is enabled
	 * and that it allows handling those edit forms.
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	private function canInjectIntoCategoryForm(): bool
	{
		// If the Categories plugin is unpublished, I cannot continue.
		if (!PluginHelper::isEnabled('socialmagick', 'categories'))
		{
			return false;
		}

		// If the Categories plugin is limited by frontend editing or user groups, I cannot continue.
		$params = PluginHelper::getPlugin('socialmagick', 'categories')->params;
		$params = $params instanceof Registry ? $params : new Registry($params);

		if ($this->limitedByFrontendEditing($params) || $this->limitedByUserGroups($params))
		{
			return false;
		}

		return true;
	}
}