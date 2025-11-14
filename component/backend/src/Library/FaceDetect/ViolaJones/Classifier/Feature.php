<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect\ViolaJones\Classifier;

\defined('_JEXEC') || die;

use JsonSerializable;

/**
 * A Haar cascade classifier's feature (part of a stage).
 *
 * Architectural note: Classifier -> array of Stage -> array of Feature -> array of Rect
 */
final class Feature implements JsonSerializable
{
	/**
	 * Constructor method.
	 *
	 * @param   float        $threshold  The threshold value.
	 * @param   float        $left_val   The left value.
	 * @param   float        $right_val  The right value.
	 * @param   array        $size       The array representing the size.
	 * @param   array<Rect>  $rects      The rectangles in this feature.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	public function __construct(
		private readonly float $threshold,
		private readonly float $left_val,
		private readonly float $right_val,
		private readonly array $size,
		private readonly array $rects
	)
	{
	}

	/**
	 * Creates an instance of the class from an array.
	 *
	 * @param   array  $array  The array containing the data to create the instance.
	 *                         It must include keys 'threshold', 'left', 'right', 'size', and 'rects'.
	 *
	 * @return  self   Returns a new instance of the class populated with the given array data.
	 * @since   3.0.0
	 */
	public static function fromArray(array $array): self
	{
		return new self($array['threshold'],
			$array['left'],
			$array['right'],
			$array['size'],
			array_map(
				fn($arr) => Rect::fromArray($arr),
				$array['rects']
			));
	}

	/**
	 * Calculates and returns a value based on the properties of the object, the input image data,
	 * and the scaling factor.
	 *
	 * @param   array  $grayImage  The integral image data (grayscale image representation).
	 * @param   array  $squares    The squared integral image data.
	 * @param   int    $i          The x-coordinate for the top-left corner of the region.
	 * @param   int    $j          The y-coordinate for the top-left corner of the region.
	 * @param   float  $scale      The scaling factor applied to the features of the object.
	 *
	 * @return  float   The calculated value based on the input image data, scale, and threshold.
	 * @since   3.0.0
	 */
	public function getVal(array $grayImage, array $squares, int $i, int $j, float $scale): float
	{
		$w        = (int) ($scale * $this->size[0]);
		$h        = (int) ($scale * $this->size[1]);
		$inv_area = 1 / ($w * $h);

		$total_x  = $grayImage[$i + $w][$j + $h] + $grayImage[$i][$j] - $grayImage[$i][$j + $h] - $grayImage[$i + $w][$j];
		$total_x2 = $squares[$i + $w][$j + $h] + $squares[$i][$j] - $squares[$i][$j + $h] - $squares[$i + $w][$j];

		$moy   = $total_x * $inv_area;
		$vnorm = $total_x2 * $inv_area - $moy * $moy;
		$vnorm = ($vnorm > 1) ? sqrt($vnorm) : 1;

		$rect_sum = 0;

		for ($k = 0; $k < count($this->rects); $k++)
		{
			$r   = $this->rects[$k];
			$rx1 = $i + (int) ($scale * $r->x1);
			$rx2 = $i + (int) ($scale * ($r->x1 + $r->y1));
			$ry1 = $j + (int) ($scale * $r->x2);
			$ry2 = $j + (int) ($scale * ($r->x2 + $r->y2));

			$rect_sum += (int) (($grayImage[$rx2][$ry2] - $grayImage[$rx1][$ry2] - $grayImage[$rx2][$ry1] + $grayImage[$rx1][$ry1]) * $r->weight);
		}

		$rect_sum2 = $rect_sum * $inv_area;

		return ($rect_sum2 < $this->threshold * $vnorm ? $this->left_val : $this->right_val);
	}

	/**
	 * Serializes the object to a value that can be serialized natively to JSON.
	 *
	 * @return  array  The data which can be serialized by json_encode(), typically an array or object.
	 * @since   3.0.0
	 */
	public function jsonSerialize(): array
	{
		return [
			'threshold' => $this->threshold,
			'left'      => $this->left_val,
			'right'     => $this->right_val,
			'size'      => $this->size,
			'rects'     => $this->rects,
		];
	}
}
