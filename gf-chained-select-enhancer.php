<?php
/**
 * Plugin Name: Chained Select Enhancer for Gravity Forms
 * Plugin URI: https://github.com/guilamu/gf-chained-select-enhancer
 * Description: Enhances Gravity Forms Chained Selects with auto-select functionality and column hiding options.
 * Version: 1.00
 * Author: Guilamu
 * Author URI: guilamu@gmail.com
 * Text Domain: gf-chained-select-enhancer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GF_Auto_Select_Chained_Selects {

    /**
     * Constructor: Set up WordPress hooks
     */
    public function __construct() {
        add_action('gform_field_standard_settings', array($this, 'add_auto_select_option'), 10, 2);
        add_action('gform_editor_js', array($this, 'editor_script'));
        add_filter('gform_field_content', array($this, 'add_auto_select_property'), 10, 5);
        add_filter('gform_chained_selects_input_choices', array($this, 'auto_select_only_choice'), 10, 3);
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        add_action('wp_head', array($this, 'output_hide_columns_css'));
    }

    /**
     * Load plugin textdomain for translations
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('gf-chained-select-enhancer', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Add auto-select, hide columns, and full width options to Gravity Forms field settings
     */
    public function add_auto_select_option($position, $form_id) {
        if ($position == 25) {
            ?>
            <li class="auto_select_setting field_setting">
                <input type="checkbox" id="field_auto_select" onclick="SetFieldProperty('autoSelectOnly', this.checked);" />
                <label for="field_auto_select" class="inline">
                    <?php esc_html_e('Automatically select when only one option is available', 'gf-chained-select-enhancer'); ?>
                </label>
            </li>
            <li class="full_width_setting field_setting">
                <input type="checkbox" id="field_full_width" onclick="SetFieldProperty('fullWidth', this.checked);" />
                <label for="field_full_width" class="inline">
                    <?php esc_html_e('Make vertical chained select full width', 'gf-chained-select-enhancer'); ?>
                </label>
            </li>
            <li class="hide_columns_setting field_setting">
                <label for="field_hide_columns" class="inline">
                    <?php esc_html_e('Hide columns (comma-separated)', 'gf-chained-select-enhancer'); ?>
                </label>
                <textarea id="field_hide_columns" onchange="SetFieldProperty('hideColumns', this.value);" rows="3" style="width: 100%;"></textarea>
            </li>
            <?php
        }
    }

    /**
     * Add JavaScript to the form editor to handle the new field settings
     */
    public function editor_script() {
        ?>
        <script type='text/javascript'>
            // Add new field settings to chained select fields
            fieldSettings.chainedselect += ', .auto_select_setting, .hide_columns_setting, .full_width_setting';
            
            // Set field properties when loading field settings
            jQuery(document).on('gform_load_field_settings', function(event, field, form) {
                jQuery('#field_auto_select').prop('checked', field.autoSelectOnly == true);
                jQuery('#field_hide_columns').val(field.hideColumns || '');
                jQuery('#field_full_width').prop('checked', field.fullWidth == true);
            });
        </script>
        <?php
    }

    /**
     * Add auto-select property to the field HTML
     */
    public function add_auto_select_property($content, $field, $value, $lead_id, $form_id) {
        if ($field->type == 'chainedselect' && $field->autoSelectOnly) {
            $content .= '<input type="hidden" class="gfield_chainedselect_auto_select" value="1" />';
        }
        return $content;
    }

    /**
     * Auto-select the only choice if enabled
     */
    public function auto_select_only_choice($choices, $form_id, $field) {
        if ($field->autoSelectOnly) {
            $choices = $this->gfcs_auto_select_only_choice($choices);
        }
        return $choices;
    }

    /**
     * Recursive function to auto-select the only choice in nested choices
     */
    private function gfcs_auto_select_only_choice($choices) {
        if (count($choices) == 1) {
            $choices[0]['isSelected'] = true;
        }

        foreach ($choices as &$choice) {
            if (!empty($choice['choices'])) {
                $choice['choices'] = $this->gfcs_auto_select_only_choice($choice['choices']);
            }
        }

        return $choices;
    }

    /**
     * Output CSS to hide specified columns and apply full width
     */
    public function output_hide_columns_css() {
        $forms = GFAPI::get_forms();
        $css = '';
        $fullWidthCssNeeded = false;

        foreach ($forms as $form) {
            foreach ($form['fields'] as $field) {
                if ($field->type == 'chainedselect') {
                    if (!empty($field->hideColumns)) {
                        $columns = explode(',', $field->hideColumns);
                        foreach ($columns as $column) {
                            $column = trim($column);
                            if (is_numeric($column)) {
                                $css .= "#input_{$form['id']}_{$field->id}_{$column} { display: none !important; }\n";
                            }
                        }
                    }
                    if (!empty($field->fullWidth)) {
                        $fullWidthCssNeeded = true;
                    }
                }
            }
        }

        if ($fullWidthCssNeeded) {
            $css .= ".gfield_chainedselect.vertical select { width: 100% !important; }\n";
        }

        if (!empty($css)) {
            echo "<style type='text/css'>\n{$css}</style>\n";
        }
    }
}

// Instantiate the plugin class
new GF_Auto_Select_Chained_Selects();
