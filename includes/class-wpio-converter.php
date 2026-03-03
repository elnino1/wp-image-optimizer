<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPIO_Converter
{

    private static $instance = null;

    /**
     * High quality setting for AVIF/WebP.
     */
    private $quality = 80;

    public static function get_instance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Check if server supports required image manipulation formats
     * 
     * @return array Array of booleans for support status ['avif' => bool, 'webp' => bool]
     */
    public function get_server_support()
    {
        $support = [
            'avif' => false,
            'webp' => false,
        ];

        // Check Imagick (Preferred for modern formats if compiled with support)
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $imagick = new Imagick();
            $formats = $imagick->queryFormats();
            if (in_array('AVIF', $formats) || in_array('AVIF', array_map('strtoupper', $formats))) {
                $support['avif'] = true;
            }
            if (in_array('WEBP', $formats) || in_array('WEBP', array_map('strtoupper', $formats))) {
                $support['webp'] = true;
            }
        }

        // Fallback to GD if Imagick doesn't support them or isn't installed
        if (extension_loaded('gd') && function_exists('gd_info')) {
            $gd_info = gd_info();
            if (!$support['avif'] && isset($gd_info['AVIF Support']) && $gd_info['AVIF Support']) {
                $support['avif'] = true;
            }
            if (!$support['webp'] && isset($gd_info['WebP Support']) && $gd_info['WebP Support']) {
                $support['webp'] = true;
            }
        }

        return $support;
    }

    /**
     * Optimize an image file (convert to AVIF and WebP)
     *
     * @param string $source_path Path to the original file.
     * @param string $mime_type   The MIME type of the original file.
     * @return array Generated file paths ['avif' => path, 'webp' => path] or empty array on failure.
     */
    public function process_image($source_path, $mime_type)
    {
        if (!file_exists($source_path)) {
            return [];
        }

        // Only process JPEG and PNG
        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return [];
        }

        $support = $this->get_server_support();
        if (!$support['avif'] && !$support['webp']) {
            return []; // No support for modern formats
        }

        $path_parts = pathinfo($source_path);
        $base_path = $path_parts['dirname'] . '/' . $path_parts['filename'];

        $generated = [];

        // Attempt optimization using WP Image Editor first if possible, 
        // but WP image editor is abstracted. We might need direct Imagick/GD depending on WP version support for AVIF.

        // Let's implement direct conversion for robustness, preferring Imagick.
        $imagick_loaded = extension_loaded('imagick') && class_exists('Imagick');

        if ($support['avif']) {
            $avif_path = $base_path . '.avif';
            if ($this->convert_to($source_path, $avif_path, 'avif', $mime_type, $imagick_loaded)) {
                $generated['avif'] = $avif_path;
            }
        }

        if ($support['webp']) {
            $webp_path = $base_path . '.webp';
            if ($this->convert_to($source_path, $webp_path, 'webp', $mime_type, $imagick_loaded)) {
                $generated['webp'] = $webp_path;
            }
        }

        return $generated;
    }

    /**
     * Converts image to a specific format
     */
    private function convert_to($source, $destination, $format, $mime_type, $use_imagick)
    {
        if ($use_imagick) {
            try {
                $image = new Imagick($source);
                $image->setImageFormat($format);
                $image->setImageCompressionQuality($this->quality);
                // QA Edge Case #5 Fix: Do not strip metadata (like Exif/ICC profiles)
                // as this can break color rendering for certain images.
                // $image->stripImage(); 
                $result = $image->writeImage($destination);
                $image->clear();
                $image->destroy();
                return $result === true;
            } catch (Exception $e) {
                // Fallback to GD if Imagick fails
            }
        }

        // GD Fallback
        if ($mime_type === 'image/jpeg') {
            $image = @imagecreatefromjpeg($source);
        } elseif ($mime_type === 'image/png') {
            $image = @imagecreatefrompng($source);
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
        } else {
            return false;
        }

        if (!$image) {
            return false;
        }

        $result = false;
        if ($format === 'webp' && function_exists('imagewebp')) {
            $result = imagewebp($image, $destination, $this->quality);
        } elseif ($format === 'avif' && function_exists('imageavif')) {
            $result = imageavif($image, $destination, $this->quality);
        }

        imagedestroy($image);
        return $result;
    }
}
