<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Mixin;


use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Utilities\ArrayHelper;

/**
 * A Trait for MVC Conctrollers to implement copy (duplicate) functionality.
 *
 * @since  3.0.0
 */
trait ControllerCopyTrait
{
	use ControllerEvents;

	/**
	 * Method to copy (duplicate) a list of items.
	 *
	 * @return  void
	 *
	 * @since   7.0
	 */
	public function copy()
	{
		// Check for request forgeries
		$this->checkToken();

		// Get items to publish from the request.
		$cid = $this->input->get('cid', [], 'array');

		if (empty($cid))
		{
			$this->app->getLogger()->warning(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), ['category' => 'jerror']);

			$this->setRedirect(
				Route::_(
					'index.php?option=' . $this->option . '&view=' . $this->view_list
					. $this->getRedirectToListAppend(), false
				)
			);

			return;
		}

		// Get the model.
		$model = $this->getModel();

		// Make sure the item ids are integers
		$cid = ArrayHelper::toInteger($cid);

		$this->triggerEvent('onBeforeCopy', [&$cid]);

		// Publish the items.
		try
		{
			try
			{
				$copyMap = $model->copy($cid) ?: [];
				/** @noinspection PhpDeprecationInspection */
				$errors        = method_exists($model, 'getErrors') ? $model->getErrors() : [];
				$copiedSuccess = count($copyMap);
				$copiedFailed  = count($cid) - $copiedSuccess;
			}
			catch (\Exception $e)
			{
				$copyMap       = 0;
				$copiedSuccess = 0;
				$copiedFailed  = 0;
				$errors        = [$e->getMessage()];
			}

			$app           = Factory::getApplication();

			if (count($errors))
			{
				foreach ($errors as $error)
				{
					$app->enqueueMessage($error, 'error');
				}
			}

			if ($copiedFailed > 0)
			{
				$app->enqueueMessage(Text::plural($this->text_prefix . '_N_ITEMS_FAILED_COPY', $copiedFailed), 'error');
			}

			if ($copiedSuccess > 0)
			{
				$this->setMessage(Text::plural($this->text_prefix . '_N_ITEMS_COPIED', \count($cid)));
			}
		}
		catch (\Exception $e)
		{
			$this->setMessage($e->getMessage(), 'error');
		}

		$this->triggerEvent('onAfterCopy', [&$cid, &$copyMap]);

		$this->setRedirect(
			Route::_(
				'index.php?option=' . $this->option . '&view=' . $this->view_list
				. $this->getRedirectToListAppend(), false
			)
		);
	}

}