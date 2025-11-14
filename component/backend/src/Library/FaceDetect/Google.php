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
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Http\HttpFactory;
use Throwable;

/**
 * Face detection adapter using the Google Cloud Vision API.
 *
 * @since  3.0.0
 */
class Google implements AdapterInterface
{
	use ImageEncodingTrait;

	public function getCoordinates(Imagick|GdImage $image): array
	{
		// Create the Google Cloud Vision API payload
		$payload = [
			'requests' => [
				[
					'image'    => [
						'content' => $this->encodeImage($image),
					],
					'features' => [
						'maxResults' => 10,
						'type'       => 'FACE_DETECTION',
					],
				],
			],
		];

		// Call the Google Vision API
		try
		{
			$response = (new HttpFactory())->getHttp()->post(
				'https://vision.googleapis.com/v1/images:annotate',
				json_encode($payload),
				[
					'X-goog-api-key' => ComponentHelper::getParams('com_socialmagick')->get('facedect_google_apikey', ''),
					'Content-Type'   => 'application/json',
				]
			);
		}
		catch (Throwable)
		{
			// Network error: no face detected
			return [null, null, null, null];
		}

		unset($payload);

		// Analyse the response
		$httpStatusCode = $response->getStatusCode();
		$bodyStream = $response->getBody();
		$bodyStream->rewind();
		$body = $bodyStream->getContents();

		// We expect an HTTP 200
		if ($httpStatusCode != 200)
		{
			return [null, null, null, null];
		}

		// JSON-decode the response
		$result = @json_decode($body ?? '', true);

		// Make sure we have a valid, non-error reponse
		if (is_null($result) || isset($result['error']) || !isset($result['responses']) || !is_array($result['responses']) || empty($result['responses']))
		{
			return [null, null, null, null];
		}

		// Extract face bounding boxes from the detected faces' polygons
		$faces = [];

		foreach ($result['responses'] as $response)
		{
			if (!isset($response['faceAnnotations']) || !is_array($response['faceAnnotations']) || empty($response['faceAnnotations']))
			{
				continue;
			}

			if (!isset($response['faceAnnotations']['fdBoundingPoly']) || !is_array($response['faceAnnotations']['fdBoundingPoly']) || empty($response['faceAnnotations']['fdBoundingPoly']))
			{
				continue;
			}

			$x = [];
			$y = [];

			foreach ($response['faceAnnotations']['fdBoundingPoly'] as $poly)
			{
				if (!isset($poly['vertices']) || !is_array($poly['vertices']) || empty($poly['vertices']))
				{
					continue;
				}

				foreach ($poly['vertices'] as $vertice)
				{
					if (!isset($vertice['x']) || !isset($vertice['y']) || !is_int($vertice['x']) || !is_int($vertice['y']))
					{
						continue;
					}

					$x[] = $vertice['x'];
					$y[] = $vertice['y'];
				}
			}

			$faces[] = [min($x), min($y), max($x), max($y)];
		}

		// No faces?
		if (empty($faces))
		{
			return [null, null, null, null];
		}

		// Get the overall bounding box
		$x = [];
		$y = [];

		foreach ($faces as $face)
		{
			$x[] = $face[0];
			$x[] = $face[2];
			$y[] = $face[1];
			$y[] = $face[3];
		}

		return [min($x), min($y), max($x), max($y)];
	}
}