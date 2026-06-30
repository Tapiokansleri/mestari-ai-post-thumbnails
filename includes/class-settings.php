<?php

if (!defined('ABSPATH')) {
  exit;
}

class MAPT_Settings {
  public static function init() {
    add_action('admin_menu', [__CLASS__, 'register_menu']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
  }

  public static function register_menu() {
    add_options_page(
      __('Mestari AI Post Thumbnails', 'mestari-ai-post-thumbnails'),
      __('AI Thumbnails', 'mestari-ai-post-thumbnails'),
      'manage_options',
      'mestari-ai-post-thumbnails',
      [__CLASS__, 'render_page']
    );
  }

  public static function register_settings() {
    register_setting('mapt_settings', MAPT_Generator::OPTION_API_KEY, [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => '',
    ]);

    register_setting('mapt_settings', MAPT_Generator::OPTION_EXTRA_PROMPT, [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_textarea_field',
      'default' => '',
    ]);
  }

  public static function enqueue_assets($hook) {
    if ($hook !== 'settings_page_mestari-ai-post-thumbnails') {
      return;
    }

    wp_enqueue_style(
      'mapt-admin',
      plugins_url('assets/admin.css', MAPT_PLUGIN_FILE),
      [],
      MAPT_VERSION
    );

    wp_enqueue_script(
      'mapt-admin',
      plugins_url('assets/admin.js', MAPT_PLUGIN_FILE),
      [],
      MAPT_VERSION,
      true
    );

    wp_localize_script('mapt-admin', 'maptAdmin', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('mapt_admin'),
      'generating' => __('Generating…', 'mestari-ai-post-thumbnails'),
      'generate' => __('Generate thumbnail', 'mestari-ai-post-thumbnails'),
      'done' => __('Done', 'mestari-ai-post-thumbnails'),
      'error' => __('Error', 'mestari-ai-post-thumbnails'),
      'invalidResponse' => __('The server returned an unexpected response. If the image was created, refresh this page to check.', 'mestari-ai-post-thumbnails'),
      'checkingUpdates' => __('Checking for updates…', 'mestari-ai-post-thumbnails'),
      'checkUpdates' => __('Check for updates', 'mestari-ai-post-thumbnails'),
      'installUpdate' => __('Install update', 'mestari-ai-post-thumbnails'),
    ]);
  }

  public static function render_page() {
    if (!current_user_can('manage_options')) {
      return;
    }

    $missing = MAPT_Generator::get_posts_missing_thumbnail();
    $api_key = MAPT_Generator::get_api_key();
    $extra_prompt = MAPT_Generator::get_extra_prompt();
    ?>
    <div class="wrap mapt-wrap">
      <h1><?php esc_html_e('Mestari AI Post Thumbnails', 'mestari-ai-post-thumbnails'); ?></h1>
      <p class="description">
        <?php esc_html_e('Generate 1000×625 featured images (400:250 ratio) from post titles using OpenAI gpt-image-2.', 'mestari-ai-post-thumbnails'); ?>
      </p>

      <div class="mapt-panel mapt-panel--updates">
        <h2><?php esc_html_e('Plugin updates', 'mestari-ai-post-thumbnails'); ?></h2>
        <p>
          <?php
          printf(
            /* translators: %s: version number */
            esc_html__('Installed version: %s', 'mestari-ai-post-thumbnails'),
            esc_html(MAPT_VERSION)
          );
          ?>
        </p>
        <p class="description">
          <?php esc_html_e('Updates are delivered automatically from GitHub releases.', 'mestari-ai-post-thumbnails'); ?>
          <a href="https://github.com/Tapiokansleri/mestari-ai-post-thumbnails" target="_blank" rel="noopener noreferrer">GitHub</a>
        </p>
        <p>
          <button type="button" class="button" id="mapt-check-updates">
            <?php esc_html_e('Check for updates', 'mestari-ai-post-thumbnails'); ?>
          </button>
        </p>
        <div id="mapt-update-status" class="mapt-update-status" aria-live="polite"></div>
      </div>

      <form method="post" action="options.php" class="mapt-settings-form">
        <?php settings_fields('mapt_settings'); ?>

        <h2><?php esc_html_e('Settings', 'mestari-ai-post-thumbnails'); ?></h2>

        <table class="form-table" role="presentation">
          <tr>
            <th scope="row">
              <label for="mapt_openai_api_key"><?php esc_html_e('OpenAI API key', 'mestari-ai-post-thumbnails'); ?></label>
            </th>
            <td>
              <input
                type="password"
                id="mapt_openai_api_key"
                name="<?php echo esc_attr(MAPT_Generator::OPTION_API_KEY); ?>"
                value="<?php echo esc_attr($api_key); ?>"
                class="regular-text"
                autocomplete="off"
              />
              <p class="description">
                <?php esc_html_e('Your API key from platform.openai.com. Stored in the WordPress database.', 'mestari-ai-post-thumbnails'); ?>
              </p>
            </td>
          </tr>
          <tr>
            <th scope="row">
              <label for="mapt_extra_prompt"><?php esc_html_e('Extra prompt text', 'mestari-ai-post-thumbnails'); ?></label>
            </th>
            <td>
              <textarea
                id="mapt_extra_prompt"
                name="<?php echo esc_attr(MAPT_Generator::OPTION_EXTRA_PROMPT); ?>"
                rows="4"
                class="large-text"
              ><?php echo esc_textarea($extra_prompt); ?></textarea>
              <p class="description">
                <?php esc_html_e('Optional instructions appended to every image prompt (e.g. brand colours, style, subject matter).', 'mestari-ai-post-thumbnails'); ?>
              </p>
            </td>
          </tr>
        </table>

        <?php submit_button(__('Save settings', 'mestari-ai-post-thumbnails')); ?>
      </form>

      <hr />

      <h2><?php esc_html_e('Posts missing a featured image', 'mestari-ai-post-thumbnails'); ?></h2>

      <?php if ($api_key === '') : ?>
        <div class="notice notice-warning inline">
          <p><?php esc_html_e('Add your OpenAI API key above before generating thumbnails.', 'mestari-ai-post-thumbnails'); ?></p>
        </div>
      <?php endif; ?>

      <?php if (empty($missing)) : ?>
        <p><?php esc_html_e('All published posts have a featured image.', 'mestari-ai-post-thumbnails'); ?></p>
      <?php else : ?>
        <p>
          <?php
          printf(
            esc_html(_n('%d post is missing a thumbnail.', '%d posts are missing a thumbnail.', count($missing), 'mestari-ai-post-thumbnails')),
            count($missing)
          );
          ?>
        </p>
        <table class="widefat striped mapt-missing-table">
          <thead>
            <tr>
              <th><?php esc_html_e('Post', 'mestari-ai-post-thumbnails'); ?></th>
              <th><?php esc_html_e('Date', 'mestari-ai-post-thumbnails'); ?></th>
              <th><?php esc_html_e('Action', 'mestari-ai-post-thumbnails'); ?></th>
              <th><?php esc_html_e('Status', 'mestari-ai-post-thumbnails'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($missing as $post) : ?>
              <tr data-post-id="<?php echo esc_attr($post->ID); ?>">
                <td>
                  <strong>
                    <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>">
                      <?php echo esc_html(get_the_title($post)); ?>
                    </a>
                  </strong>
                </td>
                <td><?php echo esc_html(get_the_date('', $post)); ?></td>
                <td>
                  <button
                    type="button"
                    class="button button-primary mapt-generate-btn"
                    data-post-id="<?php echo esc_attr($post->ID); ?>"
                    <?php disabled($api_key === ''); ?>
                  >
                    <?php esc_html_e('Generate thumbnail', 'mestari-ai-post-thumbnails'); ?>
                  </button>
                </td>
                <td class="mapt-status" aria-live="polite"></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <?php
  }
}
