<?php
/**
 * Plugin Name: Chained Select Enhancer for Gravity Forms
 * Description: Enhances Gravity Forms Chained Selects with auto-select, column hiding (with improved UI), and full-width display
 * Version: 1.04
 * Author: guilamu
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GF_CHAINED_SELECT_ENHANCER_VERSION', '1.04' );
define( 'GF_CHAINED_SELECT_ENHANCER_PATH', plugin_dir_path( __FILE__ ) );
define( 'GF_CHAINED_SELECT_ENHANCER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Initialize plugin
 */
add_action( 'gform_loaded', array( 'GF_Chained_Select_Enhancer', 'load' ), 5 );

class GF_Chained_Select_Enhancer {

    public static function load() {
        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }

        require_once( GF_CHAINED_SELECT_ENHANCER_PATH . 'class-gf-chained-select-enhancer-addon.php' );
        GFAddOn::register( 'GF_Chained_Select_Enhancer_AddOn' );
    }
}

/**
 * Enqueue scripts and styles for frontend
 */
add_action( 'wp_enqueue_scripts', 'gf_chained_select_enhancer_enqueue_scripts' );
function gf_chained_select_enhancer_enqueue_scripts() {
    wp_enqueue_script(
        'gf-chained-select-enhancer',
        GF_CHAINED_SELECT_ENHANCER_URL . 'js/gf-chained-select-enhancer.js',
        array( 'jquery' ),
        GF_CHAINED_SELECT_ENHANCER_VERSION
    );
}

/**
 * Enqueue scripts and styles for admin
 */
add_action( 'gform_field_standard_settings', 'gf_chained_select_enhancer_admin_enqueue_scripts', 10, 2 );
function gf_chained_select_enhancer_admin_enqueue_scripts( $position, $form_id ) {
    if ( $position === 1200 ) {
        wp_enqueue_script(
            'gf-chained-select-enhancer-admin',
            GF_CHAINED_SELECT_ENHANCER_URL . 'js/admin-toggles.js',
            array( 'jquery' ),
            GF_CHAINED_SELECT_ENHANCER_VERSION,
            true
        );

        wp_enqueue_style(
            'gf-chained-select-enhancer-admin',
            GF_CHAINED_SELECT_ENHANCER_URL . 'css/admin-toggles.css',
            array(),
            GF_CHAINED_SELECT_ENHANCER_VERSION
        );

        wp_localize_script(
            'gf-chained-select-enhancer-admin',
            'GFChainedSelectEnhancerL10n',
            array(
                'column'    => __( 'Column', 'gf-chained-select-enhancer' ),
                'noColumns' => __( 'No columns available', 'gf-chained-select-enhancer' ),
                'hideLabel' => __( 'Hide this column', 'gf-chained-select-enhancer' ),
            )
        );
    }
}

/**
 * Add the hide columns field setting
 */
add_action( 'gform_field_standard_settings', 'gf_chained_select_enhancer_add_field_setting', 10, 2 );
function gf_chained_select_enhancer_add_field_setting( $position, $form_id ) {
    if ( $position === 1200 ) {
        ?>
        <li class="gf_chained_select_enhancers_hide_columns_setting field_setting">
            <label for="gf_chained_select_enhancers_hide_columns">
                <?php _e( 'Hide Columns', 'gf-chained-select-enhancer' ); ?>
            </label>
            <div id="gf_chained_select_enhancers_hide_columns_wrapper" class="gf-chained-select-toggle-container">
                <p class="description"><?php _e( 'Select which columns to hide:', 'gf-chained-select-enhancer' ); ?></p>
                <div id="gf_chained_select_enhancers_columns_list" class="gf-chained-select-columns-list">
                    <!-- Toggle switches will be inserted here by JavaScript -->
                </div>
                <!-- Hidden field to store the comma-separated column values -->
                <input type="hidden" id="gf_chained_select_enhancers_hide_columns" name="gf_chained_select_enhancers_hide_columns" value="" />
            </div>
        </li>
        <?php
    }
}

/**
 * Make field setting visible only for chained select fields
 */
add_action( 'gform_field_standard_settings', 'gf_chained_select_enhancer_field_condition', 10, 2 );
function gf_chained_select_enhancer_field_condition( $position, $form_id ) {
    if ( $position === 1200 ) {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function() {
                jQuery(document).on('gform_field_standard_settings_change', function() {
                    var field = GetSelectedField();
                    if ( field.type === 'chained_select' ) {
                        jQuery('.gf_chained_select_enhancers_hide_columns_setting').show();
                        GFChainedSelectEnhancer.renderToggleSwitches();
                    } else {
                        jQuery('.gf_chained_select_enhancers_hide_columns_setting').hide();
                    }
                });

                // Initial check
                var field = GetSelectedField();
                if ( field && field.type === 'chained_select' ) {
                    jQuery('.gf_chained_select_enhancers_hide_columns_setting').show();
                } else {
                    jQuery('.gf_chained_select_enhancers_hide_columns_setting').hide();
                }
            });
        </script>
        <?php
    }
}

/**
 * Filter to save the hide columns setting
 */
add_filter( 'gform_field_value_gf_chained_select_enhancers_hide_columns', function( $value, $field ) {
    return isset( $field['gf_chained_select_enhancers_hide_columns'] ) ? $field['gf_chained_select_enhancers_hide_columns'] : '';
}, 10, 2 );

/**
 * Load translations
 */
add_action( 'plugins_loaded', 'gf_chained_select_enhancer_load_textdomain' );
function gf_chained_select_enhancer_load_textdomain() {
    load_plugin_textdomain(
        'gf-chained-select-enhancer',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}
