<?php

/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @noinspection PhpComposerExtensionStubsInspection */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ImageGenerator\Adapter;

use Imagick;
use ImagickDraw;
use ImagickException;
use ImagickPixel;
use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') || die();

/**
 * An OpenGraph image renderer using the ImageMagic library and the `imagick` PHP extension..
 *
 * @since  1.0.0
 */
class ImagickAdapter extends AbstractAdapter implements AdapterInterface
{
	public function makeImage(string $text, array $template, string $outFile, ?string $extraImage): void
	{
		/**
		 * ***** !!! WARNING !!! ***** !!! DO NOT REMOVE THIS LINE !!!! *****
		 *
		 * There is a really weird issue with Joomla 4 (does not happen in Joomla 3). This code:
		 * $foo = new Imagick();
		 * $foo->destroy();
		 * set_time_limit(30);
		 * causes an immediate timeout to trigger **even if** the wall clock time elapsed is under one second.
		 *
		 * Joomla calls set_time_limit() in its filesystem functions. So, any attempt to write the generated image file
		 * on a site using the FTP layer would result in an inexplicable error about the time limit being exceeded even
		 * when it doesn't happen.
		 *
		 * Even setting the time limits to ludicrous values, like 900000 (over a day!), triggers this weird bug.
		 *
		 * The only thing that works is setting a zero time limit.
		 *
		 * This is definitely a weird Joomla 4 issue which I am strongly disinclined to debug. I am just going to go
		 * through with this unholy, dirty trick and call it a day.
		 */
		$this->setTimeLimit(0);

		// Get the template's dimensions
		$templateWidth  = $template['template-w'] ?? 1200;
		$templateHeight = $template['template-h'] ?? 630;

		// Setup the base image upon which we will superimpose the layered image (if any) and the text
		$image = new Imagick();

		// Create a new, transparent backdrop
		$transparentPixel = new ImagickPixel('transparent');
		$image->newImage($templateWidth, $templateHeight, $transparentPixel);
		$transparentPixel->destroy();

		// Replace the backdrop with a solid color backdrop if the opacity is greater than zero.
		$opacity = $template['base-color-alpha'];

		if ($opacity > 0.0001)
		{
			$pixel = new ImagickPixel($this->hexColorWithOpacity($template['base-color'], $opacity));

			$image->newImage($templateWidth, $templateHeight, $pixel);

			$pixel->destroy();
		}

		// Overlay the base image
		if ($template['base-image'] ?? null)
		{
			// So, Joomla 4 adds some crap to the image. Let's fix that.
			$baseImage = $template['base-image'];

			$imageInfo = HTMLHelper::_('cleanImageURL', $baseImage);
			$baseImage = $imageInfo->url;

			if (!@file_exists($baseImage))
			{
				$baseImage = JPATH_ROOT . '/' . $baseImage;
			}

			$tmpImg = $this->resize($baseImage, $templateWidth, $templateHeight);
			$imgX   = 0;
			$imgY   = 0;

			$this->applyImageEffects($tmpImg, $template, 'base-image-');

			$image->compositeImage($tmpImg, Imagick::COMPOSITE_OVER, $imgX, $imgY);
		}

		// Add extra image
		if ($template['use-article-image'] != '0' && $extraImage)
		{
			$extraCanvas      = new Imagick();
			$transparentPixel = new ImagickPixel('transparent');

			$extraCanvas->newImage($templateWidth, $templateHeight, $transparentPixel);
			$transparentPixel->destroy();

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

			$anchor         = $template['image-anchor'] ?? 'center';
			$featureSize    = $template['image_object_size'] ?? 0;
			$objectPadding  = $template['image_object_padding'] ?? 0;

			$tmpImg = $this->resize(
				$extraImage,
				$tmpWidth,
				$tmpHeight,
				$anchor,
				$template['image-clip-transform-x'] ?? 0,
				$template['image-clip-transform-y'] ?? 0,
				$featureSize,
				$objectPadding
			);

			$extraCanvas->compositeImage(
				$tmpImg,
				Imagick::COMPOSITE_DEFAULT,
				(int) $imgX,
				(int) $imgY
			);


			// Apply image effects
			$this->applyImageEffects($extraCanvas, $template, 'image-');

			if ($template['image-z'] == 'under')
			{
				$extraCanvas->compositeImage(
					$image,
					Imagick::COMPOSITE_OVER,
					-((int) $imgX),
					-((int) $imgY));
				$image->compositeImage(
					$extraCanvas,
					Imagick::COMPOSITE_COPY,
					(int) $imgX,
					(int) $imgY);

			}
			elseif ($template['image-z'] == 'over')
			{
				$image->compositeImage(
					$extraCanvas,
					Imagick::COMPOSITE_DEFAULT,
					(int) $imgX,
					(int) $imgY);
			}

			$extraCanvas->destroy();
		}

		// Overlay the text (if necessary)
		$strokeColor = $template['text-stroke-color'] ?? '#000000';
		$strokeWidth = $template['text-stroke-width'] ?? 0;
		$this->renderOverlayText($text, $template, $image, $strokeColor, $strokeWidth);

		// Write the image
		$imageFormat = $this->getNormalizedExtension($outFile);
		$image->setImageFormat($imageFormat);

		switch ($imageFormat)
		{
			case 'jpg':
				$image->setImageCompression(Imagick::COMPRESSION_JPEG);
				$image->setImageCompressionQuality($this->quality);
				break;

			case 'png':
				$image->setImageCompressionQuality($this->quality);
				$image->setOption('png:compression-strategy', '0'); // 0-4, compression strategy

			case 'webp':
				$image->setImageCompressionQuality($this->quality);
				$image->setOption('webp:lossless', $this->quality >= 90 ? 'true' : 'false');
				break;
		}

		file_put_contents($outFile, $image);

		$image->clear();
	}

	public function isSupported(): bool
	{
		// Quick escape route if the Imagick extension is not loaded / compiled in.
		if (function_exists('extension_loaded') && extension_loaded('imagick') !== true)
		{
			return false;
		}

		// Make sure the Imagick and ImagickPixel classes are not disabled.
		return class_exists('Imagick') && class_exists('ImagickPixel');
	}

	private function hexColorWithOpacity(string $baseColorHex, int $opacity): string
	{
		// Clamp the opacity
		$opacity = max(min($opacity, 100), 0);

		// Extreme values fon't have to go through calculations.
		if ($opacity == 0)
		{
			return $baseColorHex . '00';
		}
		elseif ($opacity == 100)
		{
			return $baseColorHex . 'FF';
		}

		$alpha = (int) round($opacity * 255 / 100);
		$hex   = substr(base_convert(($alpha + 0x10000), 10, 16), -2, 2);

		return $baseColorHex . $hex;
	}

	/**
	 * Resize and crop an image
	 *
	 * @param   string   $src    The path to the original image.
	 * @param   numeric  $new_w  New width, in pixels.
	 * @param   numeric  $new_h  New height, in pixels.
	 * @param   string   $focus  Focus of the image; default is center.
	 *
	 * @return  Imagick
	 *
	 * @throws ImagickException
	 *
	 * @since   1.0.0
	 */
	private function resize(
		string $src,
		int    $new_w,
		int    $new_h,
		string $focus = 'center',
		int    $clipTransformX = 0,
		int    $clipTransformY = 0,
		int    $desiredSize = 0,
		int    $objectPadding = 0
	): Imagick
	{
		$image = new Imagick($src);

		/**
		 * Set a transparent background for SVG files.
		 *
		 * The `ImageMagick` library in PHP preserves the transparency of SVG images by default when reading them into
		 * an instance. However, if your SVG has a transparent background, and you need to ensure it stays that way
		 * during processing or conversion, explicitly setting the image's background color as transparent is
		 * recommended.
		 */
		if (str_ends_with($src, '.svg'))
		{
			$image->setImageBackgroundColor(new ImagickPixel('transparent'));
			$image->setImageAlphaChannel(Imagick::CHANNEL_ALPHA);
		}

		$w = $image->getImageWidth();
		$h = $image->getImageHeight();

		[$resize_w, $resize_h] = $this->getBestFitDimensions($w, $h, $new_w, $new_h);
		$scalingFactor = $resize_w / $w;

		// Conditionally change the scaling factor to fit the detected object into the requested width.
		if ($focus === 'detect')
		{
			[
				$featureX1, $featureY1, $featureX2, $featureY2,
			] = $this->getObjectCoordinates($image);

			$doResizeInfo = $this->resizeUsingDetectedObjects(
				$featureX1,
				$featureY1,
				$featureX2,
				$featureY2,
				$scalingFactor,
				$desiredSize,
				$objectPadding
			);

			if ($doResizeInfo['changeScale'])
			{
				$scalingFactor = $doResizeInfo['scalingFactor'];
				$resize_w      = $scalingFactor * $w;
				$resize_h      = $scalingFactor * $h;
			}

			$featureX1 = $doResizeInfo['originX'];
			$featureY1 = $doResizeInfo['originY'];
		}

		$image->resizeImage((int) $resize_w, (int) $resize_h, Imagick::FILTER_LANCZOS, 0.9);

		$xCenter = (int) (($resize_w - $new_w) / 2);
		$yCenter = (int) (($resize_h - $new_h) / 2);
		$north   = 0;
		$south   = (int) ($resize_h - $new_h);
		$west    = 0;
		$east    = (int) ($resize_w - $new_w);

		[$sourceX, $sourceY] = match ($focus)
		{
			'northwest' => [$west, $north],
			'north' => [$xCenter, $north],
			'northeast' => [$east, $north],
			'west' => [$west, $yCenter],
			default => [$xCenter, $yCenter],
			'east' => [$east, $yCenter],
			'southwest' => [$west, $south],
			'south' => [$xCenter, $south],
			'southeast' => [$east, $south],
			'detect' => [$featureX1, $featureY1]
		};

		// If object detection fails, it sets the source X and Y to NULL. Catch that and use the image centre instead.
		if ($sourceX === null && $sourceY === null)
		{
			[$sourceX, $sourceY] = [$xCenter, $yCenter];
		}

		if ($clipTransformX != 0 || $clipTransformY != 0)
		{
			[
				$clipTransformX, $clipTransformY,
			] = $this->nudgeClipRegion($resize_w, $resize_h, $sourceX, $sourceY, $new_w, $new_h, $clipTransformX, $clipTransformY);
			$sourceX += $clipTransformX;
			$sourceY += $clipTransformY;
		}

		$image->cropImage((int) $new_w, (int) $new_h, $sourceX, $sourceY);

		return $image;
	}

	/**
	 * Overlay the text on the image.
	 *
	 * @param   string   $text         The text to render.
	 * @param   array    $template     The OpenGraph image template definition.
	 * @param   Imagick  $image        The image to overlay the text.
	 * @param   string   $strokeColor  The stroke color (optional, default empty)
	 * @param   int      $strokeWidth  The stroke width in pixels (optional, default 0)
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function renderOverlayText(string $text, array $template, Imagick &$image, string $strokeColor = '', int $strokeWidth = 0): void
	{
		// Make sure we are told to overlay text
		if (($template['overlay_text'] ?? 1) != 1)
		{
			return;
		}

		// Normalize text
		$text = $this->preProcessText($text, false);

		// Break text into lines that fit within the box, adding ellipsis if needed
		$fittedText = $this->fitTextIntoLines($text, $template, $strokeWidth);

		if (empty($fittedText))
		{
			return;
		}

		// Set up the text
		$theText = new Imagick();
		$theText->setBackgroundColor('transparent');

		/* Font properties */
		$theText->setFont($this->normalizeFont($template['text-font']));

		if ($template['font-size'] > 0)
		{
			$theText->setPointSize($template['font-size']);
		}

		/* Create text */
		$gravity = match ($template['text-align'])
		{
			'left' => Imagick::GRAVITY_NORTHWEST,
			'right' => Imagick::GRAVITY_NORTHEAST,
			default => Imagick::GRAVITY_NORTH,
		};

		$theText->setGravity($gravity);

		// Add stroke if specified
		if (!empty($strokeColor) && $strokeWidth > 0)
		{
			// Create stroke version first
			$strokeText = new Imagick();
			$strokeText->setBackgroundColor('transparent');
			$strokeText->setFont($this->normalizeFont($template['text-font']));

			if ($template['font-size'] > 0)
			{
				$strokeText->setPointSize($template['font-size']);
			}

			$strokeText->setGravity($gravity);

			// Create a `caption:` pseudo image with stroke
			$strokeText->newPseudoImage($template['text-width'],
				$template['text-height'],
				'caption:' . $fittedText);
			$strokeText->setBackgroundColor('transparent');

			// Remove extra height
			$strokeText->trimImage(0.0);

			// Apply stroke color
			$strokeClut       = new Imagick();
			$strokeColorPixel = new ImagickPixel($strokeColor);
			$strokeClut->newImage(1, 1, $strokeColorPixel);
			$strokeColorPixel->destroy();
			$strokeText->clutImage($strokeClut, 7);
			$strokeClut->destroy();

			// Create stroke effect by compositing multiple offset copies
			$strokeCanvas = new Imagick();
			$strokeCanvas->newImage($strokeText->getImageWidth() + ($strokeWidth * 2),
				$strokeText->getImageHeight() + ($strokeWidth * 2),
				new ImagickPixel('transparent'));

			// Draw stroke at multiple offset positions
			for ($x = -$strokeWidth; $x <= $strokeWidth; $x++)
			{
				for ($y = -$strokeWidth; $y <= $strokeWidth; $y++)
				{
					if ($x !== 0 || $y !== 0)
					{
						$strokeCanvas->compositeImage($strokeText,
							Imagick::COMPOSITE_OVER,
							$strokeWidth + $x,
							$strokeWidth + $y);
					}
				}
			}

			$strokeText->destroy();
		}

		// Create a `caption:` pseudo image that only manages text.
		$theText->newPseudoImage($template['text-width'],
			$template['text-height'],
			'caption:' . $fittedText);
		$theText->setBackgroundColor('transparent');

		// Remove extra height.
		$theText->trimImage(0.0);

		// Set text color
		$clut           = new Imagick();
		$textColorPixel = new ImagickPixel($template['text-color']);
		$clut->newImage(1, 1, $textColorPixel);
		$textColorPixel->destroy();
		$theText->clutImage($clut, 7);
		$clut->destroy();

		// Figure out text vertical position
		$yPos = $template['text-y-absolute'];

		if ($template['text-y-center'] == '1')
		{
			$yPos = (int) (($image->getImageHeight() - $theText->getImageHeight() - 2 * $strokeWidth) / 2.0 + $template['text-y-adjust']);
		}

		// Figure out text horizontal position
		$xPos = $template['text-x-absolute'];

		if ($template['text-x-center'] == '1')
		{
			$xPos = (int) (($image->getImageWidth() - $theText->getImageWidth() - 2 * $strokeWidth) / 2.0 + $template['text-x-adjust']);
		}

		if ($this->debugText)
		{
			// Bounding box
			$bbY = $template['text-y-absolute'];

			if ($template['text-y-center'] == '1')
			{
				$bbY = (int) (($image->getImageHeight() - $template['text-height']) / 2.0 + $template['text-y-adjust']);
			}

			$bbX = $template['text-x-absolute'];

			if ($template['text-x-center'] == '1')
			{
				$bbX = (int) (($image->getImageWidth() - $template['text-width']) / 2.0 + $template['text-x-adjust']);
			}

			$draw        = new ImagickDraw();
			$strokeColor = new ImagickPixel('#ff00ff');
			$fillColor   = new ImagickPixel('#ffff0050');
			$draw->setStrokeColor($strokeColor);
			$draw->setFillColor($fillColor);
			$draw->setStrokeOpacity(1);
			$draw->setStrokeWidth(2);
			$draw->rectangle(1, 1, $template['text-width'], $template['text-height']);
			$debugImage       = new Imagick();
			$transparentPixel = new ImagickPixel('transparent');
			$debugImage->newImage($template['text-width'], $template['text-height'], $transparentPixel);
			$debugImage->drawImage($draw);

			$strokeColor->destroy();
			$fillColor->destroy();
			$draw->destroy();
			$transparentPixel->destroy();

			$image->compositeImage($debugImage, Imagick::COMPOSITE_OVER, $bbX, $bbY);
			$debugImage->destroy();

			// Text lines
			$debugW = $theText->getImageWidth() + 2 * $strokeWidth;
			$debugH = $theText->getImageHeight() + 2 * $strokeWidth;

			$draw        = new ImagickDraw();
			$strokeColor = new ImagickPixel('#ff00ff');
			$fillColor   = new ImagickPixel('transparent');
			$draw->setStrokeColor($strokeColor);
			$draw->setStrokeDashArray([5, 5]);
			$draw->setFillColor($fillColor);
			$draw->setStrokeOpacity(1);
			$draw->setStrokeWidth(2);
			$draw->rectangle(1, 1, $debugW - 1, $debugH - 1);

			$debugImage       = new Imagick();
			$transparentPixel = new ImagickPixel('transparent');
			$debugImage->newImage($debugW, $debugH, $transparentPixel);

			$debugImage->drawImage($draw);

			$strokeColor->destroy();
			$fillColor->destroy();
			$draw->destroy();
			$transparentPixel->destroy();

			$image->compositeImage(
				$debugImage,
				Imagick::COMPOSITE_OVER,
				(int) $xPos,
				(int) $yPos
			);
			$debugImage->destroy();
		}

		// If we have a stroke we need to composite the text on top of the stroke, otherwise text opacity will not work.
		if (isset($strokeCanvas))
		{
			$strokeCanvas->compositeImage(
				$theText,
				Imagick::COMPOSITE_DEFAULT,
				$strokeWidth,
				$strokeWidth
			);

			$theText->destroy();

			unset($theText);

			$theText = $strokeCanvas;

			unset($strokeCanvas);
		}

		// Apply opacity to $theText
		$this->applyImageEffectOpacity($theText, $template, 'text-');

		// Composite bestfit caption over base image.
		$image->compositeImage(
			$theText,
			Imagick::COMPOSITE_DEFAULT,
			(int) $xPos,
			(int) $yPos);

		$theText->destroy();
	}

	/**
	 * Fit text into lines that can be contained within the template box, adding ellipsis if needed.
	 *
	 * @param   string  $text         The original text
	 * @param   array   $template     The template configuration
	 * @param   int     $strokeWidth  The text stroke width
	 *
	 * @return  string  The text formatted with line breaks and ellipsis if needed
	 *
	 * @since   1.0.0
	 */
	private function fitTextIntoLines(string $text, array $template, int $strokeWidth = 0): string
	{
		$words       = explode(' ', $text);
		$lines       = [];
		$currentLine = '';
		$maxWidth    = $template['text-width'];
		$maxHeight   = $template['text-height'];
		$lineSpacing = 1.35; // Similar to what GD adapter uses

		foreach ($words as $word)
		{
			$testLine = empty($currentLine) ? $word : $currentLine . ' ' . $word;

			// Measure the test line
			$testMetrics = $this->measureText($testLine, $template, $strokeWidth);

			if ($testMetrics['width'] <= $maxWidth)
			{
				// This word fits on the current line
				$currentLine = $testLine;
			}
			else
			{
				// This word doesn't fit, start a new line
				if (!empty($currentLine))
				{
					$lines[] = $currentLine;
				}
				$currentLine = $word;
			}
		}

		// Add the last line if it has content
		if (!empty($currentLine))
		{
			$lines[] = $currentLine;
		}

		// Now check if all lines fit vertically
		$totalHeight = $this->calculateTotalTextHeight($lines, $template, $lineSpacing, $strokeWidth);

		if ($totalHeight > $maxHeight)
		{
			// We need to remove lines and add ellipsis
			$lines = $this->truncateLinesToFitHeight($lines, $template, $maxHeight, $lineSpacing, $strokeWidth);
		}

		return implode("\n", $lines);
	}

	/**
	 * Measure the dimensions of text as it would be rendered.
	 *
	 * @param   string  $text         The text to measure
	 * @param   array   $template     The template configuration
	 * @param   int     $strokeWidth  The text stroke width
	 *
	 * @return  array   Array with 'width' and 'height' keys
	 *
	 * @since   1.0.0
	 */
	private function measureText(string $text, array $template, int $strokeWidth = 0): array
	{
		$draw = new ImagickDraw();
		$draw->setFont($this->normalizeFont($template['text-font']));

		if ($template['font-size'] > 0)
		{
			$draw->setFontSize($template['font-size']);
		}

		// Set gravity for alignment
		$gravity = match ($template['text-align'])
		{
			'left' => Imagick::GRAVITY_NORTHWEST,
			'right' => Imagick::GRAVITY_NORTHEAST,
			default => Imagick::GRAVITY_NORTH,
		};
		$draw->setGravity($gravity);

		// Create a temporary image to get text metrics
		$tempImage = new Imagick();
		$tempImage->newImage(1, 1, new ImagickPixel('transparent'));

		$metrics = $tempImage->queryFontMetrics($draw, $text);

		$draw->destroy();
		$tempImage->destroy();

		return [
			'width'  => (int) ceil($metrics['textWidth']) + 2 * $strokeWidth,
			'height' => (int) ceil($metrics['textHeight']) + 2 * $strokeWidth,
		];
	}

	/**
	 * Calculate the total height needed for all lines with line spacing.
	 *
	 * @param   array  $lines        Array of text lines
	 * @param   array  $template     Template configuration
	 * @param   float  $lineSpacing  Line spacing factor
	 * @param   int    $strokeWidth  The text stroke width
	 *
	 * @return  int     Total height in pixels
	 *
	 * @since   1.0.0
	 */
	private function calculateTotalTextHeight(array $lines, array $template, float $lineSpacing, int $strokeWidth = 0): int
	{
		if (empty($lines))
		{
			return 0;
		}

		$firstLineHeight = $this->measureText($lines[0], $template, $strokeWidth)['height'];
		$totalHeight     = $firstLineHeight;

		// Add height for additional lines with spacing
		for ($i = 1; $i < count($lines); $i++)
		{
			$lineHeight  = $this->measureText($lines[$i], $template, $strokeWidth)['height'];
			$totalHeight += $lineHeight * $lineSpacing;
		}

		return (int) ceil($totalHeight);
	}

	/**
	 * Truncate lines to fit within the maximum height and add ellipsis to the last line.
	 *
	 * @param   array  $lines        Array of text lines
	 * @param   array  $template     Template configuration
	 * @param   int    $maxHeight    Maximum height in pixels
	 * @param   float  $lineSpacing  Line spacing factor
	 * @param   int    $strokeWidth  The text stroke width
	 *
	 * @return  array   Truncated array of lines with ellipsis on the last line
	 *
	 * @since   1.0.0
	 */
	private function truncateLinesToFitHeight(array $lines, array $template, int $maxHeight, float $lineSpacing, int $strokeWidth = 0): array
	{
		$fittedLines   = [];
		$currentHeight = 0;
		$maxWidth      = $template['text-width'];

		foreach ($lines as $line)
		{
			$lineMetrics = $this->measureText($line, $template, $strokeWidth);
			$lineHeight  = $lineMetrics['height'];

			if (count($fittedLines) > 0)
			{
				$lineHeight *= $lineSpacing;
			}

			if ($currentHeight + $lineHeight <= $maxHeight)
			{
				$fittedLines[] = $line;
				$currentHeight += $lineHeight;
			}
			else
			{
				// This line won't fit, so we need to add ellipsis to the previous line
				if (!empty($fittedLines))
				{
					$lastLine      = array_pop($fittedLines);
					$ellipsisLine  = $this->addEllipsisToLine($lastLine, $template, $maxWidth, $strokeWidth);
					$fittedLines[] = $ellipsisLine;
				}
				break;
			}
		}

		return $fittedLines;
	}

	/**
	 * Add ellipsis to a line, truncating words if necessary to fit within the width.
	 *
	 * @param   string  $line         The original line
	 * @param   array   $template     Template configuration
	 * @param   int     $maxWidth     Maximum width in pixels
	 * @param   int     $strokeWidth  The text stroke width
	 *
	 * @return  string  Line with ellipsis that fits within the width
	 *
	 * @since   1.0.0
	 */
	private function addEllipsisToLine(string $line, array $template, int $maxWidth, int $strokeWidth = 0): string
	{
		// First try adding ellipsis to the full line
		$testLine    = $line . '…';
		$testMetrics = $this->measureText($testLine, $template, $strokeWidth);

		if ($testMetrics['width'] <= $maxWidth)
		{
			return $testLine;
		}

		// If it doesn't fit, remove words until it does
		$words = explode(' ', $line);

		while (count($words) > 1)
		{
			array_pop($words);
			$testLine    = implode(' ', $words) . '…';
			$testMetrics = $this->measureText($testLine, $template, $strokeWidth);

			if ($testMetrics['width'] <= $maxWidth)
			{
				return $testLine;
			}
		}

		// If we still don't fit with just one word, truncate character by character
		if (!empty($words))
		{
			$word  = $words[0];
			$chars = mb_str_split($word);

			while (count($chars) > 1)
			{
				array_pop($chars);
				$testLine    = implode('', $chars) . '…';
				$testMetrics = $this->measureText($testLine, $template, $strokeWidth);

				if ($testMetrics['width'] <= $maxWidth)
				{
					return $testLine;
				}
			}
		}

		// Fallback to just ellipsis
		return '…';
	}


	/**
	 * Apply various image effects to the base image.
	 *
	 * @param   Imagick  $image     The image to apply effects to
	 * @param   array    $template  The template configuration
	 * @param   string   $prefix    Prefix for the template array keys
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	private function applyImageEffects(Imagick &$image, array $template, string $prefix = 'image-'): void
	{
		$this->applyImageEffectGrayscale($image, $template, $prefix);
		$this->applyImageEffectSepia($image, $template, $prefix);
		$this->applyImageEffectOpacity($image, $template, $prefix);
	}

	/**
	 * Apply a grayscale effect to an image based on the specified intensity in the template.
	 *
	 * @param   Imagick  $image     The image object to modify, passed by reference.
	 * @param   array    $template  Template configuration containing grayscale intensity.
	 * @param   string   $prefix    Prefix to identify the grayscale configuration key in the template.
	 *
	 * @return  void
	 * @throws  ImagickException
	 * @since   3.0.0
	 */
	private function applyImageEffectGrayscale(Imagick &$image, array $template, string $prefix): void
	{
		if (!isset($template[$prefix . 'grayscale']) || $template[$prefix . 'grayscale'] <= 0)
		{
			return;
		}

		$intensity = (float) $template[$prefix . 'grayscale'] / 100.0;

		if ($intensity >= 1.0)
		{
			// Full grayscale - use simple desaturation
			$image->modulateImage(100, 0, 100);
		}
		else
		{
			// Partial grayscale - blend original with grayscale version
			$grayscaleImage = clone $image;
			$grayscaleImage->modulateImage(100, 0, 100);

			// Create a composite with the desired intensity
			$originalOpacity  = 1.0 - $intensity;
			$grayscaleOpacity = $intensity;

			// Apply opacity to both images
			$image->evaluateImage(Imagick::EVALUATE_MULTIPLY, $originalOpacity, Imagick::CHANNEL_ALPHA);
			$grayscaleImage->evaluateImage(Imagick::EVALUATE_MULTIPLY, $grayscaleOpacity, Imagick::CHANNEL_ALPHA);

			// Composite the grayscale over the original
			$image->compositeImage($grayscaleImage, Imagick::COMPOSITE_OVER, 0, 0);

			$grayscaleImage->destroy();
		}
	}

	/**
	 * Applies a sepia effect to an image with the specified intensity.
	 *
	 * The intensity is defined in the template configuration and can range from 0 to 1.
	 * If the intensity is 1, a full sepia effect is applied using the built-in sepia tone method.
	 * Otherwise, a partial sepia effect is created by blending the original image with a sepia-toned version.
	 *
	 * @param   Imagick  $image     The image object to apply the sepia effect to, passed by reference
	 * @param   array    $template  The configuration template containing the sepia intensity value
	 * @param   string   $prefix    The prefix for locating the sepia intensity within the template
	 *
	 * @return  void
	 * @throws  ImagickException
	 * @throws  \ImagickPixelException
	 * @since   3.0.0
	 */
	private function applyImageEffectSepia(Imagick &$image, array $template, string $prefix): void
	{
		if (!isset($template[$prefix . 'sepia']) || $template[$prefix . 'sepia'] <= 0)
		{
			return;
		}

		$intensity = (float) $template[$prefix . 'sepia'] / 100.0;

		if ($intensity >= 1.0)
		{
			// Full sepia using built-in method
			$image->sepiaToneImage(80);
		}
		else
		{
			// Partial sepia - blend original with sepia version
			$sepiaImage = clone $image;

			// Create sepia version using color matrix for better control
			$sepiaImage->modulateImage(100, 0, 100); // First convert to grayscale

			// Apply sepia tint using color matrix for a more natural look
			$colorMatrix = [
				1.2, 0.2, 0.2, 0, 0,    // Red values
				0.2, 1.0, 0.2, 0, 0,    // Green values
				0.2, 0.2, 0.8, 0, 0,    // Blue values
				0, 0, 0, 1, 0,          // Alpha values
				0, 0, 0, 0, 1,           // Offset values
			];
			$sepiaImage->colorMatrixImage($colorMatrix);

			// Blend based on intensity
			$originalOpacity = 1.0 - $intensity;
			$sepiaOpacity    = $intensity;

			// Apply opacity to both images
			$image->evaluateImage(Imagick::EVALUATE_MULTIPLY, $originalOpacity, Imagick::CHANNEL_ALPHA);
			$sepiaImage->evaluateImage(Imagick::EVALUATE_MULTIPLY, $sepiaOpacity, Imagick::CHANNEL_ALPHA);

			// Composite the sepia over the original
			$image->compositeImage($sepiaImage, Imagick::COMPOSITE_OVER, 0, 0);

			$sepiaImage->destroy();
		}
	}

	/**
	 * Applies an opacity effect to an Imagick image based on the given template configuration.
	 *
	 * @param   Imagick  $image     Reference to the Imagick image object to which the opacity effect will be applied.
	 * @param   array    $template  Template array containing configuration values, specifically the opacity value
	 *                              associated with a prefix.
	 * @param   string   $prefix    Prefix used to locate the opacity value in the template array.
	 *
	 * @return  void
	 * @throws  ImagickException
	 * @since   3.0.0
	 */
	private function applyImageEffectOpacity(Imagick &$image, array $template, string $prefix): void
	{
		if (!isset($template[$prefix . 'opacity']) || $template[$prefix . 'opacity'] >= 100 || $template[$prefix . 'opacity'] < 0)
		{
			return;
		}

		$opacity = (float) $template[$prefix . 'opacity'] / 100.0;

		// Ensure opacity is within valid range
		$opacity = max(0.0, min(1.0, $opacity));

		// Apply opacity to the alpha channel
		$image->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity, Imagick::CHANNEL_ALPHA);
	}
}