<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension\Feature;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Table\Table;
use Joomla\Event\Event;

trait FormTabs
{
	/**
	 * Runs when Joomla is preparing a form. Used to add extra form fieldsets to core pages.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 *
	 * @since        1.0.0
	 */
	public function onContentPrepareForm(Event $event): void
	{
		/**
		 * @var Form  $form The form to be altered.
		 * @var mixed $data The associated data for the form.
		 */
		[$form, $data] = array_values($event->getArguments());
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = true;

		$this->loadLanguage();
		$this->loadLanguage('plg_system_socialmagick.sys');

		Form::addFormPath(__DIR__ . '/../../form');

		switch ($form->getName())
		{
			// A menu item is being added/edited
			case 'com_menus.item':
				// TODO Only show something if it's a com_content or com_categories menu item
				$form->loadFile('socialmagick_menu', false);
				break;

			// A core content category is being added/edited
			case 'com_categories.categorycom_content':
				$form->loadFile('socialmagick_category', false);
				break;

			// An article is being added/edited
			case 'com_content.article':
				$form->loadFile('socialmagick_article', false);
				break;
		}

		$event->setArgument('result', $result);
	}

	/**
	 * Triggered when Joomla is saving content. Used to save the SocialMagick configuration.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onContentBeforeSave(Event $event): void
	{
		/**
		 * @var   string|null       $context Context for the content being saved
		 * @var   Table|object      $table   Joomla table object where the content is being saved to
		 * @var   bool              $isNew   Is this a new record?
		 * @var   object|array|null $data    Data being saved (Joomla 4)
		 */
		[$context, $table, $isNew, $data] = array_values($event->getArguments());
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = true;
		$event->setArgument('result', $result);

		$data = (array) $data;

		// Make sure I have data to save
		if (!isset($data['socialmagick']))
		{
			return;
		}

		$key = null;

		switch ($context)
		{
			case 'com_menus.item':
			case 'com_categories.category':
				$key = 'params';
				break;

			case 'com_content.article':
				$key = 'attribs';
				break;
		}

		if (is_null($key))
		{
			return;
		}

		$params        = @json_decode($table->{$key}, true) ?? [];
		$table->{$key} = json_encode(array_merge($params, ['socialmagick' => $data['socialmagick']]));
	}

	/**
	 * Triggered when Joomla is loading content. Used to load the Social Magick configuration.
	 *
	 * This is used for both articles and article categories.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onContentPrepareData(Event $event): void
	{
		/**
		 * @var   string|null  $context Context for the content being loaded
		 * @var   object|array $data    Data being saved
		 */
		[$context, $data] = array_values($event->getArguments());
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = true;
		$event->setArgument('result', $result);

		$key = null;

		switch ($context)
		{
			case 'com_menus.item':
			case 'com_categories.category':
				$key = 'params';
				break;

			case 'com_content.article':
				$key = 'attribs';
				break;
		}

		if (is_null($key))
		{
			return;
		}

		if (!isset($data->{$key}) || !isset($data->{$key}['socialmagick']))
		{
			return;
		}

		$data->socialmagick = $data->{$key}['socialmagick'];
		unset ($data->{$key}['socialmagick']);
	}
}