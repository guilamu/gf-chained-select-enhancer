/**
 * Chained Select Enhancer Admin JavaScript
 *
 * @package GF_Chained_Select_Enhancer
 */

/* global jQuery, fieldSettings, SetFieldProperty, GetSelectedField, form, gfcsSettings */

(function ($) {
    'use strict';

    // NOTE: XLSX Plupload support is injected via PHP inline script
    // (see class-gf-chained-select-enhancer.php inject_xlsx_plupload_script)

    var settings;
    var sectionIdCounter = 0;
    var dragState = null;

    function countColumns(field) {
        if (!field || !field.inputs || !Array.isArray(field.inputs)) {
            return 0;
        }

        return field.inputs.length;
    }

    function getFieldInputs(field) {
        if (!field || !Array.isArray(field.inputs)) {
            return [];
        }

        return field.inputs.filter(function (input) {
            return !!input && typeof input === 'object' && input.id;
        });
    }

    function getFieldInputMap(field) {
        var inputMap = {};

        getFieldInputs(field).forEach(function (input) {
            inputMap[String(input.id)] = input;
        });

        return inputMap;
    }

    function getFieldInputIds(field) {
        return getFieldInputs(field).map(function (input) {
            return String(input.id);
        });
    }

    function escapeHtml(value) {
        return $('<div />').text(value || '').html();
    }

    function createSectionId() {
        sectionIdCounter += 1;
        return 'gfcs-section-' + Date.now().toString(36) + '-' + sectionIdCounter;
    }

    function parseColumnSections(field) {
        var rawSections = field && field.columnSections ? field.columnSections : '';

        if (!rawSections) {
            return null;
        }

        if (typeof rawSections === 'object') {
            return rawSections;
        }

        try {
            return JSON.parse(rawSections);
        } catch (error) {
            return null;
        }
    }

    function getHiddenColumnIdLookup(field) {
        var hiddenLookup = {};
        var hiddenColumns = field && field.hideColumns ? String(field.hideColumns) : '';
        var inputs = getFieldInputs(field);

        if (!hiddenColumns) {
            return hiddenLookup;
        }

        hiddenColumns.split(',').forEach(function (rawIndex) {
            var index = parseInt(String(rawIndex).trim(), 10);

            if (!isNaN(index) && inputs[index]) {
                hiddenLookup[String(inputs[index].id)] = true;
            }
        });

        return hiddenLookup;
    }

    function buildLegacySectionGroups(field, decoded) {
        var inputIds = getFieldInputIds(field);
        var titlesByStart = {};
        var starts = [];
        var groups = [];

        Object.keys(decoded || {}).forEach(function (key) {
            var index = parseInt(key, 10);
            var title = String(decoded[key] || '').trim();

            if (!isNaN(index) && title) {
                titlesByStart[index] = title;
            }
        });

        starts = Object.keys(titlesByStart).map(function (key) {
            return parseInt(key, 10);
        }).sort(function (left, right) {
            return left - right;
        });

        if (!starts.length) {
            return groups;
        }

        if (starts[0] > 0) {
            groups.push({
                id: createSectionId(),
                title: '',
                columnIds: inputIds.slice(0, starts[0])
            });
        }

        starts.forEach(function (start, position) {
            var nextStart = position + 1 < starts.length ? starts[position + 1] : inputIds.length;

            if (start >= inputIds.length) {
                return;
            }

            groups.push({
                id: createSectionId(),
                title: titlesByStart[start],
                columnIds: inputIds.slice(start, nextStart)
            });
        });

        return groups;
    }

    function buildStoredSectionGroups(field, decoded) {
        var inputMap = getFieldInputMap(field);
        var rawGroups = Array.isArray(decoded) ? decoded : (decoded && Array.isArray(decoded.groups) ? decoded.groups : []);
        var usedLookup = {};
        var groups = [];

        rawGroups.forEach(function (group) {
            var columnIds = [];

            if (!group || typeof group !== 'object') {
                return;
            }

            (Array.isArray(group.columnIds) ? group.columnIds : []).forEach(function (rawColumnId) {
                var columnId = String(rawColumnId || '').trim();

                if (!columnId || !inputMap[columnId] || usedLookup[columnId]) {
                    return;
                }

                usedLookup[columnId] = true;
                columnIds.push(columnId);
            });

            groups.push({
                id: String(group.id || createSectionId()),
                title: String(group.title || ''),
                columnIds: columnIds
            });
        });

        return groups;
    }

    function normalizeSectionGroups(field) {
        var inputIds = getFieldInputIds(field);
        var decoded = parseColumnSections(field);
        var groups = [];
        var usedLookup = {};
        var leftovers = [];
        var fallbackGroup = null;

        if (decoded) {
            if (
                (Array.isArray(decoded) && (decoded.length === 0 || (decoded[0] && typeof decoded[0] === 'object' && !Array.isArray(decoded[0]))))
                || (decoded && typeof decoded === 'object' && Array.isArray(decoded.groups))
            ) {
                groups = buildStoredSectionGroups(field, decoded);
            } else if (decoded && typeof decoded === 'object') {
                groups = buildLegacySectionGroups(field, decoded);
            }
        }

        groups.forEach(function (group) {
            group.columnIds.forEach(function (columnId) {
                usedLookup[columnId] = true;
            });
        });

        leftovers = inputIds.filter(function (columnId) {
            return !usedLookup[columnId];
        });

        if (!groups.length) {
            groups.push({
                id: createSectionId(),
                title: '',
                columnIds: leftovers.length ? leftovers.slice() : inputIds.slice()
            });
            leftovers = [];
        }

        if (leftovers.length) {
            groups.forEach(function (group) {
                if (!fallbackGroup && !String(group.title || '').trim() && group.columnIds.length === 0) {
                    fallbackGroup = group;
                }
            });

            if (!fallbackGroup) {
                fallbackGroup = {
                    id: createSectionId(),
                    title: '',
                    columnIds: []
                };
                groups.push(fallbackGroup);
            }

            fallbackGroup.columnIds = fallbackGroup.columnIds.concat(leftovers);
        }

        return groups;
    }

    function buildColumnManagerState(field, previousState) {
        return {
            groups: normalizeSectionGroups(field),
            hiddenById: getHiddenColumnIdLookup(field),
            collapsedGroupIds: previousState && previousState.collapsedGroupIds ? previousState.collapsedGroupIds : {},
            editingGroupId: previousState && previousState.editingGroupId ? previousState.editingGroupId : ''
        };
    }

    function refreshColumnManagerState(field) {
        field._gfcsColumnManagerUi = buildColumnManagerState(field, field._gfcsColumnManagerUi || null);
        return field._gfcsColumnManagerUi;
    }

    function syncColumnManagerToField(field) {
        var state = field && field._gfcsColumnManagerUi;
        var inputs = getFieldInputs(field);
        var indexById = {};
        var hiddenIndices = [];
        var payloadGroups = [];

        if (!state) {
            return;
        }

        inputs.forEach(function (input, index) {
            indexById[String(input.id)] = index;
        });

        state.groups.forEach(function (group) {
            payloadGroups.push({
                id: String(group.id || createSectionId()),
                title: String(group.title || '').trim(),
                columnIds: group.columnIds.filter(function (columnId) {
                    return Object.prototype.hasOwnProperty.call(indexById, columnId);
                })
            });
        });

        Object.keys(state.hiddenById).forEach(function (columnId) {
            if (state.hiddenById[columnId] && Object.prototype.hasOwnProperty.call(indexById, columnId)) {
                hiddenIndices.push(indexById[columnId]);
            }
        });

        hiddenIndices.sort(function (left, right) {
            return left - right;
        });

        field.columnSections = JSON.stringify({
            version: 2,
            groups: payloadGroups
        });
        field.hideColumns = hiddenIndices.join(',');

        $('#field_column_sections').val(field.columnSections);
        $('#field_hide_columns').val(field.hideColumns);

        if (typeof SetFieldProperty === 'function') {
            SetFieldProperty('columnSections', field.columnSections);
            SetFieldProperty('hideColumns', field.hideColumns);
        }
    }

    function getSectionAnchorMap(field) {
        var state = field && field._gfcsColumnManagerUi ? field._gfcsColumnManagerUi : buildColumnManagerState(field, null);
        var indexById = {};
        var anchors = {};

        getFieldInputs(field).forEach(function (input, index) {
            indexById[String(input.id)] = index;
        });

        state.groups.forEach(function (group) {
            var title = String(group.title || '').trim();
            var anchorIndex = null;

            if (!title) {
                return;
            }

            group.columnIds.forEach(function (columnId) {
                if (state.hiddenById[columnId] || !Object.prototype.hasOwnProperty.call(indexById, columnId)) {
                    return;
                }

                if (anchorIndex === null || indexById[columnId] < anchorIndex) {
                    anchorIndex = indexById[columnId];
                }
            });

            if (anchorIndex !== null) {
                anchors[anchorIndex] = title;
            }
        });

        return anchors;
    }

    function formatSectionMeta(columnCount, hiddenCount) {
        var parts = [
            columnCount + ' ' + (columnCount === 1 ? settings.columnSingular : settings.columnPlural)
        ];

        if (hiddenCount > 0) {
            parts.push(hiddenCount + ' ' + (hiddenCount === 1 ? settings.hiddenColumnSingular : settings.hiddenColumnPlural));
        }

        return parts.join(' · ');
    }

    function getGroupDisplayTitle(group, index) {
        var title = String(group.title || '').trim();
        return title || settings.untitledSection + ' ' + (index + 1);
    }

    function clearManagerDragIndicators() {
        var container = document.getElementById('gfcs_column_toggles');

        if (!container) {
            return;
        }

        container.querySelectorAll('.is-dragging, .is-drop-before, .is-drop-after, .is-drop-target').forEach(function (element) {
            element.classList.remove('is-dragging', 'is-drop-before', 'is-drop-after', 'is-drop-target');
        });
    }

    function focusSectionTitleInput(groupId) {
        var focusInput = function () {
            var input = document.querySelector('.gfcs-section-title-input[data-group-id="' + groupId + '"]');

            if (input) {
                input.focus();
                input.select();
            }
        };

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(focusInput);
        } else {
            setTimeout(focusInput, 0);
        }
    }

    function toggleGroupCollapsed(field, groupId) {
        var collapsedGroups = field._gfcsColumnManagerUi.collapsedGroupIds;

        collapsedGroups[groupId] = !collapsedGroups[groupId];
        renderColumnToggles(field);
    }

    function setEditingGroup(field, groupId) {
        field._gfcsColumnManagerUi.editingGroupId = groupId || '';
        renderColumnToggles(field);

        if (groupId) {
            focusSectionTitleInput(groupId);
        }
    }

    function moveColumnBetweenGroups(field, columnId, targetGroupId, targetColumnId, position) {
        var state = field._gfcsColumnManagerUi;
        var targetGroup = null;

        if (!state || !columnId || !targetGroupId) {
            return;
        }

        state.groups.forEach(function (group) {
            var currentIndex = group.columnIds.indexOf(columnId);

            if (currentIndex !== -1) {
                group.columnIds.splice(currentIndex, 1);
            }

            if (group.id === targetGroupId) {
                targetGroup = group;
            }
        });

        if (!targetGroup) {
            return;
        }

        if (!targetColumnId || position === 'append') {
            targetGroup.columnIds.push(columnId);
        } else {
            var targetIndex = targetGroup.columnIds.indexOf(targetColumnId);

            if (targetIndex === -1) {
                targetGroup.columnIds.push(columnId);
            } else {
                if (position === 'after') {
                    targetIndex += 1;
                }

                targetGroup.columnIds.splice(targetIndex, 0, columnId);
            }
        }

        syncColumnManagerToField(field);
        clearManagerDragIndicators();
        dragState = null;
        renderColumnToggles(field);
        refreshSubLabelPlacementPreview(field);
    }

    function createColumnRow(field, group, columnId) {
        var state = field._gfcsColumnManagerUi;
        var input = getFieldInputMap(field)[columnId];
        var row;
        var handle;
        var label;
        var toggleLabel;
        var checkbox;
        var slider;
        var status;

        if (!input) {
            return null;
        }

        row = document.createElement('div');
        row.className = 'gfcs-column-row';
        row.setAttribute('data-column-id', columnId);
        row.setAttribute('data-group-id', group.id);
        row.draggable = true;

        if (state.hiddenById[columnId]) {
            row.classList.add('is-hidden');
        }

        handle = document.createElement('button');
        handle.type = 'button';
        handle.className = 'gfcs-row-handle';
        handle.setAttribute('aria-label', settings.reorderColumn);
        handle.innerHTML = '<span class="dashicons dashicons-menu-alt3" aria-hidden="true"></span>';

        label = document.createElement('div');
        label.className = 'gfcs-column-row__label';
        label.textContent = input.label || ('Column ' + columnId);

        toggleLabel = document.createElement('label');
        toggleLabel.className = 'gfcs-toggle-switch gfcs-column-row__switch';

        checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = !!state.hiddenById[columnId];

        slider = document.createElement('span');
        slider.className = 'gfcs-toggle-slider';

        status = document.createElement('span');
        status.className = 'gfcs-toggle-status ' + (checkbox.checked ? 'gfcs-hidden' : 'gfcs-visible');
        status.textContent = checkbox.checked ? settings.hidden : settings.visible;

        checkbox.addEventListener('change', function () {
            state.hiddenById[columnId] = this.checked;
            syncColumnManagerToField(field);
            renderColumnToggles(field);
            refreshSubLabelPlacementPreview(field);
        });

        row.addEventListener('dragstart', function (event) {
            dragState = {
                columnId: columnId,
                sourceGroupId: group.id
            };
            row.classList.add('is-dragging');

            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', columnId);
            }
        });

        row.addEventListener('dragend', function () {
            dragState = null;
            clearManagerDragIndicators();
        });

        row.addEventListener('dragover', function (event) {
            var rect;
            var placeAfter;

            if (!dragState || dragState.columnId === columnId) {
                return;
            }

            event.preventDefault();
            rect = row.getBoundingClientRect();
            placeAfter = event.clientY > rect.top + rect.height / 2;

            clearManagerDragIndicators();
            row.classList.add(placeAfter ? 'is-drop-after' : 'is-drop-before');
        });

        row.addEventListener('drop', function (event) {
            var rect;
            var placeAfter;

            if (!dragState || dragState.columnId === columnId) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            rect = row.getBoundingClientRect();
            placeAfter = event.clientY > rect.top + rect.height / 2;

            moveColumnBetweenGroups(field, dragState.columnId, group.id, columnId, placeAfter ? 'after' : 'before');
        });

        toggleLabel.appendChild(checkbox);
        toggleLabel.appendChild(slider);

        row.appendChild(handle);
        row.appendChild(label);
        row.appendChild(toggleLabel);
        row.appendChild(status);

        return row;
    }

    function createSectionCard(field, group, index) {
        var state = field._gfcsColumnManagerUi;
        var card = document.createElement('section');
        var header = document.createElement('div');
        var heading = document.createElement('div');
        var meta = document.createElement('div');
        var actions = document.createElement('div');
        var editButton = document.createElement('button');
        var toggleButton = document.createElement('button');
        var body = document.createElement('div');
        var hiddenCount = 0;

        card.className = 'gfcs-section-card';
        if (state.collapsedGroupIds[group.id]) {
            card.classList.add('is-collapsed');
        }

        header.className = 'gfcs-section-header';
        header.addEventListener('click', function (event) {
            if (event.target.closest('.gfcs-section-action') || event.target.closest('.gfcs-section-title-input')) {
                return;
            }

            toggleGroupCollapsed(field, group.id);
        });

        heading.className = 'gfcs-section-heading';
        if (state.editingGroupId === group.id) {
            var titleInput = document.createElement('input');
            var originalTitle = String(group.title || '');

            titleInput.type = 'text';
            titleInput.className = 'gfcs-section-title-input';
            titleInput.value = originalTitle;
            titleInput.setAttribute('data-group-id', group.id);
            titleInput.addEventListener('click', function (event) {
                event.stopPropagation();
            });
            titleInput.addEventListener('input', function () {
                group.title = this.value;
                syncColumnManagerToField(field);
                refreshSubLabelPlacementPreview(field);
            });
            titleInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    this.blur();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    group.title = originalTitle;
                    syncColumnManagerToField(field);
                    field._gfcsColumnManagerUi.editingGroupId = '';
                    renderColumnToggles(field);
                    refreshSubLabelPlacementPreview(field);
                }
            });
            titleInput.addEventListener('blur', function () {
                group.title = this.value.trim();
                syncColumnManagerToField(field);
                field._gfcsColumnManagerUi.editingGroupId = '';
                renderColumnToggles(field);
                refreshSubLabelPlacementPreview(field);
            });
            heading.appendChild(titleInput);
        } else {
            var title = document.createElement('span');
            title.className = 'gfcs-section-title';
            title.textContent = getGroupDisplayTitle(group, index);
            heading.appendChild(title);
        }

        hiddenCount = group.columnIds.filter(function (columnId) {
            return !!state.hiddenById[columnId];
        }).length;

        meta.className = 'gfcs-section-meta';
        meta.textContent = formatSectionMeta(group.columnIds.length, hiddenCount);

        actions.className = 'gfcs-section-actions';

        editButton.type = 'button';
        editButton.className = 'gfcs-section-action gfcs-section-edit';
        editButton.setAttribute('aria-label', settings.renameSection);
        editButton.innerHTML = '<span class="dashicons dashicons-edit" aria-hidden="true"></span>';
        editButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setEditingGroup(field, group.id);
        });

        toggleButton.type = 'button';
        toggleButton.className = 'gfcs-section-action gfcs-section-toggle';
        toggleButton.setAttribute('aria-label', state.collapsedGroupIds[group.id] ? settings.expandSection : settings.collapseSection);
        toggleButton.innerHTML = state.collapsedGroupIds[group.id]
            ? '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>'
            : '<span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>';
        toggleButton.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            toggleGroupCollapsed(field, group.id);
        });

        actions.appendChild(editButton);
        actions.appendChild(toggleButton);

        body.className = 'gfcs-section-body';
        body.setAttribute('data-group-id', group.id);
        body.hidden = !!state.collapsedGroupIds[group.id];

        body.addEventListener('dragover', function (event) {
            if (!dragState) {
                return;
            }

            event.preventDefault();
            clearManagerDragIndicators();
            body.classList.add('is-drop-target');
        });

        body.addEventListener('drop', function (event) {
            if (!dragState) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            moveColumnBetweenGroups(field, dragState.columnId, group.id, null, 'append');
        });

        if (group.columnIds.length) {
            group.columnIds.forEach(function (columnId) {
                var row = createColumnRow(field, group, columnId);

                if (row) {
                    body.appendChild(row);
                }
            });
        } else {
            var emptyState = document.createElement('div');
            emptyState.className = 'gfcs-empty-section';
            emptyState.textContent = settings.dropColumnsHere;
            body.appendChild(emptyState);
        }

        header.appendChild(heading);
        header.appendChild(meta);
        header.appendChild(actions);

        card.appendChild(header);
        card.appendChild(body);

        return card;
    }

    function createAddSectionButton(field) {
        var button = document.createElement('button');
        var icon = document.createElement('span');
        var label = document.createElement('span');

        button.type = 'button';
        button.className = 'gfcs-add-section';
        button.addEventListener('click', function () {
            var newGroupId = createSectionId();

            field._gfcsColumnManagerUi.groups.push({
                id: newGroupId,
                title: settings.newSectionTitle,
                columnIds: []
            });
            field._gfcsColumnManagerUi.editingGroupId = newGroupId;
            syncColumnManagerToField(field);
            renderColumnToggles(field);
            focusSectionTitleInput(newGroupId);
        });

        icon.className = 'dashicons dashicons-plus-alt2';
        icon.setAttribute('aria-hidden', 'true');

        label.textContent = settings.addSection;

        button.appendChild(icon);
        button.appendChild(label);

        return button;
    }

    function renderColumnToggles(field) {
        var container = document.getElementById('gfcs_column_toggles');
        var managerState;

        if (!container) {
            return;
        }

        while (container.firstChild) {
            container.removeChild(container.firstChild);
        }

        if (countColumns(field) === 0) {
            var noColumnsMsg = document.createElement('p');
            noColumnsMsg.style.color = '#666';
            noColumnsMsg.style.fontStyle = 'italic';
            noColumnsMsg.textContent = settings.noColumnsFound;
            container.appendChild(noColumnsMsg);
            return;
        }

        managerState = field._gfcsColumnManagerUi || refreshColumnManagerState(field);

        managerState.groups.forEach(function (group, index) {
            container.appendChild(createSectionCard(field, group, index));
        });

        container.appendChild(createAddSectionButton(field));
    }

    function updateHideColumns() {
        var field = typeof GetSelectedField === 'function' ? GetSelectedField() : null;

        if (isChainedSelectField(field)) {
            syncColumnManagerToField(field);
            renderColumnToggles(field);
            refreshSubLabelPlacementPreview(field);
        }
    }

    function updateColumnSections() {
        updateHideColumns();
    }

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

    function isChainedSelectField(field) {
        return !!field && (field.type === 'chainedselect' || field.type === 'chained_select');
    }

    function ensureLeftSubLabelOption() {
        var select = document.getElementById('field_sub_label_placement');
        var option;

        if (!select || select.querySelector('option[value="left"]')) {
            return;
        }

        option = document.createElement('option');
        option.value = 'left';
        option.textContent = settings.leftOfField;
        select.appendChild(option);
    }

    function positionSubLabelWidthSetting() {
        var anchor = document.querySelector('.sub_label_placement_setting');
        var setting = document.querySelector('.gfcs_sub_label_width_setting');

        if (!anchor || !setting || setting.previousElementSibling === anchor) {
            return;
        }

        anchor.insertAdjacentElement('afterend', setting);
    }

    function normalizeSubLabelWidthRatio(value) {
        if (value === '1-1' || value === '2-1') {
            return value;
        }

        return '1-2';
    }

    function applySubLabelWidthRatioPreview(field, inputContainer) {
        var ratio;

        if (!inputContainer) {
            return;
        }

        ratio = normalizeSubLabelWidthRatio(field && field.gfcsSubLabelRatio);

        inputContainer.classList.toggle('gfcs-sub-label-ratio-half', ratio === '1-1');
        inputContainer.classList.toggle('gfcs-sub-label-ratio-label-wide', ratio === '2-1');
    }

    function getEffectiveSubLabelPlacement(field) {
        if (!field) {
            return '';
        }

        if (field.subLabelPlacement) {
            return field.subLabelPlacement;
        }

        return typeof form !== 'undefined' && form ? form.subLabelPlacement || '' : '';
    }

    function refreshSubLabelWidthSetting(field) {
        var select = document.getElementById('field_gfcs_sub_label_width');
        var setting = document.querySelector('.gfcs_sub_label_width_setting');
        var isLeftPlacement = getEffectiveSubLabelPlacement(field) === 'left';

        positionSubLabelWidthSetting();

        if (select) {
            select.value = normalizeSubLabelWidthRatio(field && field.gfcsSubLabelRatio);
        }

        if (setting) {
            setting.style.display = isLeftPlacement ? '' : 'none';
        }
    }

    function refreshSubLabelPlacementPreview(field) {
        var placement;
        var isSubLabelAbove;
        var subLabelClass;
        var inputContainer;
        var inputCssClass;
        var sectionAnchors;
        var markup = '';

        if (!isChainedSelectField(field) || !field.inputs || !field.inputs.length) {
            return;
        }

        inputContainer = document.querySelector('#field_' + field.id + ' .ginput_chained_selects_container');
        if (!inputContainer) {
            return;
        }

        placement = getEffectiveSubLabelPlacement(field);
        isSubLabelAbove = placement === 'above';
        subLabelClass = placement === 'hidden_label' ? 'hidden_sub_label screen-reader-text' : '';
        inputCssClass = field.chainedSelectsAlignment === 'horizontal' ? 'gform-grid-col--size-auto' : '';
        sectionAnchors = getSectionAnchorMap(field);

        field.inputs.forEach(function (input, index) {
            var fieldId = input.id;
            var fieldIdUnderScore = fieldId.replace('.', '_');
            var htmlId = 'input_' + field.formId + '_' + fieldIdUnderScore;
            var escapedLabel = escapeHtml(input.label || '');
            var inputCssClassAttribute = inputCssClass ? ' class="' + inputCssClass + '"' : '';
            var inputContainerClass = inputCssClass ? 'gform-grid-col ' + inputCssClass : 'gform-grid-col';
            var options = '<option value="" selected="selected" class="gf_placeholder">' + escapedLabel + '</option>';
            var inputSubLabel = '<label for="' + htmlId + '" id="' + htmlId + '_label" class="gform-field-label gform-field-label--type-sub ' + subLabelClass + '">' + escapedLabel + '</label>';

            if (sectionAnchors[index]) {
                markup += '<div class="gfcs-column-section"><div class="gfcs-column-section__label">' + escapeHtml(sectionAnchors[index]) + '</div></div>';
            }

            markup += '<span id="' + htmlId + '_container" class="' + inputContainerClass + '">';
            if (isSubLabelAbove) {
                markup += inputSubLabel;
            }
            markup += '<select name="input_' + fieldId + '" id="' + htmlId + '"' + inputCssClassAttribute + ' disabled="disabled">' + options + '</select>';
            if (!isSubLabelAbove) {
                markup += inputSubLabel;
            }
            markup += '</span>\n';
        });

        markup += '<span class="gf_chain_complete" style="display:none;">&nbsp;</span>';

        inputContainer.innerHTML = markup;
        inputContainer.classList.toggle('gfcs-sub-label-left', placement === 'left');
        applySubLabelWidthRatioPreview(field, inputContainer);
    }

    function applySubLabelPlacementPreview(field) {
        var inputContainer;

        if (!isChainedSelectField(field)) {
            return;
        }

        inputContainer = document.querySelector('#field_' + field.id + ' .ginput_chained_selects_container');
        if (!inputContainer) {
            return;
        }

        inputContainer.classList.toggle('gfcs-sub-label-left', getEffectiveSubLabelPlacement(field) === 'left');
        applySubLabelWidthRatioPreview(field, inputContainer);
    }

    function exportCurrentField(event) {
        var field;
        var formId;
        var fieldId;
        var statusEl;
        var exportForm;
        var fields;
        var name;

        if (event) {
            event.preventDefault();
        }

        field = typeof GetSelectedField === 'function' ? GetSelectedField() : null;

        if (!field) {
            alert(settings.selectFieldFirst);
            return;
        }

        formId = typeof form !== 'undefined' ? form.id : 0;
        fieldId = field.id;
        statusEl = document.getElementById('gfcs_export_status');

        if (statusEl) {
            statusEl.textContent = settings.exporting;
            statusEl.className = '';
        }

        exportForm = document.createElement('form');
        exportForm.method = 'POST';
        exportForm.action = settings.ajaxurl;
        exportForm.target = '_blank';
        exportForm.style.display = 'none';

        fields = {
            action: 'gfcs_export_field_csv',
            form_id: formId,
            field_id: fieldId,
            nonce: settings.nonce
        };

        for (name in fields) {
            if (Object.prototype.hasOwnProperty.call(fields, name)) {
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

    $(document).ready(function () {
        settings = window.gfcsSettings || {
            hidden: 'Hidden',
            visible: 'Visible',
            leftOfField: 'To the left of the field',
            noColumnsFound: 'No columns found',
            sectionBeforeColumn: 'Section before this column',
            sectionTitlePlaceholder: 'Leave empty if no section starts here',
            columnSingular: 'column',
            columnPlural: 'columns',
            hiddenColumnSingular: 'hidden column',
            hiddenColumnPlural: 'hidden columns',
            untitledSection: 'Section',
            newSectionTitle: 'New Section',
            addSection: 'Add a section',
            dropColumnsHere: 'Drop columns here',
            renameSection: 'Rename section',
            collapseSection: 'Collapse section',
            expandSection: 'Expand section',
            reorderColumn: 'Reorder column',
            nonce: '',
            ajaxurl: '',
            selectFieldFirst: 'Please select a chained select field first',
            exporting: 'Exporting...',
            exportComplete: 'Export complete',
            exportFailed: 'Export failed'
        };

        ensureLeftSubLabelOption();
        positionSubLabelWidthSetting();

        $(document).on('gform_load_field_settings', function (event, field) {
            if (isChainedSelectField(field)) {
                refreshColumnManagerState(field);
                ensureLeftSubLabelOption();
                refreshSubLabelWidthSetting(field);
                $('#field_auto_select').prop('checked', field.autoSelectOnly === true);
                $('#field_full_width').prop('checked', field.fullWidth === true);
                $('#field_hide_columns').val(field.hideColumns || '');
                $('#field_column_sections').val(field.columnSections || '');
                if (field.subLabelPlacement === 'left') {
                    $('#field_sub_label_placement').val('left');
                } else if (!field.subLabelPlacement) {
                    $('#field_sub_label_placement').val('');
                }
                renderColumnToggles(field);
                updateFullWidthPreview(field.id, field.fullWidth === true);
                refreshSubLabelPlacementPreview(field);
            }
        });

        $(document).on('change', '#field_sub_label_placement', function () {
            var field = typeof GetSelectedField === 'function' ? GetSelectedField() : null;

            if (!isChainedSelectField(field)) {
                return;
            }

            field.subLabelPlacement = this.value;
            refreshSubLabelWidthSetting(field);
            refreshSubLabelPlacementPreview(field);
        });

        $(document).on('change', '#field_gfcs_sub_label_width', function () {
            var field = typeof GetSelectedField === 'function' ? GetSelectedField() : null;

            if (!isChainedSelectField(field)) {
                return;
            }

            field.gfcsSubLabelRatio = normalizeSubLabelWidthRatio(this.value);
            refreshSubLabelPlacementPreview(field);
        });

        $(document).on('change', '#field_full_width', function () {
            var field = typeof GetSelectedField === 'function' ? GetSelectedField() : null;

            if (field) {
                updateFullWidthPreview(field.id, this.checked);
            }
        });

        window.gfcsExportCurrentField = exportCurrentField;

        if (typeof form !== 'undefined' && form && form.fields) {
            form.fields.forEach(function (field) {
                if (isChainedSelectField(field)) {
                    if (field.fullWidth === true) {
                        updateFullWidthPreview(field.id, true);
                    }
                    applySubLabelPlacementPreview(field);
                }
            });
        }
    });

})(jQuery);