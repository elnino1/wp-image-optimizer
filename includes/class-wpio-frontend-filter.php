<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPIO_Frontend_Filter
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
        // Filter standard content
        add_filter('the_content', [$this, 'filter_content_images'], 99);
        // Filter post thumbnails (featured images)
        add_filter('post_thumbnail_html', [$this, 'filter_image_html'], 99, 5);
        // Filter any image retrieved via wp_get_attachment_image
        add_filter('wp_get_attachment_image', [$this, 'filter_image_html'], 99, 5);
    }

    /**
     * Filters full post content to replace <img> tags with <picture> tags
     */
    public function filter_content_images($content)
    {
        if (empty($content)) {
            return $content;
        }

        // QA Edge Case #3 Fix: Cache the filtered content to avoid running regex on every page load
        // We use a hash of the content as the cache key.
        $cache_key = 'wpio_content_' . md5($content);
        $cached_content = wp_cache_get($cache_key, 'wpio_frontend');

        if (false !== $cached_content) {
            return $cached_content;
        }

        // QA Edge Case: Avoid double-wrapping img tags that are already inside a <picture> element.
        // Strategy: temporarily replace <picture>...</picture> blocks with placeholders, run the img
        // replacement on the remaining content, then restore the placeholders.
        $placeholders = [];
        $filtered_content = preg_replace_callback(
            '/<picture[\s\S]*?<\/picture>/is',
            function ($matches) use (&$placeholders) {
                $key = '__WPIO_PICTURE_' . count($placeholders) . '__';
                $placeholders[$key] = $matches[0];
                return $key;
            },
            $content
        );

        // Now replace bare <img> tags (none of which are inside a picture block)
        $filtered_content = preg_replace_callback('/<img[^>]+>/is', function ($matches) {
            return $this->convert_img_tag($matches[0]);
        }, $filtered_content);

        // Restore the original picture blocks
        if (!empty($placeholders)) {
            $filtered_content = str_replace(array_keys($placeholders), array_values($placeholders), $filtered_content);
        }

        // Cache the result for 12 hours (it will be cleared if post is updated, usually object cache handles this)
        wp_cache_set($cache_key, $filtered_content, 'wpio_frontend', 12 * HOUR_IN_SECONDS);

        return $filtered_content;
    }

    /**
     * Filters individual image HTML strings (like featured images)
     */
    public function filter_image_html($html, $post_id = null, $post_thumbnail_id = null, $size = null, $attr = null)
    {
        return $this->convert_img_tag($html);
    }

    /**
     * Converts a standard <img> tag to a <picture> tag with AVIF and WebP sources 
     * if the optimized files exist next to the original URL.
     */
    private function convert_img_tag($img_html)
    {
        // If it's already in a picture tag, leave it alone (crude check)
        if (strpos($img_html, '<picture') !== false) {
            return $img_html;
        }

        // Extract the src attribute
        preg_match('/src=["\']([^"\']+)["\']/is', $img_html, $src_match);
        if (empty($src_match[1])) {
            return $img_html;
        }

        $original_url = $src_match[1];

        // Only process local images (inside wp-content/uploads)
        $upload_dir = wp_upload_dir();
        if (strpos($original_url, $upload_dir['baseurl']) === false) {
            return $img_html;
        }

        // Since our upload handler replaces the actual attachment to point to .avif or .webp,
        // the src might ALREADY be an .avif or .webp file.
        $extension = strtolower(pathinfo($original_url, PATHINFO_EXTENSION));

        // We want to construct the picture tag. 
        // If the main src is .avif, we should provide .webp as a fallback for Safari/older browsers.
        // If the main src is .webp, we should provide .avif just in case, though less common.
        // If it's still .jpeg/.png, we check if .avif/.webp exist.

        $base_url_no_ext = preg_replace('/\.[^.]+$/', '', $original_url);

        $avif_url = $base_url_no_ext . '.avif';
        $webp_url = $base_url_no_ext . '.webp';

        // We need to verify if these files actually exist on disk before serving them.
        $relative_path = str_replace(trailingslashit($upload_dir['baseurl']), '', $base_url_no_ext);
        $base_file_path = trailingslashit($upload_dir['basedir']) . $relative_path;

        $avif_exists = file_exists($base_file_path . '.avif');
        $webp_exists = file_exists($base_file_path . '.webp');

        if (!$avif_exists && !$webp_exists) {
            return $img_html; // No optimized versions exist
        }

        // Build the picture tag
        $picture_html = '<picture>';

        if ($avif_exists) {
            // Extract srcset if it exists (for responsive images)
            $srcset_avif = $this->generate_extension_srcset($img_html, 'avif');
            $srcset_attr = $srcset_avif ? ' srcset="' . esc_attr($srcset_avif) . '"' : ' srcset="' . esc_url($avif_url) . '"';

            // Extract sizes if it exists
            preg_match('/sizes=["\']([^"\']+)["\']/is', $img_html, $sizes_match);
            $sizes_attr = !empty($sizes_match[1]) ? ' sizes="' . esc_attr($sizes_match[1]) . '"' : '';

            $picture_html .= '<source type="image/avif"' . $srcset_attr . $sizes_attr . '>';
        }

        if ($webp_exists) {
            $srcset_webp = $this->generate_extension_srcset($img_html, 'webp');
            $srcset_attr = $srcset_webp ? ' srcset="' . esc_attr($srcset_webp) . '"' : ' srcset="' . esc_url($webp_url) . '"';

            preg_match('/sizes=["\']([^"\']+)["\']/is', $img_html, $sizes_match);
            $sizes_attr = !empty($sizes_match[1]) ? ' sizes="' . esc_attr($sizes_match[1]) . '"' : '';

            $picture_html .= '<source type="image/webp"' . $srcset_attr . $sizes_attr . '>';
        }

        // We no longer need to force the fallback <img> to be WebP because
        // we are preserving the original JPEG/PNG file as per the updated PRD.
        // Therefore, the original `<img>` tag remains untouched, providing
        // the ultimate fallback.

        $picture_html .= $img_html;
        $picture_html .= '</picture>';

        return $picture_html;
    }

    /**
     * Helper to rewrite a srcset string to point to a specific extension
     */
    private function generate_extension_srcset($img_html, $new_extension)
    {
        preg_match('/srcset=["\']([^"\']+)["\']/is', $img_html, $srcset_match);
        if (empty($srcset_match[1])) {
            return false;
        }

        $original_srcset = $srcset_match[1];
        // Replace .jpg, .jpeg, .png, .avif, .webp with the new extension
        $new_srcset = preg_replace('/\.(jpg|jpeg|png|avif|webp)(\s+[0-9]+[wx])/i', '.' . $new_extension . '$2', $original_srcset);

        return $new_srcset;
    }
}
