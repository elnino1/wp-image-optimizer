<?php
/**
 * Codeception / PHPUnit bootstrap for the Unit suite.
 *
 * Defines lightweight stubs for WordPress functions so the plugin classes can
 * be loaded and exercised without a real WordPress installation.
 *
 * All stubs use if (!function_exists()) guards so they are safe to call from
 * multiple test classes in the same suite run.
 */

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__, 2) . '/');
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

// ---------------------------------------------------------------------------
// Core WP hook functions
// ---------------------------------------------------------------------------

if (!function_exists('add_filter')) {
    function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1)
    {
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook_name, $callback, $priority = 10, $accepted_args = 1)
    {
        return true;
    }
}

// ---------------------------------------------------------------------------
// Attachment / post meta helpers
// ---------------------------------------------------------------------------

if (!function_exists('get_post_mime_type')) {
    function get_post_mime_type($id)
    {
        return 'image/jpeg';
    }
}

if (!function_exists('get_attached_file')) {
    function get_attached_file($id)
    {
        return dirname(__DIR__) . '/_data/test-image.jpg';
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($id, $key, $val)
    {
        global $mock_post_meta;
        $mock_post_meta[$id][$key] = $val;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($id, $key = '', $single = false)
    {
        global $mock_post_meta;
        if ($key === '') {
            return $mock_post_meta[$id] ?? [];
        }
        $val = $mock_post_meta[$id][$key] ?? null;
        return $single ? $val : [$val];
    }
}

// ---------------------------------------------------------------------------
// Upload directory helpers
// ---------------------------------------------------------------------------

if (!function_exists('wp_get_upload_dir')) {
    function wp_get_upload_dir()
    {
        return [
            'basedir' => dirname(__DIR__) . '/_data',
            'baseurl' => 'http://localhost/wp-content/uploads',
        ];
    }
}

if (!function_exists('wp_upload_dir')) {
    /**
     * Integration tests that use a temporary uploads directory store their
     * path in $GLOBALS['_wpio_test_uploads_basedir'] / '_baseurl'.
     * Fall back to the standard _data dir used by unit tests.
     */
    function wp_upload_dir()
    {
        return [
            'basedir' => $GLOBALS['_wpio_test_uploads_basedir'] ?? dirname(__DIR__) . '/_data',
            'baseurl' => $GLOBALS['_wpio_test_uploads_baseurl'] ?? 'http://localhost/wp-content/uploads',
        ];
    }
}

// ---------------------------------------------------------------------------
// Object cache stubs (always miss so tests aren't affected by caching)
// ---------------------------------------------------------------------------

if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = '', $force = false, &$found = null)
    {
        $found = false;
        return false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = '', $expire = 0)
    {
        return true;
    }
}

// ---------------------------------------------------------------------------
// Escaping / URL helpers
// ---------------------------------------------------------------------------

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url)
    {
        return $url;
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string)
    {
        return rtrim((string) $string, '/\\') . '/';
    }
}
