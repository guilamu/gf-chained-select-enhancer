<?php
/**
 * Plugin Name: Chained Select Enhancer for Gravity Forms
 * Plugin URI: https://github.com/guilamu/gf-chained-select-enhancer
 * Description: Enhances Gravity Forms Chained Selects with auto-select functionality and column hiding options.
 * Version: 1.10
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
        add_action('admin_head', array($this, 'add_toggle_switch_styles'));
    }

    /**
     * Load plugin textdomain for translations
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('gf-chained-select-enhancer', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    /**
     * Add toggle switch styles to admin
     */
    public function add_toggle_switch_styles() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'gf_edit_forms') !== false) {
            ?>
            <style type="text/css">
                .hide_columns_setting .gfcs-toggle-switches {
                    margin-top: 10px;
                    clear: both;
                }
                .hide_columns_setting .gfcs-toggle-item {
                    display: flex !important;
                    align-items: center;
                    margin-bottom: 8px;
                    padding: 6px 0;
                    width: 100%;
                }
                .hide_columns_setting .gfcs-toggle-switch {
                    position: relative;
                    display: inline-block !important;
                    width: 44px !important;
                    height: 24px !important;
                    margin-right: 10px;
                    flex-shrink: 0;
                    vertical-align: middle;
                }
                .hide_columns_setting .gfcs-toggle-switch input[type="checkbox"] {
                    opacity: 0 !important;
                    width: 0 !important;
                    height: 0 !important;
                    position: absolute !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }
                .hide_columns_setting .gfcs-toggle-slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #22a753;
                    transition: .3s;
                    border-radius: 24px;
                    width: 44px;
                    height: 24px;
                }
                .hide_columns_setting .gfcs-toggle-slider:before {
                    position: absolute;
                    content: "";
                    height: 18px;
                    width: 18px;
                    left: 23px;
                    bottom: 3px;
                    background-color: white;
                    transition: .3s;
                    border-radius: 50%;
                }
                .hide_columns_setting .gfcs-toggle-switch input[type="checkbox"]:checked + .gfcs-toggle-slider {
                    background-color: #ccc;
                }
                .hide_columns_setting .gfcs-toggle-switch input[type="checkbox"]:focus + .gfcs-toggle-slider {
                    box-shadow: 0 0 1px #22a753;
                }
                .hide_columns_setting .gfcs-toggle-switch input[type="checkbox"]:checked + .gfcs-toggle-slider:before {
                    transform: translateX(-20px);
                }
                .hide_columns_setting .gfcs-toggle-label {
                    font-size: 13px;
                    color: #333;
                    cursor: pointer;
                    user-select: none;
                    flex: 1;
                    line-height: 24px;
                }
                .hide_columns_setting .gfcs-no-columns {
                    color: #666;
                    font-style: italic;
                    padding: 8px 0;
                }
                .hide_columns_setting .gfcs-help-text {
                    color: #666;
                    font-size: 12px;
                    margin-top: 5px;
                    margin-bottom: 8px;
                    font-style: italic;
                }
            </style>
            <?php
        }
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
                <label for="field_hide_columns" class="inline" style="display: block; margin-bottom: 5px;">
                    <?php esc_html_e('Hide Columns', 'gf-chained-select-enhancer'); ?>
                </label>
                <div id="gfcs_column_toggles" class="gfcs-toggle-switches"></div>
                <input type="hidden" id="field_hide_columns" onchange="SetFieldProperty('hideColumns', this.value);" />
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
            // Translation strings for JavaScript
            var gfcsLabels = {
                hidden: '<?php esc_html_e("Hidden", "gf-chained-select-enhancer"); ?>',
                visible: '<?php esc_html_e("Visible", "gf-chained-select-enhancer"); ?>'
            };

            // Add new field settings to chained select fields
            fieldSettings.chainedselect += ', .auto_select_setting, .hide_columns_setting, .full_width_setting';
            
            // Function to count columns in chained select
            function gfcsCountColumns(field) {
                if (!field || !field.inputs || !Array.isArray(field.inputs)) {
                    return 0;
                }
                return field.inputs.length;
            }

            // Function to get column labels
            function gfcsGetColumnLabels(field) {
                var labels = [];
                if (field && field.inputs && Array.isArray(field.inputs)) {
                    field.inputs.forEach(function(input, index) {
                        var label = input.label || 'Column ' + (index + 1);
                        labels.push(label);
                    });
                }
                return labels;
            }

            // Function to render toggle switches
            function gfcsRenderColumnToggles(field) {
                var container = jQuery('#gfcs_column_toggles');
                container.empty();
                
                var columnCount = gfcsCountColumns(field);
                
                if (columnCount === 0) {
                    container.html('<div class="gfcs-no-columns">' + 
                        <?php echo json_encode(__('No columns detected. Add choices to see column toggles.', 'gf-chained-select-enhancer')); ?> + 
                        '</div>');
                    return;
                }

                var columnLabels = gfcsGetColumnLabels(field);
                var hiddenColumns = gfcsGetHiddenColumns();

                for (var i = 1; i <= columnCount; i++) {
                    var columnLabel = columnLabels[i - 1] || ('Column ' + i);
                    var isHidden = hiddenColumns.indexOf(i) !== -1;
                    
                    var toggleHtml = 
                        '<div class="gfcs-toggle-item">' +
                            '<label class="gfcs-toggle-switch">' +
                                '<input type="checkbox" ' +
                                    'data-column="' + i + '" ' +
                                    'onchange="gfcsToggleColumn(this)" ' +
                                    (isHidden ? 'checked' : '') + '>' +
                                '<span class="gfcs-toggle-slider"></span>' +
                            '</label>' +
                            '<span class="gfcs-toggle-label" onclick="gfcsToggleLabelClick(this)">' +
                                columnLabel + ' <span style="color: #999; font-size: 11px;">(' + 
                                (isHidden ? gfcsLabels.hidden : gfcsLabels.visible) + ')</span>' +
                            '</span>' +
                        '</div>';
                    
                    container.append(toggleHtml);
                }
            }

            // Function to get hidden columns from field property
            function gfcsGetHiddenColumns() {
                var hideColumnsValue = jQuery('#field_hide_columns').val();
                if (!hideColumnsValue) {
                    return [];
                }
                return hideColumnsValue.split(',').map(function(col) {
                    return parseInt(col.trim());
                }).filter(function(col) {
                    return !isNaN(col);
                });
            }

            // Function to update hidden columns field
            function gfcsUpdateHiddenColumns() {
                var hiddenColumns = [];
                jQuery('#gfcs_column_toggles input[type="checkbox"]:checked').each(function() {
                    hiddenColumns.push(jQuery(this).data('column'));
                });
                
                var hideColumnsValue = hiddenColumns.join(',');
                jQuery('#field_hide_columns').val(hideColumnsValue).trigger('change');
            }

            // Toggle column visibility
            window.gfcsToggleColumn = function(checkbox) {
                gfcsUpdateHiddenColumns();
            };

            // Toggle when clicking label
            window.gfcsToggleLabelClick = function(label) {
                var checkbox = jQuery(label).siblings('.gfcs-toggle-switch').find('input[type="checkbox"]');
                checkbox.prop('checked', !checkbox.prop('checked'));
                gfcsUpdateHiddenColumns();
            };

            // Set field properties when loading field settings
            jQuery(document).on('gform_load_field_settings', function(event, field, form) {
                jQuery('#field_auto_select').prop('checked', field.autoSelectOnly == true);
                jQuery('#field_hide_columns').val(field.hideColumns || '');
                jQuery('#field_full_width').prop('checked', field.fullWidth == true);
                
                // Render column toggles for chained select fields
                if (field.type === 'chainedselect') {
                    gfcsRenderColumnToggles(field);
                }
            });

            // Re-render toggles when field is updated (e.g., when choices are modified)
            jQuery(document).on('gform_field_updated', function(event, field, form) {
                if (field.type === 'chainedselect') {
                    gfcsRenderColumnToggles(field);
                }
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
                                    $css .= "#input_{$form['id']}_{$field->id}_{$column}_container { 
                                    display: none !important;
                                    height: 0 !important;
                                    margin: 0 !important;
                                    padding: 0 !important;
                                    overflow: hidden !important;
                                }\n";
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
