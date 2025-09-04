<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var  \Akeeba\Component\SocialMagick\Administrator\View\Templates\HtmlView $this */

$returnUrl = base64_encode('index.php?option=com_socialmagick&view=' . $this->getName());

?>

<?php if(!$this->pluginExists): ?>
	<div class="alert alert-danger small">
		<span class="fa fa-circle-exclamation" aria-hidden="true"></span>
		<?=Text::_('COM_SOCIALMAGICK_COMMON_ERR_PLUGIN_NOT_EXIST'); ?>
	</div>
<?php elseif(!$this->pluginActive): ?>
	<div class="alert alert-danger small">
		<p>
			<span class="fa fa-circle-exclamation" aria-hidden="true"></span>
			<?=Text::_('COM_SOCIALMAGICK_COMMON_ERR_PLUGIN_INACTIVE'); ?>
		</p>
	</div>
<?php endif; ?>
