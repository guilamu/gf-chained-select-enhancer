<?php
/**
 * Plugin Name: Chained Select Enhancer for Gravity Forms
 * Description: Enhances Gravity Forms Chained Selects with auto-select, column hiding (with improved UI), and full-width display
 * Version: 1.05
 * Author: guilamu
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GF_CHAINED_SELECT_ENHANCER_VERSION', '1.05' );
define( 'GF_CHAINED_SELECT_ENHANCER_PATH', plugin_dir_path( __FILE__ ) );
define( 'GF_CHAINED_SELECT_ENHANCER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load translations
 */
add_action( 'init', 'gf_chained_select_enhancer_load_textdomain' );
function gf_chained_select_enhancer_load_textdomain() {
    load_plugin_textdomain(
        'gf-chained-select-enhancer',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}

/**
 * Enqueue frontend scripts and styles
 */
add_action( 'wp_enqueue_scripts', 'gf_chained_select_enhancer_enqueue_frontend' );
function gf_chained_select_enhancer_enqueue_frontend() {
    if ( class_exists( 'GFForms' ) ) {
        wp_enqueue_script(
            'gf-chained-select-enhancer',
            GF_CHAINED_SELECT_ENHANCER_URL . 'js/gf-chained-select-enhancer.js',
            array( 'jquery' ),
            GF_CHAINED_SELECT_ENHANCER_VERSION
        );
    }
}

/**
 * Enqueue admin scripts and styles
 */
add_action( 'admin_enqueue_scripts', 'gf_chained_select_enhancer_enqueue_admin' );
function gf_chained_select_enhancer_enqueue_admin( $hook ) {
    if ( strpos( $hook, 'gf_edit_forms' ) === false ) {
        return;
    }

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
            'column'    => esc_html__( 'Column', 'gf-chained-select-enhancer' ),
            'noColumns' => esc_html__( 'No columns available', 'gf-chained-select-enhancer' ),
            'hideLabel' => esc_html__( 'Hide this column', 'gf-chained-select-enhancer' ),
        )
    );
}

/**
 * Add the hide columns field setting UI to Gravity Forms editor
 */
add_action( 'gform_field_standard_settings', 'gf_chained_select_enhancer_add_field_setting', 10, 2 );
function gf_chained_select_enhancer_add_field_setting( $position, $form_id ) {
    if ( intval( $position ) !== 1200 ) {
        return;
    }

    ?>
    <li class="gf_chained_select_enhancers_hide_columns_setting field_setting" style="display:none;">
        <label for="gf_chained_select_enhancers_hide_columns">
            <?php esc_html_e( 'Hide Columns', 'gf-chained-select-enhancer' ); ?>
        </label>
        <div id="gf_chained_select_enhancers_hide_columns_wrapper" class="gf-chained-select-toggle-container">
            <p class="description"><?php esc_html_e( 'Select which columns to hide:', 'gf-chained-select-enhancer' ); ?></p>
            <div id="gf_chained_select_enhancers_columns_list" class="gf-chained-select-columns-list">
                <!-- Toggle switches will be inserted here by JavaScript -->
            </div>
            <!-- Hidden field to store the comma-separated column values -->
            <input type="hidden" id="gf_chained_select_enhancers_hide_columns" name="gf_chained_select_enhancers_hide_columns" value="" />
        </div>
    </li>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Show/hide the field setting based on field type
            $(document).on('gform_load_field_settings', function() {
                var field = GetSelectedField();
                if ( field && field.type === 'chained_select' ) {
                    $('.gf_chained_select_enhancers_hide_columns_setting').show();
                    // Render toggles after a short delay to ensure field is loaded
                    setTimeout(function() {
                        GFChainedSelectEnhancer.renderToggleSwitches();
                    }, 100);
                } else {
                    $('.gf_chained_select_enhancers_hide_columns_setting').hide();
                }
            });

            // Handle the auto-select and full-width settings (from original plugin)
            $(document).on('gform_load_field_settings', function() {
                var field = GetSelectedField();
                if ( field && field.type === 'chained_select' ) {
                    $('#gf_chained_select_enhancers_auto_select').prop('checked', field.gf_chained_select_enhancers_auto_select ? true : false);
                    $('#gf_chained_select_enhancers_full_width').prop('checked', field.gf_chained_select_enhancers_full_width ? true : false);
                    $('#gf_chained_select_enhancers_hide_columns').val(field.gf_chained_select_enhancers_hide_columns ? field.gf_chained_select_enhancers_hide_columns : '');
                }
            });
        });
    </script>
    <?php
}

/**
 * Add other field settings (auto-select, full-width)
 * These are from the original plugin
 */
add_action( 'gform_field_standard_settings', 'gf_chained_select_enhancer_add_other_settings', 10, 2 );
function gf_chained_select_enhancer_add_other_settings( $position, $form_id ) {
    if ( intval( $position ) !== 1200 ) {
        return;
    }

    ?>
    <!-- Auto-select setting -->
    <li class="gf_chained_select_enhancers_auto_select_setting field_setting" style="display:none;">
        <label for="gf_chained_select_enhancers_auto_select">
            <input type="checkbox" id="gf_chained_select_enhancers_auto_select" name="gf_chained_select_enhancers_auto_select" value="1" onchange="SetFieldProperty('gf_chained_select_enhancers_auto_select', this.checked);" />
            <?php esc_html_e( 'Automatically select when only one option is available', 'gf-chained-select-enhancer' ); ?>
        </label>
    </li>

    <!-- Full-width setting -->
    <li class="gf_chained_select_enhancers_full_width_setting field_setting" style="display:none;">
        <label for="gf_chained_select_enhancers_full_width">
            <input type="checkbox" id="gf_chained_select_enhancers_full_width" name="gf_chained_select_enhancers_full_width" value="1" onchange="SetFieldProperty('gf_chained_select_enhancers_full_width', this.checked);" />
            <?php esc_html_e( 'Make vertical chained select full width', 'gf-chained-select-enhancer' ); ?>
        </label>
    </li>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $(document).on('gform_load_field_settings', function() {
                var field = GetSelectedField();
                if ( field && field.type === 'chained_select' ) {
                    $('.gf_chained_select_enhancers_auto_select_setting').show();
                    $('.gf_chained_select_enhancers_full_width_setting').show();
                } else {
                    $('.gf_chained_select_enhancers_auto_select_setting').hide();
                    $('.gf_chained_select_enhancers_full_width_setting').hide();
                }
            });
        });
    </script>
    <?php
}

/**
 * Display enhanced chained select fields on frontend
 */
add_filter( 'gform_field_content', 'gf_chained_select_enhancer_field_content', 10, 5 );
function gf_chained_select_enhancer_field_content( $content, $field, $value, $lead_id, $form_id ) {
    if ( $field['type'] !== 'chained_select' ) {
        return $content;
    }

    // Add data attributes for frontend JavaScript
    if ( ! empty( $field['gf_chained_select_enhancers_hide_columns'] ) ) {
        $content = str_replace(
            'class="gfield-choice-input gfield-choice-input_chained_select',
            'data-gf-chained-select-hide-columns="' . esc_attr( $field['gf_chained_select_enhancers_hide_columns'] ) . '" class="gfield-choice-input gfield-choice-input_chained_select',
            $content
        );
    }

    if ( ! empty( $field['gf_chained_select_enhancers_auto_select'] ) ) {
        $content = str_replace(
            'class="gfield-choice-input gfield-choice-input_chained_select',
            'data-gf-chained-select-auto-select="1" class="gfield-choice-input gfield-choice-input_chained_select',
            $content
        );
    }

    if ( ! empty( $field['gf_chained_select_enhancers_full_width'] ) ) {
        $content = str_replace(
            'class="gfield-choice-input gfield-choice-input_chained_select',
            'data-gf-chained-select-full-width="1" class="gfield-choice-input gfield-choice-input_chained_select',
            $content
        );
    }

    return $content;
}
