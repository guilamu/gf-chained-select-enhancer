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

    function bootstrapSingleOptionReadonly(attempt) {
        var retryCount = typeof attempt === 'number' ? attempt : 0;

        if (refreshSingleOptionReadonlyFields(document) || retryCount >= 120) {
            return;
        }

        window.setTimeout(function () {
            bootstrapSingleOptionReadonly(retryCount + 1);
        }, 50);
    }

    $(bootstrapSingleOptionReadonly);

    $(document).on('gform_post_render gform_post_conditional_logic', function (event, formId) {
        if (formId) {
            refreshSingleOptionReadonlyFields($('#gform_wrapper_' + formId));
            return;
        }

        refreshSingleOptionReadonlyFields(document);
    });
})(jQuery);