<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Mixin;

defined('_JEXEC') || die;

use ReflectionMethod;
use ReflectionObject;

/**
 * A trait for MVC Controllers which automatically registers tasks based on the availability of public methods.
 *
 * @since  3.0.0
 */
trait ControllerRegisterTasksTrait
{
	/**
	 * Automatically register controller tasks.
	 *
	 * Only public, user defined methods whose names do not start with 'onBefore', 'onAfter' or '_' are registered as
	 * controller tasks.
	 *
	 * @param   string|null  $defaultTask  The default task. NULL to use 'main' or 'default', whichever exists.
	 */
	protected function registerControllerTasks(?string $defaultTask = null)
	{
		$defaultTask = $defaultTask ?? (method_exists($this, 'main') ? 'main' : 'display');

		$this->registerDefaultTask($defaultTask);

		$refObj = new ReflectionObject($this);

		/** @var ReflectionMethod $refMethod */
		foreach ($refObj->getMethods(ReflectionMethod::IS_PUBLIC) as $refMethod)
		{
			if (
				!$refMethod->isUserDefined() ||
				$refMethod->isStatic() || $refMethod->isAbstract() || $refMethod->isClosure() ||
				$refMethod->isConstructor() || $refMethod->isDestructor()

			)
			{
				continue;
			}

			$method = $refMethod->getName();

			if (substr($method, 0, 1) == '_')
			{
				continue;
			}

			if (substr($method, 0, 8) == 'onBefore')
			{
				continue;
			}

			if (substr($method, 0, 7) == 'onAfter')
			{
				continue;
			}

			$this->registerTask($method, $method);
		}
	}
}