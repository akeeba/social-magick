<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Model;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator\ImageGenerator;
use Akeeba\Component\SocialMagick\Administrator\Mixin\LegacyObjectTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ModelCopyTrait;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
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
	 * Saves the given data while processing custom parameters.
	 *
	 * Filters the input data to separate keys that are not part of the database table fields or
	 * Joomla-specific keys such as 'tags' and 'jcfields'. These keys are stored in a 'params' array.
	 * The filtered array is then passed to the parent save method for persistence.
	 *
	 * @param   array  $data  The data to be saved, including any custom parameters and standard table fields.
	 *
	 * @return  bool  True on successful save, false otherwise.
	 * @throws  Exception
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

	/**
	 * Returns the sample image data from the examples.json file.
	 *
	 * @return  array<array>
	 * @since   3.0.0
	 */
	public function getSampleImageData(): array
	{
		$source = JPATH_PUBLIC . '/media/com_socialmagick/images/examples/examples.json';
		$json   = @file_get_contents($source);

		if (empty($json))
		{
			return [];
		}

		$examples = @json_decode($json, true);

		if (empty($examples) || !is_array($examples))
		{
			return [];
		}

		return $examples;
	}

	/**
	 * Returns the credits for an example image.
	 *
	 * @param   string  $imageKey
	 *
	 * @return  array{source: string, credits: string, width: int, height: int}
	 * @since   3.0.0
	 */
	public function getSampleImageCredits(string $imageKey): array
	{
		$data = $this->getSampleImageData()[$imageKey] ?? null;

		return $data ?? [
			'source'  => '',
			'credits' => '',
			'width'   => 0,
			'height'  => 0,
		];
	}

	/**
	 * Returns a preview image for the given template options
	 *
	 * @param   array        $templateParams  The template parameters to use.
	 * @param   string|null  $text            The text to render.
	 * @param   string       $sampleImage     The sample image to use.
	 *
	 * @return  string|null  The URL to the preview image.
	 * @since   3.0.0
	 */
	public function getPreviewImage(array $templateParams = [], ?string $text = null, string $sampleImage = 'erensever', bool $textDebug = false): ?string
	{
		if (empty($templateParams))
		{
			return null;
		}

		$cParams        = clone ComponentHelper::getParams('com_socialmagick');
		$db             = $this->getDatabase();
		$cParams->set('textdebug', $textDebug ? '1' : '0');

		$imageGenerator = new ImageGenerator($cParams, $db);

		$text           ??= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_PREVIEW_TEXT');
		$extraImage     = JPATH_PUBLIC . '/media/com_socialmagick/images/examples/' . $sampleImage . '.jpg';

		if ($templateParams['image_source'] === 'none')
		{
			$extraImage = '';
		}
		elseif ($templateParams['image_source'] === 'static')
		{
			$extraImage = JPATH_ROOT . '/' .
				urldecode(HTMLHelper::cleanImageURL($templateParams['static_image'])?->url ?? '') ?: '';
		}

		try
		{
			$generatedImage = $imageGenerator->getOGImage($text, $templateParams, $extraImage);
		}
		catch (Exception)
		{
			$generatedImage = [];
		}

		return $generatedImage['imageURL'] ?? null;
	}

	/**
	 * Retrieves the preview image URL for a template by its ID.
	 *
	 * @param   int          $templateId   The ID of the template to retrieve the preview image for.
	 * @param   string|null  $text         Optional text to be included in the preview image.
	 * @param   string       $sampleImage  The sample image type to use if applicable.
	 *
	 * @return  string|null  The URL of the preview image, or null on failure.
	 * @since   3.0.0
	 */
	public function getPreviewImageById(int $templateId, ?string $text = null, string $sampleImage = 'erensever', bool $textDebug = false): ?string
	{
		try
		{
			$template = $this->getItem($templateId);
		}
		catch (\Throwable)
		{
			return null;
		}

		if (!$template || $template->id != $templateId)
		{
			return null;
		}

		return $this->getPreviewImage($template->params, $text, $sampleImage, $textDebug);
	}

	/**
	 * Loads the form data for template editing process.
	 *
	 * Data is loaded from the user state (in case the last save was denied), falling back to the DB item. The loaded
	 * data is normalised so that the `params` keys are moved as top-level form data. This allows us to use Joomla!
	 * Forms to edit the template without having to create dozens of table columns.
	 *
	 * @return  object  The data prepared for the form, as an object.
	 * @throws  Exception
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
}