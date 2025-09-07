<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\SocialMagick\Menus\Extension;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\AbstractPlugin;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\FormInjectedEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemDescriptionEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemImageEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemParametersEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\ItemTitleEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\MenuItemFormEvent;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\PluginHelper;

/**
 * Integrate Joomla Menus to SocialMagick.
 *
 * This plugin injects an OpenGraph form when editing menu items, allowing you to override SocialMagick settings at the
 * menu item level. Other plugins in the `socialmagick` group can handle the onSocialMagickMenuItemForm event to tell
 * this plugin to use a different, component-specific form for that tab.
 *
 * IMPORTANT! This plugin DOES NOT implement onSocialMagickItemParameters on purpose. While that would be the
 * architecturally correct way to do it, you –the end user– would have to make sure that this plugin is always published
 * LAST. That would be critical for the parameter inheritance to work correctly, e.g. menu parameters overriding article
 * parameters and not vice versa. This is a tall order for all but the most advanced Joomla users, which is why we chose
 * to instead hard-code the parameter cascading rules into the code.
 */
final class Menus extends AbstractPlugin
{
	public function __construct($config = [])
	{
		$this->supportedComponent = 'com_menus';
		$this->itemInjectedForms  = [
			'com_menus.item' => 'socialmagick_menu',
		];

		parent::__construct($config);
	}

	/**
	 * Returns a custom form to inject to a menu item being edited by com_menus.
	 *
	 * This calls the onSocialMagickMenuItemForm event so that other plugins in the `socialmagick` group can override
	 * the injected form with their own, custom form with different, component-specific options.
	 *
	 * @param   FormInjectedEvent  $event
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	public function onSocialMagickFormInjected(FormInjectedEvent $event): void
	{
		/** @var Form $form */
		$form               = $event->getForm();
		$currentFormContext = $form->getName();

		if ($currentFormContext !== 'com_menus.item')
		{
			return;
		}

		/**
		 * Get a custom form name from the plugins.
		 *
		 * IMPORTANT! If a custom form name is returned, the plugin has also loaded necessary language files, and added
		 * its form path using the statuc Form::addFormPath method. Therefore, we just need to use loadFile().
		 */
		PluginHelper::importPlugin('socialmagick');
		$smEvent  = new MenuItemFormEvent(arguments: ['formdata' => $form->getData()]);
		$results  = $this->getApplication()->getDispatcher()->dispatch($smEvent->getName(), $smEvent)['result'];
		$formName = array_reduce($results, fn($carry, $result) => $carry ?? $result, null);

		// No custom form. Use the generic one, built into this plugin.
		if (!$formName)
		{
			// Load language files
			$this->loadLanguage('com_socialmagick');
			$this->loadLanguage('plg_' . $this->_type . '_' . $this->_name);

			// Add this plugin's `form` directory into the global form path source
			Form::addFormPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/form');

			$formName = $this->itemInjectedForms['com_menus.item'];
		}

		$event->addResult($formName);
	}

	/** @inheritDoc */
	public function onSocialMagickItemImage(ItemImageEvent $event): void
	{
		// Does not apply to Menu Items.
	}

	/** @inheritDoc */
	public function onSocialMagickItemTitle(ItemTitleEvent $event): void
	{
		// Does not apply to Menu Items.
	}

	/** @inheritDoc */
	public function onSocialMagickItemDescription(ItemDescriptionEvent $event): void
	{
		// Does not apply to Menu Items.
	}

	/** @inheritDoc */
	public function onSocialMagickItemParameters(ItemParametersEvent $event): void
	{
		// Does not apply to Menu Items. See the class docblock.
	}
}