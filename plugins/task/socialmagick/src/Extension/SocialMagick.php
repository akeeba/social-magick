<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\Task\SocialMagick\Extension;

use Akeeba\Component\SocialMagick\Administrator\Model\ImagesModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Task\Task;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;

class SocialMagick extends CMSPlugin implements SubscriberInterface
{
	use TaskPluginTrait;

	/** @inheritDoc */
	private const TASKS_MAP = [
		'socialmagick.removeoldimages' => [
			'langConstPrefix' => 'PLG_TASK_SOCIALMAGICK_TASK_REMOVEOLDIMAGES',
			'form'            => 'removeoldimages',
			'method'          => 'removeOldImages',
		],
	];

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  3.0.0
	 */
	protected $autoloadLanguage = true;

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		// This task is disabled if the Akeeba Backup component is not installed or has been unpublished
		if (!ComponentHelper::isEnabled('com_socialmagick'))
		{
			return [];
		}

		return [
			'onTaskOptionsList'    => 'advertiseRoutines',
			'onExecuteTask'        => 'standardRoutineHandler',
			'onContentPrepareForm' => 'enhanceTaskItemForm',
		];
	}

	/**
	 * Handles the `socialmagick.removeoldimages` scheduled task.
	 *
	 * @param   ExecuteTaskEvent  $event
	 *
	 * @return  int
	 * @throws  \Exception
	 * @since   3.0.0
	 */
	private function removeOldImages(ExecuteTaskEvent $event): int
	{
		// Get some basic information about the task at hand.
		/** @var Task $task */
		$params    = $event->getArgument('params') ?: (new \stdClass());
		$olderThan = $params->olderthan ?? 'P1W';

		/** @var MVCFactoryInterface $factory */
		$factory = $this->getApplication()->bootComponent('com_socialmagick')->getMvcFactory();
		/** @var ImagesModel $model */
		$model = $factory->createModel('Images', 'Administrator', ['ignore_request' => true]);

		$model->removeOldImages($olderThan);

		return Status::OK;
	}
}