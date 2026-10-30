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

        // The browser pixel already reports page views via ttq.page(), which has no event_id to deduplicate against.
        if ($event['event_name'] === 'PageView' && !empty($settings['enable_tiktok_browser']) && $event['source'] === 'browser') {
            return false;
        }

        $endpoint = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';
        $user_data = $event['user_data'];

        // Events API 2.0 format.
        $payload = array(
            'event_source' => 'web',
            'event_source_id' => $pixel_id,
            'data' => array(
                array(
                    'event' => $event['event_name'],
                    'event_time' => (int) $event['event_time'],
                    'event_id' => $event['event_id'],
                    'user' => array_filter(array(
                        'email' => $user_data['em'] ?? null,
                        'phone' => $user_data['ph_e164'] ?? null,
                        'external_id' => $user_data['external_id'] ?? null,
                        'ip' => $user_data['client_ip_address'] ?? null,
                        'user_agent' => $user_data['client_user_agent'] ?? null,
                        'ttp' => $user_data['ttp'] ?? null,
                        'ttclid' => $user_data['ttclid'] ?? null,
                    )),
                    'page' => array_filter(array(
                        'url' => $event['event_source_url'],
                        'referrer' => $event['referrer_url'] ?? null,
                    )),
                    'properties' => self::properties($event['custom_data']),
                ),
            ),
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
        if ($status < 200 || $status >= 300) {
            return false;
        }

        // TikTok answers HTTP 200 with a non-zero "code" on validation errors.
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['code']) ? (int) $body['code'] === 0 : true;
    }

    private static function properties(array $custom_data)
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

        // TikTok uses content_id inside contents.
        if (!empty($custom_data['contents']) && is_array($custom_data['contents'])) {
            $custom_data['contents'] = array_map(function ($item) {
                if (is_array($item) && isset($item['id']) && !isset($item['content_id'])) {
                    $item['content_id'] = $item['id'];
                    unset($item['id']);
                }
                return $item;
            }, Custom_Pixels_Event_Normalizer::numeric_contents($custom_data['contents']));
        }

        return empty($custom_data) ? new stdClass() : $custom_data;
    }
}
