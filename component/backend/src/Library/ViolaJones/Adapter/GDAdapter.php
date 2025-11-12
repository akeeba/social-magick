<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ViolaJones\Adapter;

\defined('_JEXEC') || die;

use RuntimeException;

final class GDAdapter extends AbstractAdapter
{
	/**
	 * The GD image resource, or NULL
	 *
	 * @var   resource|null
	 * @since 3.0.0
	 */
	protected $imageResource;

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	public function isSupported(): bool
	{
		// Quick escape route if the GD extension is not loaded / compiled in.
		if (function_exists('extension_loaded') && extension_loaded('gd') !== true)
		{
			return false;
		}

		$functions = [
			'imagecreatetruecolor',
			'imagealphablending',
			'imagecolorallocatealpha',
			'imagefilledrectangle',
			'imagerectangle',
			'imagecopy',
			'imagedestroy',
			'imagesavealpha',
			'imagepng',
			'imagejpeg',
			'getimagesize',
			'imagecreatefromjpeg',
			'imagecreatefrompng',
			'imagecopyresampled',
		];

		return array_reduce($functions, fn($carry, $function) => $carry && function_exists($function), true);
	}

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	protected function destroyImage(): void
	{
		if (!is_resource($this->imageResource))
		{
			return;
		}

		@imagedestroy($this->imageResource);

		$this->imageResource = null;
	}

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	protected function loadImage(string $filePath): void
	{
		$imageInfo = getimagesize($filePath);

		if (!$imageInfo)
		{
			throw new RuntimeException("Could not open file: " . $filePath);
		}

		$this->width  = $imageInfo[0];
		$this->height = $imageInfo[1];
		$imageType    = $imageInfo[2];

		$this->imageResource = match ($imageType)
		{
			IMAGETYPE_BMP => imagecreatefrombmp($filePath),
			IMAGETYPE_GIF => imagecreatefromgif($filePath),
			IMAGETYPE_JPEG => @imagecreatefromjpeg($filePath),
			IMAGETYPE_PNG => imagecreatefrompng($filePath),
			IMAGETYPE_WBMP => imagecreatefromwbmp($filePath),
			IMAGETYPE_XBM => imagecreatefromxbm($filePath),
			IMAGETYPE_WEBP => imagecreatefromwebp($filePath),
			default => null
		};

		if ($this->imageResource === null)
		{
			throw new RuntimeException("Unknown file format " . $imageType . " for " . $filePath);
		}

		// Images up to a resolution of MAX_IMAGE_RESOLUTION are okay
		$surface = $this->width * $this->height;

		if ($surface < self::MAX_IMAGE_RESOLUTION)
		{
			return;
		}

		// The image is bigger than 1MP. Try to convert to a maximum dimension of MAX_IMAGE_RESOLUTION
		$maxDimension        = max($this->width, $this->height);
		$this->scalingFactor = $maxDimension / self::MAX_IMAGE_DIMENSION;
		$this->width         = floor($this->width / $this->scalingFactor);
		$this->height        = floor($this->height / $this->scalingFactor);

		$tempImage = imagecreatetruecolor($this->width, $this->height);

		imagecopyresampled($tempImage, $this->imageResource, 0, 0, 0, 0, $this->width, $this->height, imagesx($this->imageResource), imagesy($this->imageResource));
		imagedestroy($this->imageResource);

		$this->imageResource = $tempImage;
	}

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	protected function colorAt(int $x, int $y): array
	{
		return array_values(imagecolorsforindex($this->imageResource, imagecolorat($this->imageResource, $x, $y)));
	}
}