<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect\ViolaJones\Adapter;

\defined('_JEXEC') || die;

use Imagick;
use RuntimeException;
use Throwable;

final class ImagickAdapter extends AbstractAdapter
{
	/**
	 * The Imagick image instance, or NULL
	 *
	 * @var   Imagick|null
	 * @since 3.0.0
	 */
	protected ?Imagick $imageResource = null;

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	public function isSupported(): bool
	{
		// Quick escape route if the Imagick extension is not loaded / compiled in.
		if (function_exists('extension_loaded') && extension_loaded('imagick') !== true)
		{
			return false;
		}

		// Ensure the core class exists and a few key methods are available
		if (!class_exists(Imagick::class))
		{
			return false;
		}

		return true;
	}

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	protected function destroyImage(): void
	{
		if (!$this->imageResource instanceof Imagick)
		{
			return;
		}

		if ($this->freeImageOnObjectDestruction)
		{
			try
			{
				$this->imageResource->clear();
			}
			catch (Throwable $e)
			{
				// Swallow any errors while cleaning up
			}
		}

		$this->imageResource = null;
	}

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	protected function loadImage(string $filePath): void
	{
		try
		{
			$this->imageResource = new Imagick($filePath);
		}
		catch (Throwable $e)
		{
			throw new RuntimeException('Could not open file: ' . $filePath);
		}

		$this->width  = $this->imageResource->getImageWidth();
		$this->height = $this->imageResource->getImageHeight();

		$this->conditionalImageRescaling();

	}

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	protected function colorAt(int $x, int $y): array
	{
		// Use getImagePixelColor (available in Imagick 3.x)
		$pixel = $this->imageResource?->getImagePixelColor($x, $y);

		if ($pixel === null)
		{
			return [0, 0, 0, 0];
		}

		// getColor(true) returns normalized floats [0..1] for r,g,b,a
		$color = $pixel->getColor(true);

		$r = (int) round(($color['r'] ?? 0) * 255);
		$g = (int) round(($color['g'] ?? 0) * 255);
		$b = (int) round(($color['b'] ?? 0) * 255);

		// Map Imagick alpha (0 opaque .. 1 transparent) to GD-like [0..127] scale
		$aFloat = (float) ($color['a'] ?? 0.0); // 0.0 opaque, 1.0 transparent
		$alpha  = (int) round($aFloat * 127);

		return [$r, $g, $b, $alpha];
	}

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	protected function loadImageResource(mixed $imageResource): void
	{
		if (!$imageResource instanceof Imagick)
		{
			throw new RuntimeException('The provided image resource must be an Imagick instance');
		}

		$this->imageResource = $imageResource;
		$this->width         = $this->imageResource->getImageWidth();
		$this->height        = $this->imageResource->getImageHeight();

		$this->conditionalImageRescaling();
	}

	/**
	 * Conditionally resize the image.
	 *
	 * If the image resolution is greater than MAX_IMAGE_RESOLUTION we will resample the image to a smaller size (max
	 * dimension MAX_IMAGE_DIMENSION) to speed up object detection without any significant loss of accuracy.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function conditionalImageRescaling(): void
	{
		// Images up to a resolution of MAX_IMAGE_RESOLUTION are okay
		$surface = $this->width * $this->height;

		if ($surface < self::MAX_IMAGE_RESOLUTION)
		{
			return;
		}

		// The image is bigger than the allowed surface. Downscale so the larger dimension is MAX_IMAGE_DIMENSION.
		$maxDimension        = max($this->width, $this->height);
		$this->scalingFactor = min(1.0, $maxDimension / self::MAX_IMAGE_DIMENSION);
		$this->width         = (int) floor($this->width / $this->scalingFactor);
		$this->height        = (int) floor($this->height / $this->scalingFactor);

		if (!$this->freeImageOnObjectDestruction)
		{
			$newResource = clone $this->imageResource;
			$this->imageResource = $newResource;
		}

		// Resize using a high-quality filter; maintain the exact target size previously computed.
		$this->imageResource->resizeImage($this->width, $this->height, Imagick::FILTER_LANCZOS, 1.0, false);

		// Since we have a resampled temporary image we will need to free it when we're done with it.
		$this->freeImageOnObjectDestruction = true;
	}
}
