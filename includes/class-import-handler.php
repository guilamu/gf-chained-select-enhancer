<?php
/**
 * Import handler for Chained Select Enhancer.
 *
 * Hooks into the original Gravity Forms Chained Selects plugin to add XLSX support.
 *
 * @package GF_Chained_Select_Enhancer
 */

defined('ABSPATH') || exit;

/**
 * GFCS_Import_Handler class.
 *
 * Handles XLSX file imports for chained select fields.
 */
class GFCS_Import_Handler
{

    /**
     * XLSX parser instance.
     *
     * @var GFCS_XLSX_Parser
     */
    private $xlsx_parser;

    /**
     * Constructor: Set up hooks.
     */
    public function __construct()
    {
        // Create custom file upload field BEFORE original handler (priority 5, original is 10)
        // This allows us to include xlsx in allowedExtensions from the start
        add_filter('gform_multifile_upload_field', array($this, 'create_xlsx_file_upload_field'), 5, 3);

        // Hook into file upload before the original handler (priority 5, original is 10)
        // so CSV and XLSX imports can preserve intermediate blank cells.
        add_filter('gform_post_multifile_upload', array($this, 'handle_file_upload'), 5, 5);

        // Modify localized script strings to show correct file type info
        add_filter('gform_localized_script_data', array($this, 'modify_localized_strings'), 10, 1);
    }

    /**
     * Create a custom file upload field with XLSX support for chained selects.
     *
     * This runs BEFORE the original GF Chained Selects handler (priority 5 vs 10),
     * allowing us to include xlsx in the allowed extensions from the start.
     *
     * @param GF_Field|null $field    The field object.
     * @param array         $form     The form object.
     * @param int           $field_id The field ID.
     * @return GF_Field|null Modified field object.
     */
    public function create_xlsx_file_upload_field($field, $form, $field_id)
    {
        // The $field will be null for a newly created Chained Select field
        // or it will be the actual field for existing fields
        $is_chainedselect = $field === null || 
            (is_object($field) && $field->get_input_type() === 'chainedselect');

        if (!$is_chainedselect) {
            return $field;
        }

        // Create a custom file upload field with CSV and XLSX support
        // This matches what GF Chained Selects does, but adds xlsx
        if (!class_exists('GF_Field_FileUpload')) {
            return $field;
        }

        $new_field = new GF_Field_FileUpload(array(
            'id'                => $field_id,
            'multipleFiles'     => true,
            'maxFiles'          => 1,
            'maxFileSize'       => '',
            'allowedExtensions' => 'csv,xlsx',
            'isChainedSelect'   => true,
            'inputs'            => is_object($field) && isset($field->inputs) ? $field->inputs : null,
        ));

        return $new_field;
    }

    /**
     * Handle CSV and XLSX file uploads for chained selects.
     *
     * @param array  $form             The form object.
     * @param object $field            The field object.
     * @param string $uploaded_filename The original filename.
     * @param string $tmp_file_name    The temporary file name.
     * @param string $file_path        The full path to the uploaded file.
     * @return mixed
     */
    public function handle_file_upload($form, $field, $uploaded_filename, $tmp_file_name, $file_path)
    {
        // Only handle chained select fields
        if (!is_object($field) || empty($field->isChainedSelect)) {
            return $form;
        }

        // Only handle CSV and XLSX files.
        $extension = strtolower(pathinfo($uploaded_filename, PATHINFO_EXTENSION));
        if (!in_array($extension, array('csv', 'xlsx'), true)) {
            return $form;
        }

        // Verify the file is actually an XLSX (ZIP with proper structure)
        if ('xlsx' === $extension && !$this->validate_xlsx_mime($file_path)) {
            if (class_exists('GFAsyncUpload')) {
                GFAsyncUpload::die_error(422, esc_html__('Invalid XLSX file.', 'gf-chained-select-enhancer'));
            }
            wp_die(esc_html__('Invalid XLSX file.', 'gf-chained-select-enhancer'), '', array('response' => 422));
        }

        // Capability check - nonce alone is not authorization
        if (!current_user_can('gravityforms_edit_forms') && !current_user_can('manage_options')) {
            if (class_exists('GFAsyncUpload')) {
                GFAsyncUpload::die_error(403, esc_html__('Permission denied.', 'gravityforms'));
            }
            wp_die(esc_html__('Permission denied.', 'gf-chained-select-enhancer'), '', array('response' => 403));
        }

        // Verify nonce
        if (!wp_verify_nonce(rgpost('_gform_file_upload_nonce_' . $form['id']), 'gform_file_upload_' . $form['id'])) {
            if (class_exists('GFAsyncUpload')) {
                GFAsyncUpload::die_error(403, esc_html__('Permission denied.', 'gravityforms'));
            }
            wp_die(esc_html__('Permission denied.', 'gf-chained-select-enhancer'), '', array('response' => 403));
        }

        // Process the uploaded file.
        $import = 'xlsx' === $extension
            ? $this->import_xlsx_choices($file_path, $field)
            : $this->import_csv_choices($file_path, $field);

        if (is_wp_error($import)) {
            $error_message = $import->get_error_message();
            if (class_exists('GFAsyncUpload')) {
                $status_code = 422;
                GFAsyncUpload::die_error($status_code, $error_message);
            }
            wp_die(esc_html($error_message), '', array('response' => 422));
        }

        // Return same response format as original CSV handler
        $output = array(
            'status' => 'ok',
            'data' => array(
                'temp_filename' => $tmp_file_name,
                'uploaded_filename' => str_replace("\\'", "'", urldecode($uploaded_filename)),
                'choices' => $import['choices'],
                'inputs' => $import['inputs'],
            )
        );

        $encoded = wp_json_encode($output);
        if (false === $encoded) {
            $json_error = json_last_error_msg();
            if (class_exists('GFAsyncUpload')) {
                GFAsyncUpload::die_error(422, $json_error);
            }
            wp_die(esc_html($json_error), '', array('response' => 422));
        }

        header('Content-Type: application/json; charset=utf-8');
        die($encoded);
    }

    /**
     * Import choices from a CSV file.
     *
     * @param string $file_path The path to the CSV file.
     * @param object $field     The field object.
     * @return array|WP_Error Array with 'inputs' and 'choices' or error.
     */
    private function import_csv_choices($file_path, $field)
    {
        $rows = $this->read_csv_rows($file_path);

        if (is_wp_error($rows)) {
            return $rows;
        }

        return $this->import_choices_from_rows(
            $rows,
            $field,
            __('CSV must have a header row and at least one data row.', 'gf-chained-select-enhancer')
        );
    }

    /**
     * Import choices from XLSX file.
     *
     * Converts XLSX rows to hierarchical chained select choices structure.
     *
     * @param string $file_path The path to the XLSX file.
     * @param object $field     The field object.
     * @return array|WP_Error Array with 'inputs' and 'choices' or error.
     */
    private function import_xlsx_choices($file_path, $field)
    {
        if (!$this->xlsx_parser) {
            $this->xlsx_parser = new GFCS_XLSX_Parser();
        }

        $rows = $this->xlsx_parser->extract_all_rows($file_path);

        if (is_wp_error($rows)) {
            return $rows;
        }

        return $this->import_choices_from_rows(
            $rows,
            $field,
            __('XLSX must have a header row and at least one data row.', 'gf-chained-select-enhancer')
        );
    }

    /**
     * Read all rows from a CSV file.
     *
     * @param string $file_path The path to the CSV file.
     * @return array|WP_Error
     */
    private function read_csv_rows(string $file_path)
    {
        $handle = fopen($file_path, 'r');
        if (false === $handle) {
            return new WP_Error('csv_unreadable', __('CSV file could not be read.', 'gf-chained-select-enhancer'));
        }

        $first_line = fgets($handle);
        if (false === $first_line) {
            fclose($handle);

            return array();
        }

        $delimiter = $this->detect_csv_delimiter($first_line);
        rewind($handle);

        $rows = array();

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (!is_array($row)) {
                continue;
            }

            if (isset($row[0])) {
                $row[0] = $this->strip_utf8_bom((string) $row[0]);
            }

            $rows[] = array_map(static function ($item) {
                return is_string($item) ? $item : '';
            }, $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Detect the most likely delimiter used by the CSV header row.
     *
     * @param string $line Header row sample.
     * @return string
     */
    private function detect_csv_delimiter(string $line): string
    {
        $best_delimiter = ',';
        $best_field_count = 0;

        foreach (array(',', ';', "\t") as $delimiter) {
            $field_count = count(str_getcsv($line, $delimiter));

            if ($field_count > $best_field_count) {
                $best_delimiter = $delimiter;
                $best_field_count = $field_count;
            }
        }

        return $best_delimiter;
    }

    /**
     * Strip a UTF-8 BOM from the start of the first CSV cell.
     *
     * @param string $value CSV cell value.
     * @return string
     */
    private function strip_utf8_bom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value);
    }

    /**
     * Convert tabular rows into GF chained select inputs and choices.
     *
     * @param array       $rows                      Source rows.
     * @param object      $field                     The field object.
     * @param string      $insufficient_data_message Error message when too few rows are available.
     * @return array|WP_Error Array with 'inputs' and 'choices' or error.
     */
    private function import_choices_from_rows(array $rows, $field, string $insufficient_data_message)
    {
        if (count($rows) < 2) {
            return new WP_Error('insufficient_data', $insufficient_data_message);
        }

        if ($this->is_choice_limit_exceeded($rows)) {
            return new WP_Error('column_max_exceeded', __('One of your columns has exceeded the limit for unique values.', 'gf-chained-select-enhancer'));
        }

        $header = $rows[0];
        $choices = array();
        $inputs = $this->build_inputs_from_header($header, $field);

        for ($row_index = 1; $row_index < count($rows); $row_index++) {
            $normalized_row = $this->normalize_row_for_import($rows[$row_index], count($header));

            if (empty($normalized_row)) {
                continue;
            }

            $this->append_row_to_choices($choices, $normalized_row);
        }

        $this->array_values_recursive($choices);

        return compact('inputs', 'choices');
    }

    /**
     * Build field inputs from the header row.
     *
     * @param array  $header Header cells.
     * @param object $field  The field object.
     * @return array
     */
    private function build_inputs_from_header(array $header, $field): array
    {
        $inputs = array();

        // Build inputs from header row (same logic as original CSV handler)
        $i = 1;
        foreach ($header as $index => $item) {
            if (0 === $i % 10) {
                $i++;
            }

            $inputs[] = array(
                'id' => $field->id . '.' . $i,
                'label' => wp_strip_all_tags(trim($item)),
                'name' => isset($field->inputs[$index]['name']) ? $field->inputs[$index]['name'] : '',
            );
            $i++;
        }

        return $inputs;
    }

    /**
     * Determine whether a row contains at least one non-empty value.
     *
     * @param array $row Source row.
     * @return bool
     */
    private function has_non_empty_values(array $row): bool
    {
        foreach ($row as $item) {
            if ('' !== trim((string) $item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Locate the last non-empty cell in a row.
     *
     * @param array $row          Source row.
     * @param int   $column_count Maximum columns to inspect.
     * @return int|null
     */
    private function get_last_non_empty_index(array $row, int $column_count): ?int
    {
        for ($index = $column_count - 1; $index >= 0; $index--) {
            $value = array_key_exists($index, $row) ? trim((string) $row[$index]) : '';

            if ('' !== $value) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Normalize a row for chained-select import while preserving blank gaps.
     *
     * @param array $row          Source row.
     * @param int   $column_count Total header column count.
     * @return array
     */
    private function normalize_row_for_import(array $row, int $column_count): array
    {
        $last_non_empty_index = $this->get_last_non_empty_index($row, $column_count);

        if (null === $last_non_empty_index) {
            return array();
        }

        $normalized_row = array();

        for ($index = 0; $index <= $last_non_empty_index; $index++) {
            $value = array_key_exists($index, $row) ? trim((string) $row[$index]) : '';

            if ('' === $value) {
                $normalized_row[] = GFCS_INTERNAL_EMPTY_CHOICE_TOKEN;
                continue;
            }

            $normalized_row[] = $this->sanitize_choice_value($value);
        }

        return $normalized_row;
    }

    /**
     * Append a normalized row to the nested choices structure.
     *
     * @param array $choices Nested choices tree.
     * @param array $row     Normalized row values.
     * @return void
     */
    private function append_row_to_choices(array &$choices, array $row): void
    {
        $parent = &$choices;

        foreach ($row as $item) {
            if (!isset($parent[$item])) {
                $parent[$item] = array(
                    'text' => $item,
                    'value' => $item,
                    'isSelected' => false,
                    'choices' => array(),
                );
            }

            $parent = &$parent[$item]['choices'];
        }

        unset($parent);
    }

    /**
     * Validate that a file is a genuine XLSX (ZIP archive with expected structure).
     *
     * @param string $file_path Path to the file.
     * @return bool True if valid XLSX.
     */
    private function validate_xlsx_mime($file_path)
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }

        $zip = new ZipArchive();
        // Some ZIP builds expose ZipArchive without the RDONLY mode constant.
        $zip_open_flags = defined('ZipArchive::RDONLY') ? ZipArchive::RDONLY : 0;

        if (true !== $zip->open($file_path, $zip_open_flags)) {
            return false;
        }

        // A valid XLSX must contain [Content_Types].xml and xl/workbook.xml
        $has_content_types = (false !== $zip->locateName('[Content_Types].xml'));
        $has_workbook = (false !== $zip->locateName('xl/workbook.xml'));
        $zip->close();

        return $has_content_types && $has_workbook;
    }

    /**
     * Sanitize choice value (same as original plugin).
     *
     * @param string $value The value to sanitize.
     * @return string Sanitized value.
     */
    private function sanitize_choice_value($value)
    {
        return wp_kses($value, 'post');
    }

    /**
     * Check if choice limit is exceeded.
     *
     * @param array $rows The rows from the file.
     * @return bool True if limit exceeded.
     */
    private function is_choice_limit_exceeded($rows)
    {
        $uniques = array();
        $limit = apply_filters('gravityformschainedselects_column_unique_values_limit', 5000);
        $limit = apply_filters('gform_chainedselects_column_unique_values_limit', $limit);

        foreach ($rows as $row_index => $row) {
            // Skip header row
            if (0 === $row_index) {
                $uniques = array_pad(array(), count($row), array());
                continue;
            }

            if (!$this->has_non_empty_values($row)) {
                continue;
            }

            foreach ($row as $column => $item) {
                if (!isset($uniques[$column])) {
                    continue;
                }

                if (!in_array($item, $uniques[$column])) {
                    $uniques[$column][] = $item;
                }

                if (count($uniques[$column]) > $limit) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Convert associative array to numeric indexes recursively.
     *
     * @param array  $choices The choices array (by reference).
     * @param string $prop    The property name containing children.
     * @return array The modified array.
     */
    private function array_values_recursive(&$choices, $prop = 'choices')
    {
        $choices = array_values($choices);

        for ($i = 0; $i < count($choices); $i++) {
            if (!empty($choices[$i][$prop])) {
                $choices[$i][$prop] = $this->array_values_recursive($choices[$i][$prop], $prop);
            }
        }

        return $choices;
    }

    /**
     * Modify localized script strings to mention XLSX support.
     *
     * @param array $data The localized script data.
     * @return array Modified data.
     */
    public function modify_localized_strings($data)
    {
        if (isset($data['strings']['errorFileType'])) {
            $data['strings']['errorFileType'] = __('Only CSV and XLSX files are allowed.', 'gf-chained-select-enhancer');
        }

        return $data;
    }
}
