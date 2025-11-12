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
 * Each adapter implements the same algorithm, but supports a different PHP image handling extension.
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
	 * Scans an image file for detected objects and returns an array of found face rectangles.
	 *
	 * @param   string  $filePath
	 *
	 * @return  array
	 * @since   3.0.0
	 */
	public function scan(string $filePath): array;

	/**
	 * Is this adapter supported on this server?
	 *
	 * @return  bool
	 *
	 * @since   3.0.0
	 */
	public function isSupported(): bool;
}