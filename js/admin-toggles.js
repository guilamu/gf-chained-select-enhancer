var GFChainedSelectEnhancer = {
    currentField: null,

    /**
     * Initialize event listeners
     */
    init: function() {
        jQuery(document).on('gform_load_field_settings', this.onFieldSettingsLoad.bind(this));
        jQuery(document).on('change', '.gf-chained-select-toggle', this.onToggleChange.bind(this));
    },

    /**
     * Handle field settings load
     */
    onFieldSettingsLoad: function() {
        var field = GetSelectedField();
        if (field && field.type === 'chained_select') {
            this.currentField = field;
            this.renderToggleSwitches();
        }
    },

    /**
     * Render toggle switches based on available columns
     */
    renderToggleSwitches: function() {
        var field = GetSelectedField();
        if (!field || field.type !== 'chained_select') {
            return;
        }

        this.currentField = field;
        var $container = jQuery('#gf_chained_select_enhancers_columns_list');
        var choices = field.choices || [];
        var columnCount = choices.length > 0 ? (choices[0].choices ? choices[0].choices.length : 0) : 0;

        // Get currently hidden columns
        var hiddenColumns = this.getHiddenColumns(field);

        // Clear existing toggles
        $container.empty();

        // Show message if no columns
        if (columnCount === 0) {
            $container.html(
                '<p class="description">' +
                GFChainedSelectEnhancerL10n.noColumns +
                '</p>'
            );
            return;
        }

        // Create toggle switches for each column
        for (var i = 1; i <= columnCount; i++) {
            var isHidden = hiddenColumns.includes(i);
            var toggleId = 'gf_chained_select_toggle_col_' + i;

            var $toggleHtml = jQuery(
                '<div class="gf-chained-select-toggle-item">' +
                    '<label class="gf-chained-select-toggle-switch">' +
                        '<input type="checkbox" class="gf-chained-select-toggle" ' +
                            'data-column="' + i + '" id="' + toggleId + '" ' +
                            (isHidden ? 'checked' : '') + ' ' +
                            'title="' + GFChainedSelectEnhancerL10n.hideLabel + '">' +
                        '<span class="gf-chained-select-slider"></span>' +
                    '</label>' +
                    '<label class="gf-chained-select-column-label" for="' + toggleId + '">' +
                        GFChainedSelectEnhancerL10n.column + ' ' + i +
                    '</label>' +
                '</div>'
            );

            $container.append($toggleHtml);
        }
    },

    /**
     * Get currently hidden columns from field metadata
     */
    getHiddenColumns: function(field) {
        var metadata = field.gf_chained_select_enhancers_hide_columns || '';
        if (!metadata) return [];
        return metadata.split(',').map(function(col) {
            return parseInt(col.trim());
        }).filter(function(col) {
            return !isNaN(col);
        });
    },

    /**
     * Handle toggle change
     */
    onToggleChange: function() {
        this.updateHiddenColumns();
    },

    /**
     * Update the hidden columns in field settings
     */
    updateHiddenColumns: function() {
        var $checkboxes = jQuery('.gf-chained-select-toggle:checked');
        var hiddenColumns = [];

        $checkboxes.each(function() {
            hiddenColumns.push(jQuery(this).data('column'));
        });

        // Sort numerically
        hiddenColumns.sort(function(a, b) {
            return a - b;
        });

        // Update the field setting
        SetFieldProperty('gf_chained_select_enhancers_hide_columns', 
            hiddenColumns.join(','));

        // Also update the hidden input field for direct access
        jQuery('#gf_chained_select_enhancers_hide_columns').val(hiddenColumns.join(','));
    }
};

// Initialize on document ready
jQuery(document).ready(function() {
    GFChainedSelectEnhancer.init();
});
