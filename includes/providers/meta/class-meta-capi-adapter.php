<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_Meta_CAPI_Adapter
{
    // Graph API versions are removed ~2 years after release (v20.0 was removed on 2026-09-24).
    const API_VERSION = 'v26.0';

    public static function send(array $event, array $settings)
    {
        $pixel_id = sanitize_text_field($settings['meta_pixel_id'] ?? '');
        $token = sanitize_text_field($settings['meta_access_token'] ?? '');

        if (empty($pixel_id) || empty($token)) {
            return false;
        }

        $endpoint = sprintf('https://graph.facebook.com/%s/%s/events', self::API_VERSION, rawurlencode($pixel_id));
        $payload = array(
            'data' => array(
                array(
                    'event_name' => $event['event_name'],
                    'event_time' => (int) $event['event_time'],
                    'event_id' => $event['event_id'],
                    'event_source_url' => $event['event_source_url'],
                    'action_source' => $event['action_source'],
                    'user_data' => self::user_data($event['user_data']),
                    'custom_data' => self::custom_data($event['custom_data']),
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

    /**
     * Meta expects hashed identifiers under em/ph/external_id (as arrays).
     */
    private static function user_data(array $user_data)
    {
        $output = array(
            'em' => !empty($user_data['em']) ? array($user_data['em']) : null,
            'ph' => !empty($user_data['ph']) ? array($user_data['ph']) : null,
            'external_id' => !empty($user_data['external_id']) ? array($user_data['external_id']) : null,
            'client_ip_address' => $user_data['client_ip_address'] ?? null,
            'client_user_agent' => $user_data['client_user_agent'] ?? null,
            'fbc' => $user_data['fbc'] ?? null,
            'fbp' => $user_data['fbp'] ?? null,
        );

        return array_filter($output);
    }

    private static function custom_data(array $custom_data)
    {
        if (isset($custom_data['value'])) {
            $value = Custom_Pixels_Event_Normalizer::to_number($custom_data['value']);
            if ($value === null) {
                unset($custom_data['value']);
            } else {
                $custom_data['value'] = $value;
            }
        }

        if (!empty($custom_data['currency'])) {
            $custom_data['currency'] = strtoupper($custom_data['currency']);
        }

        if (!empty($custom_data['contents']) && is_array($custom_data['contents'])) {
            $custom_data['contents'] = Custom_Pixels_Event_Normalizer::numeric_contents($custom_data['contents']);
        }

        // An empty PHP array would encode as [] instead of {}.
        return empty($custom_data) ? new stdClass() : $custom_data;
    }
}
