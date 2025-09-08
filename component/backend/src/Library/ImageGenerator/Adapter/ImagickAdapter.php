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
use ImagickPixel;
use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') || die();

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
			$alpha   = (int) round($opacity * 255 / 100);
			$hex     = substr(base_convert(($alpha + 0x10000), 10, 16), -2, 2);
			$pixel   = new ImagickPixel($template['base-color'] . $hex);

			$image->newImage($templateWidth, $templateHeight, $pixel);

			$pixel->destroy();
		}

		// Overlay the base image
		if ($template['base-image'])
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
				$tmpImg = $this->resize($extraImage, $templateWidth, $templateHeight);
				$imgX   = 0;
				$imgY   = 0;
				$extraCanvas->compositeImage(
					$tmpImg,
					Imagick::COMPOSITE_DEFAULT,
					(int) $imgX,
					(int) $imgY
				);
			}
			else
			{
				$tmpImg = $this->resize($extraImage, $template['image-width'], $template['image-height']);
				$imgX   = $template['image-x'];
				$imgY   = $template['image-y'];
				$extraCanvas->compositeImage(
					$tmpImg,
					Imagick::COMPOSITE_DEFAULT,
					0,
					0);
			}

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
		$this->renderOverlayText($text, $template, $image);

		// Write the image
		$imageFormat = $this->getNormalizedExtension($outFile);
		$image->setImageFormat($imageFormat);

		switch ($imageFormat)
		{
			case 'jpg':
				$image->setCompressionQuality($this->quality);
				$image->setImageCompression(Imagick::COMPRESSION_JPEG);
				break;

			case 'png':
				$image->setImageCompressionQuality(100 - $this->quality);
				break;
		}

		if (!file_put_contents($outFile, $image))
		{
			file_put_contents($outFile, $image);
		}

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
	 * @throws \ImagickException
	 *
	 * @since   1.0.0
	 */
	private function resize(string $src, $new_w, $new_h, string $focus = 'center'): Imagick
	{
		$image = new Imagick($src);

		$w = $image->getImageWidth();
		$h = $image->getImageHeight();

		$resize_w = $new_w;
		$resize_h = $h * $new_w / $w;

		if ($w > $h)
		{
			$resize_w = $w * $new_h / $h;
			$resize_h = $new_h;

			if ($resize_w < $new_w)
			{
				$resize_w = $new_w;
				$resize_h = $h * $new_w / $w;
			}
		}

		$image->resizeImage((int) $resize_w, (int) $resize_h, Imagick::FILTER_LANCZOS, 0.9);

		switch ($focus)
		{
			case 'northwest':
				$image->cropImage((int) $new_w, (int) $new_h, 0, 0);
				break;

			default:
			case 'center':
				$image->cropImage((int) $new_w, (int) $new_h, (int) (($resize_w - $new_w) / 2), (int) (($resize_h - $new_h) / 2));
				break;

			case 'northeast':
				$image->cropImage((int) $new_w, (int) $new_h, (int) ($resize_w - $new_w), 0);
				break;

			case 'southwest':
				$image->cropImage((int) $new_w, (int) $new_h, 0, (int) ($resize_h - $new_h));
				break;

			case 'southeast':
				$image->cropImage((int) $new_w, (int) $new_h, (int) ($resize_w - $new_w), (int) ($resize_h - $new_h));
				break;
		}

		return $image;
	}

	/**
	 * Overlay the text on the image.
	 *
	 * @param   string          $text      The text to render.
	 * @param   array           $template  The OpenGraph image template definition.
	 * @param   Imagick  $image     The image to overlay the text.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	private function renderOverlayText(string $text, array $template, Imagick &$image): void
	{
		// Make sure we are told to overlay text
		if (($template['overlay_text'] ?? 1) != 1)
		{
			return;
		}

		// Normalize text
		$text = $this->preProcessText($text, false);

		// Break text into lines that fit within the box, adding ellipsis if needed
		$fittedText = $this->fitTextIntoLines($text, $template);

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
		$gravity = match($template['text-align'])
		{
			'left' => Imagick::GRAVITY_NORTHWEST,
			'right' => Imagick::GRAVITY_NORTHEAST,
			default => Imagick::GRAVITY_NORTH,
		};

		$theText->setGravity($gravity);

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
			$yPos = ($image->getImageHeight() - $theText->getImageHeight()) / 2.0 + $template['text-y-adjust'];
		}

		// Figure out text horizontal position
		$xPos = $template['text-x-absolute'];

		if ($template['text-x-center'] == '1')
		{
			$xPos = ($image->getImageWidth() - $theText->getImageWidth()) / 2.0 + $template['text-x-adjust'];
		}

		if ($this->debugText)
		{
			$debugW = $theText->getImageWidth();
			$debugH = $theText->getImageHeight();

			$draw        = new ImagickDraw();
			$strokeColor = new ImagickPixel('#ff00ff');
			$fillColor   = new ImagickPixel('#ffff0050');
			$draw->setStrokeColor($strokeColor);
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
	 * @param   string  $text      The original text
	 * @param   array   $template  The template configuration
	 *
	 * @return  string  The text formatted with line breaks and ellipsis if needed
	 *
	 * @since   1.0.0
	 */
	private function fitTextIntoLines(string $text, array $template): string
	{
		$words = explode(' ', $text);
		$lines = [];
		$currentLine = '';
		$maxWidth = $template['text-width'];
		$maxHeight = $template['text-height'];
		$lineSpacing = 1.35; // Similar to what GD adapter uses

		foreach ($words as $word)
		{
			$testLine = empty($currentLine) ? $word : $currentLine . ' ' . $word;
			
			// Measure the test line
			$testMetrics = $this->measureText($testLine, $template);
			
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
		$totalHeight = $this->calculateTotalTextHeight($lines, $template, $lineSpacing);
		
		if ($totalHeight > $maxHeight)
		{
			// We need to remove lines and add ellipsis
			$lines = $this->truncateLinesToFitHeight($lines, $template, $maxHeight, $lineSpacing);
		}

		return implode("\n", $lines);
	}

	/**
	 * Measure the dimensions of text as it would be rendered.
	 *
	 * @param   string  $text      The text to measure
	 * @param   array   $template  The template configuration
	 *
	 * @return  array   Array with 'width' and 'height' keys
	 *
	 * @since   1.0.0
	 */
	private function measureText(string $text, array $template): array
	{
		$draw = new ImagickDraw();
		$draw->setFont($this->normalizeFont($template['text-font']));

		if ($template['font-size'] > 0)
		{
			$draw->setFontSize($template['font-size']);
		}

		// Set gravity for alignment
		$gravity = match($template['text-align'])
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
			'width' => (int) ceil($metrics['textWidth']),
			'height' => (int) ceil($metrics['textHeight'])
		];
	}

	/**
	 * Calculate the total height needed for all lines with line spacing.
	 *
	 * @param   array   $lines       Array of text lines
	 * @param   array   $template    Template configuration
	 * @param   float   $lineSpacing Line spacing factor
	 *
	 * @return  int     Total height in pixels
	 *
	 * @since   1.0.0
	 */
	private function calculateTotalTextHeight(array $lines, array $template, float $lineSpacing): int
	{
		if (empty($lines))
		{
			return 0;
		}

		$firstLineHeight = $this->measureText($lines[0], $template)['height'];
		$totalHeight = $firstLineHeight;

		// Add height for additional lines with spacing
		for ($i = 1; $i < count($lines); $i++)
		{
			$lineHeight = $this->measureText($lines[$i], $template)['height'];
			$totalHeight += $lineHeight * $lineSpacing;
		}

		return (int) ceil($totalHeight);
	}

	/**
	 * Truncate lines to fit within the maximum height and add ellipsis to the last line.
	 *
	 * @param   array   $lines      Array of text lines
	 * @param   array   $template   Template configuration
	 * @param   int     $maxHeight  Maximum height in pixels
	 * @param   float   $lineSpacing Line spacing factor
	 *
	 * @return  array   Truncated array of lines with ellipsis on the last line
	 *
	 * @since   1.0.0
	 */
	private function truncateLinesToFitHeight(array $lines, array $template, int $maxHeight, float $lineSpacing): array
	{
		$fittedLines = [];
		$currentHeight = 0;
		$maxWidth = $template['text-width'];

		foreach ($lines as $line)
		{
			$lineMetrics = $this->measureText($line, $template);
			$lineHeight = $lineMetrics['height'];
			
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
					$lastLine = array_pop($fittedLines);
					$ellipsisLine = $this->addEllipsisToLine($lastLine, $template, $maxWidth);
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
	 * @param   string  $line      The original line
	 * @param   array   $template  Template configuration
	 * @param   int     $maxWidth  Maximum width in pixels
	 *
	 * @return  string  Line with ellipsis that fits within the width
	 *
	 * @since   1.0.0
	 */
	private function addEllipsisToLine(string $line, array $template, int $maxWidth): string
	{
		// First try adding ellipsis to the full line
		$testLine = $line . '…';
		$testMetrics = $this->measureText($testLine, $template);

		if ($testMetrics['width'] <= $maxWidth)
		{
			return $testLine;
		}

		// If it doesn't fit, remove words until it does
		$words = explode(' ', $line);
		
		while (count($words) > 1)
		{
			array_pop($words);
			$testLine = implode(' ', $words) . '…';
			$testMetrics = $this->measureText($testLine, $template);
			
			if ($testMetrics['width'] <= $maxWidth)
			{
				return $testLine;
			}
		}

		// If we still don't fit with just one word, truncate character by character
		if (!empty($words))
		{
			$word = $words[0];
			$chars = mb_str_split($word);
			
			while (count($chars) > 1)
			{
				array_pop($chars);
				$testLine = implode('', $chars) . '…';
				$testMetrics = $this->measureText($testLine, $template);
				
				if ($testMetrics['width'] <= $maxWidth)
				{
					return $testLine;
				}
			}
		}

		// Fallback to just ellipsis
		return '…';
	}
}