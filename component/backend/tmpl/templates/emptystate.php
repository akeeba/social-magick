<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;

$displayData = [
	'textPrefix' => 'COM_SOCIALMAGICK_TEMPLATES',
	'formURL'    => 'index.php?option=com_socialmagick&view=templates',
	//'helpURL'    => 'https://www.example.com',
	'icon'       => 'fa fa-paint-roller',
];

$user = Factory::getApplication()->getIdentity();

if ($user->authorise('core.create', 'com_socialmagick'))
{
	$displayData['createURL'] = 'index.php?option=com_socialmagick&task=template.add';
}

echo LayoutHelper::render('joomla.content.emptystate', $displayData);
