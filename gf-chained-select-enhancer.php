<?php
/**
 * Plugin Name:       Chained Select Enhancer for Gravity Forms
 * Description:       Enhances Gravity Forms Chained Selects with auto-select, column hiding, and full-width options; adds per-column toggle UI for Hide Columns in the editor.
 * Version:           1.1.0
 * Author:            Guilaume (guilamu)
 * License:           GPL-2.0-or-later
 * Text Domain:       gf-chained-select-enhancer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'GFCSE_VERSION' ) ) {
    define( 'GFCSE_VERSION', '1.1.0' );
}

/**
 * NOTE:
 * - This file focuses on the editor UI for "Hide columns" with toggle switches.
 * - It keeps a legacy CSV property in sync for backward compatibility with any existing front-end logic.
 * - Front-end logic can normalize via gfcse_get_hidden_columns() defined below.
 */

/**
 * Output the "Hide columns" field setting in the Advanced tab for fields.
 * We use the Gravity Forms editor settings hook to inject a custom setting.
 * Visible only for chainedselect fields via editor JS.
 */
add_action( 'gform_field_advanced_settings', function( $position, $form_id ) {
    if ( (int) $position !== 50 ) {
        return;
    }
    ?>
    <li class="gfcse_hide_columns_setting field_setting">
        <label class="section_label" for="gfcse_hide_columns">
            <?php esc_html_e( 'Hide columns', 'gf-chained-select-enhancer' ); ?>
        </label>

        <div id="gfcse_hide_columns_toggles" class="gfcse-toggles" aria-describedby="gfcse_hide_columns_help"></div>

        <p id="gfcse_hide_columns_help" class="description">
            <?php esc_html_e( 'Toggle columns to hide in the editor preview and on the front end.', 'gf-chained-select-enhancer' ); ?>
        </p>

        <!-- Legacy CSV (hidden) kept for backward compatibility with previous versions/logic -->
        <input type="text" id="gfcse_hide_columns" style="display:none;"
               onchange="SetFieldProperty('gfcseHiddenColumnsCsv', this.value); SetFieldProperty('gfcse_hide_columns', this.value);" />
    </li>
    <?php
}, 10, 2 );

/**
 * Print editor JavaScript to:
 * - Show the custom setting for chainedselect fields.
 * - Detect column count.
 * - Render toggle switches.
 * - Sync array property + legacy CSV properties on change and on load.
 */
add_action( 'gform_editor_js', function() { ?>
<script>
( function( $ ) {

    // 1) Expose our setting on the chainedselect field
    // If fieldSettings.chainedselect exists, append; otherwise, initialize.
    fieldSettings.chainedselect = fieldSettings.chainedselect
        ? fieldSettings.chainedselect + ', .gfcse_hide_columns_setting'
        : '.gfcse_hide_columns_setting';

    // Helpers to convert between CSV string and array<int>
    function csvToArray(csv) {
        if (!csv) return [];
        return csv.split(',').map(function(s){ return parseInt($.trim(s),10); }).filter(Number.isInteger);
    }
    function arrayToCsv(arr) {
        return (arr || []).join(',');
    }

    // Heuristics to detect the number of columns (levels) for the chainedselect field.
    // Priority:
    //   1) field.inputs length (multi-input fields) if provided by Chained Selects add-on
    //   2) Depth from first choice "value" split by '|' if flattened path-like values are present
    //   3) field.gfcseColCount if user code or AJAX sets it
    //   4) Safe fallback = 2
    function detectColumnCount(field) {
        if (Array.isArray(field.inputs) && field.inputs.length) {
            return field.inputs.length;
        }
        if (field.choices && field.choices.length) {
            var first = field.choices[0];
            if (first && first.value && typeof first.value === 'string' && first.value.indexOf('|') > -1) {
                return first.value.split('|').length;
            }
        }
        if (field.gfcseColCount && Number.isInteger(field.gfcseColCount)) {
            return field.gfcseColCount;
        }
        return 2;
    }

    // Render the toggle switches into our setting container
    function renderToggles(field) {
        var $wrap = $('#gfcse_hide_columns_toggles');
        if (!$wrap.length) return;

        var colCount = detectColumnCount(field);
        var selected = field.gfcseHiddenColumns || csvToArray(field.gfcseHiddenColumnsCsv || field.gfcse_hide_columns);
        selected = Array.isArray(selected) ? selected : [];

        $wrap.empty();

        for (var i = 1; i <= colCount; i++) {
            var id = 'gfcse_col_' + i;
            var checked = selected.indexOf(i) !== -1 ? 'checked' : '';
            var html = ''
                + '<label class="gfcse-toggle" for="'+id+'">'
                +   '<input type="checkbox" id="'+id+'" data-col="'+i+'" '+checked+'>'
                +   '<span class="gfcse-slider" aria-hidden="true"></span>'
                +   '<span class="gfcse-label">Col '+i+'</span>'
                + '</label>';
            $wrap.append(html);
        }

        // On change, synchronize all properties
        $wrap.off('change', 'input[type=checkbox]').on('change', 'input[type=checkbox]', function(){
            var col = parseInt($(this).data('col'), 10);
            var next = new Set(selected);
            if (this.checked) next.add(col); else next.delete(col);
            selected = Array.from(next).sort(function(a,b){ return a-b; });

            var csv = arrayToCsv(selected);

            // Persist to field properties: new array + new CSV + legacy CSV
            SetFieldProperty('gfcseHiddenColumns', selected);
            SetFieldProperty('gfcseHiddenColumnsCsv', csv);
            SetFieldProperty('gfcse_hide_columns', csv);

            // Keep hidden legacy input in sync
            $('#gfcse_hide_columns').val(csv);
        });
    }

    // Load saved values when a field is opened in the editor
    $(document).on('gform_load_field_settings', function(e, field/*, form */) {
        // Normalize from any of the three storages
        var arr = Array.isArray(field.gfcseHiddenColumns) ? field.gfcseHiddenColumns : [];
        if (!arr.length) {
            arr = csvToArray(field.gfcseHiddenColumnsCsv || field.gfcse_hide_columns);
        }

        // Rebuild CSV and push normalized values back on the field object
        var csv = arrayToCsv(arr);
        field.gfcseHiddenColumns = arr;
        field.gfcseHiddenColumnsCsv = csv;
        field.gfcse_hide_columns   = csv;

        // Keep hidden legacy input in sync
        $('#gfcse_hide_columns').val(csv || '');

        // Render the toggles for the detected number of columns
        renderToggles(field);
    });

} )( jQuery );
</script>
<?php } );

/**
 * Admin styles for the editor toggle UI
 * Print minimal CSS only on the Gravity Forms Form Editor screen.
 */
add_action( 'admin_head', function() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->id !== 'forms_page_gf_edit_forms' ) {
        return;
    }
    ?>
    <style>
    .gfcse-toggles { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.25rem; }
    .gfcse-toggle { position:relative; display:inline-flex; align-items:center; gap:.4rem; cursor:pointer; user-select:none; }
    .gfcse-toggle input { position:absolute; opacity:0; width:0; height:0; }
    .gfcse-slider { width:34px; height:18px; background:#c9c9c9; border-radius:999px; position:relative; transition:background .2s; }
    .gfcse-slider::after { content:''; position:absolute; width:14px; height:14px; border-radius:50%; background:#fff; top:2px; left:2px; transition:transform .2s; box-shadow:0 1px 2px rgba(0,0,0,.2); }
    .gfcse-toggle input:checked + .gfcse-slider { background:#2ea3f2; }
    .gfcse-toggle input:checked + .gfcse-slider::after { transform:translateX(16px); }
    .gfcse-label { font-size:12px; line-height:1; }
    </style>
    <?php
} );

/**
 * Optional: AJAX endpoint to compute column count from a CSV header.
 * If you store a CSV path on the field (e.g., field.gfcseCsvPath), call this from editor JS
 * to set field.gfcseColCount on the fly and re-render toggles after it returns.
 */
add_action( 'wp_ajax_gfcse_csv_col_count', function() {
    if ( ! current_user_can( 'gravityforms_edit_forms' ) ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
    }

    $path = isset($_POST['csv_path']) ? wp_unslash( $_POST['csv_path'] ) : '';
    $path = $path ? wp_normalize_path( $path ) : '';

    if ( ! $path || ! file_exists( $path ) ) {
        wp_send_json_error( array( 'message' => 'CSV not found' ), 404 );
    }

    $fh = @fopen( $path, 'r' );
    if ( ! $fh ) {
        wp_send_json_error( array( 'message' => 'Cannot open CSV' ), 500 );
    }

    $first = fgetcsv( $fh );
    fclose( $fh );

    $count = is_array( $first ) ? count( $first ) : 0;
    wp_send_json_success( array( 'columns' => max( 0, (int) $count ) ) );
} );

/**
 * Utility: normalize hidden columns to a stable array<int> for front-end usage.
 * Existing code that previously read a CSV can transition to this helper safely.
 */
function gfcse_get_hidden_columns( $field ) {
    $arr = array();

    if ( isset( $field->gfcseHiddenColumns ) && is_array( $field->gfcseHiddenColumns ) ) {
        $arr = $field->gfcseHiddenColumns;
    } elseif ( ! empty( $field->gfcseHiddenColumnsCsv ) ) {
        $arr = array_map( 'absint', array_map( 'trim', explode( ',', $field->gfcseHiddenColumnsCsv ) ) );
    } elseif ( ! empty( $field->gfcse_hide_columns ) ) {
        $arr = array_map( 'absint', array_map( 'trim', explode( ',', $field->gfcse_hide_columns ) ) );
    }

    $arr = array_values( array_unique( array_filter( $arr, 'is_int' ) ) );
    sort( $arr );
    return $arr;
}

/**
 * END OF FILE FILE
 */
