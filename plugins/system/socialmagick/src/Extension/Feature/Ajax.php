<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Extension\Feature;

use Joomla\Event\Event;

trait Ajax
{
	/**
	 * AJAX handler
	 *
	 * @return  void
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onAjaxSocialmagick(Event $event)
	{
		$key     = trim($this->params->get('cron_url_key', ''));
		$maxExec = max(1, (int) $this->params->get('cron_max_exec', 20));
		$days    = max(1, (int) $this->params->get('old_images_after', 180));

		if (empty($key))
		{
			header('HTTP/1.0 403 Forbidden');

			return;
		}

		try
		{
			$this->getHelper()->deleteOldImages($days, $maxExec);
		}
		catch (\Exception $e)
		{
			header('HTTP/1.0 500 Internal Server Error');

			echo $e->getCode() . ' SocialMagick.php' . $e->getMessage();

			return;
		}

		echo "OK";
	}

}