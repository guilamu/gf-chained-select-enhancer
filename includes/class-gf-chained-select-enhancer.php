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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Frontend hooks.
        add_filter('gform_field_content', array($this, 'add_auto_select_property'), 10, 5);
        add_filter('gform_chained_selects_input_choices', array($this, 'auto_select_only_choice'), 10, 7);
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
        $admin_css_version = file_exists($this->plugin_path . 'assets/css/admin.css')
            ? (string) filemtime($this->plugin_path . 'assets/css/admin.css')
            : $this->version;
        $admin_js_version = file_exists($this->plugin_path . 'assets/js/admin.js')
            ? (string) filemtime($this->plugin_path . 'assets/js/admin.js')
            : $this->version;

        wp_enqueue_style(
            'gfcs-admin',
            $this->plugin_url . 'assets/css/admin.css',
            array(),
            $admin_css_version
        );

        wp_enqueue_script(
            'gfcs-admin',
            $this->plugin_url . 'assets/js/admin.js',
            array('jquery'),
            $admin_js_version,
            true
        );

        // Only expose nonce and export settings on GF form editor pages
        $localize_data = array(
            'hidden' => __('Hidden', 'gf-chained-select-enhancer'),
            'visible' => __('Visible', 'gf-chained-select-enhancer'),
            'leftOfField' => __('To the left of the field', 'gf-chained-select-enhancer'),
            'noColumnsFound' => __('No columns found', 'gf-chained-select-enhancer'),
            'sectionBeforeColumn' => __('Section before this column', 'gf-chained-select-enhancer'),
            'sectionTitlePlaceholder' => __('Leave empty if no section starts here', 'gf-chained-select-enhancer'),
            'columnSingular' => __('column', 'gf-chained-select-enhancer'),
            'columnPlural' => __('columns', 'gf-chained-select-enhancer'),
            'hiddenColumnSingular' => __('hidden column', 'gf-chained-select-enhancer'),
            'hiddenColumnPlural' => __('hidden columns', 'gf-chained-select-enhancer'),
            'untitledSection' => __('Section', 'gf-chained-select-enhancer'),
            'newSectionTitle' => __('New Section', 'gf-chained-select-enhancer'),
            'addSection' => __('Add a section', 'gf-chained-select-enhancer'),
            'dropColumnsHere' => __('Drop columns here', 'gf-chained-select-enhancer'),
            'toggleSideBySide' => __('Display section next to the following section', 'gf-chained-select-enhancer'),
            'sideBySide' => __('Side by side', 'gf-chained-select-enhancer'),
            'renameSection' => __('Rename section', 'gf-chained-select-enhancer'),
            'deleteEmptySection' => __('Delete empty section', 'gf-chained-select-enhancer'),
            'collapseSection' => __('Collapse section', 'gf-chained-select-enhancer'),
            'expandSection' => __('Expand section', 'gf-chained-select-enhancer'),
            'reorderColumn' => __('Reorder column', 'gf-chained-select-enhancer'),
            'invalidOrderAfter' => __('Line "%1$s" should be after "%2$s".', 'gf-chained-select-enhancer'),
            'invalidOrderBefore' => __('Line "%1$s" should be before "%2$s".', 'gf-chained-select-enhancer'),
            'currentSourceFile' => __('Current source file', 'gf-chained-select-enhancer'),
            'replaceSourceFileHint' => __('Select a file below to replace it.', 'gf-chained-select-enhancer'),
            'selectFieldFirst' => __('Please select a chained select field first', 'gf-chained-select-enhancer'),
            'exporting' => __('Exporting...', 'gf-chained-select-enhancer'),
            'exportComplete' => __('Export complete', 'gf-chained-select-enhancer'),
            'exportFailed' => __('Export failed', 'gf-chained-select-enhancer'),
        );

        // Only include nonce and ajaxurl on GF form editor pages
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_gf_page = $screen && (strpos($screen->id, 'gf_edit_forms') !== false || strpos($screen->id, 'gravityforms') !== false);
        if ($is_gf_page || (isset($_GET['page']) && strpos(sanitize_text_field(wp_unslash($_GET['page'])), 'gf_') === 0)) {
            $localize_data['nonce'] = wp_create_nonce('gfcs_export_csv');
            $localize_data['ajaxurl'] = admin_url('admin-ajax.php');
        }

        wp_localize_script(
            'gfcs-admin',
            'gfcsSettings',
            $localize_data
        );
    }

    /**
     * Enqueue frontend assets.
     *
     * @return void
     */
    public function enqueue_frontend_assets(): void
    {
        if (is_admin()) {
            return;
        }

        $frontend_css_version = file_exists($this->plugin_path . 'assets/css/frontend.css')
            ? (string) filemtime($this->plugin_path . 'assets/css/frontend.css')
            : $this->version;
        $frontend_js_version = file_exists($this->plugin_path . 'assets/js/frontend.js')
            ? (string) filemtime($this->plugin_path . 'assets/js/frontend.js')
            : $this->version;

        wp_enqueue_style(
            'gfcs-frontend',
            $this->plugin_url . 'assets/css/frontend.css',
            array(),
            $frontend_css_version
        );

        wp_enqueue_script(
            'gfcs-frontend-script',
            $this->plugin_url . 'assets/js/frontend.js',
            array('jquery'),
            $frontend_js_version,
            true
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
        <li class="auto_select_single_readonly_setting field_setting">
            <input type="checkbox" id="field_auto_select_single_readonly" onclick="SetFieldProperty('autoSelectSingleReadOnly', this.checked);" />
            <label for="field_auto_select_single_readonly" class="inline">
                <?php esc_html_e('Make the field read-only when its only available option is auto-selected', 'gf-chained-select-enhancer'); ?>
            </label>
        </li>
        <li class="full_width_setting field_setting">
            <input type="checkbox" id="field_full_width" onclick="SetFieldProperty('fullWidth', this.checked);" />
            <label for="field_full_width" class="inline">
                <?php esc_html_e('Make vertical chained select full width', 'gf-chained-select-enhancer'); ?>
            </label>
        </li>
        <li class="gfcs_sub_label_width_setting field_setting">
            <label for="field_gfcs_sub_label_width" class="section_label">
                <?php esc_html_e('Left sub-label width', 'gf-chained-select-enhancer'); ?>
            </label>
            <select id="field_gfcs_sub_label_width" onchange="SetFieldProperty('gfcsSubLabelRatio', this.value);">
                <option value="1-2"><?php esc_html_e('1/3 label, 2/3 field', 'gf-chained-select-enhancer'); ?></option>
                <option value="1-1"><?php esc_html_e('1/2 label, 1/2 field', 'gf-chained-select-enhancer'); ?></option>
                <option value="2-1"><?php esc_html_e('2/3 label, 1/3 field', 'gf-chained-select-enhancer'); ?></option>
            </select>
        </li>
        <li class="hide_columns_setting field_setting">
            <label for="field_hide_columns" class="inline" style="display: block; margin-bottom: 5px;">
                <?php esc_html_e('Manage Columns', 'gf-chained-select-enhancer'); ?>
            </label>
            <div id="gfcs_column_toggles" class="gfcs-toggle-switches"></div>
            <input type="hidden" id="field_hide_columns" onchange="SetFieldProperty('hideColumns', this.value);" />
            <input type="hidden" id="field_column_sections" onchange="SetFieldProperty('columnSections', this.value);" />
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
        $left_of_field = esc_html__('To the left of the field', 'gf-chained-select-enhancer');
        ?>
        <script type='text/javascript'>
            // Register field settings with Gravity Forms editor
            // This must be inline as it needs to execute at the correct GF initialization time
            if (typeof fieldSettings !== 'undefined') {
                fieldSettings.chainedselect += ', .auto_select_setting, .auto_select_single_readonly_setting, .hide_columns_setting, .full_width_setting, .gfcs_sub_label_width_setting, .csv_export_setting';
            }

            (function($) {
                function gfcsEnsureLeftSubLabelOption() {
                    var $select = $('#field_sub_label_placement');

                    if (!$select.length || $select.find('option[value="left"]').length) {
                        return;
                    }

                    $('<option />', {
                        value: 'left',
                        text: <?php echo wp_json_encode($left_of_field); ?>
                    }).appendTo($select);
                }

                function gfcsPositionSubLabelWidthSetting() {
                    var $setting = $('.gfcs_sub_label_width_setting');
                    var $anchor = $('.sub_label_placement_setting');

                    if ($setting.length && $anchor.length) {
                        $setting.insertAfter($anchor.first());
                    }
                }

                $(function() {
                    gfcsEnsureLeftSubLabelOption();
                    gfcsPositionSubLabelWidthSetting();
                });

                $(document).on('gform_load_field_settings', function(event, field) {
                    if (!field || (field.type !== 'chainedselect' && field.type !== 'chained_select')) {
                        return;
                    }

                    gfcsEnsureLeftSubLabelOption();
                    gfcsPositionSubLabelWidthSetting();

                    if (field.subLabelPlacement === 'left') {
                        $('#field_sub_label_placement').val('left');
                    }
                });
            })(jQuery);
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

        if ('chainedselect' !== $field_type && 'chained_select' !== $field_type) {
            return $field_content;
        }

        if (!empty($field->autoSelectOnly)) {
            $field_content = str_replace('<select', '<select data-auto-select-only="true"', $field_content);

            if (!empty($field->autoSelectSingleReadOnly)) {
                $field_content = str_replace('<select', '<select data-gfcs-single-option-readonly="true"', $field_content);
            }
        }

        if ($this->is_left_sub_label_placement($field, (int) $form_id)) {
            $field_content = $this->add_chained_select_container_class($field_content, 'gfcs-sub-label-left');

            $ratio_class = $this->get_sub_label_width_ratio_class($field);
            if ('' !== $ratio_class) {
                $field_content = $this->add_chained_select_container_class($field_content, $ratio_class);
            }
        }

        $field_content = $this->inject_column_sections_markup($field_content, $field, (int) $form_id);

        return $field_content;
    }

    /**
     * Determine whether the field should render sub-labels on the left.
     *
     * The original Chained Selects plugin resolves this from either the field
     * setting or the form default. The enhancer mirrors that logic here so the
     * frontend no longer depends on a patched renderer in the source plugin.
     *
     * @param object $field   Field object.
     * @param int    $form_id Form ID.
     * @return bool
     */
    private function is_left_sub_label_placement($field, int $form_id): bool
    {
        static $form_sub_label_placements = array();

        if (!is_object($field)) {
            return false;
        }

        $field_sub_label_placement = isset($field->subLabelPlacement)
            ? (string) $field->subLabelPlacement
            : '';

        if ('left' === $field_sub_label_placement) {
            return true;
        }

        if ('' !== $field_sub_label_placement || $form_id <= 0) {
            return false;
        }

        if (!array_key_exists($form_id, $form_sub_label_placements)) {
            $form_sub_label_placements[$form_id] = '';

            if (class_exists('GFAPI')) {
                $form = call_user_func(array('GFAPI', 'get_form'), $form_id);

                if (is_array($form) && isset($form['subLabelPlacement'])) {
                    $form_sub_label_placements[$form_id] = (string) $form['subLabelPlacement'];
                }
            }
        }

        return 'left' === $form_sub_label_placements[$form_id];
    }

    /**
     * Add a CSS class to the chained select container.
     *
     * @param string $field_content Field HTML content.
     * @param string $class_name    CSS class to inject.
     * @return string
     */
    private function add_chained_select_container_class(string $field_content, string $class_name): string
    {
        if ('' === $class_name || false !== strpos($field_content, $class_name)) {
            return $field_content;
        }

        $updated_content = preg_replace(
            '/(<div[^>]*class=)(["\'])([^"\']*\bginput_chained_selects_container\b[^"\']*)(["\'])/i',
            '$1$2$3 ' . $class_name . '$4',
            $field_content,
            1
        );

        return is_string($updated_content) ? $updated_content : $field_content;
    }

    /**
     * Get the normalized left sub-label width ratio.
     *
     * @param object $field Field object.
     * @return string
     */
    private function get_sub_label_width_ratio($field): string
    {
        if (!is_object($field) || !isset($field->gfcsSubLabelRatio)) {
            return '1-2';
        }

        $ratio = sanitize_key((string) $field->gfcsSubLabelRatio);

        if (in_array($ratio, array('1-2', '1-1', '2-1'), true)) {
            return $ratio;
        }

        return '1-2';
    }

    /**
     * Resolve the CSS class for the configured left sub-label width.
     *
     * @param object $field Field object.
     * @return string
     */
    private function get_sub_label_width_ratio_class($field): string
    {
        switch ($this->get_sub_label_width_ratio($field)) {
            case '1-1':
                return 'gfcs-sub-label-ratio-half';

            case '2-1':
                return 'gfcs-sub-label-ratio-label-wide';

            default:
                return '';
        }
    }

    /**
     * Parse hidden column indices from field settings.
     *
     * @param object $field Field object.
     * @return int[]
     */
    private function get_hidden_column_indices($field): array
    {
        if (!is_object($field) || empty($field->hideColumns) || !is_string($field->hideColumns)) {
            return array();
        }

        $hidden_indices = array();

        foreach (explode(',', $field->hideColumns) as $index) {
            $index = trim($index);
            if ($index === '' || !is_numeric($index)) {
                continue;
            }

            $hidden_indices[] = (int) $index;
        }

        return array_values(array_unique($hidden_indices));
    }

    /**
     * Convert legacy index-based section titles into grouped section data.
     *
     * @param array<int, string> $sections Legacy section titles keyed by input index.
     * @param string[]           $input_ids Field input IDs in their native order.
     * @return array<int, array<string, mixed>>
     */
    private function convert_legacy_column_sections_to_groups(array $sections, array $input_ids): array
    {
        if (empty($sections) || empty($input_ids)) {
            return array();
        }

        ksort($sections);

        $groups = array();
        $section_starts = array_keys($sections);
        $input_count = count($input_ids);

        if ($section_starts[0] > 0) {
            $groups[] = array(
                'id' => uniqid('gfcssection', false),
                'title' => '',
                'columnIds' => array_slice($input_ids, 0, $section_starts[0]),
                'pairWithNext' => false,
            );
        }

        foreach ($section_starts as $position => $start_index) {
            if ($start_index >= $input_count) {
                continue;
            }

            $next_start = $position + 1 < count($section_starts) ? $section_starts[$position + 1] : $input_count;

            $groups[] = array(
                'id' => uniqid('gfcssection', false),
                'title' => sanitize_text_field((string) $sections[$start_index]),
                'columnIds' => array_slice($input_ids, $start_index, $next_start - $start_index),
                'pairWithNext' => false,
            );
        }

        return $groups;
    }

    /**
     * Ensure section pairings remain valid and non-overlapping.
     *
     * @param array<int, array<string, mixed>> $groups Grouped section data.
     * @return array<int, array<string, mixed>>
     */
    private function normalize_section_group_pairings(array $groups): array
    {
        $normalized_groups = array();
        $previous_owns_pair = false;
        $group_count = count($groups);

        foreach ($groups as $index => $group) {
            if (!is_array($group)) {
                continue;
            }

            $column_ids = isset($group['columnIds']) && is_array($group['columnIds']) ? $group['columnIds'] : array();
            $pair_with_next = false;

            if ($previous_owns_pair) {
                $previous_owns_pair = false;
            } else {
                $pair_with_next = !empty($group['pairWithNext']) && $index < $group_count - 1;
                $previous_owns_pair = $pair_with_next;
            }

            $normalized_groups[] = array(
                'id' => isset($group['id']) ? sanitize_key((string) $group['id']) : uniqid('gfcssection', false),
                'title' => isset($group['title']) ? sanitize_text_field((string) $group['title']) : '',
                'columnIds' => array_values(array_filter(array_map('strval', $column_ids), static function ($column_id) {
                    return '' !== trim($column_id);
                })),
                'pairWithNext' => $pair_with_next,
            );
        }

        return $normalized_groups;
    }

    /**
     * Parse grouped column section data from field settings.
     *
     * @param object $field Field object.
     * @return array<int, array<string, mixed>>
     */
    private function get_column_section_groups($field): array
    {
        if (!is_object($field) || empty($field->inputs) || !is_array($field->inputs)) {
            return array();
        }

        $input_ids = array();
        foreach ($field->inputs as $input) {
            if (is_array($input) && isset($input['id'])) {
                $input_ids[] = (string) $input['id'];
            }
        }

        if (empty($input_ids)) {
            return array();
        }

        $raw_sections = empty($field->columnSections) ? null : $field->columnSections;
        $decoded = is_array($raw_sections) ? $raw_sections : json_decode((string) $raw_sections, true);
        $groups = array();

        if (is_array($decoded)) {
            if (isset($decoded['groups']) && is_array($decoded['groups'])) {
                $decoded = $decoded['groups'];
            }

            $is_sequential_array = empty($decoded) || array_keys($decoded) === range(0, count($decoded) - 1);

            if ($is_sequential_array) {
                $input_lookup = array_fill_keys($input_ids, true);
                $used_lookup = array();

                foreach ($decoded as $group) {
                    if (!is_array($group)) {
                        continue;
                    }

                    $column_ids = array();
                    $raw_column_ids = isset($group['columnIds']) && is_array($group['columnIds']) ? $group['columnIds'] : array();

                    foreach ($raw_column_ids as $column_id) {
                        $column_id = (string) $column_id;

                        if ($column_id === '' || !isset($input_lookup[$column_id]) || isset($used_lookup[$column_id])) {
                            continue;
                        }

                        $column_ids[] = $column_id;
                        $used_lookup[$column_id] = true;
                    }

                    $groups[] = array(
                        'id' => isset($group['id']) ? sanitize_key((string) $group['id']) : uniqid('gfcssection', false),
                        'title' => isset($group['title']) ? sanitize_text_field((string) $group['title']) : '',
                        'columnIds' => $column_ids,
                        'pairWithNext' => !empty($group['pairWithNext']),
                    );
                }
            } else {
                $legacy_sections = array();

                foreach ($decoded as $index => $title) {
                    if (!is_numeric($index)) {
                        continue;
                    }

                    $index = (int) $index;
                    $title = sanitize_text_field((string) $title);

                    if ($index < 0 || $title === '') {
                        continue;
                    }

                    $legacy_sections[$index] = $title;
                }

                $groups = $this->convert_legacy_column_sections_to_groups($legacy_sections, $input_ids);
            }
        }

        $used_lookup = array();
        foreach ($groups as $group) {
            if (!isset($group['columnIds']) || !is_array($group['columnIds'])) {
                continue;
            }

            foreach ($group['columnIds'] as $column_id) {
                $used_lookup[(string) $column_id] = true;
            }
        }

        $unassigned = array();
        foreach ($input_ids as $input_id) {
            if (!isset($used_lookup[$input_id])) {
                $unassigned[] = $input_id;
            }
        }

        if (empty($groups)) {
            $groups[] = array(
                'id' => uniqid('gfcssection', false),
                'title' => '',
                'columnIds' => $input_ids,
                'pairWithNext' => false,
            );
        } elseif (!empty($unassigned)) {
            $groups[] = array(
                'id' => uniqid('gfcssection', false),
                'title' => '',
                'columnIds' => $unassigned,
                'pairWithNext' => false,
            );
        }

        return $this->normalize_section_group_pairings($groups);
    }

    /**
     * Get section groups ready for frontend rendering.
     *
     * @param object $field Field object.
     * @return array<int, array<string, mixed>>
     */
    private function get_renderable_section_groups($field): array
    {
        if (!is_object($field) || empty($field->inputs) || !is_array($field->inputs)) {
            return array();
        }

        $groups = $this->get_column_section_groups($field);
        if (empty($groups)) {
            return array();
        }

        $hidden_lookup = array_fill_keys($this->get_hidden_column_indices($field), true);
        $input_index_by_id = array();

        foreach ($field->inputs as $index => $input) {
            if (is_array($input) && isset($input['id'])) {
                $input_index_by_id[(string) $input['id']] = $index;
            }
        }

        $renderable_groups = array();

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $column_ids = array();
            $raw_column_ids = isset($group['columnIds']) && is_array($group['columnIds']) ? $group['columnIds'] : array();

            foreach ($raw_column_ids as $column_id) {
                $column_id = (string) $column_id;

                if (!isset($input_index_by_id[$column_id])) {
                    continue;
                }

                if (isset($hidden_lookup[$input_index_by_id[$column_id]])) {
                    continue;
                }

                $column_ids[] = $column_id;
            }

            if (empty($column_ids)) {
                continue;
            }

            $renderable_groups[] = array(
                'id' => isset($group['id']) ? sanitize_key((string) $group['id']) : uniqid('gfcssection', false),
                'title' => isset($group['title']) ? sanitize_text_field((string) $group['title']) : '',
                'columnIds' => $column_ids,
                'pairWithNext' => !empty($group['pairWithNext']),
            );
        }

        return $this->normalize_section_group_pairings($renderable_groups);
    }

    /**
     * Get the inner HTML of a DOM node.
     *
     * @param \DOMNode $node DOM node.
     * @return string
     */
    private function get_dom_inner_html(\DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child_node) {
            $html .= $node->ownerDocument->saveHTML($child_node);
        }

        return $html;
    }

    /**
     * Rebuild the chained select container with grouped section wrappers.
     *
     * @param string                              $field_content Field markup.
     * @param object                              $field         Field object.
     * @param int                                 $form_id       Form ID.
     * @param array<int, array<string, mixed>>    $groups        Renderable section groups.
     * @return string|null
     */
    private function rebuild_column_sections_markup(string $field_content, $field, int $form_id, array $groups): ?string
    {
        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            return null;
        }

        $previous_error_state = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $load_options = 0;

        if (defined('LIBXML_HTML_NOIMPLIED')) {
            $load_options |= LIBXML_HTML_NOIMPLIED;
        }

        if (defined('LIBXML_HTML_NODEFDTD')) {
            $load_options |= LIBXML_HTML_NODEFDTD;
        }

        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?><div id="gfcs-root">' . $field_content . '</div>', $load_options);
        if (!$loaded) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous_error_state);

            return null;
        }

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//*[@id="gfcs-root"]')->item(0);
        $container = $xpath->query('.//*[@id="' . sprintf('input_%d_%d', $form_id, $field->id) . '"]', $root)->item(0);

        if (!$root instanceof DOMElement || !$container instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous_error_state);

            return null;
        }

        $input_nodes = array();

        foreach ($field->inputs as $input) {
            if (!is_array($input) || !isset($input['id'])) {
                continue;
            }

            $input_container_id = $this->get_input_container_id($form_id, $input);
            if ('' === $input_container_id) {
                continue;
            }

            $input_node = $xpath->query('.//*[@id="' . $input_container_id . '"]', $container)->item(0);
            if ($input_node instanceof DOMElement) {
                $input_nodes[(string) $input['id']] = $input_node->cloneNode(true);
            }
        }

        $completion_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " gf_chain_complete ")]', $container)->item(0);
        $completion_clone = $completion_node instanceof DOMElement ? $completion_node->cloneNode(true) : null;

        while ($container->firstChild) {
            $container->removeChild($container->firstChild);
        }

        $block_nodes = array();

        foreach ($groups as $group) {
            if (!is_array($group) || empty($group['columnIds']) || !is_array($group['columnIds'])) {
                continue;
            }

            $block = $dom->createElement('div');
            $block_classes = array('gfcs-column-section-block');
            $title = isset($group['title']) ? trim((string) $group['title']) : '';

            if ('' === $title) {
                $block_classes[] = 'gfcs-column-section-block--untitled';
            }

            $block->setAttribute('class', implode(' ', $block_classes));

            if ('' !== $title) {
                $section = $dom->createElement('div');
                $section->setAttribute('class', 'gfcs-column-section');

                $label = $dom->createElement('div');
                $label->setAttribute('class', 'gfcs-column-section__label');
                $label->appendChild($dom->createTextNode($title));

                $section->appendChild($label);
                $block->appendChild($section);
            }

            $has_columns = false;

            foreach ($group['columnIds'] as $column_id) {
                $column_id = (string) $column_id;

                if (!isset($input_nodes[$column_id])) {
                    continue;
                }

                $block->appendChild($input_nodes[$column_id]->cloneNode(true));
                $has_columns = true;
            }

            if (!$has_columns) {
                continue;
            }

            $block_nodes[] = array(
                'node' => $block,
                'pairWithNext' => !empty($group['pairWithNext']),
            );
        }

        if (empty($block_nodes)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous_error_state);

            return null;
        }

        for ($index = 0; $index < count($block_nodes); $index++) {
            if (!empty($block_nodes[$index]['pairWithNext']) && isset($block_nodes[$index + 1])) {
                $row = $dom->createElement('div');
                $row->setAttribute('class', 'gfcs-column-section-row');
                $row->appendChild($block_nodes[$index]['node']);
                $row->appendChild($block_nodes[$index + 1]['node']);
                $container->appendChild($row);
                $index++;
                continue;
            }

            $container->appendChild($block_nodes[$index]['node']);
        }

        if ($completion_clone instanceof DOMElement) {
            $container->appendChild($completion_clone);
        }

        $output = $this->get_dom_inner_html($root);

        libxml_clear_errors();
        libxml_use_internal_errors($previous_error_state);

        return $output;
    }

    /**
     * Inject simple section headings when full grouped layout rebuilding is unavailable.
     *
     * @param string                           $field_content Field markup.
     * @param object                           $field         Field object.
     * @param int                              $form_id       Form ID.
     * @param array<int, array<string, mixed>> $groups        Renderable section groups.
     * @return string
     */
    private function inject_section_heading_anchors(string $field_content, $field, int $form_id, array $groups): string
    {
        $anchors = $this->get_section_anchor_indices($groups, $field);

        if (empty($anchors)) {
            return $field_content;
        }

        krsort($anchors);

        foreach ($anchors as $index => $title) {
            if (!isset($field->inputs[$index]) || !is_array($field->inputs[$index])) {
                continue;
            }

            $container_id = $this->get_input_container_id($form_id, $field->inputs[$index]);
            if ('' === $container_id) {
                continue;
            }

            $section_markup = sprintf(
                "<div class='gfcs-column-section'><div class='gfcs-column-section__label'>%s</div></div>",
                esc_html($title)
            );

            $pattern = '/(<span id=["\']' . preg_quote($container_id, '/') . '["\'][^>]*>)/';
            $field_content = preg_replace($pattern, $section_markup . '$1', $field_content, 1);
        }

        return $field_content;
    }

    /**
     * Resolve section titles to the first visible column in each section group.
     *
     * @param array<int, array<string, mixed>> $groups Grouped section data.
     * @param object                           $field  Field object.
     * @return array<int, string>
     */
    private function get_section_anchor_indices(array $groups, $field): array
    {
        if (empty($groups) || !is_object($field) || empty($field->inputs) || !is_array($field->inputs)) {
            return array();
        }

        $hidden_lookup = array_fill_keys($this->get_hidden_column_indices($field), true);
        $input_id_to_index = array();

        foreach ($field->inputs as $index => $input) {
            if (is_array($input) && isset($input['id'])) {
                $input_id_to_index[(string) $input['id']] = $index;
            }
        }

        $anchors = array();

        foreach ($groups as $group) {
            $title = isset($group['title']) ? sanitize_text_field((string) $group['title']) : '';
            if ($title === '') {
                continue;
            }

            $anchor_index = null;
            $column_ids = isset($group['columnIds']) && is_array($group['columnIds']) ? $group['columnIds'] : array();

            foreach ($column_ids as $column_id) {
                $column_id = (string) $column_id;

                if (!isset($input_id_to_index[$column_id])) {
                    continue;
                }

                $index = $input_id_to_index[$column_id];
                if (isset($hidden_lookup[$index])) {
                    continue;
                }

                if ($anchor_index === null || $index < $anchor_index) {
                    $anchor_index = $index;
                }
            }

            if ($anchor_index !== null) {
                $anchors[$anchor_index] = $title;
            }
        }

        ksort($anchors);

        return $anchors;
    }

    /**
     * Build the container ID used by the chained select renderer for an input.
     *
     * @param int   $form_id Form ID.
     * @param array $input   Input configuration.
     * @return string
     */
    private function get_input_container_id(int $form_id, array $input): string
    {
        if (!isset($input['id'])) {
            return '';
        }

        return sprintf(
            'input_%d_%s_container',
            $form_id,
            str_replace('.', '_', (string) $input['id'])
        );
    }

    /**
     * Add CSS classes to an input container span in the rendered field markup.
     *
     * @param string   $field_content Field markup.
     * @param string   $container_id  Container DOM ID.
     * @param string[] $classes       Classes to append.
     * @return string
     */
    private function add_classes_to_input_container_markup(string $field_content, string $container_id, array $classes): string
    {
        $classes = array_values(array_filter(array_unique(array_map('sanitize_html_class', $classes))));
        if ('' === $container_id || empty($classes)) {
            return $field_content;
        }

        $class_string = implode(' ', $classes);
        $pattern = '/(<span\s+id=["\']' . preg_quote($container_id, '/') . '["\']\s+class=["\'])([^"\']*)(["\'][^>]*>)/';

        if (preg_match($pattern, $field_content)) {
            return preg_replace($pattern, '$1$2 ' . $class_string . '$3', $field_content, 1);
        }

        return $field_content;
    }

    /**
     * Insert markup immediately before an input container span.
     *
     * @param string $field_content Field markup.
     * @param string $container_id  Container DOM ID.
     * @param string $markup        Markup to prepend.
     * @return string
     */
    private function prepend_markup_before_input_container(string $field_content, string $container_id, string $markup): string
    {
        if ('' === $container_id || '' === $markup) {
            return $field_content;
        }

        $pattern = '/(<span\s+id=["\']' . preg_quote($container_id, '/') . '["\'][^>]*>)/';

        return preg_replace($pattern, $markup . '$1', $field_content, 1);
    }

    /**
     * Append CSS classes to a DOM element without duplicating existing values.
     *
     * @param \DOMElement $element DOM element.
     * @param string[]     $classes Classes to append.
     * @return void
     */
    private function append_dom_element_classes(\DOMElement $element, array $classes): void
    {
        $existing_classes = preg_split('/\s+/', trim((string) $element->getAttribute('class')));
        $existing_classes = array_filter(is_array($existing_classes) ? $existing_classes : array());
        $new_classes = array_values(array_filter(array_unique(array_map('sanitize_html_class', $classes))));

        if (empty($new_classes)) {
            return;
        }

        $merged_classes = array_values(array_unique(array_merge($existing_classes, $new_classes)));
        $element->setAttribute('class', implode(' ', $merged_classes));
    }

    /**
     * Inject section headings and pair layout classes without nesting the column spans.
     *
     * @param string                           $field_content Field markup.
     * @param object                           $field         Field object.
     * @param int                              $form_id       Form ID.
     * @param array<int, array<string, mixed>> $groups        Renderable section groups.
     * @return string
     */
    private function inject_flat_section_layout_markup(string $field_content, $field, int $form_id, array $groups): string
    {
        if (!is_object($field) || empty($field->inputs) || !is_array($field->inputs)) {
            return $field_content;
        }

        foreach ($groups as $group) {
            if (!empty($group['pairWithNext'])) {
                $field_content = $this->add_chained_select_container_class($field_content, 'gfcs-has-paired-sections');
                break;
            }
        }

        if (class_exists('DOMDocument') && class_exists('DOMXPath')) {
            $previous_error_state = libxml_use_internal_errors(true);
            $dom = new DOMDocument('1.0', 'UTF-8');
            $load_options = 0;

            if (defined('LIBXML_HTML_NOIMPLIED')) {
                $load_options |= LIBXML_HTML_NOIMPLIED;
            }

            if (defined('LIBXML_HTML_NODEFDTD')) {
                $load_options |= LIBXML_HTML_NODEFDTD;
            }

            $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?><div id="gfcs-root">' . $field_content . '</div>', $load_options);
            if ($loaded) {
                $xpath = new DOMXPath($dom);
                $root = $xpath->query('//*[@id="gfcs-root"]')->item(0);
                $container = $xpath->query('.//*[@id="' . sprintf('input_%d_%d', $form_id, $field->id) . '"]', $root)->item(0);

                if ($root instanceof DOMElement && $container instanceof DOMElement) {
                    $input_nodes = array();
                    $break_before_indices = array();

                    foreach ($field->inputs as $input) {
                        if (!is_array($input) || !isset($input['id'])) {
                            continue;
                        }

                        $input_container_id = $this->get_input_container_id($form_id, $input);
                        if ('' === $input_container_id) {
                            continue;
                        }

                        $input_node = $xpath->query('.//*[@id="' . $input_container_id . '"]', $container)->item(0);
                        if ($input_node instanceof DOMElement) {
                            $input_nodes[(string) $input['id']] = $input_node->cloneNode(true);
                        }
                    }

                    foreach ($groups as $index => $group) {
                        if (!is_array($group) || empty($group['pairWithNext']) || !isset($groups[$index + 1], $groups[$index + 2])) {
                            continue;
                        }

                        $break_before_indices[$index + 2] = true;
                    }

                    $completion_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " gf_chain_complete ")]', $container)->item(0);
                    $completion_clone = $completion_node instanceof DOMElement ? $completion_node->cloneNode(true) : null;

                    while ($container->firstChild) {
                        $container->removeChild($container->firstChild);
                    }

                    foreach ($groups as $index => $group) {
                        $column_ids = isset($group['columnIds']) && is_array($group['columnIds']) ? $group['columnIds'] : array();
                        $group_nodes = array();
                        $title = isset($group['title']) ? trim((string) $group['title']) : '';

                        if (empty($column_ids)) {
                            continue;
                        }

                        $is_paired_left = !empty($group['pairWithNext']) && isset($groups[$index + 1]);
                        $is_paired_right = !$is_paired_left && $index > 0 && !empty($groups[$index - 1]['pairWithNext']);
                        $layout_modifier = $is_paired_left ? 'paired-left' : ($is_paired_right ? 'paired-right' : 'full');

                        if (!empty($break_before_indices[$index])) {
                            $break_node = $dom->createElement('div');
                            $break_node->setAttribute('class', 'gfcs-column-section-break');
                            $break_node->setAttribute('aria-hidden', 'true');
                            $group_nodes[] = $break_node;
                        }

                        if ('' !== $title) {
                            $section_node = $dom->createElement('div');
                            $section_node->setAttribute('class', 'gfcs-column-section gfcs-column-section--' . $layout_modifier);

                            $label_node = $dom->createElement('div');
                            $label_node->setAttribute('class', 'gfcs-column-section__label');
                            $label_node->appendChild($dom->createTextNode($title));

                            $section_node->appendChild($label_node);
                            $group_nodes[] = $section_node;
                        }

                        foreach ($column_ids as $column_id) {
                            $column_id = (string) $column_id;

                            if (!isset($input_nodes[$column_id])) {
                                continue;
                            }

                            $input_node = $input_nodes[$column_id]->cloneNode(true);
                            if ($input_node instanceof DOMElement) {
                                $this->append_dom_element_classes(
                                    $input_node,
                                    array(
                                        'gfcs-column-section-input',
                                        'gfcs-column-section-input--' . $layout_modifier,
                                    )
                                );
                                $group_nodes[] = $input_node;
                            }
                        }

                        if (count($group_nodes) === 0 || (!$title && count($group_nodes) === 1 && $group_nodes[0] instanceof DOMElement && 'div' === $group_nodes[0]->tagName && 'gfcs-column-section-break' === $group_nodes[0]->getAttribute('class'))) {
                            continue;
                        }

                        foreach ($group_nodes as $group_node) {
                            $container->appendChild($group_node);
                        }
                    }

                    if ($completion_clone instanceof DOMElement) {
                        $container->appendChild($completion_clone);
                    }

                    $output = $this->get_dom_inner_html($root);

                    libxml_clear_errors();
                    libxml_use_internal_errors($previous_error_state);

                    return $output;
                }
            }

            libxml_clear_errors();
            libxml_use_internal_errors($previous_error_state);
        }

        $inputs_by_id = array();
        $break_before_indices = array();

        foreach ($field->inputs as $input) {
            if (is_array($input) && isset($input['id'])) {
                $inputs_by_id[(string) $input['id']] = $input;
            }
        }

        foreach ($groups as $index => $group) {
            if (!is_array($group) || empty($group['pairWithNext']) || !isset($groups[$index + 1], $groups[$index + 2])) {
                continue;
            }

            $break_before_indices[$index + 2] = true;
        }

        foreach ($groups as $index => $group) {
            $column_ids = isset($group['columnIds']) && is_array($group['columnIds']) ? $group['columnIds'] : array();
            if (empty($column_ids)) {
                continue;
            }

            $is_paired_left = !empty($group['pairWithNext']) && isset($groups[$index + 1]);
            $is_paired_right = !$is_paired_left && $index > 0 && !empty($groups[$index - 1]['pairWithNext']);
            $layout_modifier = $is_paired_left ? 'paired-left' : ($is_paired_right ? 'paired-right' : 'full');
            $first_column_id = (string) reset($column_ids);

            foreach ($column_ids as $column_id) {
                $column_id = (string) $column_id;

                if (!isset($inputs_by_id[$column_id])) {
                    continue;
                }

                $field_content = $this->add_classes_to_input_container_markup(
                    $field_content,
                    $this->get_input_container_id($form_id, $inputs_by_id[$column_id]),
                    array(
                        'gfcs-column-section-input',
                        'gfcs-column-section-input--' . $layout_modifier,
                    )
                );
            }

            if (!isset($inputs_by_id[$first_column_id])) {
                continue;
            }

            $prefix_markup = '';
            $title = isset($group['title']) ? trim((string) $group['title']) : '';

            if (!empty($break_before_indices[$index])) {
                $prefix_markup .= "<div class='gfcs-column-section-break' aria-hidden='true'></div>";
            }

            if ('' !== $title) {
                $prefix_markup .= sprintf(
                    "<div class='%s'><div class='gfcs-column-section__label'>%s</div></div>",
                    esc_attr('gfcs-column-section gfcs-column-section--' . $layout_modifier),
                    esc_html($title)
                );
            }

            if ('' === $prefix_markup) {
                continue;
            }

            $field_content = $this->prepend_markup_before_input_container(
                $field_content,
                $this->get_input_container_id($form_id, $inputs_by_id[$first_column_id]),
                $prefix_markup
            );
        }

        return $field_content;
    }

    /**
     * Inject section headings before the configured columns.
     *
     * @param string $field_content Field markup.
     * @param object $field         Field object.
     * @param int    $form_id       Form ID.
     * @return string
     */
    private function inject_column_sections_markup(string $field_content, $field, int $form_id): string
    {
        if (
            !is_object($field)
            || false !== strpos($field_content, 'gfcs-column-section')
            || empty($field->inputs)
            || !is_array($field->inputs)
        ) {
            return $field_content;
        }

        $groups = $this->get_renderable_section_groups($field);
        if (empty($groups)) {
            return $field_content;
        }

        return $this->inject_flat_section_layout_markup($field_content, $field, $form_id, $groups);
    }

    /**
     * Auto-select single choices and customize the empty-choice fallback text.
     *
     * @param array       $choices          Field choices.
     * @param array|int   $form             Form object or form ID.
     * @param object      $field            Field object.
     * @param string|bool $input_id         Current input ID.
     * @param mixed       $full_chain_value Full chained select value map.
     * @param mixed       $value            Current value.
     * @param int         $index            Input index.
     * @return array Modified choices.
     */
    public function auto_select_only_choice($choices, $form, $field, $input_id = false, $full_chain_value = null, $value = null, int $index = 0): array
    {
        $previous_input_value = null;

        if (!is_object($field)) {
            return $choices;
        }

        if (!empty($field->autoSelectOnly) && is_array($choices) && 1 === count($choices)) {
            $choices[0]['isSelected'] = true;
        }

        if (!empty($choices) || !is_string($input_id) || !is_array($full_chain_value)) {
            return $choices;
        }

        $input_id_bits = explode('.', $input_id);
        $field_id = isset($input_id_bits[0]) ? (string) $input_id_bits[0] : '';
        $input_index = isset($input_id_bits[1]) ? (int) $input_id_bits[1] : 0;

        if ('' === $field_id || $input_index <= 1) {
            return $choices;
        }

        $previous_input_id = sprintf('%s.%d', $field_id, $input_index - 1);
        $previous_input_value = array_key_exists($previous_input_id, $full_chain_value)
            ? $full_chain_value[$previous_input_id]
            : null;
        if (empty($previous_input_value)) {
            return $choices;
        }

        return array(
            array(
                'text' => wp_strip_all_tags(__('No value', 'gf-chained-select-enhancer')),
                'value' => '',
                'isSelected' => true,
                'noOptions' => true,
            ),
        );
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
            if (!empty($field->hideColumns) && !empty($field->inputs) && is_array($field->inputs)) {
                foreach ($this->get_hidden_column_indices($field) as $index) {
                    if (!isset($field->inputs[$index]) || !is_array($field->inputs[$index])) {
                        continue;
                    }

                    $container_id = $this->get_input_container_id((int) $form['id'], $field->inputs[$index]);
                    if ($container_id === '') {
                        continue;
                    }

                    $css_rules[] = sprintf('#%s { display: none !important; }', $container_id);
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
                echo '<style type="text/css">' . wp_strip_all_tags($css) . '</style>';
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
        $filename = sanitize_file_name($filename);

        // Stream directly to browser.
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');

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
