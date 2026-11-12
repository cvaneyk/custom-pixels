<?php
/**
 * Plugin Name: Custom Pixels (Meta + TikTok + ChatGPT)
 * Description: Full-funnel tracking for Meta, TikTok and ChatGPT (OpenAI Ads) with browser + server events, plus GA4, Google Tag Manager and Microsoft Clarity with Consent Mode v2.
 * Version: 0.3.0
 * Author: Carlos Van Eyk - Olimedia Agencia de Marketing
 * License: GPL-3.0-or-later
 * Text Domain: custom-pixels
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CUSTOM_PIXELS_VERSION', '0.3.0');
define('CUSTOM_PIXELS_FILE', __FILE__);
define('CUSTOM_PIXELS_DIR', plugin_dir_path(__FILE__));
define('CUSTOM_PIXELS_URL', plugin_dir_url(__FILE__));

require_once CUSTOM_PIXELS_DIR . 'includes/class-plugin-bootstrap.php';

register_activation_hook(__FILE__, function () {
    require_once CUSTOM_PIXELS_DIR . 'admin/class-settings-page.php';

    $defaults = Custom_Pixels_Settings_Page::defaults();

    if (!get_option('custom_pixels_settings')) {
        add_option('custom_pixels_settings', $defaults);
        return;
    }

    $current = get_option('custom_pixels_settings', array());
    update_option('custom_pixels_settings', wp_parse_args($current, $defaults));
});

add_action('plugins_loaded', array('Custom_Pixels_Plugin_Bootstrap', 'init'));
