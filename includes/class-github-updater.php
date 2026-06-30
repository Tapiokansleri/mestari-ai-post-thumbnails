<?php

if (!defined('ABSPATH')) {
  exit;
}

class MAPT_GitHub_Updater {

  private $repo = 'Tapiokansleri/mestari-ai-post-thumbnails';
  private $plugin_slug = 'mestari-ai-post-thumbnails';
  private $cache_key = 'mapt_github_release';
  private $cache_ttl = 21600;

  public function __construct() {
    add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
    add_filter('site_transient_update_plugins', [$this, 'inject_update']);
    add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);
    add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);

    add_action('load-update-core.php', [$this, 'refresh_release_cache']);
    add_action('load-plugins.php', [$this, 'refresh_release_cache']);
    add_action('load-update.php', [$this, 'refresh_release_cache']);
    add_action('wp_ajax_mapt_check_updates', [$this, 'ajax_check_updates']);
  }

  public function refresh_release_cache() {
    if (!current_user_can('update_plugins')) {
      return;
    }

    delete_transient($this->cache_key);
  }

  public function ajax_check_updates() {
    check_ajax_referer('mapt_admin', 'nonce');

    if (!current_user_can('update_plugins')) {
      wp_send_json_error(['message' => __('Permission denied.', 'mestari-ai-post-thumbnails')], 403);
    }

    delete_transient($this->cache_key);
    delete_site_transient('update_plugins');

    $release = $this->get_release(true);
    if (!$release) {
      wp_send_json_error([
        'message' => __('Could not reach GitHub or no releases found.', 'mestari-ai-post-thumbnails'),
      ]);
    }

    $remote_version = ltrim($release->tag_name, 'vV');
    $installed_version = MAPT_VERSION;
    $has_update = version_compare($remote_version, $installed_version, '>');

    wp_update_plugins();

    $payload = [
      'installed_version' => $installed_version,
      'remote_version' => $remote_version,
      'has_update' => $has_update,
      'message' => $has_update
        ? sprintf(
          /* translators: %s: version number */
          __('Update available: version %s', 'mestari-ai-post-thumbnails'),
          $remote_version
        )
        : __('You are running the latest version.', 'mestari-ai-post-thumbnails'),
      'updates_url' => admin_url('update-core.php'),
    ];

    if ($has_update) {
      $payload['update_url'] = wp_nonce_url(
        self_admin_url('update.php?action=upgrade-plugin&plugin=' . rawurlencode(MAPT_PLUGIN_BASENAME)),
        'upgrade-plugin_' . MAPT_PLUGIN_BASENAME
      );
    }

    wp_send_json_success($payload);
  }

  public function get_installed_version() {
    if (!function_exists('get_plugin_data')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $data = get_plugin_data(MAPT_PLUGIN_FILE);
    return $data['Version'] ?? MAPT_VERSION;
  }

  public function get_release($force = false) {
    if (!$force) {
      $cached = get_transient($this->cache_key);
      if ($cached !== false) {
        return $cached ?: false;
      }
    }

    $url = sprintf('https://api.github.com/repos/%s/releases/latest', $this->repo);
    $response = wp_remote_get($url, [
      'timeout' => 15,
      'headers' => [
        'Accept' => 'application/vnd.github+json',
        'User-Agent' => sprintf(
          'Mestari-AI-Post-Thumbnails/%s; %s',
          $this->get_installed_version(),
          home_url('/')
        ),
      ],
    ]);

    if (is_wp_error($response)) {
      set_transient($this->cache_key, 0, MINUTE_IN_SECONDS * 5);
      return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
      set_transient($this->cache_key, 0, MINUTE_IN_SECONDS * 5);
      return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response));
    if (empty($body->tag_name)) {
      set_transient($this->cache_key, 0, MINUTE_IN_SECONDS * 5);
      return false;
    }

    set_transient($this->cache_key, $body, $this->cache_ttl);
    return $body;
  }

  private function get_update_payload() {
    $installed_version = $this->get_installed_version();
    $release = $this->get_release();

    if (!$release || empty($release->tag_name)) {
      return false;
    }

    $remote_version = ltrim($release->tag_name, 'vV');
    if (!version_compare($remote_version, $installed_version, '>')) {
      return false;
    }

    $zip_url = $this->get_zip_url($release);
    if (!$zip_url) {
      return false;
    }

    return (object) [
      'slug' => $this->plugin_slug,
      'plugin' => MAPT_PLUGIN_BASENAME,
      'new_version' => $remote_version,
      'url' => sprintf('https://github.com/%s', $this->repo),
      'package' => $zip_url,
      'tested' => get_bloginfo('version'),
    ];
  }

  public function inject_update($transient) {
    if (!is_object($transient)) {
      $transient = new stdClass();
    }

    if (!isset($transient->response) || !is_array($transient->response)) {
      $transient->response = [];
    }

    if (!isset($transient->checked) || !is_array($transient->checked)) {
      $transient->checked = [];
    }

    $transient->checked[MAPT_PLUGIN_BASENAME] = $this->get_installed_version();

    $update = $this->get_update_payload();
    if ($update) {
      $transient->response[MAPT_PLUGIN_BASENAME] = $update;
    } else {
      unset($transient->response[MAPT_PLUGIN_BASENAME]);
    }

    return $transient;
  }

  public function plugin_info($result, $action, $args) {
    if ($action !== 'plugin_information') {
      return $result;
    }

    if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
      return $result;
    }

    $release = $this->get_release();
    if (!$release) {
      return $result;
    }

    if (!function_exists('get_plugin_data')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugin_data = get_plugin_data(MAPT_PLUGIN_FILE);
    $remote_version = ltrim($release->tag_name, 'vV');

    return (object) [
      'name' => $plugin_data['Name'],
      'slug' => $this->plugin_slug,
      'version' => $remote_version,
      'author' => $plugin_data['Author'],
      'homepage' => sprintf('https://github.com/%s', $this->repo),
      'download_link' => $this->get_zip_url($release),
      'sections' => [
        'description' => $plugin_data['Description'],
        'changelog' => nl2br(esc_html($release->body ?? '')),
      ],
    ];
  }

  public function post_install($response, $hook_extra, $result) {
    if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== MAPT_PLUGIN_BASENAME) {
      return $response;
    }

    if (is_wp_error($response)) {
      return $response;
    }

    if (empty($result['destination'])) {
      return $response;
    }

    global $wp_filesystem;

    $plugin_dir = wp_normalize_path(WP_PLUGIN_DIR . '/' . $this->plugin_slug);
    $destination = wp_normalize_path($result['destination']);
    $plugin_root = wp_normalize_path($this->locate_plugin_root($destination));

    if ($plugin_root !== $destination && $this->path_is_within($plugin_root, $destination)) {
      $this->promote_nested_plugin($plugin_root, $destination);
    }

    if ($plugin_root !== $plugin_dir && $this->path_is_within($plugin_root, $plugin_dir)) {
      $this->promote_nested_plugin($plugin_root, $plugin_dir);
    }

    if ($plugin_root !== $plugin_dir && !$this->path_is_within($plugin_root, $plugin_dir)) {
      $main_file = $plugin_root . '/' . $this->plugin_slug . '.php';
      if (!$wp_filesystem->exists($main_file)) {
        return new WP_Error(
          'mapt_plugin_missing',
          __('Plugin update package is missing the main plugin file.', 'mestari-ai-post-thumbnails')
        );
      }

      $staged = $plugin_dir . '.github-stage-' . wp_unique_id();
      if (!$wp_filesystem->move($plugin_root, $staged, true)) {
        return new WP_Error('mapt_plugin_stage', __('Could not stage the plugin update.', 'mestari-ai-post-thumbnails'));
      }

      if ($wp_filesystem->exists($plugin_dir)) {
        $wp_filesystem->delete($plugin_dir, true);
      }

      if (!$wp_filesystem->move($staged, $plugin_dir)) {
        if ($wp_filesystem->exists($staged)) {
          $wp_filesystem->move($staged, $plugin_dir);
        }

        return new WP_Error('mapt_plugin_install', __('Could not install the plugin update.', 'mestari-ai-post-thumbnails'));
      }
    }

    delete_transient($this->cache_key);
    delete_site_transient('update_plugins');

    return $response;
  }

  private function get_zip_url($release) {
    if (!empty($release->assets) && is_array($release->assets)) {
      foreach ($release->assets as $asset) {
        if (!empty($asset->browser_download_url) && $asset->name === 'mestari-ai-post-thumbnails.zip') {
          return $asset->browser_download_url;
        }
      }

      foreach ($release->assets as $asset) {
        if (!empty($asset->browser_download_url) && strpos($asset->name, '.zip') !== false) {
          return $asset->browser_download_url;
        }
      }
    }

    if (!empty($release->tag_name)) {
      return sprintf(
        'https://github.com/%s/archive/refs/tags/%s.zip',
        $this->repo,
        rawurlencode($release->tag_name)
      );
    }

    return false;
  }

  private function locate_plugin_root($path) {
    global $wp_filesystem;

    $main = $path . '/' . $this->plugin_slug . '.php';
    if ($wp_filesystem->exists($main)) {
      return $path;
    }

    $list = $wp_filesystem->dirlist($path, false, false);
    if (!is_array($list)) {
      return $path;
    }

    foreach ($list as $name => $info) {
      if (empty($info['type']) || $info['type'] !== 'd') {
        continue;
      }

      $candidate = $path . '/' . $name;
      if ($wp_filesystem->exists($candidate . '/' . $this->plugin_slug . '.php')) {
        return $candidate;
      }
    }

    return $path;
  }

  private function promote_nested_plugin($nested_dir, $plugin_dir) {
    global $wp_filesystem;

    if ($nested_dir === $plugin_dir) {
      return;
    }

    $list = $wp_filesystem->dirlist($nested_dir, false, false);
    if (!is_array($list)) {
      return;
    }

    foreach ($list as $name => $info) {
      $wp_filesystem->move($nested_dir . '/' . $name, $plugin_dir . '/' . $name, true);
    }

    $wp_filesystem->delete($nested_dir, true);
  }

  private function path_is_within($path, $parent) {
    $path = trailingslashit(wp_normalize_path($path));
    $parent = trailingslashit(wp_normalize_path($parent));

    return $path !== $parent && strpos($path, $parent) === 0;
  }
}
