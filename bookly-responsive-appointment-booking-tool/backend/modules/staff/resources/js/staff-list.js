jQuery(function ($) {
    let $staffList = $('#bookly-staff-list'),
        $deleteButton = $('#bookly-staff-list-delete-button'),
        $deleteModal = $('.bookly-js-delete-cascade-confirm'),
        $staffCount = $('.bookly-js-staff-count'),
        filters = {
            visibility: $('#bookly-filter-visibility'),
            archived: $('#bookly-filter-archived'),
            category: $('#bookly-filter-category'),
            search: $('#bookly-filter-search')
        }
    ;

    $('.bookly-js-select').val(null);

    $.each(BooklyL10n.datatables.staff_members.settings.filter, function (field, value) {
        if (value != '') {
            let $elem = $('#bookly-filter-' + field);
            if ($elem.is(':checkbox')) {
                $elem.prop('checked', value == '1');
            } else {
                $elem.val(value);
            }
        }
        // check if select has correct values
        if ($('#bookly-filter-' + field).prop('type') == 'select-one') {
            if ($('#bookly-filter-' + field + ' option[value="' + value + '"]').length == 0) {
                $('#bookly-filter-' + field).val(null);
            }
        }
    });

    /**
     * Init Columns.
     */
    let columns = [{
        data: 'color',
        responsivePriority: 1,
        orderable: false,
        searchable: false,
        render: function (data, type, row, meta) {
            return '<i class="fas fa-fw fa-circle" style="color:' + data + ';">';
        }
    }];

    $.each(BooklyL10n.datatables.staff_members.settings.columns, function (column, show) {
        if (show) {
            switch (column) {
                case 'category_name':
                    columns.push({
                        data: column, render: function (data, type, row, meta) {
                            return data !== null ? $.fn.dataTable.render.text().display(data) : BooklyL10n.uncategorized;
                        }
                    });
                    break;
                case 'phone':
                    columns.push({
                        data: column, render: function (data, type, row, meta) {
                            return data ? '<span style="white-space: nowrap;">' + window.booklyIntlTelInput.utils.formatNumber($.fn.dataTable.render.text().display(data), null, window.booklyIntlTelInput.utils.numberFormat.INTERNATIONAL) + '</span>' : '';
                        }
                    });
                    break;
                default:
                    columns.push({data: column, render: $.fn.dataTable.render.text()});
                    break;
            }
        }
    });
    columns.push({
        data: null,
        responsivePriority: 1,
        orderable: false,
        searchable: false,
        width: 90,
        render: function (data, type, row, meta) {
            return '<button type="button" class="btn btn-default" data-action="edit"><i class="far fa-fw fa-edit mr-lg-1"></i><span class="d-none d-lg-inline">' + BooklyL10n.edit + '…</span></button>';
        }
    });

    columns[0].responsivePriority = 0;

    /**
     * Init DataTables.
     */
    var dt = booklyDataTables.init($staffList,BooklyL10n.datatables.staff_members.settings, {
        ajax: {
            url: ajaxurl,
            method: 'POST',
            data: function (d) {
                let data = $.extend({
                    action: 'bookly_get_staff_list',
                    csrf_token: BooklyL10nGlobal.csrf_token,
                    filter: {}
                }, d);

                Object.keys(filters).map(function (filter) {
                    if (filter == 'archived') {
                        data.filter[filter] = filters[filter].prop('checked') ? 1 : 0;
                    } else {
                        data.filter[filter] = filters[filter].val();
                    }
                });

                return data;
            },
            dataSrc: function (json) {
                $staffCount.html(json.recordsFiltered);
                return json.data;
            }
        },
        columns: columns,
        rowCallback: function (row, data) {
            if (data.visibility == 'archive') {
                $(row).addClass('text-muted');
            }
        },
        add_checkbox_column: true,
    });


    $deleteButton.on('click', function () {
        $deleteModal.booklyModal('show');

        $('.bookly-js-delete', $deleteModal).off().on('click', function (e) {
            e.preventDefault();
            let data = {
                    action: 'bookly_remove_staff',
                    csrf_token: BooklyL10nGlobal.csrf_token,
                },
                ladda = rangeTools.ladda(this),
                staff_ids = [],
                $checkboxes = $staffList.find('tbody input:checked');

            $checkboxes.each(function () {
                staff_ids.push(dt.row($(this).closest('td')).data().id);
            });
            data['staff_ids[]'] = staff_ids;

            $.post(ajaxurl, data, function (response) {
                dt.rows($checkboxes.closest('td')).remove().draw();
                $staffCount.html(response.data.total);
                $(document.body).trigger('staff.deleted', [staff_ids]);
                ladda.stop();
                $deleteModal.booklyModal('hide');
            });
        });

        $('.bookly-js-edit', $deleteModal).off().on('click', function () {
            rangeTools.ladda(this);
            window.location.href = BooklyL10n.appointmentsUrl + '#staff=' + dt.row($staffList.find('tbody input:checked')[0].closest('td')).data().id;
        });
    });

    $('.bookly-js-select')
        .booklySelect2({
            width: '100%',
            theme: 'bootstrap4',
            dropdownParent: '#bookly-tbs',
            allowClear: true,
            placeholder: '',
            language: {
                noResults: function () {
                    return BooklyL10n.noResultFound;
                }
            }
        });

    /**
     * On filters change.
     */
    filters.search
        .on('keyup', function () {
            dt.search(this.value).draw();
        })
        .on('keydown', function (e) {
            if (e.keyCode == 13) {
                e.preventDefault();
                return false;
            }
        });

    function onChangeFilter() {
        dt.ajax.reload();
    }

    filters.visibility.on('change', onChangeFilter);
    filters.archived.on('change', onChangeFilter);
    filters.category.on('change', onChangeFilter);
});