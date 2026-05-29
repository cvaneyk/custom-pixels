<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_Event_Dispatcher
{
    public static function register_routes()
    {
        register_rest_route('custom-pixels/v1', '/track', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_track_request'),
            'permission_callback' => '__return_true',
        ));
    }

    public static function handle_track_request(WP_REST_Request $request)
    {
        $settings = get_option('custom_pixels_settings', array());
        $payload = $request->get_json_params() ?: array();

        if (!self::has_consent($payload, $settings)) {
            return new WP_REST_Response(array(
                'ok' => false,
                'reason' => 'consent_required',
            ), 202);
        }

        $normalized = Custom_Pixels_Event_Normalizer::normalize($payload);
        if (is_wp_error($normalized)) {
            return $normalized;
        }

        if (empty($normalized['user_data']['client_ip_address'])) {
            $normalized['user_data']['client_ip_address'] = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        }

        if (empty($normalized['user_data']['client_user_agent'])) {
            $normalized['user_data']['client_user_agent'] = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');
        }

        $normalized['user_data'] = Custom_Pixels_Event_Normalizer::maybe_hash_user_data($normalized['user_data']);

        $results = array(
            'meta' => array('browser' => false, 'server' => false),
            'tiktok' => array('browser' => false, 'server' => false),
            'event_id' => $normalized['event_id'],
            'event_name' => $normalized['event_name'],
        );

        if (!empty($settings['enable_meta_server'])) {
            $results['meta']['server'] = Custom_Pixels_Meta_CAPI_Adapter::send($normalized, $settings);
        }
        if (!empty($settings['enable_tiktok_server'])) {
            $results['tiktok']['server'] = Custom_Pixels_TikTok_Events_API_Adapter::send($normalized, $settings);
        }

        self::debug_log($normalized, $results, $settings);

        return new WP_REST_Response(array(
            'ok' => true,
            'results' => $results,
        ), 200);
    }

    public static function dispatch_internal(array $event)
    {
        $request = new WP_REST_Request('POST', '/custom-pixels/v1/track');
        $request->set_body(wp_json_encode($event));
        $request->set_header('content-type', 'application/json');

        return self::handle_track_request($request);
    }

    private static function has_consent(array $payload, array $settings)
    {
        if (empty($settings['require_consent'])) {
            return true;
        }
        return !empty($payload['consent']);
    }

    private static function debug_log(array $event, array $results, array $settings)
    {
        if (empty($settings['debug_mode'])) {
            return;
        }

        error_log('[custom-pixels] ' . wp_json_encode(array(
            'event' => $event,
            'results' => $results,
        )));
    }
}
