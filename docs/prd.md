# WordPress Image Optimizer Plugin

## Overview
A WordPress plugin that automatically converts uploaded JPEG and PNG images to AVIF and WebP formats, applies additional compression without quality loss, and provides backward compatibility for unsupported browsers. The plugin also processes existing images in the media library and updates image paths across pages and products.

## Goals
- Decrease page load times by serving lighter image formats (AVIF/WebP).
- Automate image optimization on upload.
- Retroactively optimize existing media library images.
- Provide a robust fallback mechanism for browsers that do not support modern image formats.

## Key Features & Requirements

### 1. Upload Processing
- When a user uploads a JPEG or PNG image to the Media Library, automatically convert it to:
  - **AVIF** (Primary)
  - **WebP** (Fallback)
- Apply additional lossless (or visually lossless) compression to decrease file sizes without sacrificing quality.
- The original JPEG/PNG images must be **saved by default**.
  - A settings option should be provided to "Delete Original Images" (disabled by default).

### 2. Retroactive Optimization (Existing Images)
- Provide a mechanism (e.g., a bulk processing tool in the WP Admin dashboard) to scan the existing Media Library.
- Convert existing JPEG/PNG images to AVIF and WebP formats.
- Update the database paths (e.g., in `wp_posts`, `wp_postmeta`, page builders, or WooCommerce products) so existing content points to the newly optimized images.

### 3. Fallback Mechanism
- When rendering images on the frontend (pages, posts, products):
  - Attempt to serve the **AVIF** format first.
  - If the user's browser does not support AVIF, fallback to the **WebP** image.
  - If neither modern format is supported, fallback to the **Original JPEG/PNG** image (if preserved).
  - Implementation should utilize the `<picture>` HTML element with multiple `<source>` tags for different content types, leaving the original `<img>` tag effectively pointing to the original fallback image.

## Non-Functional Requirements
- **Performance:** Bulk optimization of existing media library should run asynchronously (e.g., using AJAX or wp-cron/Action Scheduler) to avoid server timeouts.
- **Compatibility:** Ensure compatibility with WooCommerce and standard WordPress functions like `wp_get_attachment_image`.

## User Interface Requirements
- A settings page under standard WP Admin interface to manage the plugin options:
  - Start/Pause bulk conversion process.
  - View conversion progress.

## Out of Scope
- Support for other image formats for initial conversion (e.g., converting GIF or BMP).

## Technical Considerations
- Server modules: Requires checking for server-level support for AVIF and WebP creation (e.g., latest `gd` or `imagick` extensions in PHP).
- WordPress Core integration: Hooking into `wp_handle_upload` and standard image generation routines.

## Development Stories
1. **Core Image Conversion Engine:** Implement the logic to take an image path and create AVIF/WebP, replacing the original file.
2. **Upload Hooks:** Hook into `wp_generate_attachment_metadata` or `wp_handle_upload` to process images at upload time.
3. **Frontend Fallback Integration:** Modify how WordPress outputs images (filtering `wp_get_attachment_image`, `the_content`, etc.) to use the `<picture>` element pattern.
4. **Bulk Processing Feature:** Create WP admin page for batch conversion, utilizing asynchronous processing to convert existing media.
5. **Database References Updater:** Create queries/logic to update URLs in `post_content` and metadata when existing images are converted.
