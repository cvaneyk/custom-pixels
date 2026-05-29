<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_TikTok_Pixel_Adapter
{
    public static function browser_payload(array $event)
    {
        return array(
            'event' => $event['event_name'],
            'event_id' => $event['event_id'],
            'properties' => $event['custom_data'],
        );
    }
}
