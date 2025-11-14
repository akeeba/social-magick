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
 * Interface for a face deetection adapter.
 *
 * @since   3.0.0
 */
interface AdapterInterface
{
	/**
	 * Returns the coordinates of the bounding box containing all detected faces in the given image.
	 *
	 * The bounding box is returned as the top left and bottom right corner coordinates of the bounding box containing
	 * **all** detected faces. The format is [x1, y1, x2, y2].
	 *
	 * @param   GdImage|Imagick  $image  An image resource
	 *
	 * @return  array<int>  Bounding box containing all detected faces.
	 * @since   3.0.0
	 */
	public function getCoordinates(GdImage|Imagick $image): array;
}