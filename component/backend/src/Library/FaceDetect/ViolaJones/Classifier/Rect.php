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
 * A Haar cascade rectangle (part of a feature).
 *
 * Architectural note: Classifier -> array of Stage -> array of Feature -> array of Rect
 */
final class Rect implements JsonSerializable
{
	/**
	 * Constructor method.
	 *
	 * @param   int    $x1      The x-coordinate of the first point.
	 * @param   int    $x2      The x-coordinate of the second point.
	 * @param   int    $y1      The y-coordinate of the first point.
	 * @param   int    $y2      The y-coordinate of the second point.
	 * @param   float  $weight  The weight associated with the points.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	public function __construct(
		public readonly int   $x1,
		public readonly int   $x2,
		public readonly int   $y1,
		public readonly int   $y2,
		public readonly float $weight
	)
	{
	}

	/**
	 * Creates an instance of the Rect class from a string representation.
	 *
	 * @param   string  $text  A string containing space-separated values representing the coordinates and weight.
	 *
	 * @return  self  Returns a new instance of the Rect class.
	 * @since   3.0.0
	 */
	public static function fromString(string $text): self
	{
		[$x1, $x2, $y1, $y2, $f] = explode(" ", $text);

		return new Rect($x1, $x2, $y1, $y2, floatval($f));
	}

	/**
	 * Creates an instance of the class from an array.
	 *
	 * @param   array  $array  An array containing the parameters to be passed to the class constructor.
	 *
	 * @return  static  Returns a new instance of the class.
	 * @since   3.0.0
	 */
	public static function fromArray(array $array): self
	{
		return new self(...$array);
	}

	/**
	 * Prepares data for JSON serialization.
	 *
	 * @return  array  An array representing the data to be serialized into JSON format.
	 * @since   3.0.0
	 */
	public function jsonSerialize(): array
	{
		return [$this->x1, $this->x2, $this->y1, $this->y2, $this->weight];
	}
}
