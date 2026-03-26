# Image Optimizer Plugin - Architecture Document

## 1. System Overview
The WordPress Image Optimizer Plugin is designed to intercept image uploads seamlessly within WordPress, converting standard raster formats (JPEG, PNG) into modern, highly-compressed formats (AVIF, WebP). It modifies the frontend output to serve these optimized versions via the HTML `<picture>` element with appropriate fallbacks, and features a bulk processing tool to retroactively optimize existing media library items.

## 2. Core Components

### 2.1 Plugin Bootstrap (`wp-image-optimizer.php`)
- **Role:** Main entry point for the plugin.
- **Responsibilities:**
  - Setup plugin constants (paths, URLs).
  - Register activation/deactivation hooks.
  - Instantiate and initialize the core components (Admin, Upload Handler, Frontend Filter, Bulk Optimizer).

### 2.2 Image Conversion Engine (`class-wpio-converter.php`)
- **Role:** The core service responsible for processing image files.
- **Dependencies:** PHP Imagick extension or modern GD extension (needs AVIF/WebP support).
- **Responsibilities:**
  - Determine server capabilities (does it support AVIF? WebP?).
  - Read input JPEG/PNG.
  - Apply lossless (or near-lossless) compression.
  - Output `.avif` and `.webp` versions alongside (or replacing) the original.
  - Delete or preserve the original file based on configuration (for this PRD, the original is not required to be saved, so delete it).
  - Return the paths to the generated files.

### 2.3 Upload Hooks Handler (`class-wpio-upload-handler.php`)
- **Role:** Intercepts media uploads.
- **Hooks:** `wp_generate_attachment_metadata`, `wp_handle_upload`.
- **Responsibilities:**
  - Trigger `WPIO_Converter` during the standard WordPress image generation flow (when thumbnails are created).
  - Update attachment metadata to reflect the new file paths and formats. Ensure standard WP media functions know where to find the images.

### 2.4 Frontend Output Filter (`class-wpio-frontend-filter.php`)
- **Role:** Modifies HTML output on the frontend to serve the optimized variants.
- **Hooks:** `wp_get_attachment_image`, `the_content`, `post_thumbnail_html`.
- **Responsibilities:**
  - Parse HTML for `<img>` tags originating from the WP Media Library.
  - Generate a `<picture>` element wrapper.
  - Add `<source type="image/avif" srcset="...">`.
  - Add `<source type="image/webp" srcset="...">`.
  - Maintain the existing `<img>` tag as the innermost fallback.

### 2.5 Admin & Settings Interface (`class-wpio-admin.php`)
- **Role:** Provides user controls.
- **Hooks:** `admin_menu`, `admin_init`, `admin_enqueue_scripts`.
- **Responsibilities:**
  - Register a submenu page under "Settings" or "Media".
  - Provide UI to view server support status (Imagick/GD AVIF/WebP).
  - Provide UI to initiate, monitor, and pause the bulk optimization process.

### 2.6 Bulk Optimizer / Database Updater (`class-wpio-bulk-optimizer.php`)
- **Role:** Asynchronously processes existing images in the background.
- **Technologies:** WP REST API endpoints or `admin-ajax.php`.
- **Responsibilities:**
  - Expose an endpoint to fetch batches of unoptimized attachment IDs.
  - Expose an endpoint to process a single attachment ID (run `WPIO_Converter`).
  - Search and replace database references (e.g., in `wp_posts.post_content` or page builder meta) if URLs must be changed (though ideally, keeping the same base filename and just changing the extension makes this easier).

## 3. Data Flow

### Upload Flow:
1. User uploads `image.jpg`.
2. WP handles initial upload.
3. `wp_generate_attachment_metadata` fires.
4. `WPIO_Upload_Handler` catches the hook, passes file path to `WPIO_Converter`.
5. `WPIO_Converter` generates `image.avif` and `image.webp`.
6. Original `image.jpg` is deleted or replaced.
7. Attachment metadata is updated to point to the `.avif` as the primary, with `.webp` tracked in meta.

### Frontend Flow:
1. User requests a page.
2. WP assembles the content.
3. `the_content` or `wp_get_attachment_image` hook fires.
4. `WPIO_Frontend_Filter` parses the HTML.
5. If the image has optimized variants in its metadata, the `<img>` is wrapped in a `<picture>` tag pointing to `.avif` and `.webp`.

### Bulk Optimization Flow:
1. Admin opens Settings page, clicks "Start Bulk Optimization".
2. JS fetches a list of 1000 attachment IDs that haven't been processed.
3. JS sends asynchronous requests (batching 5-10 at a time) to the Bulk Optimizer endpoint.
4. Endpoint processes the images via `WPIO_Converter`, updates attachment metadata.
5. JS updates progress bar in UI.
6. Once complete, optionally trigger a database search-replace for hardcoded URLs in `post_content`.

## 4. Technical Stack
- **Languages:** PHP 7.4+ (min WP requirement), Modern vanilla JavaScript for the admin bulk processor UI.
- **WP Integration:** Standard Hooks/Filters API, REST API for bulk processing, Settings API for admin.
- **Image Processing:** `Imagick` (preferred for AVIF) or `gd`.

## 5. Security & Performance Considerations
- **Security:** Ensure uploaded files are validated as actual images (already somewhat handled by WP). Nonce verification on all admin-ajax/REST API calls for bulk processing.
- **Performance:** Bulk processing must not timeout. It will use a chunked, asynchronous JavaScript-driven approach (acting like a queue) rather than processing many images in a single PHP request.

## 6. Handoff to Dev
- The architecture requires specific handling of WordPress metadata.
- Ensure the plugin fails gracefully if the server lacks AVIF or WebP encoding support.
