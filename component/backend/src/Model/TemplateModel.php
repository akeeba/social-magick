<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Model;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Mixin\LegacyObjectTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ModelCopyTrait;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\AdminModel;

/**
 * An MVC Model for the template form view.
 *
 * @since   3.0.0
 */
class TemplateModel extends AdminModel
{
	use ModelCopyTrait;
	use LegacyObjectTrait;

	public function __construct($config = [], ?MVCFactoryInterface $factory = null, ?FormFactoryInterface $formFactory = null)
	{
		parent::__construct($config, $factory, $formFactory);

		$this->_parent_table = '';
	}

	/**
	 * @inheritDoc
	 */
	public function getForm($data = [], $loadData = true)
	{
		$form = $this->loadForm(
			'com_socialmagick.template',
			'template',
			[
				'control'   => 'jform',
				'load_data' => $loadData,
			]
		) ?: false;

		if (empty($form))
		{
			return false;
		}

		return $form;
	}

	/**
	 * Loads the form data for template editing process.
	 *
	 * Data is loaded from the user state (in case the last save was denied), falling back to the DB item. The loaded
	 * data is normalised so that the `params` keys are moved as top-level form data. This allows us to use Joomla!
	 * Forms to edit the template without having to create dozens of table columns.
	 *
	 * @return  object  The data prepared for the form, as an object.
	 * @throws  \Exception
	 * @since   3.0.0
	 */
	protected function loadFormData()
	{
		/** @var CMSApplication $app */
		$app  = Factory::getApplication();
		$data = $app->getUserState('com_socialmagick.edit.template.data', []);
		$pk   = (int) $this->getState($this->getName() . '.id');
		$item = ($pk ? (object) $this->normalizePossibleCMSObject($this->getItem()) : false) ?: [];
		$data = $data ?: $item;

		// This should not be necessary, but I am too old to be naïve.
		if (!is_object($data))
		{
			$data = (object) $data;
		}

		// Move the params as top-level form data, so our JForm displays it properly.
		if (isset($data->params))
		{
			$params = [];

			if (is_string($data->params))
			{
				$params = json_decode($data->params, true);
			}
			elseif (is_array($data->params))
			{
				$params = $data->params;
			}
			elseif (is_object($data->params))
			{
				$params = (array) $data->params;
			}

			foreach ($params as $key => $value)
			{
				$data->$key = $value;
			}

			unset($data->params);
		}

		// Now that we have actual form data, tell Joomla to do call its plugins
		$this->preprocessData('com_socialmagick.template', $data);

		return $data;
	}

	/**
	 * Saves the given data while processing custom parameters.
	 *
	 * Filters the input data to separate keys that are not part of the database table fields or
	 * Joomla-specific keys such as 'tags' and 'jcfields'. These keys are stored in a 'params' array.
	 * The filtered array is then passed to the parent save method for persistence.
	 *
	 * @param   array  $data  The data to be saved, including any custom parameters and standard table fields.
	 *
	 * @return  bool  True on successful save, false otherwise.
	 * @throws  \Exception
	 * @since   3.0.0
	 */
	public function save($data)
	{
		// Assume all form keys which are neither table field names, nor Joomla-specific are param keys.
		$paramKeys = array_filter(
			array_keys($data),
			fn($key) => !$this->getTable()->hasField($key) && !in_array($key, ['tags', 'jcfields'])
		);

		// Assemble an array for the params key
		$data['params'] = array_filter($data, fn($key) => in_array($key, $paramKeys), ARRAY_FILTER_USE_KEY);

		// Delete the data keys we put into the params array
		$data = array_filter($data, fn($key) => !in_array($key, $paramKeys), ARRAY_FILTER_USE_KEY);

		return parent::save($data);
	}
}