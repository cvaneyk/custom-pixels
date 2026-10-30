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
            'referrer_url' => esc_url_raw($event['referrer_url'] ?? ''),
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
            'contact' => 'Contact',
            'schedule' => 'Schedule',
            'subscribe' => 'Subscribe',
            'start_trial' => 'StartTrial',
        );

        $key = strtolower(trim((string) $event_name));
        return $map[$key] ?? sanitize_text_field($event_name);
    }

    public static function normalize_user_data($user_data)
    {
        if (!is_array($user_data)) {
            return array();
        }

        $allowed_keys = array(
            'email', 'phone', 'external_id', 'client_ip_address', 'client_user_agent',
            'fbc', 'fbp', 'ttp', 'ttclid', 'obref', 'oppref',
        );
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
                if ((string) $value === '') {
                    continue;
                }
                $output[$clean_key] = sanitize_text_field((string) $value);
                continue;
            }

            if (is_array($value)) {
                $items = array_map(function ($item) {
                    if (is_array($item)) {
                        return array_map(function ($v) {
                            return is_scalar($v) ? sanitize_text_field((string) $v) : '';
                        }, $item);
                    }
                    return is_scalar($item) ? sanitize_text_field((string) $item) : '';
                }, $value);
                $items = array_values(array_filter($items, function ($item) {
                    return $item !== '' && $item !== array();
                }));
                if (!empty($items)) {
                    $output[$clean_key] = $items;
                }
            }
        }

        return $output;
    }

    /**
     * Replaces raw email/phone/external_id with SHA-256 hashes so raw PII never
     * reaches the providers (nor the debug log). Each platform normalizes phones
     * differently, so both variants are kept:
     *   ph       -> digits with country code, no "+" (Meta, OpenAI)
     *   ph_e164  -> "+" + digits (TikTok)
     */
    public static function hash_user_data(array $user_data, array $settings = array())
    {
        if (!empty($user_data['email'])) {
            $user_data['em'] = self::sha256(strtolower(trim($user_data['email'])));
        }

        if (!empty($user_data['phone'])) {
            $digits = self::normalize_phone($user_data['phone'], $settings['default_phone_prefix'] ?? '');
            if ($digits !== '') {
                $user_data['ph'] = self::sha256($digits);
                $user_data['ph_e164'] = self::sha256('+' . $digits);
            }
        }

        if (!empty($user_data['external_id'])) {
            $user_data['external_id'] = self::sha256(trim($user_data['external_id']));
        }

        unset($user_data['email'], $user_data['phone']);
        return $user_data;
    }

    public static function normalize_phone($phone, $default_prefix = '')
    {
        $phone = trim((string) $phone);
        $has_country_code = strpos($phone, '+') === 0 || strpos($phone, '00') === 0;
        $digits = ltrim(preg_replace('/\D+/', '', $phone), '0');
        $prefix = preg_replace('/\D+/', '', (string) $default_prefix);

        if ($digits !== '' && !$has_country_code && $prefix !== '' && strpos($digits, $prefix) !== 0) {
            $digits = $prefix . $digits;
        }

        return $digits;
    }

    public static function sha256($value)
    {
        $value = (string) $value;
        if (preg_match('/^[a-f0-9]{64}$/', $value)) {
            return $value;
        }
        return hash('sha256', $value);
    }

    /**
     * Casts quantity/price fields of each contents item to numbers.
     */
    public static function numeric_contents(array $contents)
    {
        return array_map(function ($item) {
            if (!is_array($item)) {
                return $item;
            }
            if (isset($item['quantity']) && is_numeric($item['quantity'])) {
                $item['quantity'] = (int) $item['quantity'];
            }
            foreach (array('item_price', 'price') as $key) {
                if (isset($item[$key]) && is_numeric($item[$key])) {
                    $item[$key] = (float) $item[$key];
                }
            }
            return $item;
        }, $contents);
    }

    /**
     * Converts a decimal value (e.g. "49.90") to a number. Returns null when not numeric.
     */
    public static function to_number($value)
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }
}
