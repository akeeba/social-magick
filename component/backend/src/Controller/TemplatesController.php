<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Controller;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Mixin\ControllerCopyTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ControllerEvents;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ControllerRegisterTasksTrait;
use Akeeba\Component\SocialMagick\Administrator\Mixin\ControllerReusableModelsTrait;
use Akeeba\Component\SocialMagick\Administrator\Model\TemplateModel;
use Joomla\CMS\MVC\Controller\AdminController;

/**
 * MVC Controller for the Templates list view.
 *
 * @since 3.0.0
 */
class TemplatesController extends AdminController
{
	use ControllerEvents;
	use ControllerCopyTrait;
	use ControllerReusableModelsTrait;
	use ControllerRegisterTasksTrait;

	protected $text_prefix = 'COM_SOCIALMAGICK_TEMPLATES';

	public function getModel($name = 'Template', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Regenerates a preview image for a SocialMagick template
	 *
	 * @return  void
	 * @throws  \Exception
	 * @since   3.0.0
	 */
	public function regeneratePreview()
	{
		$previewData     = $this->input->get('preview', [], 'array');
		$templateOptions = $this->input->get('jform', [], 'array');
		$text            = $previewData['text'] ?? null;
		$sampleImage     = $previewData['sampleImage'] ?? null;
		$textDebug       = $previewData['textdebug'] ?? false;

		/** @var TemplateModel $model */
		$model = $this->getModel();
		try
		{
			$image = json_encode($model->getPreviewImage($templateOptions, $text, $sampleImage, $textDebug));
		}
		catch (\Exception)
		{
			$image = 'NULL';
		}

		echo <<< JSON
{"image": $image}
JSON;

	}

}