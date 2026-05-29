<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_Settings_Page
{
    public static function register_menu()
    {
        add_options_page(
            'Custom Pixels',
            'Custom Pixels',
            'manage_options',
            'custom-pixels',
            array(__CLASS__, 'render')
        );
    }

    public static function register_settings()
    {
        register_setting('custom_pixels_settings_group', 'custom_pixels_settings', array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
            'default' => array(),
        ));
    }

    public static function sanitize_settings($input)
    {
        return array(
            'meta_pixel_id' => sanitize_text_field($input['meta_pixel_id'] ?? ''),
            'meta_access_token' => sanitize_text_field($input['meta_access_token'] ?? ''),
            'meta_test_event_code' => sanitize_text_field($input['meta_test_event_code'] ?? ''),
            'meta_dataset_id' => sanitize_text_field($input['meta_dataset_id'] ?? ''),
            'tiktok_pixel_id' => sanitize_text_field($input['tiktok_pixel_id'] ?? ''),
            'tiktok_access_token' => sanitize_text_field($input['tiktok_access_token'] ?? ''),
            'tiktok_test_event_code' => sanitize_text_field($input['tiktok_test_event_code'] ?? ''),
            'debug_mode' => !empty($input['debug_mode']) ? 1 : 0,
            'require_consent' => !empty($input['require_consent']) ? 1 : 0,
            'enable_meta_browser' => !empty($input['enable_meta_browser']) ? 1 : 0,
            'enable_meta_server' => !empty($input['enable_meta_server']) ? 1 : 0,
            'enable_tiktok_browser' => !empty($input['enable_tiktok_browser']) ? 1 : 0,
            'enable_tiktok_server' => !empty($input['enable_tiktok_server']) ? 1 : 0,
            'github_repo' => sanitize_text_field($input['github_repo'] ?? ''),
        );
    }

    public static function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = get_option('custom_pixels_settings', array());
        ?>
        <div class="wrap">
            <h1>Custom Pixels (Meta + TikTok)</h1>
            <p>Configura tracking browser/server, consentimiento y modo debug.</p>
            <form method="post" action="options.php">
                <?php settings_fields('custom_pixels_settings_group'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="meta_pixel_id">Meta Pixel ID</label></th>
                        <td><input type="text" id="meta_pixel_id" name="custom_pixels_settings[meta_pixel_id]" value="<?php echo esc_attr($settings['meta_pixel_id'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="meta_access_token">Meta Access Token</label></th>
                        <td><input type="text" id="meta_access_token" name="custom_pixels_settings[meta_access_token]" value="<?php echo esc_attr($settings['meta_access_token'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="meta_test_event_code">Meta Test Event Code</label></th>
                        <td><input type="text" id="meta_test_event_code" name="custom_pixels_settings[meta_test_event_code]" value="<?php echo esc_attr($settings['meta_test_event_code'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tiktok_pixel_id">TikTok Pixel ID</label></th>
                        <td><input type="text" id="tiktok_pixel_id" name="custom_pixels_settings[tiktok_pixel_id]" value="<?php echo esc_attr($settings['tiktok_pixel_id'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tiktok_access_token">TikTok Access Token</label></th>
                        <td><input type="text" id="tiktok_access_token" name="custom_pixels_settings[tiktok_access_token]" value="<?php echo esc_attr($settings['tiktok_access_token'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="tiktok_test_event_code">TikTok Test Event Code</label></th>
                        <td><input type="text" id="tiktok_test_event_code" name="custom_pixels_settings[tiktok_test_event_code]" value="<?php echo esc_attr($settings['tiktok_test_event_code'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="github_repo">GitHub Repository</label></th>
                        <td>
                            <input type="text" id="github_repo" name="custom_pixels_settings[github_repo]" value="<?php echo esc_attr($settings['github_repo'] ?? 'cvaneyk/custom-pixels'); ?>" class="regular-text">
                            <p class="description">Format: owner/repo (e.g., cvaneyk/custom-pixels) for automatic updates.</p>
                        </td>
                    </tr>
                    <?php self::checkbox_row('enable_meta_browser', 'Enable Meta browser events', $settings); ?>
                    <?php self::checkbox_row('enable_meta_server', 'Enable Meta server events', $settings); ?>
                    <?php self::checkbox_row('enable_tiktok_browser', 'Enable TikTok browser events', $settings); ?>
                    <?php self::checkbox_row('enable_tiktok_server', 'Enable TikTok server events', $settings); ?>
                    <?php self::checkbox_row('require_consent', 'Require consent before tracking', $settings); ?>
                    <?php self::checkbox_row('debug_mode', 'Enable debug mode (error_log)', $settings); ?>
                </table>
                <?php submit_button(); ?>
            </form>
            <hr>
            <h2>Hooks para checkout personalizado</h2>
            <p>Lanza estos hooks desde tu integración:</p>
            <code>do_action('custom_pixels_view_content', $payload);</code><br>
            <code>do_action('custom_pixels_add_to_cart', $payload);</code><br>
            <code>do_action('custom_pixels_initiate_checkout', $payload);</code><br>
            <code>do_action('custom_pixels_purchase', $payload);</code><br>
            <p><strong>$payload</strong> soporta: event_id, consent, email, phone, external_id, value, currency, content_ids, contents, order_id, event_source_url.</p>
        </div>
        <?php
    }

    private static function checkbox_row($key, $label, $settings)
    {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="custom_pixels_settings[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($settings[$key])); ?>>
                    Enabled
                </label>
            </td>
        </tr>
        <?php
    }
}
