<?php

if (!defined('ABSPATH')) {
    exit;
}

class Custom_Pixels_GitHub_Updater
{
    private $plugin_slug;
    private $version;
    private $github_repo;
    private $cache_key;
    private $cache_allowed;

    public function __construct()
    {
        $this->plugin_slug = plugin_basename(CUSTOM_PIXELS_FILE);
        $this->version = CUSTOM_PIXELS_VERSION;
        
        $settings = get_option('custom_pixels_settings', array());
        $this->github_repo = sanitize_text_field($settings['github_repo'] ?? 'cvaneyk/custom-pixels');
        $this->cache_key = 'custom_pixels_updater_' . md5($this->github_repo);
        $this->cache_allowed = false;

        if (empty($this->github_repo)) {
            return;
        }

        add_filter('site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_source_selection', array($this, 'fix_plugin_folder_name'), 10, 3);
    }

    public function check_for_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $github_info = $this->get_github_release_info();
        if (!$github_info) {
            return $transient;
        }

        $is_newer_version = version_compare($this->version, $github_info['version'], '<');

        if ($is_newer_version) {
            $plugin_info = new stdClass();
            $plugin_info->plugin = $this->plugin_slug;
            $plugin_info->slug = dirname($this->plugin_slug);
            $plugin_info->new_version = $github_info['version'];
            $plugin_info->url = $github_info['url'];
            $plugin_info->package = $github_info['download_link'];

            $transient->response[$this->plugin_slug] = $plugin_info;
        }

        return $transient;
    }

    public function plugin_info($res, $action, $args)
    {
        if ($action !== 'plugin_information' || $args->slug !== dirname($this->plugin_slug)) {
            return $res;
        }

        $github_info = $this->get_github_release_info();
        if (!$github_info) {
            return $res;
        }

        $plugin_info = new stdClass();
        $plugin_info->name = 'Custom Pixels (Meta + TikTok)';
        $plugin_info->slug = dirname($this->plugin_slug);
        $plugin_info->version = $github_info['version'];
        $plugin_info->author = 'Custom Pixels';
        $plugin_info->homepage = $github_info['url'];
        $plugin_info->requires = '5.0';
        $plugin_info->tested = get_bloginfo('version');
        $plugin_info->download_link = $github_info['download_link'];
        $plugin_info->sections = array(
            'description' => 'Updates from GitHub repository: ' . esc_html($this->github_repo),
            'changelog' => wp_kses_post($github_info['changelog'])
        );

        return $plugin_info;
    }

    public function fix_plugin_folder_name($source, $remote_source, $upgrader)
    {
        global $wp_filesystem;

        // Check if we are upgrading this specific plugin
        if (!isset($upgrader->skin->plugin) || $upgrader->skin->plugin !== $this->plugin_slug) {
            return $source;
        }

        $expected_folder_name = dirname($this->plugin_slug);
        $source_dirname = basename(untrailingslashit($source));

        // If the extracted folder is different from the expected plugin folder name
        if ($source_dirname !== $expected_folder_name) {
            $new_source = trailingslashit($remote_source) . $expected_folder_name;
            $wp_filesystem->move($source, $new_source, true);
            return trailingslashit($new_source);
        }

        return $source;
    }

    private function get_github_release_info()
    {
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $request_url = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
        $response = wp_remote_get($request_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version')
            )
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_transient($this->cache_key, false, 15 * MINUTE_IN_SECONDS);
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['tag_name']) || empty($data['zipball_url'])) {
            return false;
        }

        $version = ltrim($data['tag_name'], 'v');

        $info = array(
            'version' => $version,
            'url' => $data['html_url'] ?? '',
            'download_link' => $data['zipball_url'],
            'changelog' => $data['body'] ?? ''
        );

        set_transient($this->cache_key, $info, 12 * HOUR_IN_SECONDS);

        return $info;
    }
}
