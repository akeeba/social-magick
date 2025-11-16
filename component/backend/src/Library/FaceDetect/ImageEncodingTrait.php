<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect;

defined('_JEXEC') || die;

use GdImage;
use Imagick;

/**
 * A trait for encoding images for use by on-line face detection APIs.
 *
 * @since  3.0.0
 */
trait ImageEncodingTrait
{
	protected float $scalingFactor = 1.0;

	protected $allowWebP = true;

	/**
	 * Return the given image object as a base sixty-four encoded PNG or WebP image.
	 *
	 * @param   GdImage|Imagick  $image  The image resource to be encoded.
	 *
	 * @return  string  The encoded binary string representation of the image.
	 * @since   3.0.0
	 */
	protected function encodeImage(Imagick|GdImage $image): string
	{
		if ($image instanceof GdImage)
		{
			return $this->encodeGdImage($image);
		}

		return $this->encodeImagickImage($image);
	}

	/**
	 * Return the given GD image object as a base sixty-four encoded PNG or WebP image.
	 *
	 * @param   GdImage  $image  The GD image resource to be encoded.
	 *
	 * @return  string  The encoded binary string representation of the image.
	 * @since   3.0.0
	 */
	private function encodeGdImage(GdImage $image): string
	{
		@ob_start();

		if (function_exists('imagewebp') && $this->allowWebP)
		{
			imagewebp($image, null, 80);
		}
		else
		{
			imagejpeg($image, null, 80);
		}

		return $this->encodeBinaryData(ob_get_clean());
	}

	/**
	 * Return the given Imagick image object as a base sixty-four encoded PNG or WebP image.
	 *
	 * @param   Imagick  $image  The GD image resource to be encoded.
	 *
	 * @return  string  The encoded binary string representation of the image.
	 * @since   3.0.0
	 */
	private function encodeImagickImage(Imagick $image): string
	{
		$imageFormat = in_array('WEBP', Imagick::queryFormats()) && $this->allowWebP ? 'webp' : 'png';

		$image = clone($image);
		$image->setImageFormat($imageFormat);

		switch ($imageFormat)
		{
			case 'png':
				$image->setImageCompressionQuality(80);
				$image->setOption('png:compression-strategy', '0'); // 0-4, compression strategy
				break;

			case 'webp':
				$image->setImageCompressionQuality(80);
				$image->setOption('webp:lossless', false);
				break;
		}

		$ret = $this->encodeBinaryData($image->getImageBlob());

		$image->clear();

		return $ret;
	}

	/**
	 * Converts the raw data to its base sixty-four representation.
	 *
	 * This method and the stupid obfuscation it uses are necessary because some hosts appear to be run by idiots.
	 *
	 * @param   string  $data  The raw data.
	 *
	 * @return  string  The encoded data.
	 * @since   3.0.0
	 */
	private function encodeBinaryData(string $data): string
	{
		// Work around idiot hosts
		$function = substr('debased', 2, -1) . (string) (2 * 32) . '_' .
			substr('end', 0, 2) . substr('recode', 2);

		return call_user_func($function, $data);
	}
}