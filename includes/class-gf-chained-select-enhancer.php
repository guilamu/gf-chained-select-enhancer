<?php
/**
 * Main Chained Select Enhancer Class
 *
 * Enhances Gravity Forms Chained Selects with auto-select functionality,
 * column hiding options, and CSV export.
 *
 * @package GF_Chained_Select_Enhancer
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GFCS_Chained_Select_Enhancer
 *
 * Main plugin functionality class.
 */
class GFCS_Chained_Select_Enhancer
{

    /**
     * Plugin version.
     *
     * @var string
     */
    private $version;

    /**
     * Plugin URL.
     *
     * @var string
     */
    private $plugin_url;

    /**
     * Plugin path.
     *
     * @var string
     */
    private $plugin_path;

    /**
     * Constructor: Set up WordPress hooks.
     *
     * @param string $version     Plugin version.
     * @param string $plugin_url  Plugin URL.
     * @param string $plugin_path Plugin path.
     */
    public function __construct(string $version, string $plugin_url, string $plugin_path)
    {
        $this->version = $version;
        $this->plugin_url = $plugin_url;
        $this->plugin_path = $plugin_path;

        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks.
     *
     * @return void
     */
    private function init_hooks(): void
    {
        // Load text domain.
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));

        // Inject XLSX support script before the GF Chained Selects script
        add_filter('script_loader_tag', array($this, 'inject_xlsx_plupload_script'), 10, 3);

        // Admin hooks - use gform_enqueue_scripts for form editor.
        add_action('gform_field_standard_settings', array($this, 'add_field_settings'), 10, 2);
        add_action('gform_editor_js', array($this, 'editor_script'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Frontend hooks.
        add_filter('gform_field_content', array($this, 'add_auto_select_property'), 10, 5);
        add_filter('gform_chained_selects_input_choices', array($this, 'auto_select_only_choice'), 10, 3);
        add_filter('gform_pre_render', array($this, 'enqueue_field_css'), 10, 1);

        // AJAX handler.
        add_action('wp_ajax_gfcs_export_field_csv', array($this, 'handle_ajax_export'));
    }

    /**
     * Load plugin textdomain for translations.
     *
     * @return void
     */
    public function load_plugin_textdomain(): void
    {
        load_plugin_textdomain(
            'gf-chained-select-enhancer',
            false,
            dirname(plugin_basename($this->plugin_path . 'gf-chained-select-enhancer.php')) . '/languages/'
        );
    }

    /**
     * Inject inline script to add XLSX support to Plupload.
     *
     * Uses script_loader_tag filter to inject our wrapper script immediately
     * before the GF Chained Selects admin-form-editor.js script tag.
     * This guarantees our wrapper runs before the GF script creates its uploader.
     *
     * @param string $tag    The script tag HTML.
     * @param string $handle The script handle.
     * @param string $src    The script source URL.
     * @return string Modified script tag HTML.
     */
    public function inject_xlsx_plupload_script(string $tag, string $handle, string $src): string
    {
        // Only modify the GF Chained Selects admin form editor script
        if ('gform_chained_selects_admin_form_editor' !== $handle) {
            return $tag;
        }

        // Wrap Plupload.Uploader constructor to modify mime_types filter for chained selects
        $inline_script = <<<'JS'
<script type="text/javascript" id="gfcs-xlsx-plupload-wrapper">
(function() {
    if (!window.plupload || !window.plupload.Uploader) {
        return;
    }
    
    var OriginalUploader = window.plupload.Uploader;
    
    window.plupload.Uploader = function(options) {
        // Check if this is the chained selects uploader
        if (options && options.container) {
            var containerId = typeof options.container === 'string' 
                ? options.container 
                : (options.container.id || '');
            
            if (containerId === 'gfcs-container' && options.filters && options.filters.mime_types) {
                // Modify filters to allow xlsx in addition to csv
                options.filters.mime_types = [
                    { title: 'Spreadsheet files', extensions: 'csv,xlsx' }
                ];
            }
        }
        OriginalUploader.call(this, options);
    };
    
    window.plupload.Uploader.prototype = OriginalUploader.prototype;
    window.plupload.Uploader.prototype.constructor = window.plupload.Uploader;
})();
</script>
JS;

        // Prepend our wrapper script before the GF script tag
        return $inline_script . "\n" . $tag;
    }

    /**
     * Enqueue admin assets.
     *
     * @return void
     */
    public function enqueue_admin_assets(): void
    {
        wp_enqueue_style(
            'gfcs-admin',
            $this->plugin_url . 'assets/css/admin.css',
            array(),
            $this->version
        );

        wp_enqueue_script(
            'gfcs-admin',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            'gfcs-admin',
            'gfcsSettings',
            array(
                'hidden' => __('Hidden', 'gf-chained-select-enhancer'),
                'visible' => __('Visible', 'gf-chained-select-enhancer'),
                'nonce' => wp_create_nonce('gfcs_export_csv'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'selectFieldFirst' => __('Please select a chained select field first', 'gf-chained-select-enhancer'),
                'exporting' => __('Exporting...', 'gf-chained-select-enhancer'),
                'exportComplete' => __('Export complete', 'gf-chained-select-enhancer'),
                'exportFailed' => __('Export failed', 'gf-chained-select-enhancer'),
            )
        );
    }

    /**
     * Add custom field settings for chained select fields in the form editor.
     *
     * @param int $position Position in settings.
     * @param int $form_id  Form ID.
     * @return void
     */
    public function add_field_settings(int $position, int $form_id): void
    {
        if (25 !== $position) {
            return;
        }
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
            <button type="button" id="gfcs_export_csv_btn" class="button button-large" style="width: 100%; text-align: center;"
                onclick="if(typeof gfcsExportCurrentField==='function') gfcsExportCurrentField(event);">
                <?php esc_html_e('Export Choices', 'gf-chained-select-enhancer'); ?>
            </button>
            <span id="gfcs_export_status" style="display: block; text-align: center; margin-top: 5px;"></span>
        </li>
        <?php
    }

    /**
     * Add JavaScript to the form editor for field settings registration.
     *
     * @return void
     */
    public function editor_script(): void
    {
        ?>
        <script type='text/javascript'>
            // Register field settings with Gravity Forms editor
            // This must be inline as it needs to execute at the correct GF initialization time
            if (typeof fieldSettings !== 'undefined') {
                fieldSettings.chainedselect += ', .auto_select_setting, .hide_columns_setting, .full_width_setting, .csv_export_setting';
            }
        </script>
        <?php
    }

    /**
     * Add data attribute to field for auto-select functionality.
     *
     * @param string $field_content Field HTML content.
     * @param object $field         Field object.
     * @param mixed  $value         Field value.
     * @param int    $lead_id       Entry ID.
     * @param int    $form_id       Form ID.
     * @return string Modified field content.
     */
    public function add_auto_select_property($field_content, $field, $value, $lead_id, $form_id): string
    {
        $field_type = is_object($field) ? $field->type : '';

        if (('chainedselect' === $field_type || 'chained_select' === $field_type) && !empty($field->autoSelectOnly)) {
            $field_content = str_replace('<select', '<select data-auto-select-only="true"', $field_content);
        }

        return $field_content;
    }

    /**
     * Auto-select when only one choice is available.
     *
     * @param array  $choices Field choices.
     * @param array  $form    Form object.
     * @param object $field   Field object.
     * @return array Modified choices.
     */
    public function auto_select_only_choice($choices, $form, $field): array
    {
        if (!is_object($field) || empty($field->autoSelectOnly)) {
            return $choices;
        }

        if (is_array($choices) && 1 === count($choices)) {
            $choices[0]['isSelected'] = true;
        }

        return $choices;
    }

    /**
     * Enqueue CSS for field customizations when form is rendered.
     *
     * @param array $form Form object.
     * @return array Unmodified form object.
     */
    public function enqueue_field_css(array $form): array
    {
        static $css_added = array();

        if (!isset($form['fields']) || !is_array($form['fields'])) {
            return $form;
        }

        $css_rules = array();

        foreach ($form['fields'] as $field) {
            $field_type = is_object($field) ? $field->type : '';
            if ('chainedselect' !== $field_type && 'chained_select' !== $field_type) {
                continue;
            }

            $key = $form['id'] . '_' . $field->id;
            if (isset($css_added[$key])) {
                continue;
            }

            // Handle hidden columns.
            if (!empty($field->hideColumns)) {
                $hidden_indices = explode(',', $field->hideColumns);
                foreach ($hidden_indices as $index) {
                    $index = intval(trim($index));
                    $col_idx = $index + 1;
                    $css_rules[] = sprintf(
                        '#input_%d_%d_%d_container { display: none !important; }',
                        $form['id'],
                        $field->id,
                        $col_idx
                    );
                }
            }

            // Handle full width.
            if (!empty($field->fullWidth)) {
                $css_rules[] = sprintf(
                    '#field_%d_%d select { width: 100%% !important; min-width: 100%% !important; }',
                    $form['id'],
                    $field->id
                );
            }

            $css_added[$key] = true;
        }

        if (!empty($css_rules)) {
            $css = implode(' ', $css_rules);
            // Output CSS in footer to ensure it loads after form
            add_action('wp_footer', function() use ($css) {
                echo '<style type="text/css">' . $css . '</style>';
            }, 100);
        }

        return $form;
    }

    /**
     * Handle AJAX export request.
     *
     * @return void
     */
    public function handle_ajax_export(): void
    {
        // Capability check.
        if (!current_user_can('gravityforms_edit_forms') && !current_user_can('manage_options')) {
            wp_die(
                esc_html__('Access denied', 'gf-chained-select-enhancer'),
                esc_html__('Error', 'gf-chained-select-enhancer'),
                array('response' => 403)
            );
        }

        // Nonce verification.
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'gfcs_export_csv')) {
            wp_die(
                esc_html__('Security check failed', 'gf-chained-select-enhancer'),
                esc_html__('Error', 'gf-chained-select-enhancer'),
                array('response' => 403)
            );
        }

        // Input validation.
        $form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;
        $field_id = isset($_POST['field_id']) ? absint($_POST['field_id']) : 0;

        if (!$form_id || !$field_id) {
            wp_die(
                esc_html__('Invalid parameters', 'gf-chained-select-enhancer'),
                esc_html__('Error', 'gf-chained-select-enhancer'),
                array('response' => 400)
            );
        }

        // Generate and stream CSV.
        $this->stream_field_csv($form_id, $field_id);
    }

    /**
     * Stream field data as CSV directly to browser.
     *
     * @param int $form_id  Form ID.
     * @param int $field_id Field ID.
     * @return void
     */
    private function stream_field_csv(int $form_id, int $field_id): void
    {
        if (!class_exists('GFAPI')) {
            wp_die(
                esc_html__('Gravity Forms not available', 'gf-chained-select-enhancer'),
                esc_html__('Error', 'gf-chained-select-enhancer'),
                array('response' => 500)
            );
        }

        $form = GFAPI::get_form($form_id);
        if (!$form) {
            wp_die(
                esc_html__('Form not found', 'gf-chained-select-enhancer'),
                esc_html__('Error', 'gf-chained-select-enhancer'),
                array('response' => 404)
            );
        }

        $field = null;
        foreach ($form['fields'] as $f) {
            $ftype = is_object($f) ? $f->type : '';
            if ((int) $f->id === $field_id && ('chainedselect' === $ftype || 'chained_select' === $ftype)) {
                $field = $f;
                break;
            }
        }

        if (!$field) {
            wp_die(
                esc_html__('Chained select field not found', 'gf-chained-select-enhancer'),
                esc_html__('Error', 'gf-chained-select-enhancer'),
                array('response' => 404)
            );
        }

        $csv_content = array();

        // Headers from input labels.
        $headers = array();
        if (isset($field->inputs) && is_array($field->inputs)) {
            foreach ($field->inputs as $input) {
                $headers[] = isset($input['label']) ? $input['label'] : '';
            }
        }

        if (!empty($headers)) {
            $csv_content[] = $headers;
        }

        // Generate combinations.
        $choices = isset($field->choices) ? $field->choices : array();
        $rows = array();
        $this->generate_combinations($choices, array(), $rows);

        foreach ($rows as $row) {
            $csv_content[] = $row;
        }

        // Generate filename.
        $filename = sprintf(
            'chained_select_form_%d_field_%d_%s.csv',
            $form_id,
            $field_id,
            gmdate('Y-m-d_His')
        );

        // Stream directly to browser.
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $handle = fopen('php://output', 'w');
        if ($handle) {
            foreach ($csv_content as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }

        exit;
    }

    /**
     * Generate all possible combinations (Cartesian product) from chained select choices.
     *
     * @param array    $choices      The choices array from the field.
     * @param string[] $current_path Current path in the recursion.
     * @param array    $rows         Reference to the output rows array.
     * @return void
     */
    private function generate_combinations(array $choices, array $current_path, array &$rows): void
    {
        if (empty($choices)) {
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
}
