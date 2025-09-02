<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension\Traits;

\defined('_JEXEC') || die;

use Joomla\CMS\Document\HtmlDocument;

trait ConditionalMetaTrait
{
	/**
	 * Apply a meta attribute if it doesn't already exist
	 *
	 * @param   string  $name       The name of the meta to add
	 * @param   mixed   $value      The value of the meta to apply
	 * @param   string  $attribute  Meta attribute, default is 'property', could also be 'name'
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function conditionallyApplyMeta(string $name, $value, string $attribute = 'property'): void
	{
		/** @var HtmlDocument $doc */
		$doc = $this->getApplication()->getDocument();

		$existing = $doc->getMetaData($name, $attribute);

		if (!empty($existing))
		{
			return;
		}

		$doc->setMetaData($name, $value, $attribute);
	}

}