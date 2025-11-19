<?php
/**
 * Plugin Name: Chained Select Enhancer for Gravity Forms
 * Plugin URI: https://github.com/guilamu/gf-chained-select-enhancer
 * Description: Enhances Gravity Forms Chained Selects with auto-select functionality, column hiding options, and CSV export.
 * Version: 1.2
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
        add_action('wp_ajax_gfcs_export_field_csv', array($this, 'handle_ajax_export'));
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
            }
            .hide_columns_setting .gfcs-toggle-slider:before {
                position: absolute;
                content: "";
                height: 18px;
                width: 18px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: .3s;
                border-radius: 50%;
            }
            .hide_columns_setting .gfcs-toggle-switch input[type="checkbox"]:checked + .gfcs-toggle-slider {
                background-color: #dc3232;
            }
            .hide_columns_setting .gfcs-toggle-switch input[type="checkbox"]:checked + .gfcs-toggle-slider:before {
                transform: translateX(20px);
            }
            .hide_columns_setting .gfcs-toggle-label {
                font-size: 13px;
                color: #444;
                font-weight: normal;
                user-select: none;
                flex: 1;
            }
            .hide_columns_setting .gfcs-toggle-status {
                display: inline-block !important;
                visibility: visible !important;
                font-size: 11px;
                color: #666;
                margin-left: auto;
                padding-left: 10px;
                font-weight: 600;
                min-width: 60px;
                text-align: right;
            }
            .hide_columns_setting .gfcs-toggle-status.gfcs-hidden {
                display: inline-block !important;
                visibility: visible !important;
                color: #dc3232 !important;
            }
            .hide_columns_setting .gfcs-toggle-status.gfcs-visible {
                display: inline-block !important;
                visibility: visible !important;
                color: #22a753 !important;
            }
            .csv_export_setting {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
            }
            #gfcs_export_status {
                color: #22a753;
                font-size: 12px;
            }
        </style>
            <?php
        }
    }

    /**
     * Add custom field settings for chained select fields in the form editor
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
           <li class="csv_export_setting field_setting">
               <button type="button" id="gfcs_export_csv_btn" class="button button-large" style="width: 100%; text-align: center;" onclick="gfcsExportCurrentField(event);">
                   <?php esc_html_e('Export Choices', 'gf-chained-select-enhancer'); ?>
               </button>
               <span id="gfcs_export_status" style="display: block; text-align: center; margin-top: 5px;"></span>
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
            fieldSettings.chainedselect += ', .auto_select_setting, .hide_columns_setting, .full_width_setting, .csv_export_setting';
            
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
                var container = document.getElementById('gfcs_column_toggles');
                if (!container) return;
                
                container.innerHTML = '';
                
                var columnCount = gfcsCountColumns(field);
                if (columnCount === 0) {
                    container.innerHTML = '<p style="color: #666; font-style: italic;">No columns found</p>';
                    return;
                }
                
                var labels = gfcsGetColumnLabels(field);
                var hideColumns = field.hideColumns || '';
                var hiddenIndices = hideColumns ? hideColumns.split(',').map(function(i) { return parseInt(i); }) : [];
                
                for (var i = 0; i < columnCount; i++) {
                    var isHidden = hiddenIndices.indexOf(i) !== -1;
                    
                    var itemDiv = document.createElement('div');
                    itemDiv.className = 'gfcs-toggle-item';
                    
                    var toggleLabel = document.createElement('label');
                    toggleLabel.className = 'gfcs-toggle-switch';
                    
                    var checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.checked = isHidden;
                    checkbox.setAttribute('data-column-index', i);
                    checkbox.onchange = function() {
                        gfcsUpdateHideColumns();
                    };
                    
                    var slider = document.createElement('span');
                    slider.className = 'gfcs-toggle-slider';
                    
                    toggleLabel.appendChild(checkbox);
                    toggleLabel.appendChild(slider);
                    
                    var labelText = document.createElement('span');
                    labelText.className = 'gfcs-toggle-label';
                    labelText.textContent = labels[i];
                    
                    var status = document.createElement('span');
                    status.className = 'gfcs-toggle-status ' + (isHidden ? 'hidden' : 'visible');
                    status.textContent = isHidden ? gfcsLabels.hidden : gfcsLabels.visible;
                    
                    checkbox.onchange = function() {
                        var isChecked = this.checked;
                        var statusEl = this.parentElement.parentElement.querySelector('.gfcs-toggle-status');
                        if (statusEl) {
                            statusEl.textContent = isChecked ? gfcsLabels.hidden : gfcsLabels.visible;
                            statusEl.className = 'gfcs-toggle-status ' + (isChecked ? 'hidden' : 'visible');
                        }
                        gfcsUpdateHideColumns();
                    };
                    
                    itemDiv.appendChild(toggleLabel);
                    itemDiv.appendChild(labelText);
                    itemDiv.appendChild(status);
                    
                    container.appendChild(itemDiv);
                }
            }

            // Function to update hideColumns field property
            function gfcsUpdateHideColumns() {
                var checkboxes = document.querySelectorAll('#gfcs_column_toggles input[type="checkbox"]');
                var hiddenIndices = [];
                
                checkboxes.forEach(function(checkbox) {
                    if (checkbox.checked) {
                        var index = parseInt(checkbox.getAttribute('data-column-index'));
                        hiddenIndices.push(index);
                    }
                });
                
                var hideColumnsValue = hiddenIndices.join(',');
                document.getElementById('field_hide_columns').value = hideColumnsValue;
                SetFieldProperty('hideColumns', hideColumnsValue);
            }

            // Bind to the load field settings event
            jQuery(document).on('gform_load_field_settings', function(event, field, form) {
                if (field.type === 'chainedselect' || field.type === 'chained_select') {
                    jQuery('#field_auto_select').prop('checked', field.autoSelectOnly == true);
                    jQuery('#field_full_width').prop('checked', field.fullWidth == true);
                    jQuery('#field_hide_columns').val(field.hideColumns || '');
                    gfcsRenderColumnToggles(field);
                }
            });

            // Export current field to CSV
            function gfcsExportCurrentField(event) {
                event.preventDefault();
                var field = GetSelectedField();
                if (!field) {
                    alert('<?php esc_html_e("Please select a chained select field first", "gf-chained-select-enhancer"); ?>');
                    return;
                }
                
                var formId = form.id;
                var fieldId = field.id;
                var statusEl = document.getElementById('gfcs_export_status');
                
                statusEl.innerHTML = '<?php esc_html_e("Exporting...", "gf-chained-select-enhancer"); ?>';
                
                // Create form and submit
                var exportForm = document.createElement('form');
                exportForm.method = 'POST';
                exportForm.action = ajaxurl;
                exportForm.target = '_blank';
                
                var actionField = document.createElement('input');
                actionField.type = 'hidden';
                actionField.name = 'action';
                actionField.value = 'gfcs_export_field_csv';
                exportForm.appendChild(actionField);
                
                var formIdField = document.createElement('input');
                formIdField.type = 'hidden';
                formIdField.name = 'form_id';
                formIdField.value = formId;
                exportForm.appendChild(formIdField);
                
                var fieldIdField = document.createElement('input');
                fieldIdField.type = 'hidden';
                fieldIdField.name = 'field_id';
                fieldIdField.value = fieldId;
                exportForm.appendChild(fieldIdField);
                
                var nonceField = document.createElement('input');
                nonceField.type = 'hidden';
                nonceField.name = 'nonce';
                nonceField.value = '<?php echo wp_create_nonce("gfcs_export_csv"); ?>';
                exportForm.appendChild(nonceField);
                
                document.body.appendChild(exportForm);
                exportForm.submit();
                document.body.removeChild(exportForm);
                
                setTimeout(function() {
                    statusEl.innerHTML = '✓ <?php esc_html_e("Export complete", "gf-chained-select-enhancer"); ?>';
                    setTimeout(function() { statusEl.innerHTML = ''; }, 3000);
                }, 1000);
            }
        </script>
        <?php
    }

    /**
     * Add data attribute to field for auto-select functionality
     */
    public function add_auto_select_property($field_content, $field, $value, $lead_id, $form_id) {
        if (($field->type === 'chainedselect' || $field->type === 'chained_select') && !empty($field->autoSelectOnly)) {
            $field_content = str_replace('<select', '<select data-auto-select-only="true"', $field_content);
        }
        
        return $field_content;
    }

    /**
     * Auto-select when only one choice is available
     */
    public function auto_select_only_choice($choices, $form, $field) {
        if (!is_object($field) || empty($field->autoSelectOnly)) {
            return $choices;
        }
        
        if (is_array($choices) && count($choices) == 1) {
            $choices[0]['isSelected'] = true;
        }
        
        return $choices;
    }

    /**
     * Output custom CSS to hide specified columns and set full width
     */
    public function output_hide_columns_css() {
        if (!class_exists('GFAPI')) {
            return;
        }
        
        $forms = GFAPI::get_forms();
        $css_rules = array();
        
        foreach ($forms as $form) {
            if (!isset($form['fields']) || !is_array($form['fields'])) {
                continue;
            }

            foreach ($form['fields'] as $field) {
                if ($field->type !== 'chainedselect' && $field->type !== 'chained_select') {
                    continue;
                }

                // Handle Hide Columns
                if (!empty($field->hideColumns)) {
                    $hidden_indices = explode(',', $field->hideColumns);
                    foreach ($hidden_indices as $index) {
                        $index = intval(trim($index));
                        $col_idx = $index + 1;
                        
                        // Target the container to hide label + input
                        $css_rules[] = sprintf(
                            '#input_%d_%d_%d_container { display: none !important; }',
                            $form['id'],
                            $field->id,
                            $col_idx
                        );
                    }
                }
                
                // Handle Full Width
                if (!empty($field->fullWidth)) {
                    $css_rules[] = sprintf(
                        '#field_%d_%d select { width: 100%% !important; min-width: 100%% !important; }',
                        $form['id'],
                        $field->id
                    );
                }
            }
        }
        
        if (!empty($css_rules)) {
            echo '<style type="text/css">' . implode(' ', $css_rules) . '</style>';
        }
    }

    /**
     * Handle AJAX export request
     */
    public function handle_ajax_export() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied');
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'gfcs_export_csv')) {
            wp_die('Nonce verification failed');
        }
        
        $form_id = absint($_POST['form_id']);
        $field_id = absint($_POST['field_id']);
        
        $export = $this->export_field_to_csv($form_id, $field_id);
        
        if ($export) {
            wp_redirect($export['url']);
            exit;
        } else {
            wp_die('Export failed');
        }
    }

    /**
     * Generate all possible combinations (Cartesian product)
     */
    private function generate_combinations($choices, $current_path, &$rows) {
        if (!is_array($choices) || empty($choices)) {
            return;
        }
        
        foreach ($choices as $choice) {
            $text = isset($choice['text']) ? $choice['text'] : '';
            $new_path = array_merge($current_path, array($text));
            $sub_choices = isset($choice['choices']) ? $choice['choices'] : array();
            
            if (!empty($sub_choices) && is_array($sub_choices)) {
                $this->generate_combinations($sub_choices, $new_path, $rows);
            } else {
                $rows[] = $new_path;
            }
        }
    }

    /**
     * Export field data to CSV
     */
    private function export_field_to_csv($form_id, $field_id) {
        if (!class_exists('GFAPI')) {
            return false;
        }
        
        $form = GFAPI::get_form($form_id);
        if (!$form) {
            return false;
        }
        
        $field = null;
        foreach ($form['fields'] as $f) {
            if ($f->id == $field_id && ($f->type === 'chainedselect' || $f->type === 'chained_select')) {
                $field = $f;
                break;
            }
        }
        
        if (!$field) {
            return false;
        }
        
        $csv_content = array();
        
        // Headers from input labels
        $headers = array();
        if (isset($field->inputs) && is_array($field->inputs)) {
            foreach ($field->inputs as $input) {
                $headers[] = $input['label'];
            }
        }
        
        if (!empty($headers)) {
            $csv_content[] = $headers;
        }
        
        // Generate combinations
        $choices = isset($field->choices) ? $field->choices : array();
        $rows = array();
        $this->generate_combinations($choices, array(), $rows);
        
        foreach ($rows as $row) {
            $csv_content[] = $row;
        }
        
        // Generate CSV file
        $filename = 'chained_select_form_' . $form_id . '_field_' . $field_id . '_' . date('Y-m-d_His') . '.csv';
        $upload_dir = wp_upload_dir();
        
        if (!is_array($upload_dir) || empty($upload_dir['path'])) {
            return false;
        }
        
        $filepath = $upload_dir['path'] . '/' . $filename;
        $handle = fopen($filepath, 'w');
        
        if (!$handle) {
            return false;
        }
        
        foreach ($csv_content as $row) {
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        
        return array(
            'filename' => $filename,
            'url' => $upload_dir['url'] . '/' . $filename,
            'path' => $filepath
        );
    }
}

// Initialize the plugin
new GF_Auto_Select_Chained_Selects();
