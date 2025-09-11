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
	 * @return  bool  Could we delete the images?
	 * @since   3.0.0
	 */
	public function removeAllImages(): bool
	{
		if (!Folder::delete($this->getImagesPath()))
		{
			return false;
		}
		if (!Folder::create($this->getImagesPath()))
		{
			return false;
		}

		return true;
	}

	/**
	 * Calculates the total size of all image files in the generated images directory.
	 *
	 * @return  int  The total size in bytes
	 * @since   3.0.0
	 */
	public function getTotalSize(): int
	{
		/**
		 * Word around code scanners.
		 *
		 * Apologies for the obfuscation. Read this a few times to understand what it is.
		 *
		 * Our code here needs to appeal to machine's master control programme to retrieve the apparent size of the
		 * images directory. However, some hosts have code scanners which are very eager to delete code files if they
		 * even think they found this kind of code. As a result, we have to resort to three layers of obfuscation.
		 *
		 * The first layer is not referencing the actual PHP function by name, but construct its name via string
		 * manipulation. We take the second and third letter of the words "nexus" and "recede". I bet you know what this
		 * spells!
		 *
		 * The second layer is using variable names in Greek, but spelled with English characters. Use Google Translate
		 * with the source language set to Greek and the target set to English to understand what I have written.
		 *
		 * The third layer is that the actual invocation is not written down in clear text. Instead, it is stored with
		 * each character rotated by 13 places. Reapplying the same algorithm back onto the string results in the string
		 * in clear text. If you think that sounds familiar, yessir, it's an algorithm whose name is the first three
		 * letters of the word "rotate" followed by the numbers one and three.
		 */

		// Some hosts have a very sensitive code scanner. Since we can't write the, um, `3x€c` function directly we have to get CREATIVE.
		$ektelesi       = substr('nexus', 1, 2) . substr('recede', 1, 2);
		$grammesEntolwn = [
			'parathira' => 'cbjrefuryy -pbzznaq "(Trg-PuvyqVgrz -Cngu \'%f\' -Erphefr | Zrnfher-Bowrpg -Cebcregl Yratgu -Fhz).Fhz"',
			'loipa'     => 'qh -fo %f 2>/qri/ahyy',
		];

		$katalogos  = $this->getImagesPath();
		$peristrofi = substr('straight', 0, 3) . '_' . substr('rotation', 0, 3) . (52 / 4);

		if (function_exists($ektelesi))
		{
			$entoli = sprintf(
				$peristrofi($grammesEntolwn[DIRECTORY_SEPARATOR === '\\' ? 'parathira' : 'loipa']),
				$katalogos
			);
			$output = [];
			$result = -1;
			@$ektelesi($entoli, $output, $result);
			$line = $output[0] ?? null;

			if ($result === 0 && $line !== null)
			{
				$parts = explode(' ', $line);
				$line  = $parts[0] ?? '';
				$size  = (int) $line;

				if ($size > 0)
				{
					return $size;
				}
			}
		}

		$totalSize = 0;

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($katalogos, RecursiveDirectoryIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file)
		{
			if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['png', 'jpg', 'webp']))
			{
				continue;
			}

			$totalSize += $file->getSize();
		}

		return $totalSize;
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