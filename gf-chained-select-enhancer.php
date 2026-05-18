<?php
/**
 * Plugin Name: Chained Select Enhancer for Gravity Forms
 * Plugin URI: https://github.com/guilamu/gf-chained-select-enhancer
 * Description: Enhances Gravity Forms Chained Selects with auto-select functionality, column hiding options, and XLSX file support.
 * Version: 1.9.9
 * Author: Guilamu
 * Author URI: https://github.com/guilamu
 * Text Domain: gf-chained-select-enhancer
 * Domain Path: /languages
 * Update URI: https://github.com/guilamu/gf-chained-select-enhancer
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: AGPL-3.0-or-later
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants.
define('GFCS_VERSION', '1.9.9');
define('GFCS_PLUGIN_FILE', plugin_basename(__FILE__));
define('GFCS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GFCS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files.
require_once GFCS_PLUGIN_PATH . 'includes/class-github-updater.php';
require_once GFCS_PLUGIN_PATH . 'includes/class-gf-chained-select-enhancer.php';
require_once GFCS_PLUGIN_PATH . 'includes/class-xlsx-parser.php';
require_once GFCS_PLUGIN_PATH . 'includes/class-import-handler.php';


/**
 * Check whether the Chained Selects add-on dependency is loaded.
 *
 * @return bool
 */
function gfcs_has_chained_selects_dependency(): bool
{
    return defined('GF_CHAINEDSELECTS_VERSION')
        || function_exists('gf_chained_selects')
        || class_exists('GF_ChainedSelects_Bootstrap');
}


/**
 * Check whether the runtime dependencies required by the enhancer are loaded.
 *
 * @return bool
 */
function gfcs_has_runtime_dependencies(): bool
{
    if (!class_exists('GFForms')) {
        return false;
    }

    return gfcs_has_chained_selects_dependency();
}


/**
 * Display an admin notice when the Chained Selects add-on is missing.
 *
 * @return void
 */
function gfcs_missing_chained_selects_notice(): void
{
    if (!current_user_can('activate_plugins') || gfcs_has_chained_selects_dependency()) {
        return;
    }

    printf(
        '<div class="notice notice-warning"><p>%s</p></div>',
        esc_html__(
            'Chained Select Enhancer for Gravity Forms requires the Gravity Forms Chained Selects Add-On to be installed and active. Enhancer features are currently disabled.',
            'gf-chained-select-enhancer'
        )
    );
}


/**
 * Initialize the plugin.
 *
 * @return void
 */
function gfcs_init(): void
{
    // Initialize GitHub updater (always, for update checks).
    GFCS_GitHub_Updater::init();

    if (class_exists('GFForms') && !gfcs_has_chained_selects_dependency()) {
        add_action('admin_notices', 'gfcs_missing_chained_selects_notice');
        add_action('network_admin_notices', 'gfcs_missing_chained_selects_notice');
    }

    // Initialize main functionality only when both Gravity Forms and
    // the Chained Selects add-on are available.
    if (gfcs_has_runtime_dependencies()) {
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

    // "View details" thickbox link — same pattern as WordPress.org-hosted plugins.
    $links[] = sprintf(
        '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
        esc_url(self_admin_url(
            'plugin-install.php?tab=plugin-information&plugin=gf-chained-select-enhancer'
            . '&TB_iframe=true&width=772&height=926'
        )),
        esc_attr__('More information about Chained Select Enhancer for Gravity Forms', 'gf-chained-select-enhancer'),
        esc_attr__('Chained Select Enhancer for Gravity Forms', 'gf-chained-select-enhancer'),
        esc_html__('View details', 'gf-chained-select-enhancer')
    );

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
