<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Language\Text;

/** @var $this \Akeeba\Component\SocialMagick\Administrator\View\Template\HtmlView */

?>
<div class="card border-info mb-3">
	<div class="card-header bg-info d-flex align-items-center">
		<h4 class="flex-grow-1 text-white m-0 p-0">
			<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_PREVIEW') ?>
		</h4>
		<button type="button" class="btn btn-outline-light btn-sm"
		        data-bs-toggle="collapse" data-bs-target="#socialMagickPreviewContainer"
		        aria-expanded="true" aria-controls="socialMagickPreviewContainer"
		        title="<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_PREVIEW_SHOW_HIDE') ?>"
		>
			<span class="fa fa-fw fa-arrow-down-up-across-line"></span>
			<span class="visually-hidden"><?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_PREVIEW_SHOW_HIDE') ?></span>
		</button>
	</div>
	<div class="card-body collapse show" id="socialMagickPreviewContainer">
		<div class="alert alert-info small mt-0 mb-3">
			<span class="fa fa-fw fa-info-circle pe-1" aria-hidden="true"></span>
			<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_PREVIEW_HELP') ?>
		</div>
		<div class="row">
			<div class="col-12 col-md-6">

				<div class="mb-3">
					<label class="form-label fw-semibold" for="socialMagickPreviewSample">
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
					<div class="mt-2 d-flex gap-2 justify-content-between">
						<div class="text-primary">
							<span class="fa fa-fw fa-comments me-1 text-info" aria-hidden="true"></span>
							<span class="visually-hidden"><?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_DESCRIPTION') ?></span>
							<span id="socialMagicSampleDescription">
								<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_IMAGE_OPT_' . $this->sampleImage) ?>
							</span>
						</div>
						<div class="text-secondary">
							<span class="fa fa-fw fa-copyright me-1 text-info" aria-hidden="true"></span>
							<span class="visually-hidden"><?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_CREDITS') ?></span>
							<a id="socialMagicSampleCredits"
							   href="<?= $this->sampleImages[$this->sampleImage]['source'] ?>"
							   class="link-secondary"
							   target="_blank">
								<?= $this->sampleImages[$this->sampleImage]['credits'] ?>
							</a>
						</div>
						<div class="text-secondary">
							<span class="fa fa-fw fa-maximize me-1 text-info" aria-hidden="true"></span>
							<span class="visually-hidden"><?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_DIMENSIONS') ?></span>
							<span id="socialMagicSampleWidth"><?= $this->sampleImages[$this->sampleImage]['width'] ?></span> x <span id="socialMagicSampleHeight"><?= $this->sampleImages[$this->sampleImage]['height'] ?></span> px
						</div>
					</div>
				</div>

				<div class="mb-3">
					<label class="form-label fw-semibold" for="socialMagickPreviewText">
						<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_SAMPLE_TEXT') ?>
					</label>
					<input type="text" name="preview[text]" id="socialMagickPreviewText" class="form-control"
					       value="<?= $this->escape($this->sampleText) ?>">
				</div>

				<div class="form-check mb-3">
					<input name="preview[textdebug]" id="socialMagickPreviewTextDebug" value="1"
					       class="form-check-input" type="checkbox">
					<label class="form-check-label" for="socialMagickPreviewTextDebug">
						<?= Text::_('COM_SOCIALMAGICK_CONFIG_TEXTDEBUG_LABEL') ?>
					</label>
				</div>

				<div class="mb-3">
					<button type="button" class="btn btn-outline-success"
					        id="socialmagic_preview_refresh"
					>
						<span class="fa fa-bolt"></span>
						<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_PREVIEW_REFRESH') ?>
					</button>
				</div>

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


