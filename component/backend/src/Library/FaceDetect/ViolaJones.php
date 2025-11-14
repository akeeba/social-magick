<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect;

defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect\ViolaJones\Classifier\Classifier;
use Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect\ViolaJones\ObjectDetector;
use GdImage;
use Imagick;

/**
 * Face detection adapter using a pure PHP implementation of the Viola-Jones algorithm.
 *
 * @since  3.0.0
 */
class ViolaJones implements AdapterInterface
{
	/**
	 * @inheritDoc
	 * @since 3.0.0
	 */
	public function getCoordinates(Imagick|GdImage $image): array
	{
		$rootPath     = JPATH_ADMINISTRATOR . '/components/com_socialmagick/src/Library/ViolaJones/models/';
		$classifier   = Classifier::fromJsonFile($rootPath . 'haarcascade_frontalface_default.json.gz');
		$detector     = new ObjectDetector($classifier);
		$foundObjects = $detector->getObjects($image, 2);

		if (empty($foundObjects))
		{
			return [null, null, null, null];
		}

		$xValues = [];
		$yValues = [];

		foreach ($foundObjects as $object)
		{
			$xValues[] = $object['x'];
			$xValues[] = $object['x'] + $object['width'];
			$yValues[] = $object['y'];
			$yValues[] = $object['y'] + $object['height'];
		}

		$minX = min($xValues);
		$maxX = max($xValues);
		$minY = min($yValues);
		$maxY = max($yValues);

		return [$minX, $minY, $maxX, $maxY];
	}
}