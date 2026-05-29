<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_Event_Normalizer
{
    public static function normalize(array $event)
    {
        $raw_name = sanitize_text_field($event['event_name'] ?? '');
        $event_name = self::normalize_event_name($raw_name);

        if (empty($event_name)) {
            return new WP_Error('invalid_event_name', 'Invalid event_name received.', array('status' => 400));
        }

        $normalized = array(
            'event_name' => $event_name,
            'event_id' => sanitize_text_field($event['event_id'] ?? wp_generate_uuid4()),
            'source' => sanitize_text_field($event['source'] ?? 'browser'),
            'event_time' => absint($event['event_time'] ?? time()),
            'event_source_url' => esc_url_raw($event['event_source_url'] ?? home_url(add_query_arg(array(), $GLOBALS['wp']->request ?? ''))),
            'action_source' => sanitize_text_field($event['action_source'] ?? 'website'),
            'user_data' => self::normalize_user_data($event['user_data'] ?? array()),
            'custom_data' => self::normalize_custom_data($event['custom_data'] ?? array()),
        );

        if (empty($normalized['event_time'])) {
            $normalized['event_time'] = time();
        }

        return $normalized;
    }

    public static function normalize_event_name($event_name)
    {
        $map = array(
            'page_view' => 'PageView',
            'view_content' => 'ViewContent',
            'add_to_cart' => 'AddToCart',
            'initiate_checkout' => 'InitiateCheckout',
            'add_payment_info' => 'AddPaymentInfo',
            'purchase' => 'Purchase',
            'search' => 'Search',
            'complete_registration' => 'CompleteRegistration',
            'lead' => 'Lead',
        );

        $key = strtolower(trim((string) $event_name));
        return $map[$key] ?? sanitize_text_field($event_name);
    }

    public static function normalize_user_data($user_data)
    {
        if (!is_array($user_data)) {
            return array();
        }

        $allowed_keys = array('email', 'phone', 'external_id', 'client_ip_address', 'client_user_agent', 'fbc', 'fbp', 'ttp');
        $output = array();

        foreach ($allowed_keys as $key) {
            if (!isset($user_data[$key])) {
                continue;
            }
            $value = is_scalar($user_data[$key]) ? (string) $user_data[$key] : '';
            if ($value === '') {
                continue;
            }
            $output[$key] = sanitize_text_field($value);
        }

        return $output;
    }

    public static function normalize_custom_data($custom_data)
    {
        if (!is_array($custom_data)) {
            return array();
        }

        $output = array();
        foreach ($custom_data as $key => $value) {
            $clean_key = sanitize_key($key);
            if ($clean_key === '') {
                continue;
            }

            if (is_scalar($value)) {
                $output[$clean_key] = sanitize_text_field((string) $value);
                continue;
            }

            if (is_array($value)) {
                $output[$clean_key] = array_map(function ($item) {
                    return is_scalar($item) ? sanitize_text_field((string) $item) : '';
                }, $value);
            }
        }

        return $output;
    }

    public static function maybe_hash_user_data(array $user_data)
    {
        $keys_to_hash = array('email', 'phone');
        foreach ($keys_to_hash as $key) {
            if (empty($user_data[$key])) {
                continue;
            }

            $normalized = strtolower(trim((string) $user_data[$key]));
            $user_data[$key] = hash('sha256', $normalized);
        }
        return $user_data;
    }
}
