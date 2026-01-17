<?php
/**
 * Plugin Name: Chained Select Enhancer for Gravity Forms
 * Plugin URI: https://github.com/guilamu/gf-chained-select-enhancer
 * Description: Enhances Gravity Forms Chained Selects with auto-select functionality, column hiding options, and CSV export.
 * Version: 1.7.0
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
define('GFCS_VERSION', '1.7.0');
define('GFCS_PLUGIN_FILE', plugin_basename(__FILE__));
define('GFCS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GFCS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files.
require_once GFCS_PLUGIN_PATH . 'includes/class-github-updater.php';
require_once GFCS_PLUGIN_PATH . 'includes/class-gf-chained-select-enhancer.php';
require_once GFCS_PLUGIN_PATH . 'includes/class-xlsx-parser.php';
require_once GFCS_PLUGIN_PATH . 'includes/class-import-handler.php';


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

        // Initialize XLSX import handler for chained selects.
        new GFCS_Import_Handler();
    }

    // Register with Guilamu Bug Reporter.
    if (class_exists('Guilamu_Bug_Reporter')) {
        Guilamu_Bug_Reporter::register(array(
            'slug'        => 'gf-chained-select-enhancer',
            'name'        => 'Chained Select Enhancer for Gravity Forms',
            'version'     => GFCS_VERSION,
            'github_repo' => 'guilamu/gf-chained-select-enhancer',
        ));
    }
}
add_action('plugins_loaded', 'gfcs_init');

/**
 * Add "Report a Bug" link to plugin row meta.
 *
 * @param array  $links Plugin row meta links.
 * @param string $file  Plugin file path.
 * @return array Modified links.
 */
function gfcs_plugin_row_meta(array $links, string $file): array
{
    if (GFCS_PLUGIN_FILE !== $file) {
        return $links;
    }

    if (class_exists('Guilamu_Bug_Reporter')) {
        $links[] = sprintf(
            '<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="gf-chained-select-enhancer" data-plugin-name="%s">%s</a>',
            esc_attr__('Chained Select Enhancer for Gravity Forms', 'gf-chained-select-enhancer'),
            esc_html__('🐛 Report a Bug', 'gf-chained-select-enhancer')
        );
    } else {
        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/guilamu/guilamu-bug-reporter/releases',
            esc_html__('🐛 Report a Bug (install Bug Reporter)', 'gf-chained-select-enhancer')
        );
    }

    return $links;
}
add_filter('plugin_row_meta', 'gfcs_plugin_row_meta', 10, 2);
