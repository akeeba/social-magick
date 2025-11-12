<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ViolaJones;

\defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Library\ViolaJones\Adapter\AdapterInterface;
use Akeeba\Component\SocialMagick\Administrator\Library\ViolaJones\Adapter\GDAdapter;
use Akeeba\Component\SocialMagick\Administrator\Library\ViolaJones\Adapter\ImagickAdapter;
use Akeeba\Component\SocialMagick\Administrator\Library\ViolaJones\Classifier\Classifier;
use RuntimeException;

/**
 * Object Detection
 *
 * This implements the object detection method using Haar feature-based cascade classifiers proposed by Paul Viola and
 * Michael Jones in their 2001 paper "Rapid Object Detection using a Boosted Cascade of Simple Features".
 *
 * @link  https://en.wikipedia.org/wiki/Viola%E2%80%93Jones_object_detection_framework Overview.
 * @link  https://docs.opencv.org/3.4/db/d28/tutorial_cascade_classifier.html A more practical article from OpenCV.
 * @link  https://github.com/opencv/opencv/tree/2.2/data/haarcascades Classifiers' source.
 * @link  https://github.com/tc/jviolajones Java implementation of the algorithm.
 * @link  https://github.com/felixkoch/PHP-FaceDetector/blob/master/FaceDetector.php Inspired this implementation.
 */
class ObjectDetector
{
	private const ADAPTERS = [
		ImagickAdapter::class,
		GDAdapter::class,
	];

	/**
	 * Constructor.
	 *
	 * @param   Classifier  $classifier  The classifier to use
	 *
	 * @since   3.0.0
	 */
	public function __construct(private readonly Classifier $classifier)
	{
	}

	/**
	 * Returns an array of found objects.
	 *
	 * Each object is represented by an associative array with the keys x, y, width and height.
	 *
	 * The minNeighbours parameter determines the accuracy of detection. The default value (2) may result in some
	 * uncertain results. Increase to 3 for more confidence, at the expense of dropping valid objects detected in less
	 * clear images. Anything above this value will probably be too strict for practical use.
	 *
	 * @param   string  $filePath       The image file to analyse
	 * @param   int     $minNeighbours  The minimum neighbours to require for object detection.
	 *
	 * @return  array Coordinates of the rectangles where objects are found.
	 * @since   3.0.0
	 */
	public function getObjects(string $filePath, int $minNeighbours = 2): array
	{
		// Catch error case: missing classifier
		if (!$this->classifier instanceof Classifier || $this->classifier->getSizeX() <= 0 || $this->classifier->getSizeY() <= 0)
		{
			return [];
		}

		// Use the adapter to detect objects
		$adapter       = $this->getAdapter();
		$foundRects    = $adapter->scan($filePath);

		// Make sure the minimum neighbours is within range.
		$minNeighbours = min(max(2, $minNeighbours), 10);

		return array_map(
			fn($rect) => array_map('intval', $rect),
			$this->merge($foundRects, $minNeighbours)
		);
	}

	/**
	 * Merges detected rectangles as long as they have a number of neighbours equal to or exceeding the specified limit.
	 *
	 * @param   array  $rectangles
	 * @param   int    $min_neighbours
	 *
	 * @return  array
	 * @since   3.0.0
	 */
	private function merge(array $rectangles, int $min_neighbours): array
	{
		$return     = [];
		$ret        = [];
		$nb_classes = 0;

		for ($i = 0; $i < count($rectangles); $i++)
		{
			$found = false;

			for ($j = 0; $j < $i; $j++)
			{
				if ($this->equals($rectangles[$j], $rectangles[$i]))
				{
					$found   = true;
					$ret[$i] = $ret[$j];
				}
			}

			if (!$found)
			{
				$ret[$i] = $nb_classes;
				$nb_classes++;
			}
		}


		$neighbors = [];
		$rect      = [];

		for ($i = 0; $i < $nb_classes; $i++)
		{
			$neighbors[$i] = 0;
			$rect[$i]      = ["x" => 0, "y" => 0, "width" => 0, "height" => 0];
		}

		for ($i = 0; $i < count($rectangles); $i++)
		{
			$neighbors[$ret[$i]]++;
			$rect[$ret[$i]]['x']      += $rectangles[$i]['x'];
			$rect[$ret[$i]]['y']      += $rectangles[$i]['y'];
			$rect[$ret[$i]]['width']  += $rectangles[$i]['width'];
			$rect[$ret[$i]]['height'] += $rectangles[$i]['height'];
		}

		for ($i = 0; $i < $nb_classes; $i++)
		{
			$n = $neighbors[$i];

			if ($n >= $min_neighbours)
			{
				$return[] = [
					"x"      => ($rect[$i]['x'] * 2 + $n) / (2 * $n),
					"y"      => ($rect[$i]['y'] * 2 + $n) / (2 * $n),
					"width"  => ($rect[$i]['width'] * 2 + $n) / (2 * $n),
					"height" => ($rect[$i]['height'] * 2 + $n) / (2 * $n),
				];
			}
		}

		return $return;
	}

	/**
	 * Checks if two rectangular areas overlap, within a (hardcoded) 20% margin of error.
	 *
	 * @param   array  $r1
	 * @param   array  $r2
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	private function equals(array $r1, array $r2): bool
	{
		$distance = (int) ($r1['width'] * 0.2);

		if (
			$r2['x'] <= $r1['x'] + $distance &&
			$r2['x'] >= $r1['x'] - $distance &&
			$r2['y'] <= $r1['y'] + $distance &&
			$r2['y'] >= $r1['y'] - $distance &&
			$r2['width'] <= (int) ($r1['width'] * 1.2) &&
			(int) ($r2['width'] * 1.2) >= $r1['width']
		)
		{
			return true;
		}

		if (
			$r1['x'] >= $r2['x'] &&
			$r1['x'] + $r1['width'] <= $r2['x'] + $r2['width'] &&
			$r1['y'] >= $r2['y'] &&
			$r1['y'] + $r1['height'] <= $r2['y'] + $r2['height']
		)
		{
			return true;
		}

		return false;
	}

	/**
	 * Retrieves the appropriate adapter for object detection.
	 *
	 * @return  AdapterInterface The adapter instance that is supported and suitable for use.
	 * @throws  RuntimeException If no suitable adapter is found.
	 * @since   3.0.0
	 */
	private function getAdapter(): AdapterInterface
	{
		foreach (self::ADAPTERS as $adapterName)
		{
			/** @var AdapterInterface $o */
			$o = new $adapterName($this->classifier);

			if ($o->isSupported())
			{
				return $o;
			}
		}

		throw new RuntimeException('No suitable adapter found for PHP-based object detection.');
	}
}