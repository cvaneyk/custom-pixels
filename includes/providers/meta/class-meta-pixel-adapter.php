<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_Meta_Pixel_Adapter
{
    public static function browser_payload(array $event)
    {
        return array(
            'event' => $event['event_name'],
            'eventID' => $event['event_id'],
            'params' => $event['custom_data'],
        );
    }
}
