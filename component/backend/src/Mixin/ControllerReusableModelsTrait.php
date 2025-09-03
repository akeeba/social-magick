<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Mixin;

use Exception;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\MVC\View\ViewInterface;

defined('_JEXEC') || die;

/**
 * A trait for MVC Controllers which turns getModel into a Singleton factory.
 *
 * By default, Joomla's getModel() is a pure factory, returning a new model object instance every time you call it. This
 * is really annoying when you want to just set state when using the default display() method. Because it always returns
 * a new instance you have to duplicate the core code of the `display()` metehod, and keep track of its changes across
 * new Joomla versions. By the time you go through all five minor versions of a major Joomla version you have a full
 * head of gray hair.
 *
 * Screw that noise!
 *
 * Turning it into a Singleton factory we can be sure that we can just get our model, set state, and not care about
 * what display() does under the hood. The only caveat is that if you are (idiotically!) using getModel() to get a
 * fresh model instance from outside the controller (why, you bloody pervert?!) you will have to use `clone`. Then
 * again, if you do any of that you are a moron who deserves all the pain and suffering you will get, especially because
 * since Joomla 4.0 you can use the MVCFactory of the component's extension object to get fresh MVC instances.
 *
 * @since 3.0.0.
 */
trait ControllerReusableModelsTrait
{
	static $_models = [];

	public function getModel($name = '', $prefix = '', $config = [])
	{
		if (empty($name))
		{
			$name = ucfirst($this->input->get('view', $this->default_view));
		}

		$prefix = ucfirst($prefix ?: $this->app->getName());

		$hash = hash('md5', strtolower($name . $prefix));

		if (isset(self::$_models[$hash]))
		{
			return self::$_models[$hash];
		}

		self::$_models[$hash] = parent::getModel($name, $prefix, $config);

		return self::$_models[$hash];
	}

	/**
	 * @param   string  $name
	 * @param   string  $type
	 * @param   string  $prefix
	 * @param   array   $config
	 *
	 * @return ViewInterface|HtmlView
	 * @throws Exception
	 */
	public function getView($name = '', $type = '', $prefix = '', $config = [])
	{
		$document = $this->app->getDocument();

		if (empty($name))
		{
			$name = $this->input->get('view', $this->default_view);
		}

		if (empty($type))
		{
			$type = $document->getType();
		}

		if (empty($config))
		{
			$viewLayout = $this->input->get('layout', 'default', 'string');
			$config     = ['base_path' => $this->basePath, 'layout' => $viewLayout];
		}

		$hadView = isset(self::$views)
			&& isset(self::$views[$name])
			&& isset(self::$views[$name][$type])
			&& isset(self::$views[$name][$type][$prefix])
			&& !empty(self::$views[$name][$type][$prefix]);

		$view = parent::getView($name, $type, $prefix, $config);

		if (!$hadView)
		{
			$modelSide = ucfirst($this->app->getName());

			// Get/Create the model
			if ($model = $this->getModel($name, $modelSide, ['base_path' => $this->basePath]))
			{
				// Push the model into the view (as default)
				$view->setModel($model, true);
			}

			$view->setDocument($document);
		}

		return $view;
	}
}
