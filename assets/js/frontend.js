(function ($) {
    'use strict';

    function getFrontendChoiceData() {
        var data = window.gfcsFrontendData;

        if (!data || typeof data !== 'object') {
            return null;
        }

        if (!data.choicesByField || typeof data.choicesByField !== 'object') {
            return null;
        }

        return data;
    }

    function getCachedChoicesForField(formId, fieldId) {
        var data = getFrontendChoiceData();
        var fieldKey;

        if (!data) {
            return null;
        }

        fieldKey = String(formId || '') + '.' + String(fieldId || '');

        if (!Object.prototype.hasOwnProperty.call(data.choicesByField, fieldKey)) {
            return null;
        }

        return Array.isArray(data.choicesByField[fieldKey]) ? data.choicesByField[fieldKey] : null;
    }

    function getNoValueText() {
        var data = getFrontendChoiceData();

        if (data && data.strings && data.strings.noValue) {
            return String(data.strings.noValue);
        }

        return 'No value';
    }

    function getInternalEmptyChoiceToken() {
        var data = getFrontendChoiceData();

        if (!data || !data.token) {
            return '';
        }

        return String(data.token);
    }

    function isInternalEmptyChoiceValue(value) {
        var token = getInternalEmptyChoiceToken();

        return !!token && String(value || '') === token;
    }

    function getNextInputId(inputId) {
        var bits = String(inputId || '').split('.');
        var fieldId = bits[0] || '';
        var inputIndex = parseInt(bits[1], 10);

        if (!fieldId || isNaN(inputIndex)) {
            return '';
        }

        inputIndex += 1;

        if (inputIndex % 10 === 0) {
            inputIndex += 1;
        }

        return fieldId + '.' + inputIndex;
    }

    function findChoiceByValue(choices, value) {
        var selectedValue = String(value || '');
        var matchedChoice = null;

        if (!Array.isArray(choices) || !selectedValue) {
            return null;
        }

        choices.some(function (choice) {
            if (!choice || typeof choice !== 'object') {
                return false;
            }

            if (String(choice.value || '') !== selectedValue) {
                return false;
            }

            matchedChoice = choice;
            return true;
        });

        return matchedChoice;
    }

    function getCachedNextChoices(requestData, fieldChoices) {
        var currentInputId = String(requestData && requestData.input_id ? requestData.input_id : '');
        var fieldId = currentInputId.split('.')[0] || '';
        var valueMap = requestData && requestData.value && typeof requestData.value === 'object' ? requestData.value : {};
        var currentChoices = Array.isArray(fieldChoices) ? fieldChoices : [];
        var pathInputId = fieldId ? fieldId + '.1' : '';
        var selectedValue;
        var matchedChoice;

        if (!currentInputId || !fieldId) {
            return {
                isResolved: false,
                choices: []
            };
        }

        while (pathInputId) {
            selectedValue = Object.prototype.hasOwnProperty.call(valueMap, pathInputId)
                ? String(valueMap[pathInputId] || '')
                : '';

            if (!selectedValue) {
                return {
                    isResolved: false,
                    choices: []
                };
            }

            matchedChoice = findChoiceByValue(currentChoices, selectedValue);
            if (!matchedChoice) {
                return {
                    isResolved: false,
                    choices: []
                };
            }

            currentChoices = Array.isArray(matchedChoice.choices) ? matchedChoice.choices : [];

            if (pathInputId === currentInputId) {
                return {
                    isResolved: true,
                    choices: currentChoices
                };
            }

            pathInputId = getNextInputId(pathInputId);
        }

        return {
            isResolved: false,
            choices: []
        };
    }

    function shouldAutoSelectCachedChoice(formId, fieldId, inputId, choiceCount) {
        var $nextSelect;

        if (choiceCount !== 1 || !inputId) {
            return false;
        }

        $nextSelect = $('#field_' + formId + '_' + fieldId).find('select[name="input_' + inputId + '"]');

        return $nextSelect.is('[data-auto-select-only="true"]');
    }

    function buildCachedChainedSelectResponse(requestData, fieldChoices) {
        var currentInputId = String(requestData && requestData.input_id ? requestData.input_id : '');
        var cacheResult = getCachedNextChoices(requestData, fieldChoices);
        var nextInputId = getNextInputId(currentInputId);
        var nextChoices = cacheResult.choices;
        var shouldAutoSelect = shouldAutoSelectCachedChoice(requestData.form_id, requestData.field_id, nextInputId, nextChoices.length);

        if (!cacheResult.isResolved || !nextChoices.length) {
            return null;
        }

        return nextChoices.map(function (choice) {
            var value = choice && typeof choice === 'object' && choice.value !== null && typeof choice.value !== 'undefined'
                ? String(choice.value)
                : '';
            var text = choice && typeof choice === 'object' && choice.text !== null && typeof choice.text !== 'undefined'
                ? String(choice.text)
                : value;
            var isInternalEmptyChoice = isInternalEmptyChoiceValue(value) || isInternalEmptyChoiceValue(text);

            return {
                text: isInternalEmptyChoice ? getNoValueText() : text,
                value: value,
                isSelected: shouldAutoSelect || !!(choice && choice.isSelected === true)
            };
        });
    }

    function isCachedChainedSelectRequest(data) {
        return !!(data && typeof data === 'object' && data.action === 'gform_get_next_chained_select_choices');
    }

    function ensurePatchedChainedSelectRequests() {
        var originalPost;

        if (typeof $.post !== 'function') {
            return false;
        }

        if ($.post._gfcsChoiceCache === true) {
            return true;
        }

        originalPost = $.post;

        $.post = function (url, data, success, dataType) {
            var cachedChoices;
            var response;
            var deferred;
            var promise;

            if (isCachedChainedSelectRequest(data)) {
                cachedChoices = getCachedChoicesForField(data.form_id, data.field_id);

                if (cachedChoices) {
                    response = buildCachedChainedSelectResponse(data, cachedChoices);

                    if (!response) {
                        return originalPost.call(this, url, data, success, dataType);
                    }

                    response = JSON.stringify(response);

                    if (typeof success === 'function') {
                        window.setTimeout(function () {
                            success(response, 'success', null);
                        }, 0);
                    }

                    deferred = $.Deferred();
                    deferred.resolve(response, 'success', null);
                    promise = deferred.promise();
                    promise.abort = function () {
                        return promise;
                    };

                    return promise;
                }
            }

            return originalPost.call(this, url, data, success, dataType);
        };

        $.post._gfcsChoiceCache = true;
        $.post._gfcsOriginal = originalPost;

        return true;
    }

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

            this.each(function () {
                var $select = $(this);

                if (!disabled) {
                    lockSingleOptionField($select, context);
                }

                syncSingleOptionReadonlySubmission($select);
            });

            return $result;
        };

        $.fn.toggleSelect._gfcsSingleOptionReadonly = true;
        $.fn.toggleSelect._gfcsOriginal = originalToggleSelect;

        return true;
    }

    function getReadonlyMirrorSelector($select) {
        var selectId;

        if (!$select || !$select.length) {
            return '';
        }

        selectId = String($select.attr('id') || '');

        if (!selectId) {
            return '';
        }

        return 'input[type="hidden"][data-gfcs-readonly-mirror-for="' + selectId.replace(/"/g, '\\"') + '"]';
    }

    function getReadonlyMirror($select) {
        var selector;
        var $form;

        selector = getReadonlyMirrorSelector($select);
        if (!selector) {
            return $();
        }

        $form = $select.closest('form');

        if (!$form.length) {
            return $();
        }

        return $form.find(selector).first();
    }

    function removeSingleOptionReadonlySubmission($select) {
        var $mirror = getReadonlyMirror($select);

        if ($mirror.length) {
            $mirror.remove();
        }
    }

    function syncSingleOptionReadonlySubmission($select, shouldCreateMirror) {
        var $mirror;
        var mirrorId;
        var selectId;
        var selectName;
        var selectValue;

        if (!$select || !$select.length) {
            return false;
        }

        if (!shouldCreateMirror || !$select.prop('disabled') || !shouldLockSingleOptionField($select)) {
            removeSingleOptionReadonlySubmission($select);
            return false;
        }

        selectId = String($select.attr('id') || '');
        selectName = String($select.attr('name') || '');
        selectValue = $select.val();

        if (!selectId || !selectName || selectValue === null || String(selectValue) === '') {
            removeSingleOptionReadonlySubmission($select);
            return false;
        }

        $mirror = getReadonlyMirror($select);

        if (!$mirror.length) {
            mirrorId = selectId + '_gfcs_readonly_mirror';
            $mirror = $('<input />', {
                type: 'hidden',
                id: mirrorId,
                name: selectName,
                value: String(selectValue)
            }).attr('data-gfcs-readonly-mirror-for', selectId);

            $select.after($mirror);
        } else {
            $mirror.attr('name', selectName);
            $mirror.val(String(selectValue));
        }

        return true;
    }

    function getChainedSelectInputOrder(node) {
        var select;
        var nameMatch;
        var idMatch;

        if (!node || node.nodeType !== 1) {
            return null;
        }

        select = node.querySelector('select');
        if (!select) {
            return null;
        }

        nameMatch = String(select.name || '').match(/\.([0-9]+)$/);
        if (nameMatch) {
            return parseInt(nameMatch[1], 10);
        }

        idMatch = String(select.id || '').match(/_([0-9]+)$/);
        if (idMatch) {
            return parseInt(idMatch[1], 10);
        }

        return null;
    }

    function normalizeChainedSelectInputOrder(scope) {
        var $scope = scope && scope.jquery ? scope : $(scope || document);

        $scope.find('.ginput_chained_selects_container').each(function () {
            var container = this;
            var children = Array.prototype.slice.call(container.children || []);
            var inputNodes = [];
            var completionNode = null;
            var hasUnsupportedLayoutNodes = false;
            var sortedNodes;
            var hasDifferentOrder = false;

            children.forEach(function (child) {
                if (!child || child.nodeType !== 1) {
                    return;
                }

                if (child.classList && child.classList.contains('gf_chain_complete')) {
                    completionNode = child;
                    return;
                }

                if (child.tagName === 'SPAN' && /_container$/.test(String(child.id || '')) && child.querySelector('select')) {
                    inputNodes.push(child);
                    return;
                }

                hasUnsupportedLayoutNodes = true;
            });

            if (hasUnsupportedLayoutNodes || inputNodes.length < 2) {
                return;
            }

            sortedNodes = inputNodes.slice().sort(function (left, right) {
                var leftOrder = getChainedSelectInputOrder(left);
                var rightOrder = getChainedSelectInputOrder(right);

                if (leftOrder === null && rightOrder === null) {
                    return 0;
                }

                if (leftOrder === null) {
                    return 1;
                }

                if (rightOrder === null) {
                    return -1;
                }

                return leftOrder - rightOrder;
            });

            inputNodes.some(function (node, index) {
                if (node !== sortedNodes[index]) {
                    hasDifferentOrder = true;
                    return true;
                }

                return false;
            });

            if (!hasDifferentOrder) {
                return;
            }

            sortedNodes.forEach(function (node) {
                container.insertBefore(node, completionNode);
            });
        });
    }

    function lockSingleOptionField($select, context) {
        var originalToggleSelect;

        if (!$select || !$select.length || !shouldLockSingleOptionField($select) || !ensurePatchedToggleSelect()) {
            removeSingleOptionReadonlySubmission($select);
            return false;
        }

        originalToggleSelect = $.fn.toggleSelect._gfcsOriginal;
        originalToggleSelect.call($select, true, getHideInactiveFlag(context));
        syncSingleOptionReadonlySubmission($select);

        return true;
    }

    function refreshSingleOptionReadonlyFields(scope) {
        var $scope = scope && scope.jquery ? scope : $(scope || document);

        if (!ensurePatchedToggleSelect()) {
            return false;
        }

        $scope.find('select[data-auto-select-only="true"][data-gfcs-single-option-readonly="true"]').each(function () {
            var $select = $(this);

            lockSingleOptionField($select, false);
            syncSingleOptionReadonlySubmission($select);
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

        normalizeChainedSelectInputOrder(document);
        syncSubLabelTooltips(document);

        if (refreshSingleOptionReadonlyFields(document) || retryCount >= 120) {
            return;
        }

        window.setTimeout(function () {
            bootstrapFrontendEnhancements(retryCount + 1);
        }, 50);
    }

    ensurePatchedChainedSelectRequests();

    $(bootstrapFrontendEnhancements);

    $(document).on('mouseenter focusin change', '.ginput_chained_selects_container.gfcs-sub-label-left select, .ginput_chained_selects_container.gfcs-sub-label-left input:not([type="hidden"])', function () {
        syncFieldTooltip($(this));
    });

    $(document).on('change', '.ginput_chained_selects_container select[data-auto-select-only="true"][data-gfcs-single-option-readonly="true"]', function () {
        syncSingleOptionReadonlySubmission($(this));
    });

    $(document).on('submit', 'form', function () {
        var $form = $(this);

        refreshSingleOptionReadonlyFields($form);

        $form.find('select[data-auto-select-only="true"][data-gfcs-single-option-readonly="true"]').each(function () {
            syncSingleOptionReadonlySubmission($(this), true);
        });
    });

    $(document).on('gform_post_render gform_post_conditional_logic gform_page_loaded', function (event, formId) {
        if (formId) {
            normalizeChainedSelectInputOrder($('#gform_wrapper_' + formId));
            refreshSingleOptionReadonlyFields($('#gform_wrapper_' + formId));
            syncSubLabelTooltips($('#gform_wrapper_' + formId));
            return;
        }

        normalizeChainedSelectInputOrder(document);
        refreshSingleOptionReadonlyFields(document);
        syncSubLabelTooltips(document);
    });
})(jQuery);