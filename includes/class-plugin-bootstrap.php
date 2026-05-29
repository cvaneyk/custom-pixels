<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_Plugin_Bootstrap
{
    public static function init()
    {
        self::load_dependencies();

        add_action('admin_menu', array('Custom_Pixels_Settings_Page', 'register_menu'));
        add_action('admin_init', array('Custom_Pixels_Settings_Page', 'register_settings'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_front_assets'));
        add_action('rest_api_init', array('Custom_Pixels_Event_Dispatcher', 'register_routes'));

        Custom_Pixels_Custom_Checkout_Hooks::register();

        if (is_admin()) {
            new Custom_Pixels_GitHub_Updater();
        }
    }

    private static function load_dependencies()
    {
        require_once CUSTOM_PIXELS_DIR . 'admin/class-settings-page.php';

        require_once CUSTOM_PIXELS_DIR . 'includes/events/class-event-normalizer.php';
        require_once CUSTOM_PIXELS_DIR . 'includes/events/class-event-dispatcher.php';

        require_once CUSTOM_PIXELS_DIR . 'includes/providers/meta/class-meta-pixel-adapter.php';
        require_once CUSTOM_PIXELS_DIR . 'includes/providers/meta/class-meta-capi-adapter.php';

        require_once CUSTOM_PIXELS_DIR . 'includes/providers/tiktok/class-tiktok-pixel-adapter.php';
        require_once CUSTOM_PIXELS_DIR . 'includes/providers/tiktok/class-tiktok-events-api-adapter.php';

        require_once CUSTOM_PIXELS_DIR . 'includes/integrations/class-custom-checkout-hooks.php';
        require_once CUSTOM_PIXELS_DIR . 'includes/class-github-updater.php';
    }

    public static function enqueue_front_assets()
    {
        $settings = get_option('custom_pixels_settings', array());

        wp_register_script(
            'custom-pixels-tracker',
            CUSTOM_PIXELS_URL . 'assets/js/tracker.js',
            array(),
            CUSTOM_PIXELS_VERSION,
            true
        );

        $config = array(
            'restUrl' => esc_url_raw(rest_url('custom-pixels/v1/track')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'debugMode' => !empty($settings['debug_mode']),
            'requireConsent' => !empty($settings['require_consent']),
            'metaPixelId' => sanitize_text_field($settings['meta_pixel_id'] ?? ''),
            'tiktokPixelId' => sanitize_text_field($settings['tiktok_pixel_id'] ?? ''),
            'enableMetaBrowser' => !empty($settings['enable_meta_browser']),
            'enableMetaServer' => !empty($settings['enable_meta_server']),
            'enableTikTokBrowser' => !empty($settings['enable_tiktok_browser']),
            'enableTikTokServer' => !empty($settings['enable_tiktok_server']),
            'userData' => array(
                'external_id' => is_user_logged_in() ? (string) get_current_user_id() : '',
            ),
        );

        wp_localize_script('custom-pixels-tracker', 'CustomPixelsConfig', $config);
        wp_enqueue_script('custom-pixels-tracker');
    }
}
