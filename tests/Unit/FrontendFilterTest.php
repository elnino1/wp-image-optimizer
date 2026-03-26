<?php

namespace Tests\Unit;

use Tests\Support\UnitTester;

/**
 * Integration tests for WPIO_Frontend_Filter.
 *
 * These tests exercise the HTML transformation logic in isolation,
 * using filesystem stubs instead of a real WordPress environment.
 * A real test-image.jpg is used as the source; AVIF/WebP variants
 * are created/destroyed per test so the file-existence checks inside
 * the filter actually work.
 */
class FrontendFilterTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;

    /** @var string Directory that acts as the fake uploads base dir */
    protected string $uploads_dir;

    /** @var string URL that acts as the fake uploads base URL */
    protected string $uploads_url;

    /** @var string Path to the copied test image inside the fake uploads dir */
    protected string $image_path;

    /** @var string URL to the copied test image */
    protected string $image_url;

    protected function _before(): void
    {
        // Load classes (all WP stubs are defined in _bootstrap.php)
        require_once dirname(__DIR__, 2) . '/includes/class-wpio-converter.php';
        require_once dirname(__DIR__, 2) . '/includes/class-wpio-frontend-filter.php';

        // ----------------------------------------------------------------
        // Build a fake uploads directory using the _data fixture image
        // ----------------------------------------------------------------

        $this->uploads_dir = sys_get_temp_dir() . '/wpio_test_uploads_' . uniqid();
        mkdir($this->uploads_dir, 0777, true);
        $this->uploads_url = 'http://localhost/wp-content/uploads';

        // Expose to the wp_upload_dir() stub above
        $GLOBALS['_wpio_test_uploads_basedir'] = $this->uploads_dir;
        $GLOBALS['_wpio_test_uploads_baseurl'] = $this->uploads_url;

        // Copy the fixture image into the fake uploads dir
        $source = dirname(__DIR__) . '/_data/test-image.jpg';
        $this->image_path = $this->uploads_dir . '/test-image.jpg';
        copy($source, $this->image_path);

        $this->image_url = $this->uploads_url . '/test-image.jpg';
    }

    protected function _after(): void
    {
        // Clean up generated files and temp dir
        $base = $this->uploads_dir . '/test-image';
        @unlink($base . '.avif');
        @unlink($base . '.webp');
        @unlink($this->image_path);
        @rmdir($this->uploads_dir);
    }

    // ====================================================================
    // Helper
    // ====================================================================

    private function makeImgTag(string $url, string $extra = ''): string
    {
        return '<img src="' . $url . '" width="100" height="100"' . ($extra ? ' ' . $extra : '') . '>';
    }

    private function getFilter(): \WPIO_Frontend_Filter
    {
        // Reset singleton so each test gets a clean instance
        $ref = new \ReflectionClass(\WPIO_Frontend_Filter::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        return \WPIO_Frontend_Filter::get_instance();
    }

    // ====================================================================
    // Tests
    // ====================================================================

    /**
     * When no AVIF/WebP files exist, the img tag must be returned unmodified.
     */
    public function testImgTagPassthroughWhenNoOptimizedFilesExist(): void
    {
        $filter = $this->getFilter();
        $img_tag = $this->makeImgTag($this->image_url);

        $result = $filter->filter_image_html($img_tag);

        $this->assertSame($img_tag, $result, 'img tag should be returned unchanged when no AVIF/WebP files exist');
        $this->assertStringNotContainsString('<picture>', $result);
    }

    /**
     * When only a WebP file exists, the filter must wrap the img in a <picture>
     * with a single <source type="image/webp"> and keep the original <img> as fallback.
     */
    public function testPictureTagGeneratedWithWebpOnly(): void
    {
        // Create a stub WebP file
        touch($this->uploads_dir . '/test-image.webp');

        $filter = $this->getFilter();
        $img_tag = $this->makeImgTag($this->image_url);

        $result = $filter->filter_image_html($img_tag);

        $this->assertStringContainsString('<picture>', $result);
        $this->assertStringContainsString('</picture>', $result);
        $this->assertStringContainsString('type="image/webp"', $result);
        $this->assertStringNotContainsString('type="image/avif"', $result);
        // Original img must still be present inside the picture
        $this->assertStringContainsString($img_tag, $result);
    }

    /**
     * When only an AVIF file exists, the filter must produce a picture tag
     * with a single <source type="image/avif">.
     */
    public function testPictureTagGeneratedWithAvifOnly(): void
    {
        touch($this->uploads_dir . '/test-image.avif');

        $filter = $this->getFilter();
        $img_tag = $this->makeImgTag($this->image_url);

        $result = $filter->filter_image_html($img_tag);

        $this->assertStringContainsString('<picture>', $result);
        $this->assertStringContainsString('type="image/avif"', $result);
        $this->assertStringNotContainsString('type="image/webp"', $result);
        $this->assertStringContainsString($img_tag, $result);
    }

    /**
     * When both AVIF and WebP exist, AVIF source must appear before WebP source
     * (browser picks the first matching source).
     */
    public function testAvifSourceAppearsBeforeWebpSource(): void
    {
        touch($this->uploads_dir . '/test-image.avif');
        touch($this->uploads_dir . '/test-image.webp');

        $filter = $this->getFilter();
        $img_tag = $this->makeImgTag($this->image_url);

        $result = $filter->filter_image_html($img_tag);

        $avif_pos = strpos($result, 'type="image/avif"');
        $webp_pos = strpos($result, 'type="image/webp"');

        $this->assertNotFalse($avif_pos, 'AVIF source should be present');
        $this->assertNotFalse($webp_pos, 'WebP source should be present');
        $this->assertLessThan($webp_pos, $avif_pos, 'AVIF must appear before WebP');
    }

    /**
     * Images that are not from the uploads URL should pass through unchanged.
     */
    public function testExternalImagesAreNotModified(): void
    {
        touch($this->uploads_dir . '/test-image.avif');

        $filter = $this->getFilter();
        $img_tag = '<img src="https://external.cdn.com/image.jpg" width="100" height="100">';

        $result = $filter->filter_image_html($img_tag);

        $this->assertSame($img_tag, $result, 'External images must not be wrapped in a picture tag');
    }

    /**
     * An img tag already inside a <picture> must be returned unchanged to avoid double-wrapping.
     */
    public function testAlreadyWrappedInPictureIsNotDoubleWrapped(): void
    {
        touch($this->uploads_dir . '/test-image.avif');

        $filter = $this->getFilter();
        $existing_picture = '<picture><source type="image/avif" srcset="' . $this->image_url . '"><img src="' . $this->image_url . '"></picture>';

        $result = $filter->filter_content_images($existing_picture);

        // The outermost picture wrapper should appear only once
        $this->assertSame(1, substr_count($result, '<picture>'), 'Should not be double-wrapped in <picture>');
    }

    /**
     * filter_content_images must convert img tags embedded in HTML content.
     */
    public function testFilterContentImagesConvertsEmbeddedImgTags(): void
    {
        touch($this->uploads_dir . '/test-image.webp');

        $filter = $this->getFilter();
        $content = '<p>Some text</p>' . $this->makeImgTag($this->image_url) . '<p>More text</p>';

        $result = $filter->filter_content_images($content);

        $this->assertStringContainsString('<picture>', $result);
        $this->assertStringContainsString('type="image/webp"', $result);
        $this->assertStringContainsString('<p>Some text</p>', $result);
        $this->assertStringContainsString('<p>More text</p>', $result);
    }

    /**
     * When an img tag contains a srcset, the generated <source> srcset must also
     * point to the new format (WebP in this case).
     */
    public function testSrcsetIsRewrittenToNewFormat(): void
    {
        touch($this->uploads_dir . '/test-image.webp');

        $filter = $this->getFilter();
        $img_tag = $this->makeImgTag(
            $this->image_url,
            'srcset="' . $this->image_url . ' 1x, ' . $this->uploads_url . '/test-image-2x.jpg 2x"'
        );

        $result = $filter->filter_image_html($img_tag);

        $this->assertStringContainsString('image/webp', $result);
        // The .webp variant should appear in srcset
        $this->assertStringContainsString('.webp', $result);
    }
}
