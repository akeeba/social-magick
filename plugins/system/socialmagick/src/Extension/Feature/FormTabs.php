<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension\Feature;

use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\FormDataKeyEvent;
use Akeeba\Component\SocialMagick\Administrator\Library\Plugin\Event\FormInjectedEvent;
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

		$formName = $this->getFirstNonEmptyEventResult(
			new FormInjectedEvent(arguments: ['form' => $form])
		);

		if (!$formName)
		{
			return;
		}

		$form->loadFile($formName, false);
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

		$data = (array) $data;

		// Make sure I have data to save
		if (!isset($data['socialmagick']))
		{
			return;
		}

		$key     = $this->getFirstNonEmptyEventResult(
			new FormDataKeyEvent(arguments: ['context' => $context])
		);

		if (!is_string($key) || empty($key))
		{
			return;
		}

		$params        = @json_decode($table->{$key}, true) ?? [];
		$table->{$key} = json_encode(array_merge($params, ['socialmagick' => $data['socialmagick']]));
	}

	/**
	 * Triggered when Joomla is loading content. Used to load the SocialMagick configuration.
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

		$key     = $this->getFirstNonEmptyEventResult(
			new FormDataKeyEvent(arguments: ['context' => $context])
		);

		if (!is_string($key) || empty($key) || !isset($data->{$key}) || !isset($data->{$key}['socialmagick']))
		{
			return;
		}

		$data->socialmagick = $data->{$key}['socialmagick'];
		unset ($data->{$key}['socialmagick']);
	}
}