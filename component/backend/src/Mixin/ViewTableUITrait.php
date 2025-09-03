<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Mixin;

defined('_JEXEC') || die;

/**
 * A trait for MVC views to enable Joomla's column show/hide feature in list views.
 *
 * @since  3.0.0
 */
trait ViewTableUITrait
{
	public function tableColumnsAutohide(): void
	{
		try
		{
			$this->getDocument()->getWebAssetManager()->useScript('table.columns');
		}
		catch (\Throwable $e)
		{
			// This might indeed fail on old Joomla! versions.
		}
	}

	public function tableColumnsMultiselect(?string $tableSelector = null): void
	{
		try
		{
			$this->getDocument()->getWebAssetManager()->useScript('multiselect');

			if (empty($tableSelector))
			{
				return;
			}

			$this->getDocument()->addScriptOptions('js-multiselect', [
				'formName' => $tableSelector
			]);
		}
		catch (\Throwable $e)
		{
			// This might indeed fail on old Joomla! versions.
		}
	}
}