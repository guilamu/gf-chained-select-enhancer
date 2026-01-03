<?php
/**
 * Plugin Name: Chained Select Enhancer for Gravity Forms
 * Plugin URI: https://github.com/guilamu/gf-chained-select-enhancer
 * Description: Enhances Gravity Forms Chained Selects with auto-select functionality, column hiding options, and CSV export.
 * Version: 1.5.1
 * Author: Guilamu
 * Author URI: https://github.com/guilamu
 * Text Domain: gf-chained-select-enhancer
 * Domain Path: /languages
 * Update URI: https://github.com/guilamu/gf-chained-select-enhancer
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants.
define('GFCS_VERSION', '1.5.1');
define('GFCS_PLUGIN_FILE', plugin_basename(__FILE__));
define('GFCS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GFCS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files.
require_once GFCS_PLUGIN_PATH . 'includes/class-github-updater.php';
require_once GFCS_PLUGIN_PATH . 'includes/class-gf-chained-select-enhancer.php';


/**
 * Initialize the plugin.
 *
 * @return void
 */
function gfcs_init(): void
{
    // Initialize GitHub updater (always, for update checks).
    GFCS_GitHub_Updater::init();

    // Initialize main functionality only if Gravity Forms is active.
    if (class_exists('GFForms')) {
        new GFCS_Chained_Select_Enhancer(
            GFCS_VERSION,
            GFCS_PLUGIN_URL,
            GFCS_PLUGIN_PATH
        );
    }
}
add_action('plugins_loaded', 'gfcs_init');
