<?php
/**
 * Plugin Name: Custom Pixels (Meta + TikTok)
 * Description: Full-funnel tracking for Meta and TikTok with browser + server events.
 * Version: 0.1.4
 * Author: Carlos Van Eyk - Olimedia Agencia de Marketing
 * License: GPL-3.0-or-later
 * Text Domain: custom-pixels
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CUSTOM_PIXELS_VERSION', '0.1.4');
define('CUSTOM_PIXELS_FILE', __FILE__);
define('CUSTOM_PIXELS_DIR', plugin_dir_path(__FILE__));
define('CUSTOM_PIXELS_URL', plugin_dir_url(__FILE__));

require_once CUSTOM_PIXELS_DIR . 'includes/class-plugin-bootstrap.php';

register_activation_hook(__FILE__, function () {
    $defaults = array(
        'meta_pixel_id' => '',
        'meta_access_token' => '',
        'meta_test_event_code' => '',
        'meta_dataset_id' => '',
        'tiktok_pixel_id' => '',
        'tiktok_access_token' => '',
        'tiktok_test_event_code' => '',
        'debug_mode' => 0,
        'require_consent' => 1,
        'enable_meta_browser' => 1,
        'enable_meta_server' => 1,
        'enable_tiktok_browser' => 1,
        'enable_tiktok_server' => 1,
    );

    if (!get_option('custom_pixels_settings')) {
        add_option('custom_pixels_settings', $defaults);
        return;
    }

    $current = get_option('custom_pixels_settings', array());
    update_option('custom_pixels_settings', wp_parse_args($current, $defaults));
});

add_action('plugins_loaded', array('Custom_Pixels_Plugin_Bootstrap', 'init'));
