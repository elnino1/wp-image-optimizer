<?php
/**
 * Plugin Name: WordPress Image Optimizer
 * Plugin URI:  https://example.com/wp-image-optimizer
 * Description: Automatically converts uploaded JPEG and PNG images to AVIF and WebP formats, applying additional compression.
 * Version:     1.0.0
 * Author:      David Lenir
 * Author URI:  https://example.com
 * License:     GPL2
 * Text Domain: wp-image-optimizer
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('WPIO_VERSION', '1.0.0');
define('WPIO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPIO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Core components
require_once WPIO_PLUGIN_DIR . 'includes/class-wpio-converter.php';
require_once WPIO_PLUGIN_DIR . 'includes/class-wpio-upload-handler.php';
require_once WPIO_PLUGIN_DIR . 'includes/class-wpio-frontend-filter.php';
require_once WPIO_PLUGIN_DIR . 'includes/class-wpio-admin.php';
require_once WPIO_PLUGIN_DIR . 'includes/class-wpio-bulk-optimizer.php';

/**
 * Main plugin class
 */
class WP_Image_Optimizer
{

    /**
     * Instance of this class.
     */
    private static $instance = null;

    /**
     * Return an instance of this class.
     */
    public static function get_instance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct()
    {
        // Initialize Converter
        WPIO_Converter::get_instance();

        // Uncomment as files are added:
        WPIO_Upload_Handler::get_instance();
        WPIO_Frontend_Filter::get_instance();
        WPIO_Admin::get_instance();
        WPIO_Bulk_Optimizer::get_instance();
    }
}

// Bootstrap the plugin
WP_Image_Optimizer::get_instance();
