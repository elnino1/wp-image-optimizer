<?php


namespace Tests\Unit;

use Tests\Support\UnitTester;

class UploadTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;
    protected $test_image_path;

    protected function _before()
    {
        $this->test_image_path = __DIR__ . '/../_data/test-image.jpg';

        // Mock ABSPATH and some WP functions since we can't load the full WP Core easily
        // without wp-browser's actual `wpunit` suite type. We'll do a partial mock.
        if (!defined('ABSPATH')) {
            define('ABSPATH', __DIR__ . '/');
        }

        require_once dirname(__DIR__, 2) . '/includes/class-wpio-converter.php';

        // We need to mock a few native WP functions used in the handler and singleton construction
        if (!function_exists('add_filter')) {
            function add_filter($hook_name, $callback, $priority = 10, $accepted_args = 1)
            {
                return true;
            }
        }

        require_once dirname(__DIR__, 2) . '/includes/class-wpio-upload-handler.php';

        // We need to mock a few native WP functions used in the handler
        if (!function_exists('get_post_mime_type')) {
            function get_post_mime_type($id)
            {
                return 'image/jpeg';
            }
        }
        if (!function_exists('get_attached_file')) {
            function get_attached_file($id)
            {
                return __DIR__ . '/../_data/test-image.jpg';
            }
        }
        if (!function_exists('update_post_meta')) {
            function update_post_meta($id, $key, $val)
            {
                // Store in a global so we can assert it
                global $mock_post_meta;
                $mock_post_meta[$id][$key] = $val;
            }
        }
        if (!function_exists('wp_get_upload_dir')) {
            function wp_get_upload_dir()
            {
                return ['basedir' => __DIR__ . '/../_data', 'baseurl' => 'http://localhost/wp-content/uploads'];
            }
        }
    }

    protected function _after()
    {
        $base_path = pathinfo($this->test_image_path, PATHINFO_DIRNAME) . '/' . pathinfo($this->test_image_path, PATHINFO_FILENAME);
        @unlink($base_path . '.avif');
        @unlink($base_path . '.webp');
    }

    public function testUploadHandlerInterception()
    {
        $handler = \WPIO_Upload_Handler::get_instance();

        // Mock standard WP attachment metadata
        $metadata = [
            'width' => 100,
            'height' => 100,
            'file' => 'test-image.jpg',
            'sizes' => []
        ];

        global $mock_post_meta;
        $mock_post_meta = [];

        // Run the filter hook function manually
        $updated_metadata = $handler->process_attachment_metadata($metadata, 123, 'upload');

        // Since PRD revision, metadata should NOT be modified to replace the original
        $this->assertEquals($metadata, $updated_metadata);

        // However, the `_wpio_optimized_paths` meta should be set
        $this->assertArrayHasKey(123, $mock_post_meta);
        $this->assertArrayHasKey('_wpio_optimized_paths', $mock_post_meta[123]);

        $paths = $mock_post_meta[123]['_wpio_optimized_paths'];

        // Depending on server support, AVIF and WebP might be present
        $converter = \WPIO_Converter::get_instance();
        $support = $converter->get_server_support();

        if ($support['avif']) {
            $this->assertArrayHasKey('avif', $paths);
            $this->assertFileExists($paths['avif']);
        }

        if ($support['webp']) {
            $this->assertArrayHasKey('webp', $paths);
            $this->assertFileExists($paths['webp']);
        }
    }
}
