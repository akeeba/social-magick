<?php
/**
 * @package   socialmagick
 * @copyright Copyright (c)2025 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\SocialMagick\Administrator\Library\ParametersRetriever;

trait ExtraImageFetchTrait
{
	/**
	 * Gets an image from an item. For example, the full article image from an article.
	 *
	 * @param   object|null  $item            The item which has the images.
	 * @param   string       $imagesProperty  The item property containing the images array.
	 * @param   string       $imageSource     The key in the image array containing the actual image.
	 *
	 * @return  string|null
	 * @since   3.0.0
	 */
	private function getImageFromItem(?object $item, string $imagesProperty = 'params', string $imageSource = 'image'): ?string
	{
		// Decode images
		$itemImages = $item?->{$imagesProperty} ?? [];
		$itemImages = is_string($itemImages) ? @json_decode($itemImages, true) : $itemImages;
		$itemImages = is_array($itemImages) ? $itemImages : [];

		return empty($itemImages)
			? null :
			(($itemImages[$imageSource] ?? null) ?: null);
	}

	/**
	 * Gets an image from an item's custom fields.
	 *
	 * @param   object|null  $contentObject    The item which has the custom fields.
	 * @param   string       $customFieldName  The name of the custom field.
	 *
	 * @return  string|null
	 */
	private function getImageFromCustomField(?object $contentObject, string $customFieldName): ?string
	{
		if (empty($contentObject))
		{
			return null;
		}

		// Decode custom fields
		$jcFields = $contentObject?->jcfields ?? [];

		if (is_string($jcFields))
		{
			$jcFields = @json_decode($jcFields, true);
		}

		$jcFields = is_array($jcFields) ? $jcFields : [];

		if (empty($jcFields) || empty($customFieldName))
		{
			return null;
		}

		foreach ($jcFields as $fieldInfo)
		{
			if ($fieldInfo->name != $customFieldName)
			{
				continue;
			}

			$rawvalue = $fieldInfo->rawvalue ?? '';
			$value    = @json_decode($rawvalue, true);


			if (empty($value) && is_string($rawvalue))
			{
				return $rawvalue;
			}

			if (empty($value) || !is_array($value))
			{
				return null;
			}

			return trim($value['imagefile'] ?? '') ?: null;
		}

		return null;
	}
}