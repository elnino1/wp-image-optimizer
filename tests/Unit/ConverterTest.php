<?php


namespace Tests\Unit;

use Tests\Support\UnitTester;

class ConverterTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;
    protected $test_image_path;

    protected function _before()
    {
        // Define path to our test fixture image
        $this->test_image_path = __DIR__ . '/../_data/test-image.jpg';

        // Mock ABSPATH so the plugin file doesn't exit when loaded outside WP
        if (!defined('ABSPATH')) {
            define('ABSPATH', __DIR__ . '/');
        }

        // Ensure the WPIO_Converter class is loaded
        require_once dirname(__DIR__, 2) . '/includes/class-wpio-converter.php';
    }

    protected function _after()
    {
        // Clean up generated files
        $base_path = pathinfo($this->test_image_path, PATHINFO_DIRNAME) . '/' . pathinfo($this->test_image_path, PATHINFO_FILENAME);
        @unlink($base_path . '.avif');
        @unlink($base_path . '.webp');
    }

    public function testConverterCanDetectServerSupport()
    {
        $converter = \WPIO_Converter::get_instance();
        $support = $converter->get_server_support();

        $this->assertIsArray($support);
        $this->assertArrayHasKey('avif', $support);
        $this->assertArrayHasKey('webp', $support);
    }

    public function testImageConversionGeneratesFiles()
    {
        $converter = \WPIO_Converter::get_instance();
        $support = $converter->get_server_support();

        // If the testing server has no support for either, we skip the test
        if (!$support['avif'] && !$support['webp']) {
            $this->markTestSkipped('Server does not support AVIF or WebP conversion. Skipping test.');
        }

        // Process the fixture image
        $results = $converter->process_image($this->test_image_path, 'image/jpeg');

        $this->assertIsArray($results);

        if ($support['avif']) {
            $this->assertArrayHasKey('avif', $results);
            $this->assertFileExists($results['avif']);
        }

        if ($support['webp']) {
            $this->assertArrayHasKey('webp', $results);
            $this->assertFileExists($results['webp']);
        }
    }
}
