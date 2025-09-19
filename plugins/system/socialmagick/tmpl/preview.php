<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * @var ?string $imageLink
 * @var ?string $title
 * @var ?string $description
 */

$urlToDisplay = function (?string $url = null)
{
	$url ??= Uri::current();
	$uri = Uri::getInstance($url);

	$uriParts = explode('/', $uri->toString(['scheme', 'user', 'pass', 'host', 'port', 'path']));
	$uriParts = array_map(fn($x) => htmlentities($x, encoding: 'UTF-8'), $uriParts);
	$urlForDisplay = implode('/<wbr>', $uriParts);

	if ($uri->getQuery())
	{
		$queryParts = $uri->getQuery(true);
		$query = http_build_query($queryParts, '', '&<wbr>');
		$urlForDisplay .= '?<wbr>' . rtrim($query, '=');
	}

	return $urlForDisplay;
};


?>
<div id="plg_system_socialmagic_btn" title="<?= Text::_('PLG_SYSTEM_SOCIALMAGIC_PREVIEW_OPENGRAPH') ?>">
	<span class="fa fas fa-image" aria-hidden="true"></span>
	<span class="plg_system_socialmagic_visually_hidden"><?= Text::_('PLG_SYSTEM_SOCIALMAGIC_PREVIEW_OPENGRAPH') ?></span>
</div>

<div id="plg_system_socialmagic_preview">
	<div id="plg_system_socialmagic_modal_backdrop" class="plg_system_socialmagic_modal_backdrop">
		<div class="plg_system_socialmagic_modal_dialog">
			<div class="plg_system_socialmagic_modal_header">
				<h3 class="plg_system_socialmagic_modal_title">
					<?= Text::_('PLG_SYSTEM_SOCIALMAGIC_OPENGRAPH_PREVIEW') ?>
				</h3>
				<button class="plg_system_socialmagic_modal_close_btn" aria-label="<?= Text::_('JCLOSE') ?>">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="plg_system_socialmagic_modal_body">

				<a class="link-preview" href="<?= Uri::current() ?>" target="_blank">
					<div class="thumb" aria-hidden="true">
						<?php if (!empty($imageLink)): ?>
						<img src="<?= htmlentities($imageLink, encoding: 'utf8') ?>" alt="">
						<?php endif; ?>
					</div>
					<div class="meta">
						<span class="domain"><?= Uri::getInstance()->getHost() ?></span>
						<h4 class="title"><?= htmlentities($title, encoding: 'utf8') ?></h4>
						<?php if (!empty($description)): ?>
						<p class="excerpt"><?= htmlentities($description, encoding: 'utf8') ?></p>
						<?php endif; ?>
					</div>
				</a>

				<table class="table table-striped">
					<tbody>
					<tr>
						<th>
							<?= Text::_('PLG_SYSTEM_SOCIALMAGIC_PREVIEW_LBL_IMAGE') ?>
						</th>
						<td>
							<a href="<?= htmlentities($imageLink, encoding: 'utf8') ?>" target="_blank">
								<?= $urlToDisplay($imageLink) ?>
							</a>
						</td>
					</tr>
					<tr>
						<th>
							<?= Text::_('PLG_SYSTEM_SOCIALMAGIC_PREVIEW_LBL_URL') ?>
						</th>
						<td>
							<?= $urlToDisplay() ?>
						</td>
					</tr>
					<tr>
						<th>
							<?= Text::_('PLG_SYSTEM_SOCIALMAGIC_PREVIEW_LBL_TITLE') ?>
						</th>
						<td>
							<?= htmlentities($title, encoding: 'utf8') ?>
						</td>
					</tr>
					<tr>
						<th>
							<?= Text::_('PLG_SYSTEM_SOCIALMAGIC_PREVIEW_LBL_DESCRIPTION') ?>
						</th>
						<td>
							<?= htmlentities($description, encoding: 'utf8') ?>
						</td>
					</tr>
					</tbody>
				</table>

			</div>
		</div>
	</div>
</div>