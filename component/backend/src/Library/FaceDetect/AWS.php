<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect;

defined('_JEXEC') || die;

use Akeeba\Component\SocialMagick\Administrator\Library\FaceDetect\Amazon\RekognitionV4Signer;
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
class AWS implements AdapterInterface
{
	use ImageEncodingTrait;

	/**
	 * Constructor for the class, initializing AWS credentials and region settings.
	 *
	 * @param   string|null  $accessKey  The AWS access key. If null, it will attempt to retrieve
	 *                                   the value from the component parameters.
	 * @param   string|null  $secretKey  The AWS secret key. If null, it will attempt to retrieve
	 *                                   the value from the component parameters.
	 * @param   string|null  $region     The AWS region. If null, it will attempt to retrieve
	 *                                   the value from the component parameters.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	public function __construct(
		private ?string $accessKey = null,
		private ?string $secretKey = null,
		private ?string $region = null,
	)
	{
		$cParams         = ComponentHelper::getParams('com_socialmagick');
		$this->accessKey ??= $cParams->get('facedetect_aws_access_key', '');
		$this->secretKey ??= $cParams->get('facedetect_aws_secret_key', '');
		$this->region    ??= $cParams->get('facedetect_aws_region', '');
	}

	/**
	 * @inheritDoc
	 * @since   3.0.0
	 */
	public function getCoordinates(Imagick|GdImage $image): array
	{
		$this->allowWebP = false;
		$payload = json_encode(
			[
				'Image'      => [
					'Bytes' => $this->encodeImage($image),
				],
				'Attributes' => ['DEFAULT'],
			]
		);

		$signer = new RekognitionV4Signer();
		$headers             = $signer->buildRequestHeaders(
			'RekognitionService.DetectFaces',
			$this->region,
			$this->accessKey,
			$this->secretKey,
			$payload
		);

		// Call the AWS Rekognition API
		try
		{
			$response = (new HttpFactory())->getHttp()->post(
				$signer->getEndpointUrl($this->region),
				$payload,
				$headers
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
		$bodyStream     = $response->getBody();
		$bodyStream->rewind();
		$body = $bodyStream->getContents();

		// We expect an HTTP 200
		if ($httpStatusCode != 200)
		{
			return [null, null, null, null];
		}

		$box = $this->getUnifiedBoundingBox($body);

		if ($box === [null, null, null, null])
		{
			return [null, null, null, null];
		}

		// AWS Rekognition returns floats 0.0–1.0 instead of pixel coordinates. We need to translate them.
		if ($image instanceof GdImage)
		{
			$width  = imagesx($image);
			$height = imagesy($image);
		}
		else
		{
			$width  = $image->getImageWidth();
			$height = $image->getImageHeight();
		}

		return [
			intval($box[0] * $width),
			intval($box[1] * $height),
			intval($box[2] * $width),
			intval($box[3] * $height),
		];
	}


	/**
	 * Calculates a unified bounding box that encompasses all individual bounding boxes
	 * provided in the JSON input.
	 *
	 * @param   string|null  $jsonData  The JSON-encoded string containing face detection details,
	 *                                  specifically an array of face details with bounding box information.
	 *                                  If null or empty, the method will return an array of null values.
	 *
	 * @return  array Returns an array with four float values representing the unified bounding box
	 *               in the form: [minX, minY, maxX, maxY]. If no valid bounding boxes are found
	 *               or if the input data is invalid, the method returns an array of null values.
	 * @since   1.0.0
	 */
	private function getUnifiedBoundingBox(?string $jsonData): array
	{
		// Check if input is null or empty
		if ($jsonData === null || $jsonData === '')
		{
			return [null, null, null, null];
		}

		// Decode JSON
		$data = json_decode($jsonData, true);

		// Check if decoding was successful and has the expected structure
		if (json_last_error() !== JSON_ERROR_NONE ||
			!isset($data['FaceDetails']) ||
			!is_array($data['FaceDetails']))
		{
			return [null, null, null, null];
		}

		// If no face details found
		if (empty($data['FaceDetails']))
		{
			return [null, null, null, null];
		}

		$minX = PHP_FLOAT_MAX;
		$minY = PHP_FLOAT_MAX;
		$maxX = PHP_FLOAT_MIN;
		$maxY = PHP_FLOAT_MIN;

		// Iterate through all face details.
		foreach ($data['FaceDetails'] as $faceDetail)
		{
			// Check if BoundingBox exists and has required fields.
			if (!isset($faceDetail['BoundingBox']) ||
				!isset($faceDetail['BoundingBox']['Left']) ||
				!isset($faceDetail['BoundingBox']['Top']) ||
				!isset($faceDetail['BoundingBox']['Width']) ||
				!isset($faceDetail['BoundingBox']['Height']))
			{
				// Skip this face if the bounding box is invalid.
				continue;
			}

			$boundingBox = $faceDetail['BoundingBox'];

			// Check if all BoundingBox values are floats
			if (!is_float($boundingBox['Left']) ||
				!is_float($boundingBox['Top']) ||
				!is_float($boundingBox['Width']) ||
				!is_float($boundingBox['Height']))
			{
				// Skip this face if any bounding box value is not a float.
				continue;
			}

			// Calculate the four corners of the current bounding box.
			$x1 = $boundingBox['Left'];
			$y1 = $boundingBox['Top'];
			$x2 = $boundingBox['Left'] + $boundingBox['Width'];
			$y2 = $boundingBox['Top'] + $boundingBox['Height'];

			// Update global min/max values.
			$minX = min($minX, $x1);
			$minY = min($minY, $y1);
			$maxX = max($maxX, $x2);
			$maxY = max($maxY, $y2);
		}

		// If no valid bounding boxes were found
		if ($minX === PHP_FLOAT_MAX)
		{
			return [null, null, null, null];
		}

		return [$minX, $minY, $maxX, $maxY];
	}

}