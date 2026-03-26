<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPIO_Bulk_Optimizer
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
        add_action('wp_ajax_wpio_get_unoptimized_images', [$this, 'ajax_get_unoptimized_images']);
        add_action('wp_ajax_wpio_process_image', [$this, 'ajax_process_image']);
    }

    /**
     * Returns an array of attachment IDs that haven't been optimized yet
     */
    public function ajax_get_unoptimized_images()
    {
        check_ajax_referer('wpio_bulk_optimize', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }

        // Query for images (jpeg/png) that do NOT have our custom meta key
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png'],
            'post_status' => 'inherit',
            'posts_per_page' => -1, // Get all to process
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => '_wpio_optimized_paths',
                    'compare' => 'NOT EXISTS',
                ]
            ]
        ];

        $query = new WP_Query($args);

        wp_send_json_success([
            'ids' => $query->posts,
            'total' => $query->found_posts,
        ]);
    }

    /**
     * Process a single image from the bulk optimizer
     */
    public function ajax_process_image()
    {
        check_ajax_referer('wpio_bulk_optimize', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied.');
        }

        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;

        if (!$attachment_id) {
            wp_send_json_error('Invalid attachment ID.');
        }

        // Simulate the upload process for this attachment
        $metadata = wp_get_attachment_metadata($attachment_id);

        if (!$metadata) { // Sometimes old images don't have metadata generated
            // Try to generate it
            $file = get_attached_file($attachment_id);
            // QA Edge Case #2 Fix: Ensure $file is a valid non-empty string before checking file_exists
            if (!empty($file) && is_string($file) && file_exists($file)) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $metadata = wp_generate_attachment_metadata($attachment_id, $file);
                wp_update_attachment_metadata($attachment_id, $metadata);
            } else {
                // Mark as processed so it doesn't get picked up again
                update_post_meta($attachment_id, '_wpio_optimized_paths', 'failed-missing');
                wp_send_json_error("File missing or invalid path for ID {$attachment_id}");
            }
        }

        // Use the upload handler directly
        $upload_handler = WPIO_Upload_Handler::get_instance();
        $new_metadata = $upload_handler->process_attachment_metadata($metadata, $attachment_id, 'bulk');

        // Check if it actually did something (the meta key should be created inside process_attachment_metadata)
        $paths = get_post_meta($attachment_id, '_wpio_optimized_paths', true);

        if (empty($paths)) {
            // Mark as tried so we don't try again endlessly
            update_post_meta($attachment_id, '_wpio_optimized_paths', 'failed');
            wp_send_json_error("Failed to optimize ID {$attachment_id}");
        }

        // Update the attachment metadata in DB since upload handler modified it
        wp_update_attachment_metadata($attachment_id, $new_metadata);

        // Since we are now preserving the original images as the primary attachment,
        // we DO NOT need to perform a database Search and Replace on `post_content`.
        // The original `<img>` tags in the content will remain pointing to `.jpg` / `.png`.
        // The `WPIO_Frontend_Filter` will dynamically wrap these in `<picture>` tags
        // with AVIF/WebP sources during the `the_content` filter on the frontend.

        wp_send_json_success([
            'message' => "Successfully optimized image ID {$attachment_id}.",
            'paths' => $paths
        ]);
    }
}
