<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Language\Text;

?>

<div class="card card-body mb-3">
	<h3>Composing the OpenGraph image</h3>

	<div class="row">
		<div class="col-12 col-md-4">
			<?= Joomla\CMS\HTML\HTMLHelper::image( 'com_socialmagick/layering.svg', '', ['class' => 'img-fluid'], true, 0) ?>
		</div>
		<div class="col-12 col-md-8">
			<p>The OpenGraph image is composed by combining layers. Think of it like stacking transparencies on top of each other. The five layers, ordered from the bottom (away from you) to the top (towards you):</p>
			<ol>
				<li>
					<strong>Underlay</strong>. The Extra Image (article or custom field image), if you have selected the Underlay display option for it.
				</li>
				<li>
					<strong>Background Colour</strong>. The Background Colour you have selected. You can use non-opaque colours by adjusting the Background Colour Opacity.
				</li>
				<li>
					<strong>Base Image</strong>. The main image used in your template. If you want the Underlay and/or Colour Background to be visible, you should use an image with non-opaque regions.
				</li>
				<li>
					<strong>Overlay</strong>. The extra image (article or custom field image), if you have selected the Overlay display option for it.
				</li>
				<li>
					<strong>Text</strong>. Any text you have selected to display.
				</li>
			</ol>
		</div>
	</div>
</div>
