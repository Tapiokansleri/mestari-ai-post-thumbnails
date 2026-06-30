<?php

if (!defined('ABSPATH')) {
  exit;
}

class MAPT_Generator {
  const OPTION_API_KEY = 'mapt_openai_api_key';
  const OPTION_EXTRA_PROMPT = 'mapt_extra_prompt';

  public static function init() {
    self::maybe_migrate_legacy_options();
    add_action('wp_ajax_mapt_generate_thumbnail', [__CLASS__, 'ajax_generate']);
  }

  private static function maybe_migrate_legacy_options() {
    if (get_option(self::OPTION_API_KEY, null) === null && get_option('apt_openai_api_key', '') !== '') {
      update_option(self::OPTION_API_KEY, get_option('apt_openai_api_key', ''));
      update_option(self::OPTION_EXTRA_PROMPT, get_option('apt_extra_prompt', ''));
    }
  }

  public static function get_api_key() {
    return trim((string) get_option(self::OPTION_API_KEY, ''));
  }

  public static function get_extra_prompt() {
    return trim((string) get_option(self::OPTION_EXTRA_PROMPT, ''));
  }

  public static function get_posts_missing_thumbnail($limit = 50) {
    return get_posts([
      'post_type' => 'post',
      'post_status' => 'publish',
      'posts_per_page' => $limit,
      'meta_query' => [
        'relation' => 'OR',
        [
          'key' => '_thumbnail_id',
          'compare' => 'NOT EXISTS',
        ],
        [
          'key' => '_thumbnail_id',
          'value' => '0',
          'compare' => '=',
        ],
      ],
      'orderby' => 'date',
      'order' => 'DESC',
    ]);
  }

  public static function ajax_generate() {
    check_ajax_referer('mapt_admin', 'nonce');

    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => __('Permission denied.', 'mestari-ai-post-thumbnails')], 403);
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id) {
      wp_send_json_error(['message' => __('Invalid post.', 'mestari-ai-post-thumbnails')]);
    }

    $result = self::generate_for_post($post_id);

    if (is_wp_error($result)) {
      wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success([
      'message' => __('Thumbnail generated and set.', 'mestari-ai-post-thumbnails'),
      'attachment_id' => $result['attachment_id'],
      'thumbnail_url' => $result['thumbnail_url'],
      'post_id' => $post_id,
    ]);
  }

  public static function generate_for_post($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
      return new WP_Error('invalid_post', __('Post not found or not published.', 'mestari-ai-post-thumbnails'));
    }

    $api_key = self::get_api_key();
    if ($api_key === '') {
      return new WP_Error('missing_api_key', __('OpenAI API key is not configured.', 'mestari-ai-post-thumbnails'));
    }

    $prompt = self::build_prompt($post->post_title, self::get_extra_prompt());
    $image_data = self::request_image($api_key, $prompt);

    if (is_wp_error($image_data)) {
      return $image_data;
    }

    $attachment_id = self::save_to_media_library($image_data, $post);
    if (is_wp_error($attachment_id)) {
      return $attachment_id;
    }

    $resized = self::resize_attachment($attachment_id);
    if (is_wp_error($resized)) {
      return $resized;
    }

    set_post_thumbnail($post_id, $attachment_id);

    return [
      'attachment_id' => $attachment_id,
      'thumbnail_url' => wp_get_attachment_image_url($attachment_id, 'medium'),
    ];
  }

  public static function build_prompt($title, $extra = '') {
    $parts = [
      sprintf(
        'Create a professional blog featured image for an article titled "%s".',
        wp_strip_all_tags($title)
      ),
      'Landscape orientation, clean modern style, visually engaging, no text or watermarks.',
      sprintf(
        'Aspect ratio 8:5 (400x250), suitable for a %dx%d pixel thumbnail.',
        MAPT_TARGET_WIDTH,
        MAPT_TARGET_HEIGHT
      ),
    ];

    if ($extra !== '') {
      $parts[] = wp_strip_all_tags($extra);
    }

    return implode(' ', $parts);
  }

  private static function request_image($api_key, $prompt) {
    $payload = [
      'model' => MAPT_IMAGE_MODEL,
      'prompt' => $prompt,
      'n' => 1,
      'size' => MAPT_IMAGE_SIZE,
      'quality' => 'medium',
      'thinking' => 'off',
    ];

    $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
      'timeout' => 120,
      'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json',
      ],
      'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
      return new WP_Error('api_request_failed', $response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code !== 200) {
      $message = $body['error']['message'] ?? __('OpenAI request failed.', 'mestari-ai-post-thumbnails');
      return new WP_Error('api_error', $message);
    }

    $item = $body['data'][0] ?? null;
    if (!is_array($item)) {
      return new WP_Error('api_empty', __('OpenAI returned no image data.', 'mestari-ai-post-thumbnails'));
    }

    if (!empty($item['url'])) {
      return ['type' => 'url', 'value' => $item['url']];
    }

    if (!empty($item['b64_json'])) {
      return ['type' => 'b64', 'value' => $item['b64_json']];
    }

    return new WP_Error('api_empty', __('OpenAI returned no image URL or data.', 'mestari-ai-post-thumbnails'));
  }

  private static function save_to_media_library($image_data, WP_Post $post) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $filename = sanitize_file_name('ai-thumb-' . $post->ID . '-' . time() . '.png');

    if ($image_data['type'] === 'url') {
      $tmp = download_url($image_data['value'], 120);
      if (is_wp_error($tmp)) {
        return $tmp;
      }
    } else {
      $decoded = base64_decode($image_data['value'], true);
      if ($decoded === false) {
        return new WP_Error('invalid_image', __('Could not decode image data from OpenAI.', 'mestari-ai-post-thumbnails'));
      }

      $tmp = wp_tempnam($filename);
      if (!$tmp) {
        return new WP_Error('temp_file', __('Could not create a temporary file.', 'mestari-ai-post-thumbnails'));
      }

      if (file_put_contents($tmp, $decoded) === false) {
        @unlink($tmp);
        return new WP_Error('temp_file', __('Could not write image to a temporary file.', 'mestari-ai-post-thumbnails'));
      }
    }

    $file_array = [
      'name' => $filename,
      'tmp_name' => $tmp,
    ];

    $attachment_id = media_handle_sideload(
      $file_array,
      $post->ID,
      sprintf(__('AI thumbnail for: %s', 'mestari-ai-post-thumbnails'), $post->post_title)
    );

    if (is_wp_error($attachment_id)) {
      @unlink($tmp);
      return $attachment_id;
    }

    update_post_meta($attachment_id, '_wp_attachment_image_alt', $post->post_title);

    return $attachment_id;
  }

  private static function resize_attachment($attachment_id) {
    $file = get_attached_file($attachment_id);
    if (!$file || !file_exists($file)) {
      return new WP_Error('missing_file', __('Downloaded image file not found.', 'mestari-ai-post-thumbnails'));
    }

    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) {
      return $editor;
    }

    $size = $editor->get_size();
    $target_ratio = MAPT_TARGET_WIDTH / MAPT_TARGET_HEIGHT;
    $source_ratio = $size['width'] / $size['height'];

    if ($source_ratio > $target_ratio) {
      $crop_height = $size['height'];
      $crop_width = (int) round($crop_height * $target_ratio);
      $crop_x = (int) round(($size['width'] - $crop_width) / 2);
      $crop_y = 0;
    } else {
      $crop_width = $size['width'];
      $crop_height = (int) round($crop_width / $target_ratio);
      $crop_x = 0;
      $crop_y = (int) round(($size['height'] - $crop_height) / 2);
    }

    $cropped = $editor->crop($crop_x, $crop_y, $crop_width, $crop_height, MAPT_TARGET_WIDTH, MAPT_TARGET_HEIGHT);
    if (is_wp_error($cropped)) {
      return $cropped;
    }

    $saved = $editor->save($file);
    if (is_wp_error($saved)) {
      return $saved;
    }

    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file));

    return true;
  }
}
