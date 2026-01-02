/**
 * Chained Select Enhancer Admin JavaScript
 *
 * @package GF_Chained_Select_Enhancer
 */

/* global jQuery, fieldSettings, SetFieldProperty, GetSelectedField, form, gfcsSettings */

(function ($) {
    'use strict';

    // Settings will be read inside document.ready to ensure wp_localize_script has run
    var settings;

    /**
     * Count columns in chained select field
     *
     * @param {Object} field The field object
     * @return {number} Number of columns
     */
    function countColumns(field) {
        if (!field || !field.inputs || !Array.isArray(field.inputs)) {
            return 0;
        }
        return field.inputs.length;
    }

    /**
     * Get column labels from field
     *
     * @param {Object} field The field object
     * @return {Array} Array of column labels
     */
    function getColumnLabels(field) {
        var labels = [];
        if (field && field.inputs && Array.isArray(field.inputs)) {
            field.inputs.forEach(function (input, index) {
                var label = input.label || 'Column ' + (index + 1);
                labels.push(label);
            });
        }
        return labels;
    }

    /**
     * Create a toggle switch element
     *
     * @param {number} index Column index
     * @param {string} label Column label
     * @param {boolean} isHidden Whether column is hidden
     * @return {HTMLElement} Toggle item element
     */
    function createToggleSwitch(index, label, isHidden) {
        var itemDiv = document.createElement('div');
        itemDiv.className = 'gfcs-toggle-item';

        var toggleLabel = document.createElement('label');
        toggleLabel.className = 'gfcs-toggle-switch';

        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = isHidden;
        checkbox.setAttribute('data-column-index', index);

        var slider = document.createElement('span');
        slider.className = 'gfcs-toggle-slider';

        toggleLabel.appendChild(checkbox);
        toggleLabel.appendChild(slider);

        var labelText = document.createElement('span');
        labelText.className = 'gfcs-toggle-label';
        labelText.textContent = label;

        var status = document.createElement('span');
        status.className = 'gfcs-toggle-status ' + (isHidden ? 'gfcs-hidden' : 'gfcs-visible');
        status.textContent = isHidden ? settings.hidden : settings.visible;

        checkbox.addEventListener('change', function () {
            var isChecked = this.checked;
            status.textContent = isChecked ? settings.hidden : settings.visible;
            status.className = 'gfcs-toggle-status ' + (isChecked ? 'gfcs-hidden' : 'gfcs-visible');
            updateHideColumns();
        });

        itemDiv.appendChild(toggleLabel);
        itemDiv.appendChild(labelText);
        itemDiv.appendChild(status);

        return itemDiv;
    }

    /**
     * Render column toggle switches
     *
     * @param {Object} field The field object
     */
    function renderColumnToggles(field) {
        var container = document.getElementById('gfcs_column_toggles');
        if (!container) {
            return;
        }

        container.innerHTML = '';

        var columnCount = countColumns(field);
        if (columnCount === 0) {
            container.innerHTML = '<p style="color: #666; font-style: italic;">No columns found</p>';
            return;
        }

        var labels = getColumnLabels(field);
        var hideColumns = field.hideColumns || '';
        var hiddenIndices = hideColumns ? hideColumns.split(',').map(function (i) {
            return parseInt(i, 10);
        }) : [];

        for (var i = 0; i < columnCount; i++) {
            var isHidden = hiddenIndices.indexOf(i) !== -1;
            var toggle = createToggleSwitch(i, labels[i], isHidden);
            container.appendChild(toggle);
        }
    }

    /**
     * Update hideColumns field property from toggle states
     */
    function updateHideColumns() {
        var checkboxes = document.querySelectorAll('#gfcs_column_toggles input[type="checkbox"]');
        var hiddenIndices = [];

        checkboxes.forEach(function (checkbox) {
            if (checkbox.checked) {
                var index = parseInt(checkbox.getAttribute('data-column-index'), 10);
                hiddenIndices.push(index);
            }
        });

        var hideColumnsValue = hiddenIndices.join(',');
        var hiddenInput = document.getElementById('field_hide_columns');
        if (hiddenInput) {
            hiddenInput.value = hideColumnsValue;
        }
        if (typeof SetFieldProperty === 'function') {
            SetFieldProperty('hideColumns', hideColumnsValue);
        }
    }

    /**
     * Update full width class on field preview
     *
     * @param {number} fieldId The field ID
     * @param {boolean} isFullWidth Whether full width is enabled
     */
    function updateFullWidthPreview(fieldId, isFullWidth) {
        var container = document.querySelector('#field_' + fieldId + ' .ginput_chained_selects_container.vertical');
        if (container) {
            if (isFullWidth) {
                container.classList.add('gfcs-full-width');
            } else {
                container.classList.remove('gfcs-full-width');
            }
        }
    }

    /**
     * Export current field to CSV
     *
     * @param {Event} event Click event
     */
    function exportCurrentField(event) {
        if (event) {
            event.preventDefault();
        }

        var field = typeof GetSelectedField === 'function' ? GetSelectedField() : null;

        if (!field) {
            alert(settings.selectFieldFirst);
            return;
        }

        var formId = typeof form !== 'undefined' ? form.id : 0;
        var fieldId = field.id;

        var statusEl = document.getElementById('gfcs_export_status');

        if (statusEl) {
            statusEl.textContent = settings.exporting;
            statusEl.className = '';
        }

        // Create and submit form for download
        var exportForm = document.createElement('form');
        exportForm.method = 'POST';
        exportForm.action = settings.ajaxurl;
        exportForm.target = '_blank';
        exportForm.style.display = 'none';

        var fields = {
            'action': 'gfcs_export_field_csv',
            'form_id': formId,
            'field_id': fieldId,
            'nonce': settings.nonce
        };

        for (var name in fields) {
            if (fields.hasOwnProperty(name)) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = fields[name];
                exportForm.appendChild(input);
            }
        }

        document.body.appendChild(exportForm);
        exportForm.submit();
        document.body.removeChild(exportForm);

        setTimeout(function () {
            if (statusEl) {
                statusEl.textContent = '✓ ' + settings.exportComplete;
                setTimeout(function () {
                    statusEl.textContent = '';
                }, 3000);
            }
        }, 1000);
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function () {
        // Read settings now that wp_localize_script has definitely run
        settings = window.gfcsSettings || {
            hidden: 'Hidden',
            visible: 'Visible',
            nonce: '',
            ajaxurl: '',
            selectFieldFirst: 'Please select a chained select field first',
            exporting: 'Exporting...',
            exportComplete: 'Export complete',
            exportFailed: 'Export failed'
        };

        // Note: fieldSettings registration is handled by inline PHP script via gform_editor_js hook

        // Bind to field settings load event
        $(document).on('gform_load_field_settings', function (event, field) {
            if (field.type === 'chainedselect' || field.type === 'chained_select') {
                $('#field_auto_select').prop('checked', field.autoSelectOnly === true);
                $('#field_full_width').prop('checked', field.fullWidth === true);
                $('#field_hide_columns').val(field.hideColumns || '');
                renderColumnToggles(field);
                // Apply full width class to preview
                updateFullWidthPreview(field.id, field.fullWidth === true);
            }
        });

        // Handle full width checkbox change
        $(document).on('change', '#field_full_width', function () {
            var field = typeof GetSelectedField === 'function' ? GetSelectedField() : null;
            if (field) {
                updateFullWidthPreview(field.id, this.checked);
            }
        });

        // Expose export function globally for onclick handler
        window.gfcsExportCurrentField = exportCurrentField;
    });

})(jQuery);
