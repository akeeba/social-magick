<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Model;

use DateInterval;
use DateTime;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\Filesystem\Folder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class ImagesModel extends BaseModel
{

	/**
	 * Remove files older than the specified interval
	 *
	 * @param   string  $olderThan  DateInterval-compatible string
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	public function removeOldImages(string $olderThan = 'P10D'): void
	{
		$path            = $this->getImagesPath();
		$cutOffTimeStamp = (new DateTime())->sub(new DateInterval($olderThan))->getTimestamp();

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file)
		{
			if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['png', 'jpg', 'webp']))
			{
				continue;
			}

			if ($file->getMTime() > $cutOffTimeStamp)
			{
				continue;
			}

			@unlink($file->getRealPath());
		}
	}

	/**
	 * Deletes all generated OpenGraph images, recreating the directory to ensure it's empty.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	public function removeAllImages(): void
	{
		Folder::delete($this->getImagesPath());
		Folder::create($this->getImagesPath());
	}

	/**
	 * Retrieves the path to the directory where generated OpenGraph images are stored.
	 *
	 * @return  string The path to the images directory.
	 * @since   3.0.0
	 */
	private function getImagesPath(): string
	{
		return JPATH_PUBLIC . '/media/com_socialmagick/generated';
	}
}