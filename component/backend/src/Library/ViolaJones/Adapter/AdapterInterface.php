<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ViolaJones\Adapter;

use Akeeba\Component\SocialMagick\Administrator\Library\ViolaJones\Classifier\Classifier;

\defined('_JEXEC') || die;

/**
 * Object detection adapter.
 *
 * Each adapter implements the same algorithm but supports a different PHP image handling extension.
 *
 * @since  3.0.0
 * @internal
 */
interface AdapterInterface
{
	/**
	 * Public constructor
	 *
	 * @param   Classifier  $classifier  The classifier to use
	 *
	 * @since   3.0.0
	 */
	public function __construct(Classifier $classifier);

	/**
	 * Scans an image file for detected objects and returns an array of found object rectangles.
	 *
	 * @param   string  $filePath  The file to load.
	 *
	 * @return  array
	 * @since   3.0.0
	 */
	public function scanImageFile(string $filePath): array;

	/**
	 * Scans an image resource for detected objects and returns an array of found object rectangles.
	 *
	 * @param   mixed  $imageResource  The already loaded image resource to analyse.
	 *
	 * @return  array
	 * @since   3.0.0
	 */
	public function scanImageResource(mixed $imageResource): array;

	/**
	 * Is this adapter supported on this server?
	 *
	 * @return  bool
	 *
	 * @since   3.0.0
	 */
	public function isSupported(): bool;
}