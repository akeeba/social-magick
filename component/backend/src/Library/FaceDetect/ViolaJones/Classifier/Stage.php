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
 * A Haar cascade classifier's stage.
 *
 * Architectural note: Classifier -> array of Stage -> array of Feature -> array of Rect
 */
final class Stage implements JsonSerializable
{
	/**
	 * Constructor method to initialize the object with a specified threshold value.
	 *
	 * @param   float           $threshold  The threshold value to be set for the object.
	 * @param   array<Feature>  $features   The Feature objects in this classifier Stage.
	 *
	 * @return  void
	 */
	public function __construct(
		private readonly float $threshold,
		private readonly array $features
	)
	{
	}

	public static function fromArray(array $array): self
	{
		return new self(
			$array['threshold'],
			array_map(
				fn($feature) => Feature::fromArray($feature),
				$array['features']
			)
		);
	}

	/**
	 * Does the image pass this stage?
	 *
	 * @param   array  $grayImage
	 * @param   array  $squares
	 * @param   int    $x
	 * @param   int    $y
	 * @param   float  $scale
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	public function pass(array $grayImage, array $squares, int $x, int $y, float $scale): bool
	{
		return array_reduce(
				$this->features,
				fn(float $carry, Feature $f) => $carry + $f->getVal($grayImage, $squares, $x, $y, $scale),
				0.0
			) > $this->threshold;
	}

	/**
	 * Converts the object into a JSON-serializable format.
	 *
	 * @return  array  The data that can be serialized to JSON, typically an array or object.
	 * @since   3.0.0
	 */
	public function jsonSerialize(): array
	{
		return [
			'threshold' => $this->threshold,
			'features'  => $this->features,
		];
	}
}