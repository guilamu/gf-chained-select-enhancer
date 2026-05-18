(function ($) {
    'use strict';

    function isConfiguredForSingleOptionReadonly($select) {
        return $select.is('[data-auto-select-only="true"][data-gfcs-single-option-readonly="true"]');
    }

    function getHideInactiveFlag(context) {
        if (context && typeof context === 'object' && Object.prototype.hasOwnProperty.call(context, 'hideInactive')) {
            return !!context.hideInactive;
        }

        return !!context;
    }

    function getAvailableOptionCount($select) {
        return $select.find('option').filter(function () {
            var $option = $(this);

            return !$option.hasClass('gf_placeholder') && $option.val() !== '';
        }).length;
    }

    function shouldLockSingleOptionField($select) {
        return isConfiguredForSingleOptionReadonly($select)
            && !$select.hasClass('gf_no_options')
            && getAvailableOptionCount($select) === 1;
    }

    function ensurePatchedToggleSelect() {
        var originalToggleSelect;

        if (typeof $.fn.toggleSelect !== 'function') {
            return false;
        }

        if ($.fn.toggleSelect._gfcsSingleOptionReadonly === true) {
            return true;
        }

        originalToggleSelect = $.fn.toggleSelect;

        $.fn.toggleSelect = function (disabled, context) {
            var $result = originalToggleSelect.call(this, disabled, context);

            if (!disabled) {
                this.each(function () {
                    lockSingleOptionField($(this), context);
                });
            }

            return $result;
        };

        $.fn.toggleSelect._gfcsSingleOptionReadonly = true;
        $.fn.toggleSelect._gfcsOriginal = originalToggleSelect;

        return true;
    }

    function lockSingleOptionField($select, context) {
        var originalToggleSelect;

        if (!$select || !$select.length || !shouldLockSingleOptionField($select) || !ensurePatchedToggleSelect()) {
            return false;
        }

        originalToggleSelect = $.fn.toggleSelect._gfcsOriginal;
        originalToggleSelect.call($select, true, getHideInactiveFlag(context));

        return true;
    }

    function refreshSingleOptionReadonlyFields(scope) {
        var $scope = scope && scope.jquery ? scope : $(scope || document);

        if (!ensurePatchedToggleSelect()) {
            return false;
        }

        $scope.find('select[data-auto-select-only="true"][data-gfcs-single-option-readonly="true"]').each(function () {
            lockSingleOptionField($(this), false);
        });

        return true;
    }

    function getFieldTooltipText($field) {
        var $selectedOption;
        var optionText;

        if (!$field || !$field.length) {
            return '';
        }

        if ($field.is('select')) {
            $selectedOption = $field.find('option:selected').first();
            optionText = $.trim($selectedOption.text());

            if (!$selectedOption.length || $selectedOption.hasClass('gf_placeholder') || $field.val() === '' || !optionText) {
                return '';
            }

            return optionText;
        }

        return $.trim($field.val());
    }

    function syncFieldTooltip($field) {
        var tooltipText = getFieldTooltipText($field);

        if (!$field || !$field.length) {
            return;
        }

        if (!tooltipText) {
            $field.removeAttr('title');
            return;
        }

        $field.attr('title', tooltipText);
    }

    function syncSubLabelTooltips(scope) {
        var $scope = scope && scope.jquery ? scope : $(scope || document);

        $scope.find('.ginput_chained_selects_container.gfcs-sub-label-left label.gform-field-label.gform-field-label--type-sub').each(function () {
            var $label = $(this);
            var labelText = $.trim($label.text());
            var inputId = $label.attr('for');
            var $field = $();

            if (inputId) {
                $field = $scope.find('select[id="' + inputId + '"], input[id="' + inputId + '"]:not([type="hidden"])').first();
            }

            if (!$field.length) {
                $field = $label.siblings('select, input:not([type="hidden"])').first();
            }

            if (!labelText) {
                $label.removeAttr('title');
            } else {
                $label.attr('title', labelText);
            }

            if ($field.length) {
                syncFieldTooltip($field);
            }
        });
    }

    function bootstrapFrontendEnhancements(attempt) {
        var retryCount = typeof attempt === 'number' ? attempt : 0;

        syncSubLabelTooltips(document);

        if (refreshSingleOptionReadonlyFields(document) || retryCount >= 120) {
            return;
        }

        window.setTimeout(function () {
            bootstrapFrontendEnhancements(retryCount + 1);
        }, 50);
    }

    $(bootstrapFrontendEnhancements);

    $(document).on('mouseenter focusin change', '.ginput_chained_selects_container.gfcs-sub-label-left select, .ginput_chained_selects_container.gfcs-sub-label-left input:not([type="hidden"])', function () {
        syncFieldTooltip($(this));
    });

    $(document).on('gform_post_render gform_post_conditional_logic gform_page_loaded', function (event, formId) {
        if (formId) {
            refreshSingleOptionReadonlyFields($('#gform_wrapper_' + formId));
            syncSubLabelTooltips($('#gform_wrapper_' + formId));
            return;
        }

        refreshSingleOptionReadonlyFields(document);
        syncSubLabelTooltips(document);
    });
})(jQuery);