<?php

namespace AICLINIC\Core\Includes\Helpers;

/**
 * A helper object for images.
 */
class Image_Helper
{

	/**
	 * Image types that are supported by Open Graph.
	 *
	 * @var array
	 */
	protected static $valid_image_types = ['image/jpeg', 'image/gif', 'image/png', 'image/webp'];

	/**
	 * Image extensions that are supported by Open Graph.
	 *
	 * @var array
	 */
	protected static $valid_image_extensions = ['jpeg', 'jpg', 'gif', 'png', 'webp'];

	/**
	 * Represents the indexables repository.
	 *
	 * @var Indexable_Repository
	 */
	protected $indexable_repository;

	/**
	 * Represents the SEO Links repository.
	 *
	 * @var SEO_Links_Repository
	 */
	protected $seo_links_repository;

	/**
	 * The options helper.
	 *
	 * @var Options_Helper
	 */
	private $options_helper;

	/**
	 * The URL helper.
	 *
	 * @var Url_Helper
	 */
	private $url_helper;

	/**
	 * Determines whether or not the wanted attachment is considered valid.
	 *
	 * @param int $attachment_id The attachment ID to get the attachment by.
	 *
	 * @return bool Whether or not the attachment is valid.
	 */
	public function aiclinic_is_valid_attachment($attachment_id)
	{
		if (! \wp_attachment_is_image($attachment_id)) {
			return false;
		}

		$post_mime_type = \get_post_mime_type($attachment_id);
		if ($post_mime_type === false) {
			return false;
		}

		return $this->aiclinic_is_valid_image_type($post_mime_type);
	}

	/**
	 * Checks if the given extension is a valid extension
	 *
	 * @param string $image_extension The image extension.
	 *
	 * @return bool True when valid.
	 */
	public function aiclinic_is_extension_valid($image_extension)
	{
		return \in_array($image_extension, static::$valid_image_extensions, true);
	}

	/**
	 * Determines whether the passed mime type is a valid image type.
	 *
	 * @param string $mime_type The detected mime type.
	 *
	 * @return bool Whether or not the attachment is a valid image type.
	 */
	public function aiclinic_is_valid_image_type($mime_type)
	{
		return \in_array($mime_type, static::$valid_image_types, true);
	}

	/**
	 * Retrieves the image source for an attachment.
	 *
	 * @param int    $attachment_id The attachment.
	 * @param string $image_size    The image size to retrieve.
	 *
	 * @return string The image url or an empty string when not found.
	 */
	public function aiclinic_get_attachment_image_source($attachment_id, $image_size = 'full')
	{
		$attachment = \wp_get_attachment_image_src($attachment_id, $image_size);

		if (! $attachment) {
			return '';
		}

		return $attachment[0];
	}

	/**
	 * Retrieves the ID of the featured image.
	 *
	 * @param int $post_id The post id to get featured image id for.
	 *
	 * @return int|bool ID when found, false when not.
	 */
	public function aiclinic_get_featured_image_id($post_id)
	{
		if (! \has_post_thumbnail($post_id)) {
			return false;
		}

		return \get_post_thumbnail_id($post_id);
	}

	/**
	 * Gets the first image url of a gallery.
	 *
	 * @param int $post_id Post ID to use.
	 *
	 * @return string The image url or an empty string when not found.
	 */
	public function aiclinic_get_gallery_image($post_id)
	{
		$post = \get_post($post_id);
		if (\strpos($post->post_content, '[gallery') === false) {
			return '';
		}

		$images = \get_post_gallery_images($post);
		if (empty($images)) {
			return '';
		}

		return \reset($images);
	}

	/**
	 * Retrieves the caption for an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return string The caption when found, empty string when no caption is found.
	 */
	public function aiclinic_get_caption($attachment_id)
	{
		$caption = \wp_get_attachment_caption($attachment_id);
		if (! empty($caption)) {
			return $caption;
		}

		$caption = \get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
		if (! empty($caption)) {
			return $caption;
		}

		return '';
	}

	/**
	 * Retrieves the attachment metadata.
	 *
	 * @param int $attachment_id Attachment ID.
	 *
	 * @return array The metadata, empty array when no metadata is found.
	 */
	public function aiclinic_get_metadata($attachment_id)
	{
		$metadata = \wp_get_attachment_metadata($attachment_id);
		if (! $metadata || ! \is_array($metadata)) {
			return [];
		}

		return $metadata;
	}

	/**
	 * Retrieves the attachment image url.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size          The size to get.
	 *
	 * @return string The url when found, empty string otherwise.
	 */
	public function aiclinic_get_attachment_image_url($attachment_id, $size)
	{
		$url = \wp_get_attachment_image_url($attachment_id, $size);
		if (! $url) {
			return '';
		}

		return $url;
	}
}
