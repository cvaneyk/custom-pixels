<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI Ads (ChatGPT) Conversions API.
 * Docs: https://developers.openai.com/ads/conversions-api
 */
class Custom_Pixels_OpenAI_Conversions_API_Adapter
{
    /**
     * Plugin event name => array(OpenAI event type, data type).
     * Keep in sync with OPENAI_EVENTS in assets/js/tracker.js.
     */
    private static $event_map = array(
        'PageView' => array('page_viewed', 'contents'),
        'ViewContent' => array('contents_viewed', 'contents'),
        'AddToCart' => array('items_added', 'contents'),
        'InitiateCheckout' => array('checkout_started', 'contents'),
        'Purchase' => array('order_created', 'contents'),
        'CompleteRegistration' => array('registration_completed', 'customer_action'),
        'Lead' => array('lead_created', 'customer_action'),
        'Schedule' => array('appointment_scheduled', 'customer_action'),
        'Subscribe' => array('subscription_created', 'plan_enrollment'),
        'StartTrial' => array('trial_started', 'plan_enrollment'),
    );

    /**
     * Contact events are sent as custom events, named after the contact channel.
     */
    private static $contact_map = array(
        'phone' => 'telefono',
        'whatsapp' => 'whatsapp',
    );

    public static function send(array $event, array $settings)
    {
        $pixel_id = sanitize_text_field($settings['openai_pixel_id'] ?? '');
        $api_key = sanitize_text_field($settings['openai_api_key'] ?? '');

        if (empty($pixel_id) || empty($api_key)) {
            return false;
        }

        $mapped = self::map_event($event['event_name'], $event['custom_data']);
        if ($mapped === null) {
            return false;
        }

        $user_data = $event['user_data'];
        $country = strtoupper(sanitize_text_field($settings['default_country'] ?? ''));

        $item = array(
            'id' => $event['event_id'],
            'type' => $mapped['type'],
            'timestamp_ms' => (int) $event['event_time'] * 1000,
            'source_url' => $event['event_source_url'],
            'action_source' => 'web',
            'user' => array_filter(array(
                'emails_sha256' => !empty($user_data['em']) ? array($user_data['em']) : null,
                'phone_numbers_sha256' => !empty($user_data['ph']) ? array($user_data['ph']) : null,
                'external_ids_sha256' => !empty($user_data['external_id']) ? array($user_data['external_id']) : null,
                'countries' => $country !== '' ? array($country) : null,
                'ip_address' => $user_data['client_ip_address'] ?? null,
                'user_agent' => $user_data['client_user_agent'] ?? null,
                'obref' => $user_data['obref'] ?? null,
            )),
            'data' => self::data($mapped['data_type'], $event['custom_data']),
        );

        if (!empty($mapped['custom_event_name'])) {
            $item['custom_event_name'] = $mapped['custom_event_name'];
        }
        if (!empty($user_data['oppref'])) {
            $item['oppref'] = $user_data['oppref'];
        }

        $payload = array(
            'validate_only' => !empty($settings['openai_validate_only']),
            'events' => array($item),
        );

        $response = wp_remote_post('https://bzr.openai.com/v1/events?pid=' . rawurlencode($pixel_id), array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
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

    /**
     * Returns array(type, data_type, custom_event_name) or null when the event isn't sent to OpenAI.
     */
    public static function map_event($event_name, array $custom_data = array())
    {
        if (isset(self::$event_map[$event_name])) {
            return array(
                'type' => self::$event_map[$event_name][0],
                'data_type' => self::$event_map[$event_name][1],
                'custom_event_name' => '',
            );
        }

        if ($event_name === 'Contact') {
            $channel = $custom_data['type'] ?? '';
            return array(
                'type' => 'custom',
                'data_type' => 'custom',
                'custom_event_name' => self::$contact_map[$channel] ?? 'contacto',
            );
        }

        return null;
    }

    private static function data($data_type, array $custom_data)
    {
        $data = array('type' => $data_type);

        // Amounts are integers in minor units (49.90 EUR => 4990).
        $value = Custom_Pixels_Event_Normalizer::to_number($custom_data['value'] ?? null);
        $currency = strtoupper($custom_data['currency'] ?? '');
        if ($value !== null && $currency !== '') {
            $data['amount'] = (int) round($value * 100);
            $data['currency'] = $currency;
        }

        if ($data_type === 'customer_action') {
            return $data;
        }

        $contents = self::contents($custom_data);
        if (!empty($contents)) {
            $data['contents'] = $contents;
        }

        return $data;
    }

    private static function contents(array $custom_data)
    {
        $contents = array();

        if (!empty($custom_data['contents']) && is_array($custom_data['contents'])) {
            foreach ($custom_data['contents'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = $item['id'] ?? ($item['content_id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $content = array('id' => (string) $id);
                if (!empty($item['quantity'])) {
                    $content['quantity'] = (int) $item['quantity'];
                }
                $contents[] = $content;
            }
        } elseif (!empty($custom_data['content_ids']) && is_array($custom_data['content_ids'])) {
            foreach ($custom_data['content_ids'] as $id) {
                $contents[] = array('id' => (string) $id);
            }
        }

        return $contents;
    }
}
