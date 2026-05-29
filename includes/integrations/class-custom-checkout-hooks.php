<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_Custom_Checkout_Hooks
{
    public static function register()
    {
        add_action('custom_pixels_page_view', array(__CLASS__, 'on_page_view'), 10, 1);
        add_action('custom_pixels_view_content', array(__CLASS__, 'on_view_content'), 10, 1);
        add_action('custom_pixels_add_to_cart', array(__CLASS__, 'on_add_to_cart'), 10, 1);
        add_action('custom_pixels_initiate_checkout', array(__CLASS__, 'on_initiate_checkout'), 10, 1);
        add_action('custom_pixels_purchase', array(__CLASS__, 'on_purchase'), 10, 1);
    }



    public static function on_page_view($payload = array())
    {
        self::dispatch('PageView', $payload);
    }

    public static function on_view_content($payload = array())
    {
        self::dispatch('ViewContent', $payload);
    }

    public static function on_add_to_cart($payload = array())
    {
        self::dispatch('AddToCart', $payload);
    }

    public static function on_initiate_checkout($payload = array())
    {
        self::dispatch('InitiateCheckout', $payload);
    }

    public static function on_purchase($payload = array())
    {
        self::dispatch('Purchase', $payload);
    }

    private static function dispatch($event_name, $payload = array())
    {
        $payload = is_array($payload) ? $payload : array();

        $event = array(
            'event_name' => $event_name,
            'event_id' => sanitize_text_field($payload['event_id'] ?? wp_generate_uuid4()),
            'source' => 'server',
            'consent' => isset($payload['consent']) ? (bool) $payload['consent'] : true,
            'event_source_url' => esc_url_raw($payload['event_source_url'] ?? home_url(add_query_arg(array(), $GLOBALS['wp']->request ?? ''))),
            'user_data' => array(
                'email' => sanitize_email($payload['email'] ?? ''),
                'phone' => sanitize_text_field($payload['phone'] ?? ''),
                'external_id' => sanitize_text_field($payload['external_id'] ?? (is_user_logged_in() ? get_current_user_id() : '')),
                'client_ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
                'client_user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ),
            'custom_data' => array(
                'value' => sanitize_text_field($payload['value'] ?? ''),
                'currency' => sanitize_text_field($payload['currency'] ?? ''),
                'content_ids' => array_map('sanitize_text_field', (array) ($payload['content_ids'] ?? array())),
                'contents' => array_map(function ($item) {
                    return is_array($item) ? array_map('sanitize_text_field', $item) : array();
                }, (array) ($payload['contents'] ?? array())),
                'order_id' => sanitize_text_field($payload['order_id'] ?? ''),
            ),
        );

        Custom_Pixels_Event_Dispatcher::dispatch_internal($event);
    }
}
