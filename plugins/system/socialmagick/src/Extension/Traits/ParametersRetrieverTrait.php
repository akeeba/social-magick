<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension\Traits;

\defined('_JEXEC') || die;

use Akeeba\Plugin\System\SocialMagick\Library\ParametersRetriever;

trait ParametersRetrieverTrait
{
	private ?ParametersRetriever $paramsRetriever = null;

	protected function getParamsRetriever(): ParametersRetriever
	{
		/** @noinspection PhpParamsInspection */
		$this->paramsRetriever ??= new ParametersRetriever($this->getApplication());

		return $this->paramsRetriever;
	}
}