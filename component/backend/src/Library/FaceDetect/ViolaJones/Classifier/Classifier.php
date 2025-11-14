<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect\ViolaJones\Classifier;

\defined('_JEXEC') || die;

use Generator;
use JsonSerializable;
use function Akeeba\Component\SocialMagick\Administrator\Library\ViolaJones\Classifier\zstd_uncompress;

/**
 * A Haar cascade classifier.
 *
 * Architectural note: Classifier -> array of Stage -> array of Feature -> array of Rect
 */
final class Classifier implements JsonSerializable
{
	/**
	 * Constructor
	 *
	 * @param   array         $classifierSize  The classifier size (X and Y, as an array of integers).
	 * @param   array<Stage>  $stages          The classifier stages.
	 *
	 * @since   3.0.0
	 */
	private function __construct(private readonly array $classifierSize, private readonly array $stages)
	{
	}

	/**
	 * Creates an instance of the class from an XML file.
	 *
	 * @param   string  $classifierFile  Path to the XML file containing classifier data.
	 *
	 * @return  self  An instance of the class initialised with the data from the XML file.
	 * @since   3.0.0
	 */
	public static function fromXmlFile(string $classifierFile): self
	{
		$fileContents = self::loadFileContents($classifierFile);
		$fileContents = preg_replace("/<!--[\S|\s]*?-->/", "", $fileContents ?: '');
		$xml          = @simplexml_load_string($fileContents);

		if ($xml === false)
		{
			return new self([0, 0], []);
		}

		$classifierSize = explode(" ", strval($xml->children()->children()->size));
		$stages         = [];
		$stagesNode     = $xml->children()->children()->stages;

		foreach ($stagesNode->children() as $stageNode)
		{
			$features = [];

			foreach ($stageNode->trees->children() as $treeNode)
			{
				$rects = [];

				foreach ($treeNode->_->feature->rects->_ as $r)
				{
					$rects[] = Rect::fromString(strval($r));
				}

				$features[] = new Feature(floatval($treeNode->_->threshold), floatval($treeNode->_->left_val), floatval($treeNode->_->right_val), $classifierSize, $rects);
			}

			$stages[] = new Stage(floatval($stageNode->stage_threshold), $features);
		}

		return new self($classifierSize, $stages);
	}

	/**
	 * Creates a new instance from the given array (used to ingest JSON-serialised data).
	 *
	 * @param   array  $array  The input array containing data to construct the instance.
	 *                         Expected keys are 'sizeX', 'sizeY', and 'stages'.
	 *
	 * @return  self  Returns an instance of the class created from the provided array.
	 * @since   3.0.0
	 */
	public static function fromArray(array $array): self
	{
		return new self(
			[$array['sizeX'] ?? 0, $array['sizeY'] ?? 0],
			array_map(fn($a) => Stage::fromArray($a), $array['stages'] ?? [])
		);
	}

	/**
	 * Creates an instance from a JSON file.
	 *
	 * @param   string  $classifierFile  Path to the JSON file containing classifier data
	 *
	 * @return  self  An instance of the class
	 * @since   3.0.0
	 */
	public static function fromJsonFile(string $classifierFile): self
	{
		$fileContents = self::loadFileContents($classifierFile);

		return self::fromJson($fileContents ?: '{}');
	}

	/**
	 * Creates an instance of the class from a JSON string.
	 *
	 * @param   string  $json  The JSON string to decode and convert into an object.
	 *
	 * @return  self  Returns an instance of the class constructed from the decoded JSON.
	 * @since   3.0.0
	 */
	public static function fromJson(string $json): self
	{
		try
		{
			return self::fromArray(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
		}
		catch (\JsonException)
		{
			return new self([0, 0], []);
		}
	}

	/**
	 * Loads the contents of a specified file, handling decompression based on the file extension.
	 *
	 * @param   string  $classifierFile  The path to the file to be loaded.
	 *
	 * @return  string The decompressed content of the file, or an empty string if there is an error.
	 * @since   3.0.0
	 */
	private static function loadFileContents(string $classifierFile): string
	{
		$fileContents = @file_get_contents($classifierFile);

		if ($fileContents === false)
		{
			return '';
		}

		if (str_ends_with($classifierFile, '.gz'))
		{
			return @gzdecode($fileContents) ?: '';
		}

		if (str_ends_with($classifierFile, '.bz2'))
		{
			return @bzdecompress($fileContents, true) ?: '';
		}

		if (str_ends_with($classifierFile, '.zstd'))
		{
			/** @noinspection PhpUndefinedFunctionInspection */
			return @zstd_uncompress($fileContents, true) ?: '';
		}

		return $fileContents;
	}

	/**
	 * Retrieves the classifier size in the X dimension.
	 *
	 * @return  int  The size in the X dimension.
	 * @since   3.0.0
	 */
	public function getSizeX(): int
	{
		return $this->classifierSize[0];
	}

	/**
	 * Retrieves the classifier size in the Y dimension.
	 *
	 * @return  int  The size in the Y dimension.
	 * @since   3.0.0
	 */
	public function getSizeY(): int
	{
		return $this->classifierSize[1];
	}

	/**
	 * Provides an iterator to traverse through stages.
	 *
	 * @return  Generator  An iterator for the stages' collection.
	 * @since   3.0.0
	 */
	public function getStagesIterator(): Generator
	{
		foreach ($this->stages as $stage)
		{
			yield $stage;
		}
	}

	/**
	 * Serializes the object to a value that can be encoded to JSON.
	 *
	 * @return  array  An associative array representation of the object, containing sizeX, sizeY, and stages.
	 * @since   3.0.0
	 */
	public function jsonSerialize(): array
	{
		return [
			'sizeX'  => $this->classifierSize[0],
			'sizeY'  => $this->classifierSize[1],
			'stages' => $this->stages,
		];
	}
}