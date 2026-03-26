<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPIO_Admin
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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_post_wpio_save_settings', [$this, 'save_settings']);
    }

    public function save_settings()
    {
        check_admin_referer('wpio_save_settings');
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }

        $preserve = isset($_POST['wpio_preserve_originals']) ? 1 : 0;

        $settings = get_option('wpio_settings', []);
        $settings['preserve_originals'] = $preserve;
        update_option('wpio_settings', $settings);

        wp_redirect(add_query_arg(
            ['page' => 'wp-image-optimizer', 'settings-updated' => '1'],
            admin_url('upload.php')
        ));
        exit;
    }

    public function add_admin_menu()
    {
        add_media_page(
            'Image Optimizer',
            'Image Optimizer',
            'manage_options',
            'wp-image-optimizer',
            [$this, 'render_admin_page']
        );
    }

    public function enqueue_scripts($hook)
    {
        if ('media_page_wp-image-optimizer' !== $hook) {
            return;
        }

        // Simple inline script for the bulk optimizer
        wp_enqueue_script('wpio-admin-js', WPIO_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], WPIO_VERSION, true);
        wp_localize_script('wpio-admin-js', 'wpio_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpio_bulk_optimize'),
        ]);

        wp_enqueue_style('wpio-admin-css', WPIO_PLUGIN_URL . 'assets/css/admin.css', [], WPIO_VERSION);
    }

    public function render_admin_page()
    {
        $converter = WPIO_Converter::get_instance();
        $support = $converter->get_server_support();
        $settings = get_option('wpio_settings', []);
        $preserve_originals = isset($settings['preserve_originals']) ? (bool) $settings['preserve_originals'] : true;
        ?>
        <div class="wrap wpio-admin-wrap">
            <h1>WordPress Image Optimizer</h1>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved.</p>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2>System Status</h2>
                <p>
                    <strong>AVIF Support:</strong>
                    <?php echo $support['avif'] ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No</span>'; ?>
                </p>
                <p>
                    <strong>WebP Support:</strong>
                    <?php echo $support['webp'] ? '<span style="color:green;">Yes</span>' : '<span style="color:red;">No</span>'; ?>
                </p>
                <?php if (!$support['avif'] && !$support['webp']): ?>
                    <div class="notice notice-error inline">
                        <p>Your server does not support modern image formats. The plugin will not work.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h2>Settings</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpio_save_settings">
                    <?php wp_nonce_field('wpio_save_settings'); ?>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label for="wpio_preserve_originals">Preserve Original Images</label>
                            </th>
                            <td>
                                <input type="checkbox" id="wpio_preserve_originals" name="wpio_preserve_originals" value="1"
                                    <?php checked($preserve_originals, true); ?>>
                                <p class="description">
                                    When enabled (recommended), original JPEG/PNG files are kept as a final browser fallback.
                                    Disabling this will delete originals after conversion to AVIF/WebP.
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>

            <div class="card wpio-bulk-card">
                <h2>Bulk Optimization</h2>
                <p>Optimize existing images in your media library to AVIF and WebP.</p>

                <div id="wpio-progress-container" style="display:none; margin: 20px 0;">
                    <div id="wpio-progress-bar"
                        style="width: 100%; height: 20px; background: #ddd; border-radius: 3px; overflow: hidden;">
                        <div id="wpio-progress-inner"
                            style="width: 0%; height: 100%; background: #2271b1; transition: width 0.3s;"></div>
                    </div>
                    <p id="wpio-progress-text" style="font-weight: bold; margin-top: 10px;">Finding images...</p>
                </div>

                <button id="wpio-start-bulk" class="button button-primary button-hero">Start Bulk Optimization</button>
                <button id="wpio-pause-bulk" class="button button-secondary button-hero" style="display:none;">Pause</button>

                <div id="wpio-log"
                    style="margin-top:20px; max-height: 200px; overflow-y: auto; background: #fff; padding: 10px; border: 1px solid #ccc;">
                    <em>Log will appear here...</em>
                </div>
            </div>
        </div>
        <?php
    }
}
