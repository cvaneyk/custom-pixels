<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_Meta_CAPI_Adapter
{
    public static function send(array $event, array $settings)
    {
        $pixel_id = sanitize_text_field($settings['meta_pixel_id'] ?? '');
        $token = sanitize_text_field($settings['meta_access_token'] ?? '');

        if (empty($pixel_id) || empty($token)) {
            return false;
        }

        $endpoint = sprintf('https://graph.facebook.com/v20.0/%s/events', rawurlencode($pixel_id));
        $payload = array(
            'data' => array(
                array(
                    'event_name' => $event['event_name'],
                    'event_time' => (int) $event['event_time'],
                    'event_id' => $event['event_id'],
                    'event_source_url' => $event['event_source_url'],
                    'action_source' => $event['action_source'],
                    'user_data' => $event['user_data'],
                    'custom_data' => $event['custom_data'],
                ),
            ),
            'access_token' => $token,
        );

        if (!empty($settings['meta_test_event_code'])) {
            $payload['test_event_code'] = sanitize_text_field($settings['meta_test_event_code']);
        }

        $response = wp_remote_post($endpoint, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($payload),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        return $status >= 200 && $status < 300;
    }
}
