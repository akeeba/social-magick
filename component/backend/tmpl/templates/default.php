<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

/** @var \Akeeba\Component\SocialMagick\Administrator\View\Templates\HtmlView $this */

$this->behaviourMultiselect();

/**
 * HTMLHelper's `behavior.multiselect` is deprecated in Joomla 6.
 *
 * See Joomla PR 45925.
 */
call_user_func(function(string $formName = 'adminForm') {
	if (version_compare(JVERSION, '5.999.999', 'lt'))
	{
		HTMLHelper::_('behavior.multiselect');

		return;
	}

	$doc       = \Joomla\CMS\Factory::getApplication()->getDocument();
	$doc->addScriptOptions('js-multiselect', ['formName' => $formName]);
	$doc->getWebAssetManager()->useScript('multiselect');
});

$this->tableColumnsAutohide();
$this->tableColumnsMultiselect('#articleList');

$app               = Factory::getApplication();
$user              = $app->getIdentity();
$userId            = $user->id;
$listOrder         = $this->escape($this->state->get('list.ordering'));
$listDirn          = $this->escape($this->state->get('list.direction'));
$nullDate          = Factory::getContainer()->get(DatabaseInterface::class)->getNullDate();
$hasCategoryFilter = !empty($this->getModel()->getState('filter.category_id'));
$saveOrder         = $listOrder == 'ordering';
$baseUri           = Uri::root();

if ($saveOrder && !empty($this->items))
{
	$saveOrderingUrl = 'index.php?option=com_socialmagick&task=templates.saveOrderAjax&tmpl=component&' . $app->getFormToken() . '=1';
	HTMLHelper::_('draggablelist.draggable');
}

$i = 0;

?>

<div class="alert alert-info d-none" id="socialMagickTotalImageSizeNotice">
	<?= Text::_('COM_SOCIALMAGICK_TEMPLATE_LBL_IMAGESIZE'); ?>
	<span id="socialMagickTotalImageSize" class="text-info-emphasis fw-semibold"></span>
</div>

<form action="<?= Route::_('index.php?option=com_socialmagick&view=templates'); ?>"
	  method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?= LayoutHelper::render('joomla.searchtools.default', ['view' => $this]) ?>
				<?= $this->loadAnyTemplate('templates/plugin_warning') ?>
				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info">
						<span class="icon-info-circle" aria-hidden="true"></span><span
								class="visually-hidden"><?= Text::_('INFO'); ?></span>
						<?= Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>
					<table class="table" id="articleList">
						<caption class="visually-hidden">
							<?= Text::_('COM_SOCIALMAGICK_TEMPLATES_TABLE_CAPTION'); ?>, <span
									id="orderedBy"><?= Text::_('JGLOBAL_SORTED_BY'); ?> </span>, <span
									id="filteredBy"><?= Text::_('JGLOBAL_FILTERED_BY'); ?></span>
						</caption>
						<thead>
						<tr>
							<td class="w-1 text-center">
								<?= HTMLHelper::_('grid.checkall'); ?>
							</td>

							<th scope="col">
								<?= HTMLHelper::_('searchtools.sort', 'COM_SOCIALMAGICK_TEMPLATES_FIELD_TITLE', 'title', $listDirn, $listOrder); ?>
							</th>

							<th scope="col" class="w-1 d-none d-md-table-cell">
								<?= HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'id', $listDirn, $listOrder); ?>
							</th>
						</tr>
						</thead>
						<tbody>
						<?php foreach ($this->items as $item) : ?>
							<?php
							$canEdit    = $user->authorise('core.edit', 'com_socialmagick');
							?>
							<tr class="row<?= $i++ % 2; ?>">
								<td class="text-center">
									<?= HTMLHelper::_('grid.id', $i, $item->id, !(empty($item->checked_out_time) || ($item->checked_out_time === $nullDate)), 'cid', 'cb', $item->title); ?>
								</td>

								<td>
									<?php if ($canEdit): ?>
										<a href="<?= Route::_('index.php?option=com_socialmagick&task=template.edit&id=' . (int) $item->id); ?>"
										   title="<?= Text::_('JACTION_EDIT'); ?>">
											<?= $this->escape($item->title); ?>
										</a>
									<?php else: ?>
										<?= $this->escape($item->title); ?>
									<?php endif ?>
								</td>

								<td class="w-1 d-none d-md-table-cell">
									<?= $item->id ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>

					<?php // Load the pagination. ?>
					<?= $this->pagination->getListFooter(); ?>
				<?php endif; ?>

				<input type="hidden" name="task" value=""> <input type="hidden" name="boxchecked" value="0">
				<?= HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>
