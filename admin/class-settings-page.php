<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_Settings_Page
{
    const OPTION = 'custom_pixels_settings';
    const PAGE_SLUG = 'custom-pixels';

    public static function defaults()
    {
        return array(
            'meta_pixel_id' => '',
            'meta_access_token' => '',
            'meta_test_event_code' => '',
            'meta_dataset_id' => '',
            'tiktok_pixel_id' => '',
            'tiktok_access_token' => '',
            'tiktok_test_event_code' => '',
            'openai_pixel_id' => '',
            'openai_api_key' => '',
            'openai_validate_only' => 0,
            'default_country' => 'ES',
            'default_phone_prefix' => '34',
            'debug_mode' => 0,
            'require_consent' => 1,
            'enable_meta_browser' => 1,
            'enable_meta_server' => 1,
            'enable_tiktok_browser' => 1,
            'enable_tiktok_server' => 1,
            'enable_openai_browser' => 1,
            'enable_openai_server' => 1,
            'github_repo' => 'cvaneyk/custom-pixels',
        );
    }

    public static function get_settings()
    {
        $settings = get_option(self::OPTION, array());
        return wp_parse_args(is_array($settings) ? $settings : array(), self::defaults());
    }

    public static function register_menu()
    {
        add_options_page(
            'Custom Pixels',
            'Custom Pixels',
            'manage_options',
            self::PAGE_SLUG,
            array(__CLASS__, 'render')
        );
    }

    public static function register_settings()
    {
        register_setting('custom_pixels_settings_group', self::OPTION, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
            'default' => self::defaults(),
        ));
    }

    public static function enqueue_assets($hook)
    {
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style('custom-pixels-admin', CUSTOM_PIXELS_URL . 'admin/assets/admin.css', array(), CUSTOM_PIXELS_VERSION);
        wp_enqueue_script('custom-pixels-admin', CUSTOM_PIXELS_URL . 'admin/assets/admin.js', array(), CUSTOM_PIXELS_VERSION, true);
    }

    public static function sanitize_settings($input)
    {
        $input = is_array($input) ? $input : array();

        return array(
            'meta_pixel_id' => sanitize_text_field($input['meta_pixel_id'] ?? ''),
            'meta_access_token' => sanitize_text_field($input['meta_access_token'] ?? ''),
            'meta_test_event_code' => sanitize_text_field($input['meta_test_event_code'] ?? ''),
            'meta_dataset_id' => sanitize_text_field($input['meta_dataset_id'] ?? ''),
            'tiktok_pixel_id' => sanitize_text_field($input['tiktok_pixel_id'] ?? ''),
            'tiktok_access_token' => sanitize_text_field($input['tiktok_access_token'] ?? ''),
            'tiktok_test_event_code' => sanitize_text_field($input['tiktok_test_event_code'] ?? ''),
            'openai_pixel_id' => sanitize_text_field($input['openai_pixel_id'] ?? ''),
            'openai_api_key' => sanitize_text_field($input['openai_api_key'] ?? ''),
            'openai_validate_only' => !empty($input['openai_validate_only']) ? 1 : 0,
            'default_country' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $input['default_country'] ?? ''), 0, 2)),
            'default_phone_prefix' => preg_replace('/\D+/', '', (string) ($input['default_phone_prefix'] ?? '')),
            'debug_mode' => !empty($input['debug_mode']) ? 1 : 0,
            'require_consent' => !empty($input['require_consent']) ? 1 : 0,
            'enable_meta_browser' => !empty($input['enable_meta_browser']) ? 1 : 0,
            'enable_meta_server' => !empty($input['enable_meta_server']) ? 1 : 0,
            'enable_tiktok_browser' => !empty($input['enable_tiktok_browser']) ? 1 : 0,
            'enable_tiktok_server' => !empty($input['enable_tiktok_server']) ? 1 : 0,
            'enable_openai_browser' => !empty($input['enable_openai_browser']) ? 1 : 0,
            'enable_openai_server' => !empty($input['enable_openai_server']) ? 1 : 0,
            'github_repo' => sanitize_text_field($input['github_repo'] ?? ''),
        );
    }

    public static function render()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();
        ?>
        <div class="wrap cp-wrap">
            <header class="cp-header">
                <div class="cp-header__brand">
                    <span class="cp-logo" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg>
                    </span>
                    <div>
                        <h1 class="cp-title">Custom Pixels</h1>
                        <p class="cp-subtitle">Tracking full-funnel para Meta, TikTok y ChatGPT con eventos de navegador y servidor deduplicados.</p>
                    </div>
                </div>
                <span class="cp-version">v<?php echo esc_html(CUSTOM_PIXELS_VERSION); ?></span>
            </header>

            <?php // WP core moves admin notices (incl. "Settings saved") right after this marker. ?>
            <hr class="wp-header-end">

            <form method="post" action="options.php" class="cp-form">
                <?php settings_fields('custom_pixels_settings_group'); ?>

                <div class="cp-grid">
                    <?php
                    self::provider_card(array(
                        'key' => 'meta',
                        'name' => 'Meta',
                        'description' => 'Pixel + Conversions API (Graph ' . Custom_Pixels_Meta_CAPI_Adapter::API_VERSION . ')',
                        'badge' => 'M',
                        'id_key' => 'meta_pixel_id',
                        'fields' => array(
                            array('meta_pixel_id', 'Pixel ID', 'text', '123456789012345'),
                            array('meta_access_token', 'Access Token (CAPI)', 'secret', ''),
                            array('meta_test_event_code', 'Test Event Code', 'text', 'TEST12345', 'Déjalo vacío en producción.'),
                        ),
                    ), $settings);

                    self::provider_card(array(
                        'key' => 'tiktok',
                        'name' => 'TikTok',
                        'description' => 'Pixel + Events API 2.0',
                        'badge' => 'T',
                        'id_key' => 'tiktok_pixel_id',
                        'fields' => array(
                            array('tiktok_pixel_id', 'Pixel ID', 'text', 'C0XXXXXXXXXXXXXXXXXX'),
                            array('tiktok_access_token', 'Access Token (Events API)', 'secret', ''),
                            array('tiktok_test_event_code', 'Test Event Code', 'text', 'TEST12345', 'Déjalo vacío en producción.'),
                        ),
                    ), $settings);

                    self::provider_card(array(
                        'key' => 'openai',
                        'name' => 'ChatGPT',
                        'description' => 'OpenAI Ads Pixel + Conversions API',
                        'badge' => 'AI',
                        'id_key' => 'openai_pixel_id',
                        'fields' => array(
                            array('openai_pixel_id', 'Pixel ID', 'text', 'NRnV7BTAVbi4ZU5ynnfEeo'),
                            array('openai_api_key', 'API Key (Conversions API)', 'secret', '', 'Se crea en Ads Manager → Conversiones.'),
                        ),
                        'extra_toggles' => array(
                            array('openai_validate_only', 'Modo validación', 'El servidor valida los eventos sin guardarlos (solo pruebas).'),
                        ),
                    ), $settings);
                    ?>

                    <section class="cp-card cp-card--general">
                        <div class="cp-card__head">
                            <span class="cp-badge cp-badge--general" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                            </span>
                            <div>
                                <h2 class="cp-card__title">General</h2>
                                <p class="cp-card__desc">Consentimiento, datos por defecto y actualizaciones</p>
                            </div>
                        </div>
                        <div class="cp-card__body">
                            <?php self::toggle('require_consent', 'Requerir consentimiento', 'No se envía nada hasta llamar a <code>window.CustomPixels.grantConsent()</code>.', $settings); ?>
                            <?php self::toggle('debug_mode', 'Modo debug', 'Logs en la consola del navegador y en <code>error_log</code>.', $settings); ?>
                            <div class="cp-field-row">
                                <?php self::field('default_country', 'País por defecto', 'text', 'ES', $settings, 'ISO de 2 letras. Se envía a ChatGPT.'); ?>
                                <?php self::field('default_phone_prefix', 'Prefijo telefónico', 'text', '34', $settings, 'Se añade a teléfonos sin prefijo.'); ?>
                            </div>
                            <?php self::field('github_repo', 'Repositorio GitHub', 'text', 'cvaneyk/custom-pixels', $settings, 'Formato owner/repo para actualizaciones automáticas.'); ?>
                        </div>
                    </section>
                </div>

                <div class="cp-savebar">
                    <span class="cp-savebar__hint">Los cambios se aplican en la siguiente carga de página.</span>
                    <?php submit_button('Guardar cambios', 'primary cp-button', 'submit', false); ?>
                </div>
            </form>

            <section class="cp-card cp-card--docs">
                <div class="cp-card__head">
                    <div>
                        <h2 class="cp-card__title">Integración</h2>
                        <p class="cp-card__desc">Hooks PHP para checkouts personalizados y API JavaScript</p>
                    </div>
                </div>
                <div class="cp-card__body">
                    <?php
                    self::code_block("do_action('custom_pixels_view_content', \$payload);\ndo_action('custom_pixels_add_to_cart', \$payload);\ndo_action('custom_pixels_initiate_checkout', \$payload);\ndo_action('custom_pixels_purchase', \$payload);");
                    ?>
                    <p class="cp-help"><strong>$payload</strong> admite: event_id, consent, email, phone, external_id, value, currency, content_ids, contents, order_id, event_source_url.</p>
                    <?php
                    self::code_block("window.CustomPixels.grantConsent();\nwindow.CustomPixels.track('Purchase', {\n  custom_data: { value: 49.9, currency: 'EUR', content_ids: ['sku_123'] },\n  user_data: { email: 'user@example.com' }\n});");
                    ?>
                </div>
            </section>
        </div>
        <?php
    }

    private static function provider_card(array $provider, array $settings)
    {
        $key = $provider['key'];
        $browser_key = 'enable_' . $key . '_browser';
        $server_key = 'enable_' . $key . '_server';
        $configured = !empty($settings[$provider['id_key']]);
        $enabled = !empty($settings[$browser_key]) || !empty($settings[$server_key]);

        if ($configured && $enabled) {
            $status = array('is-active', 'Activo');
        } elseif ($configured) {
            $status = array('is-paused', 'Pausado');
        } else {
            $status = array('is-off', 'Sin configurar');
        }
        ?>
        <section class="cp-card cp-card--<?php echo esc_attr($key); ?>">
            <div class="cp-card__head">
                <span class="cp-badge cp-badge--<?php echo esc_attr($key); ?>" aria-hidden="true"><?php echo esc_html($provider['badge']); ?></span>
                <div>
                    <h2 class="cp-card__title"><?php echo esc_html($provider['name']); ?></h2>
                    <p class="cp-card__desc"><?php echo esc_html($provider['description']); ?></p>
                </div>
                <span class="cp-status <?php echo esc_attr($status[0]); ?>"><?php echo esc_html($status[1]); ?></span>
            </div>
            <div class="cp-card__body">
                <?php
                foreach ($provider['fields'] as $field) {
                    self::field($field[0], $field[1], $field[2], $field[3], $settings, $field[4] ?? '');
                }
                ?>
                <div class="cp-toggles">
                    <?php self::toggle($browser_key, 'Navegador', 'Pixel en el frontend', $settings); ?>
                    <?php self::toggle($server_key, 'Servidor', 'Envío server-side', $settings); ?>
                </div>
                <?php
                foreach ($provider['extra_toggles'] ?? array() as $toggle) {
                    self::toggle($toggle[0], $toggle[1], $toggle[2], $settings);
                }
                ?>
            </div>
        </section>
        <?php
    }

    private static function field($key, $label, $type, $placeholder, $settings, $help = '')
    {
        $id = 'cp_' . $key;
        $name = self::OPTION . '[' . $key . ']';
        ?>
        <div class="cp-field">
            <label class="cp-label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
            <?php if ($type === 'secret') : ?>
                <div class="cp-secret">
                    <input type="password" autocomplete="off" spellcheck="false" class="cp-input" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($settings[$key] ?? ''); ?>" placeholder="<?php echo esc_attr($placeholder); ?>">
                    <button type="button" class="cp-secret__toggle" data-cp-reveal="<?php echo esc_attr($id); ?>">Mostrar</button>
                </div>
            <?php else : ?>
                <input type="text" spellcheck="false" class="cp-input" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($settings[$key] ?? ''); ?>" placeholder="<?php echo esc_attr($placeholder); ?>">
            <?php endif; ?>
            <?php if ($help !== '') : ?>
                <p class="cp-help"><?php echo esc_html($help); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function toggle($key, $label, $help, $settings)
    {
        $id = 'cp_' . $key;
        ?>
        <label class="cp-toggle" for="<?php echo esc_attr($id); ?>">
            <input type="checkbox" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr(self::OPTION . '[' . $key . ']'); ?>" value="1" <?php checked(!empty($settings[$key])); ?>>
            <span class="cp-toggle__track" aria-hidden="true"><span class="cp-toggle__thumb"></span></span>
            <span class="cp-toggle__text">
                <span class="cp-toggle__label"><?php echo esc_html($label); ?></span>
                <span class="cp-toggle__help"><?php echo wp_kses($help, array('code' => array())); ?></span>
            </span>
        </label>
        <?php
    }

    private static function code_block($code)
    {
        ?>
        <div class="cp-code">
            <button type="button" class="cp-code__copy" data-cp-copy>Copiar</button>
            <pre><code><?php echo esc_html($code); ?></code></pre>
        </div>
        <?php
    }
}
