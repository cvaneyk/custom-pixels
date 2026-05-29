<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_TikTok_Events_API_Adapter
{
    public static function send(array $event, array $settings)
    {
        $pixel_id = sanitize_text_field($settings['tiktok_pixel_id'] ?? '');
        $token = sanitize_text_field($settings['tiktok_access_token'] ?? '');

        if (empty($pixel_id) || empty($token)) {
            return false;
        }

        $endpoint = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';
        $user_data = $event['user_data'];

        $payload = array(
            'pixel_code' => $pixel_id,
            'event' => $event['event_name'],
            'event_id' => $event['event_id'],
            'timestamp' => (int) $event['event_time'],
            'context' => array(
                'ad' => array(),
                'page' => array(
                    'url' => $event['event_source_url'],
                ),
                'user' => array(
                    'external_id' => $user_data['external_id'] ?? '',
                    'email' => $user_data['email'] ?? '',
                    'phone_number' => $user_data['phone'] ?? '',
                    'ip' => $user_data['client_ip_address'] ?? '',
                    'user_agent' => $user_data['client_user_agent'] ?? '',
                    'ttp' => $user_data['ttp'] ?? '',
                ),
            ),
            'properties' => $event['custom_data'],
        );

        if (!empty($settings['tiktok_test_event_code'])) {
            $payload['test_event_code'] = sanitize_text_field($settings['tiktok_test_event_code']);
        }

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Access-Token' => $token,
            ),
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
