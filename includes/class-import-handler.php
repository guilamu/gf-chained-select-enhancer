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
        add_filter('gform_post_multifile_upload', array($this, 'handle_xlsx_upload'), 5, 5);

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
     * Handle XLSX file uploads for chained selects.
     *
     * @param array  $form             The form object.
     * @param object $field            The field object.
     * @param string $uploaded_filename The original filename.
     * @param string $tmp_file_name    The temporary file name.
     * @param string $file_path        The full path to the uploaded file.
     * @return void
     */
    public function handle_xlsx_upload($form, $field, $uploaded_filename, $tmp_file_name, $file_path)
    {
        // Only handle chained select fields
        if (!is_object($field) || empty($field->isChainedSelect)) {
            return;
        }

        // Only handle XLSX files
        $extension = strtolower(pathinfo($uploaded_filename, PATHINFO_EXTENSION));
        if ('xlsx' !== $extension) {
            return; // Let original CSV handler process it
        }

        // Verify nonce
        if (!wp_verify_nonce(rgpost('_gform_file_upload_nonce_' . $form['id']), 'gform_file_upload_' . $form['id'])) {
            if (class_exists('GFAsyncUpload')) {
                GFAsyncUpload::die_error(403, esc_html__('Permission denied.', 'gravityforms'));
            }
            wp_die(esc_html__('Permission denied.', 'gf-chained-select-enhancer'), '', array('response' => 403));
        }

        // Process the XLSX file
        $import = $this->import_xlsx_choices($file_path, $field);

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

        die($encoded);
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

        if (count($rows) < 2) {
            return new WP_Error('insufficient_data', __('XLSX must have a header row and at least one data row.', 'gf-chained-select-enhancer'));
        }

        // Check choice limit (same as original CSV handler)
        if ($this->is_choice_limit_exceeded($rows)) {
            return new WP_Error('column_max_exceeded', __('One of your columns has exceeded the limit for unique values.', 'gf-chained-select-enhancer'));
        }

        $choices = array();
        $inputs = array();
        $header = $rows[0];

        // Build inputs from header row (same logic as original CSV handler)
        $i = 1;
        foreach ($header as $index => $item) {
            if ($i % 10 == 0) {
                $i++;
            }
            $inputs[] = array(
                'id' => $field->id . '.' . $i,
                'label' => wp_strip_all_tags(trim($item)),
                'name' => isset($field->inputs[$index]['name']) ? $field->inputs[$index]['name'] : '',
            );
            $i++;
        }

        // Build hierarchical choices structure (same as original CSV handler)
        for ($row_index = 1; $row_index < count($rows); $row_index++) {
            $row = $rows[$row_index];

            // Filter out empty rows
            $filtered_row = array_filter($row, 'strlen');
            if (empty($filtered_row)) {
                continue;
            }

            $parent = null;

            foreach ($row as $item) {
                if ('' === $item || null === $item) {
                    continue;
                }

                if (null === $parent) {
                    $parent = &$choices;
                }

                $item = $this->sanitize_choice_value(trim($item));

                if (!isset($parent[$item])) {
                    $parent[$item] = array(
                        'text' => $item,
                        'value' => $item,
                        'isSelected' => false,
                        'choices' => array()
                    );
                }

                $parent = &$parent[$item]['choices'];
            }

            unset($parent);
        }

        // Convert associative array to numeric indexes
        $this->array_values_recursive($choices);

        return compact('inputs', 'choices');
    }

    /**
     * Sanitize choice value (same as original plugin).
     *
     * @param string $value The value to sanitize.
     * @return string Sanitized value.
     */
    private function sanitize_choice_value($value)
    {
        $allowed_protocols = wp_allowed_protocols();
        $value = wp_kses_no_null($value, array('slash_zero' => 'keep'));
        $value = wp_kses_hook($value, 'post', $allowed_protocols);
        $value = wp_kses_split($value, 'post', $allowed_protocols);
        return $value;
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

            // Filter out empty rows
            $filtered_row = array_filter($row);
            if (empty($filtered_row)) {
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
