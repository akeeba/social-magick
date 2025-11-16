<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect\ViolaJones\Adapter;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect\ViolaJones\Classifier\Classifier;
use RuntimeException;
use Throwable;

/**
 * Abstract implementation of an object detection adapter.
 *
 * @since 3.0.0
 * @internal
 */
abstract class AbstractAdapter implements AdapterInterface
{
	protected const MAX_IMAGE_RESOLUTION = 589824;

	protected const MAX_IMAGE_DIMENSION = 386;

	/**
	 * The image width.
	 *
	 * @var          int
	 * @since        3.0.0
	 */
	protected int $width;

	/**
	 * The image height.
	 *
	 * @var          int
	 * @since        3.0.0
	 */
	protected int $height;

	protected float $scalingFactor = 1.0;

	/**
	 * Should I try to destroy the image resource on object destruction?
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	protected bool $freeImageOnObjectDestruction;

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	final public function __construct(private readonly Classifier $classifier)
	{
	}

	/**
	 * Destructor method that is automatically invoked when the object is destroyed.
	 *
	 * Ensures proper cleanup of resources by destroying the image.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	final public function __destruct()
	{
		$this->destroyImage();
	}

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	final public function scanImageFile(string $filePath): array
	{
		if (!file_exists($filePath) || !is_readable($filePath))
		{
			return [];
		}

		$this->destroyImage();

		try
		{
			$this->freeImageOnObjectDestruction = true;
			$this->loadImage($filePath);

			return $this->scanLoadedImage();
		}
		catch (Throwable)
		{
			return [];
		}
	}

	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	public function scanImageResource(mixed $imageResource): array
	{
		$this->destroyImage();

		try
		{
			$this->freeImageOnObjectDestruction = false;
			$this->loadImageResource($imageResource);

			return $this->scanLoadedImage();
		}
		catch (Throwable)
		{
			return [];
		}
	}

	abstract protected function loadImageResource(mixed $imageResource): void;

	/**
	 * Loads an image file.
	 *
	 * Updates the width and height parameters. Also set up an internal property to read the image.
	 *
	 * @param   string  $filePath  The file path to parse.
	 *
	 * @return  void
	 * @throws  RuntimeException  When we cannot load the image file.
	 * @since   3.0.0
	 */
	abstract protected function loadImage(string $filePath): void;

	/**
	 * Destroys the image resource and frees any associated memory.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	abstract protected function destroyImage(): void;

	/**
	 * Returns the colour components of the loaded image at a given location.
	 *
	 * @param   int  $x  X location.
	 * @param   int  $y  Y location
	 *
	 * @return  array  The Red, Green, Blue, and Alpha components of the colour at the given image lcoation.
	 * @since   3.0.0
	 */
	abstract protected function colorAt(int $x, int $y): array;

	/**
	 * Scans the loaded image for objects and returns an array with the found rectangles.
	 *
	 * @return  array
	 * @since   3.0.0
	 */
	private function scanLoadedImage(): array
	{
		$return = [];

		$maxScale  = min($this->width / $this->classifier->getSizeX(), $this->height / $this->classifier->getSizeY());
		$grayImage = array_fill(0, $this->width, array_fill(0, $this->height, null));
		$squares   = array_fill(0, $this->width, array_fill(0, $this->height, null));

		for ($i = 0; $i < $this->width; $i++)
		{
			$col  = 0;
			$col2 = 0;

			for ($j = 0; $j < $this->height; $j++)
			{
				$colors            = $this->colorAt($i, $j);
				$value             = (30 * $colors[0] + 59 * $colors[1] + 11 * $colors[2]) / 100;
				$grayImage[$i][$j] = ($i > 0 ? $grayImage[$i - 1][$j] : 0) + $col + $value;
				$squares[$i][$j]   = ($i > 0 ? $squares[$i - 1][$j] : 0) + $col2 + $value * $value;
				$col               += $value;
				$col2              += $value * $value;
			}
		}

		$baseScale = 2;
		$scale_inc = 1.25;
		$increment = 0.1;

		for ($scale = $baseScale; $scale < $maxScale; $scale *= $scale_inc)
		{
			$step = (int) ($scale * 24 * $increment);
			$size = (int) ($scale * 24);

			for ($x = 0; $x < $this->width - $size; $x += $step)
			{
				for ($y = 0; $y < $this->height - $size; $y += $step)
				{
					foreach ($this->classifier->getStagesIterator() as $stage)
					{
						if (!$stage->pass($grayImage, $squares, $x, $y, $scale))
						{
							continue 2;
						}
					}

					$return[] = ["x" => $x, "y" => $y, "width" => $size, "height" => $size];
				}
			}
		}

		// Rescale the detection dimensions if the scaling factor is not 1.0
		if (abs(1.0 - $this->scalingFactor) > 0.001)
		{
			$return = array_map(
				fn(array $rect) => array_map(
					fn($value) => $value * $this->scalingFactor,
					$rect
				),
				$return
			);
		}

		return $return;
	}
}