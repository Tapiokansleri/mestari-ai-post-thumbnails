<?php
/**
 * Plugin Name: Mestari AI Post Thumbnails
 * Plugin URI: https://github.com/Tapiokansleri/mestari-ai-post-thumbnails
 * Description: Generate featured images from post titles using the OpenAI Images API.
 * Version: 1.0.0
 * Author: Tapio Kansleri
 * Author URI: https://github.com/Tapiokansleri
 * Text Domain: mestari-ai-post-thumbnails
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
  exit;
}

define('MAPT_VERSION', '1.0.0');
define('MAPT_PLUGIN_FILE', __FILE__);
define('MAPT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MAPT_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('MAPT_TARGET_WIDTH', 1000);
define('MAPT_TARGET_HEIGHT', 625); // 400:250 ratio

require_once MAPT_PLUGIN_DIR . 'includes/class-generator.php';
require_once MAPT_PLUGIN_DIR . 'includes/class-github-updater.php';
require_once MAPT_PLUGIN_DIR . 'includes/class-settings.php';

add_action('plugins_loaded', function () {
  MAPT_Settings::init();
  MAPT_Generator::init();
  new MAPT_GitHub_Updater();
});
