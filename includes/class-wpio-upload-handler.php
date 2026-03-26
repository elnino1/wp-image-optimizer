<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPIO_Upload_Handler
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_filter('wp_generate_attachment_metadata', [$this, 'process_attachment_metadata'], 10, 3);
    }

    /**
     * Hook into wp_generate_attachment_metadata to process the image after WP has done its initial passes
     * The original image is processed, and WP will use the optimized versions for subsequent thumbnail generations
     * 
     * @param array  $metadata      Attachment metadata.
     * @param int    $attachment_id Attachment ID.
     * @param string $context       Context.
     * @return array
     */
    public function process_attachment_metadata($metadata, $attachment_id, $context)
    {
        // Ensure this is an image
        $mime_type = get_post_mime_type($attachment_id);
        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return $metadata;
        }

        $original_file_path = get_attached_file($attachment_id);

        if (!$original_file_path || !file_exists($original_file_path)) {
            return $metadata;
        }

        // QA Edge Case #1 Fix: Prevent re-processing if the file extension is already AVIF/WebP
        $extension = strtolower(pathinfo($original_file_path, PATHINFO_EXTENSION));
        if (in_array($extension, ['avif', 'webp'])) {
            return $metadata;
        }

        $converter = WPIO_Converter::get_instance();
        $paths = $converter->process_image($original_file_path, $mime_type);

        if (empty($paths)) {
            return $metadata;
        }

        // Save the generated paths as post meta for the frontend filter to use
        update_post_meta($attachment_id, '_wpio_optimized_paths', $paths);

        // Now process the generated thumbnails (sizes)
        if (!empty($metadata['sizes'])) {
            $uploads = wp_get_upload_dir();
            $base_dir = $uploads['basedir'] . '/' . dirname($metadata['file']) . '/';

            foreach ($metadata['sizes'] as $size => $size_info) {
                $thumbnail_path = $base_dir . $size_info['file'];
                if (file_exists($thumbnail_path)) {
                    $thumb_paths = $converter->process_image($thumbnail_path, $size_info['mime-type']);

                    // Optional: Delete the original thumbnail size if only serving AVIF/WebP
                    // unlink( $thumbnail_path );

                    // Update the metadata for this size to point to the new formats, we might need a custom metadata structure
                    // For now, we keep WP's default metadata pointing to original, but we know the .avif and .webp exist
                }
            }
        }

        // Read the admin setting for preservation.
        // Defaults to TRUE (preserve) if the setting has never been saved.
        $settings = get_option('wpio_settings', []);
        $preserve_originals = isset($settings['preserve_originals']) ? (bool) $settings['preserve_originals'] : true;

        if (!$preserve_originals) {
            // The user has chosen to delete originals.
            // Promote the best available format (AVIF preferred, then WebP) as the primary attachment.
            if (!empty($paths['avif'])) {
                $this->replace_attachment_with_format($attachment_id, $original_file_path, $paths['avif'], 'image/avif', 'avif', $metadata);
            } elseif (!empty($paths['webp'])) {
                $this->replace_attachment_with_format($attachment_id, $original_file_path, $paths['webp'], 'image/webp', 'webp', $metadata);
            }
            return $metadata;
        }

        // Default (preserve originals): Return unmodified metadata so WP still considers
        // the JPEG/PNG the primary file. The `_wpio_optimized_paths` meta stores the
        // AVIF/WebP versions for use by the frontend filter.
        return $metadata;
    }

    /**
     * Replace the original attachment reference in the database with the optimized format
     */
    private function replace_attachment_with_format($attachment_id, $old_path, $new_path, $new_mime_type, $extension, &$metadata)
    {
        // Update standard post data
        wp_update_post([
            'ID' => $attachment_id,
            'post_mime_type' => $new_mime_type,
        ]);

        // Update _wp_attached_file meta
        $uploads = wp_get_upload_dir();
        $relative_new_path = str_replace(trailingslashit($uploads['basedir']), '', $new_path);
        update_attached_file($attachment_id, $relative_new_path);

        // Update the $metadata array that WP will save next
        if (isset($metadata['file'])) {
            $path_parts = pathinfo($metadata['file']);
            $metadata['file'] = $path_parts['dirname'] . '/' . $path_parts['filename'] . '.' . $extension;
        }

        // Replace the sizes in metadata
        if (!empty($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => &$size_info) {
                $path_parts = pathinfo($size_info['file']);
                $size_info['file'] = $path_parts['filename'] . '.' . $extension;
                $size_info['mime-type'] = $new_mime_type;
            }
        }

        // Delete the original file
        @unlink($old_path);
    }
}
