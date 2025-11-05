/**
 * Frontend script for GF Chained Select Enhancer
 */

jQuery(document).ready(function($) {
    
    /**
     * Auto-select functionality
     */
    $(document).on('gravitywiz/chained_select:loaded gravitywiz/chained_select:filtered', function(event) {
        var $field = $(event.target);
        
        if ($field.hasClass('gf_chained_select_auto_select')) {
            var $selects = $field.find('select');
            
            $selects.each(function() {
                var $select = $(this);
                var $options = $select.find('option:not(:disabled)');
                
                // Auto-select if only one option available (excluding first empty option)
                if ($options.length === 2) { // Empty option + 1 real option
                    $select.val($options.eq(1).val()).trigger('change');
                }
            });
        }
    });

    /**
     * Hide columns functionality
     */
    $(document).on('gravitywiz/chained_select:loaded', function(event) {
        var $field = $(event.target);
        var hiddenColumns = $field.data('gf_chained_select_enhancer_hide_columns');
        
        if (hiddenColumns) {
            var columnsToHide = hiddenColumns.split(',').map(function(col) {
                return parseInt(col.trim());
            }).filter(function(col) {
                return !isNaN(col);
            });

            if (columnsToHide.length > 0) {
                GFChainedSelectEnhancerFrontend.hideColumns($field, columnsToHide);
            }
        }
    });

    /**
     * Full-width functionality
     */
    $(document).on('gravitywiz/chained_select:loaded', function(event) {
        var $field = $(event.target);
        
        if ($field.hasClass('gf_chained_select_full_width')) {
            var $selects = $field.find('select');
            $selects.each(function() {
                $(this).css('width', '100%');
            });
        }
    });
});

var GFChainedSelectEnhancerFrontend = {
    
    /**
     * Hide specified columns
     */
    hideColumns: function($field, columnsToHide) {
        var $selects = $field.find('select');
        
        $selects.each(function(index) {
            var columnIndex = index + 1;
            
            if (columnsToHide.includes(columnIndex)) {
                $(this).closest('.gcse_select').hide();
            }
        });
    }
};
