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
use Akeeba\Component\SocialMagick\Administrator\Model\ImagesModel;
use Akeeba\Component\SocialMagick\Administrator\Model\TemplateModel;
use Exception;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;

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
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function regeneratePreview()
	{
		$this->checkToken();

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
		catch (Exception)
		{
			$image = 'NULL';
		}

		echo <<< JSON
{"image": $image}
JSON;

	}

	/**
	 * Removes all generated OpenGraph images from the cache
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function purge()
	{
		$this->checkToken();

		if (!$this->app?->getIdentity()?->authorise('core.admin'))
		{
			throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var ImagesModel $model */
		$model  = $this->getModel('Images');
		$purged = $model->removeAllImages();

		$type    = $purged ? 'success' : 'error';
		$message = $purged
			? 'COM_SOCIALMAGICK_TEMPLATE_LBL_PURGED'
			: 'COM_SOCIALMAGICK_TEMPLATE_LBL_NOT_PURGED';

		$this->setRedirect(
			Route::_(
				'index.php?option=' . $this->option . '&view=' . $this->view_list
				. $this->getRedirectToListAppend(),
				false
			),
			Text::_($message),
			$type
		);
	}

	/**
	 * Returns the total size of the generated images folder in bytes, formatted as JSON.
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function imagestotalsize(): void
	{
		$this->checkToken();

		if (!$this->app?->getIdentity()?->authorise('core.admin'))
		{
			throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var ImagesModel $model */
		$model = $this->getModel('Images');
		$total = $model->getTotalSize();

		echo <<< JSON
{"total": $total}
JSON;

	}
}