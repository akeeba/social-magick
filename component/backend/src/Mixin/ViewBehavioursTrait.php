<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Mixin;

\defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

trait ViewBehavioursTrait
{
	/**
	 * Implements `behavior.multiselect` across different Joomla versions.
	 *
	 * Caters for the fact that HTMLHelper's `behavior.*` calls have been deprecated and/or removed in Joomla 6.
	 *
	 * See Joomla PR 45925.
	 *
	 * @param   string  $formName  The ID of the form.
	 *
	 * @throws \Exception
	 */
	public function behaviourMultiselect(string $formName = 'adminForm'): void
	{
		if (version_compare(JVERSION, '5.999.999', 'lt'))
		{
			HTMLHelper::_('behavior.multiselect', $formName);

			return;
		}

		$doc       = Factory::getApplication()->getDocument();
		$doc->addScriptOptions('js-multiselect', ['formName' => $formName]);
		$doc->getWebAssetManager()->useScript('multiselect');
	}
}