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

	<?php // --- Preview ?>
	<div class="card border-info mb-3">
		<h4 class="card-header bg-info text-white">
			<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_PREVIEW') ?>
		</h4>
		<p class="card-text px-3 py-1 mb-0 small">
			<span class="fa fa-fw fa-info-circle pe-1" aria-hidden="true"></span>
			<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_PREVIEW_HELP') ?>
		</p>
		<div class="card-body">
			<div class="row">
				<div class="col-12 col-md-6">

					<div class="mb-3">
						<label class="form-label" for="socialMagickPreviewSample">
							<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_IMAGE') ?>
						</label>
						<div class="d-flex gap-3 flex-wrap mb-1">
							<?php foreach ($this->sampleImages as $key => $details): ?>
							<?php Text::script('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_IMAGE_OPT_' . $key) ?>
							<input type="radio" class="btn-check socialMagickPreviewSample"
								   data-samplekey="<?= $key ?>"
								   name="preview[sampleImage]"
								   id="socialMagickPreviewSample<?= ucfirst($key) ?>"
								   value="<?= $key ?>" autocomplete="off"
									<?= $key === $this->sampleImage ? 'checked' : '' ?>
							>
							<label class="btn btn-outline-info d-flex flex-column align-items-center p-2 rounded-3" for="socialMagickPreviewSample<?= ucfirst($key) ?>">
								<!-- TODO I need to show the image credits. JavaScript? -->
								<img src="<?= \Joomla\CMS\Uri\Uri::root(true) ?>/media/com_socialmagick/images/examples/<?= $key ?>-128.jpg"
									 width="64" height="62"
									 alt="<?= ucfirst($key) ?>"
								>
								<span class="small mt-1"><?= ucfirst($key) ?></span>
							</label>
							<?php endforeach; ?>
						</div>
						<div class="mt-2">
							<div class="text-primary">
								<span class="fa fa-fw fa-comments me-1" aria-hidden="true"></span>
								<span class="visually-hidden"><?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_DESCRIPTION') ?></span>
								<span id="socialMagicSampleDescription">
								<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_IMAGE_OPT_' . $this->sampleImage) ?>
							</span>
							</div>
							<div class="text-secondary">
								<span class="fa fa-fw fa-copyright me-1"></span>
								<span class="visually-hidden"><?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_CREDITS') ?></span>
								<a id="socialMagicSampleCredits"
								   href="<?= $this->sampleImages[$this->sampleImage]['source'] ?>"
								   class="link-secondary"
								   target="_blank">
									<?= $this->sampleImages[$this->sampleImage]['credits'] ?>
								</a>
							</div>
							<div class="text-secondary">
								<span class="fa fa-fw fa-maximize me-1"></span>
								<span class="visually-hidden"><?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_DIMENSIONS') ?></span>
								<span id="socialMagicSampleWidth"><?= $this->sampleImages[$this->sampleImage]['width'] ?></span> x <span id="socialMagicSampleHeight"><?= $this->sampleImages[$this->sampleImage]['height'] ?></span> px
							</div>
						</div>
					</div>

					<div class="mb-3">
						<label class="form-label" for="socialMagickPreviewText">
							<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_TEXT') ?>
						</label>
						<input type="text" name="preview[text]" id="socialMagickPreviewText" class="form-control"
							   value="<?= $this->escape($this->sampleText) ?>">
					</div>

					<div class="mb-3">
						<button type="button" class="btn btn-outline-success"
								id="socialmagic_preview_refresh"
						>
							<span class="fa fa-bolt"></span>
							<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_PREVIEW_REFRESH') ?>
						</button>
					</div>

					<!-- TODO Preview options -->
				</div>
				<div class="col-12 col-md-6">
					<div class="text-center mb-2">
						<img id="socialmagic_preview_img" class="img-fluid"
							 src="<?= $this->previewImage ?? '' ?>" alt="">
					</div>
					<div class="d-flex align-items-baseline justify-content-between">
						<a id="socialmagic_preview_link" class="btn btn-outline-info btn-sm"
						   href="<?= $this->previewImage ?? '' ?>" target="_blank"
						>
							<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_PREVIEW_OPEN') ?>
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>

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
