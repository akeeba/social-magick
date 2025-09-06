<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension\Traits;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever\ParametersRetriever;

trait ParametersRetrieverTrait
{
	/**
	 * The ParametersRetriever object instance.
	 *
	 * @since 2.0.0
	 */
	private ParametersRetriever $paramsRetriever;

	/**
	 * Returns the singleton parameters retriever object instance.
	 *
	 * @return  ParametersRetriever
	 * @since   2.0.0
	 */
	protected function getParamsRetriever(): ParametersRetriever
	{
		return $this->paramsRetriever ??= new ParametersRetriever(
			$this->getApplication(), $this->getDatabase()
		);
	}
}