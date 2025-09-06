<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialMagick\Library;

defined('_JEXEC') || die();

/**
 * Interface for the OpenGraph image renderer implementations.
 *
 * @since  1.0.0
 */
interface ImageRendererInterface
{
	/**
	 * Image renderer constructor.
	 *
	 * @param   int   $quality    Quality of generated images, 10 to 100
	 * @param   bool  $debugText  Should I add bounding boxes to the text rendering?
	 */
	public function __construct(int $quality = 80, bool $debugText = false);

	/**
	 * Generate an OpenGraph image based on a template and some simple parameters
	 *
	 * @param   string       $text        The text to render.
	 * @param   array        $template    The template to apply.
	 * @param   string       $outFile     Full filesystem path of the image file to be saved.
	 * @param   string|null  $extraImage  Full filesystem path to an additional image to be layered.
	 *
	 * @since   1.0.0
	 */
	public function makeImage(string $text, array $template, string $outFile, ?string $extraImage): void;

	/**
	 * Is this renderer supported on this server?
	 *
	 * @return  bool
	 *
	 * @since   1.0.0
	 */
	public function isSupported(): bool;

	/**
	 * Returns an MD5 key based on the renderer's options.
	 *
	 * This is based on the renderer class name, and the text debug and quality settings.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function getOptionsKey(): string;
}