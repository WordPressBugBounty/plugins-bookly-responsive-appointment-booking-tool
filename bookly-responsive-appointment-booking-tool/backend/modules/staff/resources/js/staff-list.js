jQuery(function ($) {
    'use strict';

    const table = 'staff_members';
    const $staffList = $('#bookly-' + table + '-datatables');
    const $deleteModal = $('.bookly-js-delete-cascade-confirm');

    /**
     * Restore initial filter values from saved user_meta.
     */
    const visibilityOptions = [
        { value: 'public',  label: BooklyL10n.visibility_public  || 'Public' },
        { value: 'private', label: BooklyL10n.visibility_private || 'Private' },
    ];
    let visibilityValue = [];
    let categoryValue = '';
    let archivedValue = '';
    const savedFilter = BooklyL10n.datatables[table].settings.filter || {};
    if (Array.isArray(savedFilter.visibility) && savedFilter.visibility.length > 0) visibilityValue = savedFilter.visibility;
    if (savedFilter.category)   categoryValue   = String(savedFilter.category);
    if (savedFilter.archived === 1 || savedFilter.archived === '1') archivedValue = '1';
    const categoryOptions = (BooklyL10n.categories || []).map(function (c) {
        return { value: String(c.id), label: c.name };
    });
    if (categoryValue && !categoryOptions.some(function (o) { return o.value === categoryValue; })) {
        categoryValue = '';
    }

    // Show Google/Outlook calendars errors (kept from legacy code).
    if (BooklyL10n.errors.google)  booklyAlert({ error: BooklyL10n.errors.google });
    if (BooklyL10n.errors.outlook) booklyAlert({ error: BooklyL10n.errors.outlook });

    /**
     * Init Columns.
     */
    let columns = [{
        data: 'color',
        name: '__color_dot',
        orderable: false,
        permanent: true,
        class: 'bookly:w-8',
        show: true,
        render: function (data) {
            return '<i class="fas fa-fw fa-circle" style="color:' + data + ';"></i>';
        }
    }];

    $.each(BooklyL10n.datatables[table].settings.columns, function (column, show) {
        switch (column) {
            case 'category_name':
                columns.push({
                    data: column,
                    searchable: false,
                    render: function (data) {
                        return data !== null ? BooklyDatatables.escapeHtml(data) : BooklyL10n.uncategorized;
                    }
                });
                break;
            case 'phone':
                columns.push({
                    data: column,
                    render: function (data) {
                        return data
                            ? '<span style="white-space: nowrap;">'
                                + window.booklyIntlTelInput.utils.formatNumber(BooklyDatatables.escapeHtml(data), null, window.booklyIntlTelInput.utils.numberFormat.INTERNATIONAL)
                                + '</span>'
                            : '';
                    }
                });
                break;
            case 'image':
                // Image is rendered inline inside the Name cell; skip its own column.
                return;
            case 'full_name':
                columns.push({
                    data: column,
                    render: function (data, type, row) {
                        const name = BooklyDatatables.escapeHtml(data);
                        if (row.image) {
                            return '<span class="bookly:inline-flex bookly:items-center bookly:gap-2 bookly:align-middle"><img class="bookly:datatable-thumb" src="' + row.image + '"/>' + name + '</span>';
                        }
                        return name;
                    }
                });
                break;
            default:
                columns.push({
                    data: column,
                    render: function (data) { return BooklyDatatables.escapeHtml(data); }
                });
                break;
        }
        columns[columns.length - 1].title = BooklyL10n.datatables[table].titles[column] || column;
        columns[columns.length - 1].name = column;
        columns[columns.length - 1].show = show;
    });

    /**
     * Init DataTables.
     */
    const optionsConfig = {
        ajax: {
            url: ajaxurl,
            method: 'POST',
            data: function (d) {
                return $.extend({
                    action: 'bookly_get_staff_list',
                    csrf_token: BooklyL10nGlobal.csrf_token,
                    filter: {
                        visibility: visibilityValue,
                        category: categoryValue,
                        archived: archivedValue ? 1 : 0,
                    },
                }, d);
            },
        },
        columns: columns,
        tableSettings: Object.assign({}, BooklyL10n.datatables[table], { l10n: Object.assign({}, BooklyL10n.datatables.l10n, { zeroRecords: BooklyL10n.zeroRecords }) }),
        rowClass: function (row) {
            return row.visibility === 'archive' ? 'bookly:text-gray-400' : '';
        },
        edit: function (row) {
            const event = new CustomEvent('bookly:edit-staff', { detail: { id: row.id } });
            window.dispatchEvent(event);
        },
        checked: function (rows) {
            const actions = [];
            if (rows.length === 1) {
                actions.push({
                    label: BooklyL10n.duplicate,
                    icon: 'copy',
                    variant: 'outline',
                    click: function (selected) {
                        BooklyDuplicateStaffDialog.showDialog(selected[0].id, function () { bt.reload(); });
                    }
                });
            }
            actions.push({
                label: BooklyL10n.delete,
                icon: 'trash',
                variant: 'destructive',
                click: function () { $deleteModal.booklyModal('show'); }
            });
            return actions;
        },
        saveSettings: function (settings) {
            $.post(
                ajaxurl,
                Object.assign(
                    {
                        action: 'bookly_update_table_settings',
                        table: table,
                        csrf_token: BooklyL10nGlobal.csrf_token
                    },
                    settings
                )
            );
        },
        topToolbar: (function () {
            const buttons = [];
            if (BooklyL10n.isAdmin) {
                buttons.push({
                    label: BooklyL10n.order,
                    icon: 'list-ordered',
                    variant: 'outline',
                    click: function () { $('#bookly-staff-order-modal').booklyModal('show'); }
                });
                if (BooklyL10n.proEnabled) {
                    buttons.push({
                        label: BooklyL10n.manage_categories,
                        icon: 'folder',
                        variant: 'outline',
                        click: function () { $('#bookly-staff-categories-modal').booklyModal('show'); }
                    });
                }
            }
            buttons.push({
                id: 'bookly-new-staff',
                label: BooklyL10n.new_staff,
                icon: 'plus',
                variant: 'default',
                click: function () {
                    const event = new CustomEvent('bookly:create-staff');
                    window.dispatchEvent(event);
                }
            });
            return buttons;
        })(),
    };

    if (BooklyL10n.isAdmin) {
        optionsConfig.searchFilter = {
            placeholder: BooklyL10n.search,
            name: 'filter[search]',
        };
        const filtersConfig = [
            {
                type: 'checkboxGroup',
                name: 'visibility',
                label: BooklyL10n.filters.visibility,
                initialValue: visibilityValue,
                options: visibilityOptions,
                onChange: function (v) { visibilityValue = v; },
            },
        ];
        if (BooklyL10n.proEnabled) {
            if (categoryOptions.length > 0) {
                filtersConfig.push({
                    type: 'select',
                    name: 'category',
                    label: BooklyL10n.filters.category,
                    initialValue: categoryValue,
                    searchPlaceholder: BooklyL10n.search,
                    options: categoryOptions,
                    onChange: function (v) { categoryValue = v; },
                });
            }
            filtersConfig.push({
                type: 'checkbox',
                name: 'archived',
                label: BooklyL10n.filters.show_archived,
                initialValue: archivedValue,
                onChange: function (v) { archivedValue = v; },
            });
        }
        optionsConfig.filters = filtersConfig;
    }

    let bt = BooklyDatatables.showForm('bookly-' + table + '-datatables', optionsConfig);

    /**
     * Delete confirm dialog.
     */
    $('.bookly-js-delete', $deleteModal).on('click', function (e) {
        e.preventDefault();
        const data = {
            action: 'bookly_remove_staff',
            csrf_token: BooklyL10nGlobal.csrf_token,
        };
        const ladda = rangeTools.ladda(this);
        const staff_ids = bt.getCheckedRows().map(function (row) { return row.id; });

        data['staff_ids[]'] = staff_ids;

        $.post(ajaxurl, data, function () {
            $(document.body).trigger('staff.deleted', [staff_ids]);
            ladda.stop();
            bt.reload();
            $deleteModal.booklyModal('hide');
        });
    });

    $('.bookly-js-edit', $deleteModal).on('click', function () {
        rangeTools.ladda(this);
        window.location.href = BooklyL10n.appointmentsUrl + '#staff=' + bt.getCheckedRows()[0].id;
    });
});
