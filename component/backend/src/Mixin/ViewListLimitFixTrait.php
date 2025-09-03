<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Mixin;

\defined('_JEXEC') || die;

use Joomla\CMS\MVC\Model\ListModel;

/**
 * A trait for MVC Views to fix the degenerate case of ending up with a list start past the list end.
 *
 * This happens, for example, when you delete all items in the last page of a list view. Suddenly, your list start is
 * one item ahead of the list end. At best, you get a confusing page which misleads you into thinking you deleted
 * everything. At worst, you get an essentially unrecoverable PHP error. So, yeah, working around it is the way to go.
 */
trait ViewListLimitFixTrait
{
	public function fixListLimitPastTotal(ListModel $model, ?callable $getTotal = null): void
	{
		$start = $model->getState('list.start');
		$limit = $model->getState('list.limit', 10);
		$total = call_user_func($getTotal ?? fn() => $model->getTotal());

		if ($start >= $total)
		{
			$pages = $limit > 0 ? ceil($total / $limit) : 1;
			$start = max(0, $limit * ($pages - 1));

			$model->setState('list.start', $start);
		}
	}
}