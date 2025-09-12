<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator\Adapter;

defined('_JEXEC') || die();

/**
 * Abstract implementation of the OpenGraph image renderer.
 *
 * @since  1.0.0
 */
abstract class AbstractAdapter implements AdapterInterface
{
	/**
	 * Should I create bounding boxes around the rendered text?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	protected bool $debugText = false;

	/**
	 * Generated image quality, 0 (maximum compression) to 100 (uncompressed / lossless compression).
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	protected int $quality = 75;

	/** @inheritDoc */
	public function __construct(int $quality = 80, bool $debugText = false)
	{
		$this->quality   = max(min($quality, 100), 0);
		$this->debugText = $debugText;
	}

	/** @inheritDoc */
	public function getOptionsKey(): string
	{
		return md5(
			get_class($this) . '_' .
			($this->debugText ? 'textDebug_' : '') .
			'q' . $this->quality
		);
	}


	/**
	 * Pre-processes the text before rendering.
	 *
	 * This method removes Emoji and Dingbats, collapses double spaces into single spaces and converts all whitespace
	 * into spaces. Finally, it converts non-ASCII characters into HTML entities so that GD can render them correctly.
	 *
	 * @param   string  $text
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function preProcessText(string $text, bool $htmlEntities = true)
	{
		$text = $this->stripEmoji($text);
		$text = preg_replace('/\s/', ' ', $text);
		$text = preg_replace('/\s{2,}/', ' ', $text);

		return $htmlEntities ? htmlentities($text) : $text;
	}

	/**
	 * Normalize the extension of an image file and return it without the dot.
	 *
	 * @param   string  $file  The image file path or file name
	 *
	 * @return  string|null
	 *
	 * @since   1.0.0
	 */
	protected function getNormalizedExtension(string $file): ?string
	{
		if (empty($file))
		{
			return null;
		}

		$extension = pathinfo($file, PATHINFO_EXTENSION);

		switch (strtolower($extension))
		{
			// JPEG files come in different extensions
			case 'jpg':
			case 'jpe':
			case 'jpeg':
				return 'jpg';

			default:
				return $extension;
		}
	}

	/**
	 * Normalise the path to a font file.
	 *
	 * If the font file path is not absolute we look in the plugin's fonts fodler.
	 *
	 * @param   string  $font
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	protected function normalizeFont(string $font): string
	{
		// Convert a relative path to absolute
		if (!@file_exists($font))
		{
			$font = JPATH_PUBLIC . '/media/com_socialmagick/fonts/' . $font;
		}

		// If the font doesn't exist or is unreadable fall back to OpenSans Bold shipped with the component
		if (!@file_exists($font) || !@is_file($font) || !@is_readable($font))
		{
			$font = JPATH_PUBLIC . '/media/com_socialmagick/fonts/OpenSans-Bold.ttf';
		}

		return $font;
	}

	/**
	 * Convert a hexadecimal color string to an array of Red, Green, Blue and Alpha values.
	 *
	 * @param   string  $hex
	 *
	 * @return  int[]  The [R,G,B,A] array of the color.
	 *
	 * @since   1.0.0
	 */
	protected function hexToRGBA(string $hex): array
	{
		// Uppercase the hex color string
		$hex = strtoupper($hex);

		// Remove the hash sign in front
		if (substr($hex, 0, 1) === '#')
		{
			$hex = substr($hex, 1);
		}

		// Convert ABC to AABBCC
		if (strlen($hex) === 3)
		{
			$bits = str_split($hex, 1);
			$hex  = $bits[0] . $bits[0] . $bits[1] . $bits[1] . $bits[2] . $bits[2];
		}

		// Make sure the hex color string is exactly 8 characters (format: RRGGBBAA
		if (strlen($hex) < 8)
		{
			$hex = str_pad(str_pad($hex, 6, '0'), 8, 'F');
		}

		$hex = substr($hex, 0, 8);

		$hexBytes = str_split($hex, 2);

		$ret = [0, 0, 0, 255];

		foreach ($hexBytes as $index => $hexByte)
		{
			$ret[$index] = hexdec($hexByte);
		}

		return $ret;
	}

	/**
	 * Set the PHP time limit, if possible.
	 *
	 * @param   int  $limit  Time limit in seconds.
	 *
	 *
	 * @since   1.0.0
	 */
	protected function setTimeLimit(int $limit = 0)
	{
		if (!function_exists('set_time_limit'))
		{
			return;
		}

		@set_time_limit($limit);
	}

	/**
	 * Resize an $x x $y pixels image so that it can be cropped to $refX x $refY without leaving empty areas.
	 *
	 * If the images is narrower or shorter than $refX x $refY we will still have empty areas.
	 *
	 * @param   int  $x     The original image's width
	 * @param   int  $y     The original image's height
	 * @param   int  $refX  The reference width we need to achieve
	 * @param   int  $refY  The reference height we need to achieve
	 *
	 * @return  int[]  The resize dimensions for best fit resize.
	 * @since   3.0.0
	 */
	protected function getBestFitDimensions(int $x, int $y, int $refX, int $refY): array
	{
		if ($x < $refX || $y < $refY)
		{
			return [$x, $y];
		}

		$newX = $refX;
		$newY = (int) floor($y * ($newX / $x));

		if ($newY >= $refY)
		{
			return [$newX, $newY];
		}

		$newY = $refY;
		$newX = (int) floor($x * ($refY / $y));

		return [$newX, $newY];
	}

	/**
	 * Applies an X-Y transform to the clip origin point so that the clipping stays with the image boundaries.
	 *
	 * For example, let's say you have a 200x200 pixels source, and you want to clip a 100x100 image from the origin
	 * point (50, 50) with a transform of (70, 10).
	 *
	 * This would be an invalid transform because the new origin point becomes (120, 60), therefore the opposite corner
	 * of the clip region would be (220, 160) which is outside the image boundaries.
	 *
	 * This method would restrict the X-transform in this case to 50 i.e. return [50,50]. The new origin point would now
	 * be (100, 60), therefore the opposite corner of the clip region becomes (200, 160) which is well within the image
	 * boundaries.
	 *
	 * @param   int  $sourceWidth   Source image width
	 * @param   int  $sourceHeight  Source image height
	 * @param   int  $originX       X coordinate of the clip region's origin point
	 * @param   int  $originY       Y coordinate of the clip region's origin point
	 * @param   int  $copyWidth     Clipping width
	 * @param   int  $copyHeight    Clipping height
	 * @param   int  $transformX    The desired transform (nudge) of the origin point on the X axis
	 * @param   int  $transformY    A desired transform (nudge) of the origin point on the Y axis
	 *
	 * @return  array
	 */
	protected function nudgeClipRegion(int $sourceWidth, int $sourceHeight, int $originX, int $originY, int $copyWidth, int $copyHeight, int $transformX, int $transformY): array
	{
		$newOriginX = $originX + $transformX;
		$newOriginY = $originY + $transformY;

		if ($newOriginX < 0)
		{
			$transformX += abs($newOriginX);
		}
		elseif ($newOriginX + $copyWidth > $sourceWidth)
		{
			$transformX -= ($newOriginX + $copyWidth) - $sourceWidth;
		}

		if ($newOriginY < 0)
		{
			$transformY += abs($newOriginY);
		}
		elseif ($newOriginY + $copyHeight > $sourceHeight)
		{
			$transformY -= ($newOriginY + $copyHeight) - $sourceHeight;
		}

		return [$transformX, $transformY];
	}

	/**
	 * Strip Emoji and Dingbats off a string
	 *
	 * @param   string  $string  The string to process
	 *
	 * @return  string  The cleaned up string
	 *
	 * @since   1.0.0
	 */
	private function stripEmoji(string $string): string
	{

		// Match Emoticons
		$regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
		$clear_string    = preg_replace($regex_emoticons, '', $string);

		// Match Miscellaneous Symbols and Pictographs
		$regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
		$clear_string  = preg_replace($regex_symbols, '', $clear_string);

		// Match Transport And Map Symbols
		$regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
		$clear_string    = preg_replace($regex_transport, '', $clear_string);

		// Match Miscellaneous Symbols
		$regex_misc   = '/[\x{2600}-\x{26FF}]/u';
		$clear_string = preg_replace($regex_misc, '', $clear_string);

		// Match Dingbats
		$regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
		$clear_string   = preg_replace($regex_dingbats, '', $clear_string);

		return $clear_string;
	}

}