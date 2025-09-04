<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var $this \Akeeba\Component\SocialMagick\Administrator\View\Template\HtmlView */

echo $this->form->renderField('enabled');
echo $this->form->renderField('template-w');
echo $this->form->renderField('template-h');
