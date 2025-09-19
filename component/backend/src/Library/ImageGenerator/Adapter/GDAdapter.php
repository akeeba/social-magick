<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator\Adapter;

defined('_JEXEC') || die();

use Joomla\CMS\HTML\HTMLHelper;

/**
 * An OpenGraph image renderer using the GD library
 *
 * @since       1.0.0
 */
class GDAdapter extends AbstractAdapter implements AdapterInterface
{
	/** @inheritDoc */
	public function isSupported(): bool
	{
		// Quick escape route if the GD extension is not loaded / compiled in.
		if (function_exists('extension_loaded') && extension_loaded('gd') !== true)
		{
			return false;
		}

		$functions = [
			'imagecreatetruecolor',
			'imagealphablending',
			'imagecolorallocatealpha',
			'imagefilledrectangle',
			'imagerectangle',
			'imagecopy',
			'imagedestroy',
			'imagesavealpha',
			'imagepng',
			'imagejpeg',
			'getimagesize',
			'imagecreatefromjpeg',
			'imagecreatefrompng',
			'imagecopyresampled',
		];

		return array_reduce($functions, fn($carry, $function) => $carry && function_exists($function), true);
	}

	/** @inheritDoc */
	public function makeImage(string $text, array $template, string $outFile, ?string $extraImage): void
	{
		// Get the template's dimensions
		$templateWidth  = $template['template-w'] ?? 1200;
		$templateHeight = $template['template-h'] ?? 630;
		$opacity        = $template['base-color-alpha'];

		// Start with a coloured background.
		if ($opacity <= 0.001)
		{
			// No colour has been specified; use a fully transparent color
			$alpha           = 127;
			$colorProperties = [0, 0, 0];
		}
		else
		{
			// A colour has been specified; break it into R, G, B, and A components/
			$alpha           = intval(127 - round($opacity / 100 * 127));
			$colorProperties = $this->hexToRGBA($template['base-color']);
		}

		$image = imagecreatetruecolor($templateWidth, $templateHeight);

		imagealphablending($image, false);
		$color = imagecolorallocatealpha($image, $colorProperties[0], $colorProperties[1], $colorProperties[2], $alpha);
		imagefilledrectangle($image, 0, 0, $templateWidth, $templateHeight, $color);
		imagealphablending($image, true);

		// Get the base image (resized image file or solid color image)
		if ($template['base-image'] ?? null)
		{
			// Joomla 4 append infomration to the image after either a question mark OR a hash sign. Let's fix that.
			$baseImage = $template['base-image'];
			
			$imageInfo = HTMLHelper::_('cleanImageURL', $baseImage);
			$baseImage = $imageInfo->url;

			if (!@file_exists($baseImage))
			{
				$baseImage = JPATH_ROOT . '/' . $baseImage;
			}

			[$baseImage, $baseImageWidth, $baseImageHeight] = $this->loadImageFile($baseImage);
			$baseImage = $this->resizeImage($baseImage, $baseImageWidth, $baseImageHeight, $templateWidth, $templateHeight);

			$this->applyImageEffects($baseImage, $template, 'base-image-');

			imagealphablending($image, true);
			imagecopy($image, $baseImage, 0, 0, 0, 0, $templateWidth, $templateHeight);
			imagealphablending($image, false);
		}
		
		// Layer an extra image, if necessary
		if (!empty($extraImage) && ($template['use-article-image'] !== '0'))
		{
			$image = $this->layerExtraImage($image, $extraImage, $template);
		}

		// Overlay the text (if necessary)
		$strokeColor = $template['text-stroke-color'] ?? '#000000';
		$strokeWidth = $template['text-stroke-width'] ?? 0;
		$this->renderOverlayText($text, $template, $image, $strokeColor, $strokeWidth);

		// Write out the image file...
		$imageType = $this->getNormalizedExtension($outFile);
		imagesavealpha($image, true);
		@ob_start();

		switch ($imageType)
		{
			case 'png':
				if (function_exists('imagepng'))
				{
					$ret = @imagepng($image,
						null,
						(int) (min((int) floor((100 - $this->quality) / 10), 9)));
				}

				break;

			case 'gif':
				if (function_exists('imagegif'))
				{
					$ret = @imagegif($image, null);
				}

				break;
			case 'bmp':
				if (function_exists('imagebmp'))
				{
					$ret = @imagebmp($image, null);
				}

				break;
			case 'wbmp':
				if (function_exists('imagewbmp'))
				{
					$ret = @imagewbmp($image, null);
				}

				break;
			case 'jpg':
				if (function_exists('imagejpeg'))
				{
					$ret = @imagejpeg($image, null, $this->quality);
				}

				break;
			case 'xbm':
				if (function_exists('imagexbm'))
				{
					$ret = @imagexbm($image, null);
				}

				break;
			case 'webp':
				if (function_exists('imagewebp'))
				{
					$compressionLevel = $this->quality;

					if (defined('IMG_WEBP_LOSSLESS') && $this->quality >= 90)
					{
						$compressionLevel = IMG_WEBP_LOSSLESS;
					}

					$ret = @imagewebp($image, null, $compressionLevel);
				}

				break;
		}

		$imageData = @ob_get_clean();

		imagedestroy($image);

		if (!file_put_contents($outFile, $imageData))
		{
			file_put_contents($outFile, $imageData);
		}
	}

	/**
	 * Resize and blend an extra image (if applicable) over/under the provided $image resource
	 *
	 * @param   resource     $image           GD image resource. The extra image is blended over or under it.
	 * @param   string|null  $extraImagePath  Full filesystem path of the image to blend over/under $image.
	 * @param   array        $template        The SocialMagick template which defines the blending options.
	 *
	 * @return  resource  The resulting image resource
	 *
	 * @since   1.0.0
	 */
	private function layerExtraImage($image, ?string $extraImagePath, array $template)
	{
		// If we don't have an image, it doesn't exist or is unreadable return the original image unmodified
		if (empty($extraImagePath))
		{
			return $image;
		}

		if (!@file_exists($extraImagePath))
		{
			$extraImagePath = JPATH_ROOT . '/' . $extraImagePath;
		}

		if (!@file_exists($extraImagePath) || !@is_file($extraImagePath) || !@is_readable($extraImagePath))
		{
			return $image;
		}

		// Load the image
		[$tmpImg, $width, $height] = $this->loadImageFile($extraImagePath);


		// Create a transparent canvas
		$templateWidth  = $template['template-w'] ?? 1200;
		$templateHeight = $template['template-h'] ?? 630;
		$extraCanvas    = imagecreatetruecolor($templateWidth, $templateHeight);

		imagealphablending($extraCanvas, false);
		$color = imagecolorallocatealpha($extraCanvas, 255, 255, 255, 127);
		imagefilledrectangle($extraCanvas, 0, 0, $templateWidth, $templateHeight, $color);
		imagealphablending($image, true);

		if ($template['image-cover'] == '1')
		{
			$tmpWidth  = $templateWidth;
			$tmpHeight = $templateHeight;
			$imgX      = 0;
			$imgY      = 0;
		}
		else
		{
			$tmpWidth  = $template['image-width'];
			$tmpHeight = $template['image-height'];
			$imgX      = $template['image-x'];
			$imgY      = $template['image-y'];
		}

		$anchor = $template['image-anchor'] ?? 'center';
		$tmpImg = $this->resizeImage($tmpImg, $width, $height, $tmpWidth, $tmpHeight, $anchor, $template['image-clip-transform-x'] ?? 0, $template['image-clip-transform-y'] ?? 0);

		// Apply image effects
		$this->applyImageEffects($tmpImg, $template, 'image-');

		imagealphablending($extraCanvas, true);
		imagecopy($extraCanvas, $tmpImg, $imgX, $imgY, 0, 0, $tmpWidth, $tmpHeight);
		imagedestroy($tmpImg);

		if ($template['image-z'] == 'under')
		{
			// Copy $image OVER $extraCanvas
			imagealphablending($extraCanvas, true);
			imagecopy($extraCanvas, $image, 0, 0, 0, 0, $templateWidth, $templateHeight);
			imagedestroy($image);

			$image = $extraCanvas;
		}
		else
		{
			// Copy $extraCanvas OVER image
			imagealphablending($image, true);
			imagecopy($image, $extraCanvas, 0, 0, 0, 0, $templateWidth, $templateHeight);
			imagedestroy($extraCanvas);
		}

		return $image;
	}

	/**
	 * Load an image file into a GD image and get its dimensions
	 *
	 * @param   string  $filePath  The fully qualified filesystem path of the file
	 *
	 * @return  array [$image, $width, $height]
	 *
	 * @since   1.0.0
	 */
	private function loadImageFile(string $filePath): array
	{
		// Make suer the file exists and is readable, otherwise pretend getimagesize() failed.
		if (@file_exists($filePath) && @is_file($filePath) && @is_readable($filePath))
		{
			$info = @getimagesize($filePath);
		}
		else
		{
			$info = false;
		}

		// If we can't open or get info for the image we're creating a dummy 320x200 solid black image.
		if ($info === false)
		{
			$width  = 320;
			$height = 200;
			$type   = PHP_INT_MAX;
		}
		else
		{
			[$width, $height, $type,] = $info;
		}

		switch ($type)
		{
			case IMAGETYPE_BMP:
				$image = imagecreatefrombmp($filePath);
				break;

			case IMAGETYPE_GIF:
				$image = imagecreatefromgif($filePath);
				break;

			case IMAGETYPE_JPEG:
				$image = imagecreatefromjpeg($filePath);
				break;

			case IMAGETYPE_PNG:
				$image = imagecreatefrompng($filePath);
				break;

			case IMAGETYPE_WBMP:
				$image = imagecreatefromwbmp($filePath);
				break;

			case IMAGETYPE_XBM:
				$image = imagecreatefromxpm($filePath);
				break;

			case IMAGETYPE_WEBP:
				$image = imagecreatefromwebp($filePath);
				break;

			default:
				$image = imagecreatetruecolor($width, $height);
				$black = imagecolorallocate($image, 0, 0, 0);
				imagefilledrectangle($image, 0, 0, $width, $height, $black);
				break;
		}

		return [$image, $width, $height];
	}

	/**
	 * Resize and crop an image
	 *
	 * @param   resource  $image      The GD image resource.
	 * @param   int       $oldWidth   Original image width, in pixels.
	 * @param   int       $oldHeight  Original image height, in pixels.
	 * @param   int       $newWidth   Required image width, in pixels.
	 * @param   int       $newHeight  Required image height, in pixels.
	 * @param   string    $focus      Crop focus. One of 'northwest', 'center', 'northeast', 'southwest', 'southeast'
	 *
	 * @return  resource
	 *
	 * @since   1.0.0
	 */
	private function resizeImage(&$image, int $oldWidth, int $oldHeight, int $newWidth, int $newHeight, string $focus = 'center', int $clipTransformX = 0, int $clipTransformY = 0)
	{
		if (($oldWidth === $newWidth) && ($oldHeight === $newHeight))
		{
			return $image;
		}

		// Get the resize dimensions
		[$resizeWidth, $resizeHeight] = $this->getBestFitDimensions($oldWidth, $oldHeight, $newWidth, $newHeight);

		// Resize the image
		$newImage = imagecreatetruecolor((int) $resizeWidth, (int) $resizeHeight);
		imagealphablending($newImage, false);
		$transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
		imagefilledrectangle($newImage, 0, 0, (int) $resizeWidth, (int) $resizeHeight, (int) $transparent);

		imagecopyresampled($newImage, $image, 0, 0, 0, 0, (int) $resizeWidth, (int) $resizeHeight, $oldWidth, $oldHeight);
		imagedestroy($image);
		$image = $newImage;
		unset($newImage);

		// Crop the image
		$newImage = imagecreatetruecolor((int) $resizeWidth, (int) $resizeHeight);
		imagealphablending($newImage, false);
		$transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
		imagefilledrectangle($newImage, 0, 0, (int) $resizeWidth, (int) $resizeHeight, $transparent);

		$xCenter = (int) (abs($resizeWidth - $newWidth) / 2);
		$yCenter = (int) (abs($resizeHeight - $newHeight) / 2);
		$north   = 0;
		$south   = (int) abs($resizeHeight - $newHeight);
		$west    = 0;
		$east    = (int) abs($resizeWidth - $newWidth);

		[$sourceX, $sourceY] = match ($focus)
		{
			'northwest' => [$west, $north],
			'north'     => [$xCenter, $north],
			'northeast' => [$east, $north],
			'west'      => [$west, $yCenter],
			default     => [$xCenter, $yCenter],
			'east'      => [$east, $yCenter],
			'southwest' => [$west, $south],
			'south'     => [$xCenter, $south],
			'southeast' => [$east, $south],
		};

		if ($clipTransformX != 0 || $clipTransformY != 0)
		{
			[$clipTransformX, $clipTransformY] = $this->nudgeClipRegion($resizeWidth, $resizeHeight, $sourceX, $sourceY, $newWidth, $newHeight, $clipTransformX, $clipTransformY);
			$sourceX += $clipTransformX;
			$sourceY += $clipTransformY;
		}

		imagecopyresampled($newImage, $image, 0, 0, $sourceX, $sourceY, $newWidth, $newHeight, $newWidth, $newHeight);
		imagedestroy($image);
		$image = $newImage;
		unset($newImage);

		// Sharpen the resized and cropped image. Necessary since GD doesn't do Lanczos resampling :(
		$intSharpness = $this->findSharp($oldWidth, $newWidth);

		$arrMatrix = [
			[
				-1,
				-2,
				-1,
			],
			[
				-2,
				$intSharpness + 12,
				-2,
			],
			[
				-1,
				-2,
				-1,
			],
		];

		imageconvolution($image, $arrMatrix, $intSharpness, 0);

		return $image;
	}

	/**
	 * Overlay the text on the image.
	 *
	 * @param   string    $text      The text to render.
	 * @param   array     $template  The OpenGraph image template definition.
	 * @param   resource  $image     The GD image resource to overlay the text.
	 * @param   string    $strokeColor  The stroke color (optional, default empty)
	 * @param   int       $strokeWidth  The stroke width in pixels (optional, default 0)
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function renderOverlayText(string $text, array $template, &$image, string $strokeColor = '', int $strokeWidth = 0): void
	{
		// Make sure we are told to overlay text
		if (($template['overlay_text'] ?? 1) != 1)
		{
			return;
		}

		// Get template parameters I'll be using later
		$templateWidth  = $template['template-w'] ?? 1200;
		$templateHeight = $template['template-h'] ?? 630;

		// Pre-render the text
		$fontSize = ((abs($template['font-size']) >= 1) ? abs($template['font-size']) : 24) * 0.755;
		$fontPath = $this->normalizeFont($template['text-font']);
		[$textImage, $textImageWidth, $textImageHeight]
			= $this->renderText($text, $template['text-color'], $template['text-align'], $fontPath, $fontSize, $template['text-width'], $template['text-height'], $template['text-y-center'] == 1, 1.35, $strokeColor, $strokeWidth);

		if (empty($textImage))
		{
			return;
		}

		$centerVertically   = $template['text-y-center'] == 1;
		$verticalOffset     = $centerVertically ? $template['text-y-adjust'] : $template['text-y-absolute'];
		$centerHorizontally = $template['text-x-center'] == 1;
		$horizontalOffset   = $centerHorizontally ? $template['text-x-adjust'] : $template['text-x-absolute'];

		[
			$textOffsetX, $textOffsetY,
		] = $this->getTextRenderOffsets($templateWidth, $templateHeight, $textImageWidth, $textImageHeight, $centerVertically, $verticalOffset, $centerHorizontally, $horizontalOffset);

		// Apply opacity to $textImage
		$this->applyImageEffectOpacity($textImage, $template['text-opacity'] ?? 100.0);

		// Render text
		imagealphablending($image, true);
		imagealphablending($textImage, true);
		imagecopy($image, $textImage, $textOffsetX, $textOffsetY, 0, 0, $textImageWidth + 100, $textImageHeight + 100);
		imagedestroy($textImage);
	}

	/**
	 * Render text as a transparent image that's 50px oversized in every dimension.
	 *
	 * @param   string  $text         The text to render.
	 * @param   string  $color        The hex color to render it in.
	 * @param   string  $alignment    Horizontal alignment: 'left', 'center', 'right'.
	 * @param   string  $font         The font file to render it with.
	 * @param   float   $fontSize     The font size, in points.
	 * @param   int     $maxWidth     Maximum text render width, in pixels.
	 * @param   int     $maxHeight    Maximum text render height, in pixels.
	 * @param   bool    $centerTextVertically
	 * @param   float   $lineSpacing  Line spacing factor. 1.35 is what Imagick uses by default as far as I can tell.
	 * @param   string  $strokeColor  The stroke color (optional, default empty)
	 * @param   int     $strokeWidth  The stroke width in pixels (optional, default 0)
	 *
	 * @return  array  [$image, $textWidth, $textHeight]  The width and height include the 50px margin on all sides
	 *
	 * @since   1.0.0
	 */
	private function renderText(string $text, string $color, string $alignment, string $font, float $fontSize, int $maxWidth, int $maxHeight, bool $centerTextVertically, float $lineSpacing = 1.35, string $strokeColor = '', int $strokeWidth = 0)
	{
		// Pre-process text
		$text = $this->preProcessText($text);

		// Get the color
		$colorValues = $this->hexToRGBA($color);

		// Quick escape route: if the rendered string length is smaller than the maximum width
		$lines = $this->toLines($text, $fontSize, $font, $maxWidth, $strokeWidth);

		// Apply the line spacing
		$lines = $this->applyLineSpacing($lines, $lineSpacing);

		// Cut off the lines which would get us over the maximum height
		$lineCountBeforeMaxHeight = count($lines);
		$lines                    = $this->applyMaximumHeight($lines, $maxHeight);
		$lineCountAfterMaxHeight  = count($lines);

		if (empty($lines))
		{
			return [null, 0, 0];
		}

		// Add ellipses to the last line if the text didn't fit.
		if ($lineCountAfterMaxHeight < $lineCountBeforeMaxHeight)
		{
			$lastLine = array_pop($lines);

			// Try adding ellipses to the last line
			$testText       = $lastLine['text'] . '…';
			$testDimensions = $this->lineSize($testText, $fontSize, $font, $strokeWidth);

			/**
			 * If the last line is too big to fit remove the ellipses, the last word and space and re-add the ellipses,
			 * as long as there are more than one words.
			 */
			if ($testDimensions[0] > $maxWidth)
			{
				$words = explode(' ', $lastLine['text']);

				if (count($words) > 1)
				{
					array_pop($words);
					$lastLine['text'] = implode(' ', $words) . '…';
				}

				$testText       = trim($lastLine['text']);
				$testDimensions = $this->lineSize($testText, $fontSize, $font, $strokeWidth);
			}

			$lastLine['text']   = $testText;
			$lastLine['width']  = $testDimensions[0];
			$lastLine['height'] = $testDimensions[1];

			$lines[] = $lastLine;
		}

		// Align the lines horizontally
		$lines = $this->horizontalAlignLines($lines, $alignment, $maxWidth);

		// Get the real width and height of the text
		$textWidth  = array_reduce($lines, fn(int $carry, array $line) => max($carry, $line['width']), 0);
		$textHeight = array_reduce($lines, fn(int $carry, array $line) => max($carry, $line['y'] + $line['height']), 0);

		// Create a transparent image with the text dimensions
		$image = imagecreatetruecolor($maxWidth + 100, $maxHeight + 100);
		imagealphablending($image, false);

		if (!$this->debugText)
		{
			$transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
			imagefilledrectangle($image, 0, 0, $maxWidth + 100, $maxHeight + 100, $transparent);
		}
		else
		{
			$transparent = imagecolorallocatealpha($image, 255, 0, 0, 90);
			imagefilledrectangle($image, 0, 0, 50, $maxHeight + 100, $transparent);
			imagefilledrectangle($image, $maxWidth + 50, 0, $maxWidth + 100, $maxHeight + 100, $transparent);
			imagefilledrectangle($image, 50, 0, $maxWidth + 50, 50, $transparent);
			imagefilledrectangle($image, 50, $maxHeight + 50, $maxWidth + 50, $maxHeight + 100, $transparent);

			$yellow = imagecolorallocatealpha($image, 255, 255, 0, 80);
			imagefilledrectangle($image, 50, 50, $maxWidth + 50, $maxHeight + 50, $yellow);

			$purple = imagecolorallocate($image, 255, 0, 255);
			imagerectangle($image, 0, 0, $maxWidth + 99, $maxHeight + 99, $purple);
		}

		imagealphablending($image, true);

		// Render stroke if specified
		if (!empty($strokeColor) && $strokeWidth > 0)
		{
			$strokeColorValues = $this->hexToRGBA($strokeColor);
			$strokeColorResource = imagecolorallocate($image, $strokeColorValues[0], $strokeColorValues[1], $strokeColorValues[2]);

			// Get the y offset because GD is doing weird things
			$boundingBox   = imagettfbbox($fontSize, 0, $font, $lines[0]['text']);
			$yOffset       = -$boundingBox[7] + 1;
			$centerYOffset = 0;

			// At this point the text would be anchored to the top of the text box. We want it centred in the box.
			if ($centerTextVertically)
			{
				$centerYOffset = (int) ceil(($maxHeight - $textHeight) / 2.0);
			}

			// Draw stroke by rendering text multiple times with offsets
			foreach ($lines as $line)
			{
				$x1 = 50 + $line['x'] + $strokeWidth;
				$y1 = 50 + $line['y'] + $yOffset + $centerYOffset + $strokeWidth;

				// Create stroke effect by drawing text at offset positions
				for ($xOffset = -$strokeWidth; $xOffset <= $strokeWidth; $xOffset++)
				{
					for ($yOffsetStroke = -$strokeWidth; $yOffsetStroke <= $strokeWidth; $yOffsetStroke++)
					{
						if ($xOffset !== 0 || $yOffsetStroke !== 0)
						{
							imagettftext($image, $fontSize, 0, (int) ($x1 + $xOffset), (int) ($y1 + $yOffsetStroke), $strokeColorResource, $font, $line['text']);
						}
					}
				}
			}
		}

		// Render the main text on top
		$colorResource = imagecolorallocate($image, $colorValues[0], $colorValues[1], $colorValues[2]);

		// Get the y offset because GD is doing weird things
		$boundingBox   = imagettfbbox($fontSize, 0, $font, $lines[0]['text']);
		$yOffset       = -$boundingBox[7] + 1;
		$centerYOffset = 0;

		// At this point the text would be anchored to the top of the text box. We want it centred in the box.
		if ($centerTextVertically)
		{
			$centerYOffset = (int) ceil(($maxHeight - $textHeight) / 2.0);
		}

		foreach ($lines as $line)
		{
			$x1 = (int) (50 + $line['x'] + $strokeWidth);
			$y1 = (int) (50 + $line['y'] + $centerYOffset + $strokeWidth);

			imagettftext($image, $fontSize, 0, $x1, $y1 + (int) $yOffset, $colorResource, $font, $line['text']);

			if ($this->debugText)
			{
				$this->imageDottedRectangle($image,
					$x1 - $strokeWidth,
					$y1 - $strokeWidth,
					$x1 + $line['width'] - $strokeWidth,
					$y1 + $line['height'] - $strokeWidth + 1,
					$purple,
					5,
					5);
			}
		}

		return [$image, $maxWidth, $maxHeight];
	}

	/**
	 * Draw a dotted rectangle
	 *
	 * @param   resource  $image       The GD image resource
	 * @param   int       $x1          Top-left X coordinate
	 * @param   int       $y1          Top-left Y coordinate
	 * @param   int       $x2          Bottom-right X coordinate
	 * @param   int       $y2          Bottom-right Y coordinate
	 * @param   int       $color       The color resource
	 * @param   int       $dashLength  Length of each dash (default 5)
	 * @param   int       $gapLength   Length of each gap (default 5)
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function imageDottedRectangle($image, int $x1, int $y1, int $x2, int $y2, $color, int $dashLength = 5, int $gapLength = 5): void
	{
		// Ensure coordinates are in correct order
		if ($x1 > $x2) {
			[$x1, $x2] = [$x2, $x1];
		}
		if ($y1 > $y2) {
			[$y1, $y2] = [$y2, $y1];
		}

		$totalPattern = $dashLength + $gapLength;

		// Draw top edge
		for ($x = $x1; $x <= $x2; $x++)
		{
			$position = ($x - $x1) % $totalPattern;
			if ($position < $dashLength)
			{
				imagesetpixel($image, $x, $y1, $color);
			}
		}

		// Draw bottom edge
		for ($x = $x1; $x <= $x2; $x++)
		{
			$position = ($x - $x1) % $totalPattern;
			if ($position < $dashLength)
			{
				imagesetpixel($image, $x, $y2, $color);
			}
		}

		// Draw left edge
		for ($y = $y1 + 1; $y < $y2; $y++)
		{
			$position = ($y - $y1) % $totalPattern;
			if ($position < $dashLength)
			{
				imagesetpixel($image, $x1, $y, $color);
			}
		}

		// Draw right edge
		for ($y = $y1 + 1; $y < $y2; $y++)
		{
			$position = ($y - $y1) % $totalPattern;
			if ($position < $dashLength)
			{
				imagesetpixel($image, $x2, $y, $color);
			}
		}
	}

	/**
	 * Returns the rendering offsets for the text image over a base image.
	 *
	 * @param   int   $baseImageWidth      Base image width, in pixels.
	 * @param   int   $baseImageHeight     Base image height, in pixels.
	 * @param   int   $textImageWidth      Text image width, in pixels. This includes the 50px padding on either side.
	 * @param   int   $textImageHeight     Text image height, in pixels. This includes the 50px padding on either side.
	 * @param   bool  $centerVertically    Should I center the text vertically over the base image?
	 * @param   int   $verticalOffset      Offset in the vertical direction. Positive moves text down, negative moves
	 *                                     text up.
	 * @param   bool  $centerHorizontally  Should I center the text horizontally over the base image?
	 * @param   int   $horizontalOffset    Offset in the horizontal direction. Positive moves text right, negative
	 *                                     moves text left.
	 *
	 * @return  int[] Returns [x, y] where the text image should be rendered over the base image
	 *
	 * @since   1.0.0
	 */
	private function getTextRenderOffsets(int $baseImageWidth, int $baseImageHeight, int $textImageWidth, int $textImageHeight, bool $centerVertically = false, int $verticalOffset = 0, bool $centerHorizontally = false, int $horizontalOffset = 0): array
	{
		// Remember that our text image has 50px of margin on all sides? We need to subtract it.
		$realTextWidth  = $textImageWidth - 100;
		$realTextHeight = $textImageHeight - 100;

		// Start at the top left
		$x = 0;
		$y = 0;

		// If centering vertically we need to calculate a different starting Y coordinate
		if ($centerVertically)
		{
			// The -50 at the end is removing half of our 100px margin
			$y = (int) (($baseImageHeight - $realTextHeight) / 2) - 50;
		}

		// Apply any vertical offset
		$y += $verticalOffset;

		// If centering horizontally we need to calculate a different starting X coordinate
		if ($centerHorizontally)
		{
			// The -50 at the end is removing half of our 100px margin
			$x = (int) (($baseImageWidth - $realTextWidth) / 2) - 50;
		}

		// Apply any horizontal offset
		$x += $horizontalOffset;

		// Remember the 50px margin? We need to subtract it (yes, it may take us to negative dimensions, this is normal)
		$x -= 50;
		$y -= 50;

		return [$x, $y];
	}

	/**
	 * Sharpen images function.
	 *
	 * @param   int  $intOrig
	 * @param   int  $intFinal
	 *
	 * @return  int
	 * @since   1.0.0
	 *
	 * @see     https://github.com/MattWilcox/Adaptive-Images/blob/master/adaptive-images.php#L109
	 */
	private function findSharp(int $intOrig, int $intFinal): int
	{
		$intFinal = $intFinal * (750.0 / $intOrig);
		$intA     = 52;
		$intB     = -0.27810650887573124;
		$intC     = .00047337278106508946;
		$intRes   = $intA + $intB * $intFinal + $intC * $intFinal * $intFinal;

		return max(round($intRes), 0);
	}

	/**
	 * Returns the width and height of a line of text
	 *
	 * @param   string  $text         The text to render
	 * @param   float   $size         Font size, in points
	 * @param   string  $font         Font file
	 * @param   int     $strokeWidth  The stroke width in pixels (optional, default 0)
	 *
	 * @return  array  [width, height]
	 *
	 * @since   1.0.0
	 */
	private function lineSize(string $text, float $size, string $font, int $strokeWidth = 0): array
	{
		$boundingBox = imagettfbbox($size, 0, $font, $text);

		return [
			$boundingBox[2] - $boundingBox[0] + 2 * $strokeWidth,
			$boundingBox[1] - $boundingBox[7] + 2 * $strokeWidth,
		];

	}

	/**
	 * Chop the string to lines which are rendered up to a given maximum width
	 *
	 * @param   string  $text         The text to chop
	 * @param   float   $size         Font size, in points
	 * @param   string  $font         Font file
	 * @param   int     $maxWidth     Maximum width for the rendered text, in pixels
	 * @param   int     $strokeWidth  The stroke width in pixels (optional, default 0)
	 *
	 * @return  array[] The individual lines along with their width and height metrics
	 *
	 * @since   1.0.0
	 */
	private function toLines(string $text, float $size, string $font, int $maxWidth, int $strokeWidth = 0): array
	{
		// Is the line narrow enough to call it a day?
		$lineDimensions = $this->lineSize($text, $size, $font, $strokeWidth);

		if ($lineDimensions[0] < $maxWidth)
		{
			return [
				[
					'text'   => $text,
					'width'  => $lineDimensions[0],
					'height' => $lineDimensions[1],
				],
			];
		}

		// Too wide. We'll walk one word at a time to construct individual lines.
		$words             = explode(' ', $text);
		$lines             = [];
		$currentLine       = '';
		$currentDimensions = [0, 0];

		while (!empty($words))
		{
			$nextWord       = array_shift($words);
			$testLine       = $currentLine . ($currentLine ? ' ' : '') . $nextWord;
			$testDimensions = $this->lineSize($testLine, $size, $font, $strokeWidth);
			$isOversize     = $testDimensions[0] > $maxWidth;

			// Oversize word. Can't do much, your layout will suffer. I won't be doing hyphenation here!
			if ($isOversize && ($currentDimensions[0] === 0))
			{
				$lines[]           = [
					'text'   => $testLine,
					'width'  => $testDimensions[0],
					'height' => $testDimensions[1],
				];
				$currentLine       = '';
				$currentDimensions = [0, 0];
			}
			// We exceeded the maximum width. Let's commit the previous line and push back the current word to the array
			elseif ($isOversize)
			{
				$lines[]           = [
					'text'   => $currentLine,
					'width'  => $currentDimensions[0],
					'height' => $currentDimensions[1],
				];
				$currentLine       = '';
				$currentDimensions = [0, 0];

				array_unshift($words, $nextWord);
			}
			// We have not reached the limit just yet.
			else
			{
				$currentLine       = $testLine;
				$currentDimensions = $testDimensions;
			}
		}

		if (!empty($currentLine))
		{
			$lines[] = [
				'text'   => $currentLine,
				'width'  => $currentDimensions[0],
				'height' => $currentDimensions[1],
			];
		}

		return $lines;
	}

	/**
	 * Apply the horizontal alignment for the given lines.
	 *
	 * Sets the `x` element of each line accordingly.
	 *
	 * @param   array   $lines      Text lines definitions to align horizontally.
	 * @param   string  $alignment  Horizontal alignment: 'left', 'center', or 'right'.
	 * @param   int     $maxWidth   Maximum rendered image width, in pixels
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function horizontalAlignLines(array $lines, string $alignment, int $maxWidth): array
	{
		return array_map(function (array $line) use ($alignment, $maxWidth): array {
			switch ($alignment)
			{
				case 'left':
					$line['x'] = 0;
					break;

				case 'center':
					$line['x'] = ($maxWidth - $line['width']) / 2.0;
					break;

				case 'right':
					$line['x'] = $maxWidth - $line['width'];
					break;
			}

			return $line;
		}, $lines);
	}

	/**
	 * Apply a line spacing factor.
	 *
	 * All lines will have a height equal to the highest lines times $lineSpacing. This method sets the `y` element of
	 * each line accordingly.
	 *
	 * @param   array  $lines        Text lines definitions to apply line spacing to.
	 * @param   float  $lineSpacing  The line spacing factor, e.g. 1.05 for 5% whitespace between lines
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	private function applyLineSpacing(array $lines, float $lineSpacing): array
	{
		// Get the maximum line height
		$maxHeight = array_reduce($lines, fn(int $carry, array $line): int => max($carry, $line['height']), 0);

		$lineHeight = (int) ceil($maxHeight * $lineSpacing);
		$i          = -1;

		return array_map(function (array $line) use ($lineHeight, &$i) {
			$i++;
			$line['y'] = $i * $lineHeight;

			return $line;
		}, $lines);
	}

	/**
	 * Strips off any lines which would cause the text to exceed the maximum permissible image height.
	 *
	 * @param   array  $lines      The line definitions.
	 * @param   int    $maxHeight  The maximum permissible image height, in pixels.
	 *
	 * @return  array  The remaining lines
	 *
	 * @since   1.0.0
	 */
	private function applyMaximumHeight(array $lines, int $maxHeight): array
	{
		return array_filter($lines, fn(array $line): bool => ($line['y'] + $line['height']) <= $maxHeight);
	}


	/**
	 * Apply various image effects to the base image using GD.
	 *
	 * @param   resource  $image     The GD image resource to apply effects to
	 * @param   array     $template  The template configuration
	 * @param   string    $prefix    The template parameters prefix
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applyImageEffects(&$image, array $template, string $prefix = 'image-'): void
	{
		// Apply grayscale effect
		if (isset($template[$prefix . 'grayscale']) && $template[$prefix . 'grayscale'] > 0)
		{
			$this->applyImageEffectGrayscale($image, (float) $template[$prefix . 'grayscale']);
		}

		// Apply sepia effect
		if (isset($template[$prefix . 'sepia']) && $template[$prefix . 'sepia'] > 0)
		{
			$this->applyImageEffectSepia($image, (float) $template[$prefix . 'sepia']);
		}

		// Apply transparency/opacity effect
		if (isset($template[$prefix . 'opacity']) && $template[$prefix . 'opacity'] < 100 && $template[$prefix . 'opacity'] >= 0)
		{
			$this->applyImageEffectOpacity($image, (float) $template[$prefix . 'opacity']);
		}
	}

	/**
	 * Apply grayscale effect to an image
	 *
	 * @param   resource  $image      The GD image resource
	 * @param   float     $intensity  Grayscale intensity (0-100)
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applyImageEffectGrayscale(&$image, float $intensity): void
	{
		$intensity = $intensity / 100.0;

		if ($intensity >= 1.0)
		{
			// Full grayscale - use GD's built-in filter
			imagefilter($image, IMG_FILTER_GRAYSCALE);
		}
		else
		{
			// Partial grayscale - create a grayscale version and blend
			$width          = imagesx($image);
			$height         = imagesy($image);
			$grayscaleImage = imagecreatetruecolor($width, $height);

			// Enable alpha blending and preserve transparency
			imagealphablending($grayscaleImage, false);
			imagesavealpha($grayscaleImage, true);
			$transparent = imagecolorallocatealpha($grayscaleImage, 0, 0, 0, 127);
			imagefill($grayscaleImage, 0, 0, $transparent);

			// Copy and convert to grayscale
			imagecopy($grayscaleImage, $image, 0, 0, 0, 0, $width, $height);
			imagefilter($grayscaleImage, IMG_FILTER_GRAYSCALE);

			// Blend based on intensity using imagecopymerge
			imagealphablending($image, true);
			imagealphablending($grayscaleImage, true);

			// Convert intensity to alpha value (0-100 for imagecopymerge)
			$alpha = (int) round($intensity * 100);
			imagecopymerge($image, $grayscaleImage, 0, 0, 0, 0, $width, $height, $alpha);

			imagedestroy($grayscaleImage);
		}
	}

	/**
	 * Apply sepia effect to an image
	 *
	 * @param   resource  $image      The GD image resource
	 * @param   float     $intensity  Sepia intensity (0-100)
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applyImageEffectSepia(&$image, float $intensity): void
	{
		$intensity = $intensity / 100.0;

		if ($intensity >= 1.0)
		{
			// Full sepia using custom matrix
			$this->applySepiaFilter($image);
		}
		else
		{
			// Partial sepia - create sepia version and blend
			$width      = imagesx($image);
			$height     = imagesy($image);
			$sepiaImage = imagecreatetruecolor($width, $height);

			// Enable alpha blending and preserve transparency
			imagealphablending($sepiaImage, false);
			imagesavealpha($sepiaImage, true);
			$transparent = imagecolorallocatealpha($sepiaImage, 0, 0, 0, 127);
			imagefill($sepiaImage, 0, 0, $transparent);

			// Copy and apply sepia
			imagecopy($sepiaImage, $image, 0, 0, 0, 0, $width, $height);
			$this->applySepiaFilter($sepiaImage);

			// Blend based on intensity
			imagealphablending($image, true);
			imagealphablending($sepiaImage, true);

			$alpha = (int) round($intensity * 100);
			imagecopymerge($image, $sepiaImage, 0, 0, 0, 0, $width, $height, $alpha);

			imagedestroy($sepiaImage);
		}
	}

	/**
	 * Apply opacity effect to an image
	 *
	 * @param   resource  $image    The GD image resource
	 * @param   float     $opacity  Opacity value (0-100)
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applyImageEffectOpacity(&$image, float $opacity): void
	{
		if ($opacity >= 100.0 || $opacity < 0.0)
		{
			return;
		}

		$width = imagesx($image);
		$height = imagesy($image);

		// Create a new image that will hold our semi-transparent result
		$newImage = imagecreatetruecolor($width, $height);
		imagealphablending($newImage, false);
		imagesavealpha($newImage, true);

		// Process each pixel to adjust opacity
		for ($x = 0; $x < $width; $x++)
		{
			for ($y = 0; $y < $height; $y++)
			{
				$rgba = imagecolorat($image, $x, $y);

				// Extract color components
				$alpha = ($rgba >> 24) & 0x7F;
				$red   = ($rgba >> 16) & 0xFF;
				$green = ($rgba >> 8) & 0xFF;
				$blue  = $rgba & 0xFF;

				// Calculate new alpha value
				$newAlpha = min(127, $alpha + (int) ((127 - $alpha) * (100 - $opacity) / 100));

				// Allocate new color with adjusted opacity
				$color = imagecolorallocatealpha($newImage, $red, $green, $blue, $newAlpha);
				imagesetpixel($newImage, $x, $y, $color);
			}
		}

		// Replace original image with the new one
		imagedestroy($image);
		$image = $newImage;
	}

	/**
	 * Apply a sepia filter to a GD image resource using color matrix transformation.
	 *
	 * @param   resource  $image  The GD image resource
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applySepiaFilter(&$image): void
	{
		// Method 1: Using built-in imagefilter (most efficient)
		if (function_exists('imagefilter'))
		{
			// Convert to grayscale first
			imagefilter($image, IMG_FILTER_GRAYSCALE);
			
			// Apply sepia tone using colorize
			imagefilter($image, IMG_FILTER_COLORIZE, 100, 50, 0, 0);
			
			return;
		}
		
		// Method 2: Using imageconvolution for sepia matrix (fallback if imagefilter not available)
		if (function_exists('imageconvolution'))
		{
			$this->applySepiaUsingConvolution($image);
			
			return;
		}
		
		// Method 3: Fallback to pixel-by-pixel (original implementation)
		$this->applySepiaFilterPixelByPixel($image);
	}

	
	/**
	 * Apply sepia effect using color overlay technique (more efficient than pixel-by-pixel)
	 *
	 * @param   resource  $image  The GD image resource
	 * @return  void
	 * @since   3.0.0
	 */
	private function applySepiaUsingConvolution(&$image): void
	{
		/**
		 * Sepia convolution matrix coefficients
		 * @link https://lucasdavid.github.io/blog/computer-vision/vectorization/
		 */
		$matrix = [
			[0.393, 0.769, 0.189],
			[0.349, 0.686, 0.168],
			[0.272, 0.534, 0.131],
		];

		// Apply the convolution
		imageconvolution($image, $matrix, 1, 0);
	}

	/**
	 * Original pixel-by-pixel sepia implementation.
	 *
	 * This is the same as using a convolution matrix, but instead of it being implemented in fast, efficient C code it
	 * is implemented in pure PHP. The performance is abysmal. If you hit this code you're better off using underlay,
	 * apply grayscale proportional to the sepia effect you want, and finally set a color layer with color value #704214
	 * (sepia brown) with transparency equal to the 100 complement of the grayscale opacity you used.
	 *
	 * @param   resource  $image  The GD image resource
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	private function applySepiaFilterPixelByPixel(&$image): void
	{
		$width = imagesx($image);
		$height = imagesy($image);
		
		// Enable alpha blending for proper color handling
		imagealphablending($image, false);
		imagesavealpha($image, true);
		
		// Apply sepia transformation pixel by pixel
		for ($x = 0; $x < $width; $x++)
		{
			for ($y = 0; $y < $height; $y++)
			{
				$rgba = imagecolorat($image, $x, $y);
				$alpha = ($rgba & 0x7F000000) >> 24;
				$red = ($rgba & 0xFF0000) >> 16;
				$green = ($rgba & 0x00FF00) >> 8;
				$blue = $rgba & 0x0000FF;
				
				// Sepia transformation matrix
				$newRed = min(255, (int) round(($red * 0.393) + ($green * 0.769) + ($blue * 0.189)));
				$newGreen = min(255, (int) round(($red * 0.349) + ($green * 0.686) + ($blue * 0.168)));
				$newBlue = min(255, (int) round(($red * 0.272) + ($green * 0.534) + ($blue * 0.131)));
				
				$newColor = imagecolorallocatealpha($image, $newRed, $newGreen, $newBlue, $alpha);
				imagesetpixel($image, $x, $y, $newColor);
			}
		}
	}
}