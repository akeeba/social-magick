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

// Enable keep-alive and form validation JavaScript.
$wa = $this->getDocument()->getWebAssetManager()
		->useScript('keepalive')
		->useScript('form.validate');

?>

<form action="<?= Route::_('index.php?option=com_socialmagick&view=template') ?>"
	  method="post" name="adminForm" id="item-form" class="form-validate"
	  aria-label="<?= Text::_('COM_SOCIALMAGICK_TITLE_TEMPLATE_' . ((int) $this->item->id === 0 ? 'ADD' : 'EDIT'), true) ?>"
>
	<?php // --- Hidden fields ?>
	<input type="hidden" name="task" id="task" value="save" />
	<input type="hidden" name="id" id="id" value="<?= (int) ($this->item->id ?? 0) ?>" />
	<?= HTMLHelper::_('form.token') ?>

	<?php // --- Title (rendered Joomla-style) ?>
	<div class="row title-alias form-vertical mb-3">
		<div class="col-12">
			<?= $this->form->renderField('title') ?>
		</div>
	</div>

	<?= $this->loadTemplate('preview') ?>

	<?php // --- Main fields ?>
	<div class="main-card">
		<?= HTMLHelper::_('uitab.startTabSet', 'com_socialmagick_template', ['active' => 'basic', 'recall' => true, 'breakpoint' => 768]); ?>

		<?php // --- Tab: Basic ?>
		<?= HTMLHelper::_('uitab.addTab', 'com_socialmagick_template', 'basic', Text::_('COM_SOCIALMAGICK_TEMPLATES_FIELDSET_BASIC')); ?>
		<?= $this->loadTemplate('basic') ?>
		<?= HTMLHelper::_('uitab.endTab'); ?>

		<?php foreach ($this->form->getFieldsets() as $fieldset): ?>
			<?php if (in_array($fieldset->name, ['basic'])): continue; endif; ?>
			<?= HTMLHelper::_('uitab.addTab', 'com_socialmagick_template', $fieldset->name, Text::_($fieldset->label)); ?>

			<?php
			// Load an optional template
			try { echo $this->loadAnyTemplate('default_' . $fieldset->name, false); } catch (Throwable) {}
			?>

			<?php if ($fieldset->description): ?>
				<div class="alert alert-info">
					<?= Text::_($fieldset->description) ?>
				</div>
			<?php endif; ?>

			<?= $this->form->renderFieldset($fieldset->name) ?>
			<?= HTMLHelper::_('uitab.endTab'); ?>
		<?php endforeach; ?>

		<?= HTMLHelper::_('uitab.endTabSet'); ?>
	</div>

</form>
