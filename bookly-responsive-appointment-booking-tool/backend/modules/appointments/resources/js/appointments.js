jQuery(function ($) {
    'use strict';
    const { today, getLocalTimeZone, parseDate } = window.BooklyDatatables.calendarDate;

    let
        $appointmentsList = $('#bookly-appointments-datatables'),
        $printDialog = $('#bookly-print-dialog'),
        $printSelectAll = $('#bookly-js-print-select-all', $printDialog),
        $printButton = $(':submit', $printDialog),
        $exportDialog = $('#bookly-export-dialog'),
        $exportSelectAll = $('#bookly-js-export-select-all', $exportDialog),
        $exportForm = $('form', $exportDialog),
        isMobile = false,
        urlParts = document.URL.split('#'),
        columns = []
    ;

    try {
        document.createEvent('TouchEvent');
        isMobile = true;
    } catch (e) {}

    // Tailwind classes per appointment status — used by the status column badge.
    const statusBadgeClass = {
        pending:    'bookly:bg-amber-100 bookly:text-amber-800 bookly:border-amber-200',
        approved:   'bookly:bg-green-100 bookly:text-green-800 bookly:border-green-200',
        cancelled:  'bookly:bg-gray-100 bookly:text-gray-700 bookly:border-gray-200',
        rejected:   'bookly:bg-red-100 bookly:text-red-800 bookly:border-red-200',
        waitlisted: 'bookly:bg-purple-100 bookly:text-purple-800 bookly:border-purple-200',
        done:       'bookly:bg-blue-100 bookly:text-blue-800 bookly:border-blue-200',
    };
    const defaultBadgeClass = 'bookly:bg-gray-100 bookly:text-gray-700 bookly:border-gray-200';

    /**
     * Filter state — каждый bind через FilterRenderer обновляет соответствующую переменную
     * через onChange; ajax.data() их сериализует в backend POST.
     *
     * Date values: undefined (any), 'tasks' (special), либо { start: CalendarDate, end: CalendarDate }.
     * Select values: '' или строковый id.
     * Status: массив значений (пустой = "any", полный = "any" — backend трактует одинаково).
     */
    let dateValue = undefined;
    let createdDateValue = undefined;
    let staffValue = '';
    let customerValue = '';
    let serviceValue = '';
    let locationValue = '';
    let statusValue = [];

    const tz = getLocalTimeZone();
    const t = today(tz);
    const startOfMonth = (d) => d.set({ day: 1 });
    const endOfMonth   = (d) => d.set({ day: 1 }).add({ months: 1 }).subtract({ days: 1 });
    // ISO-неделя (Mon..Sun). 0=Sun → 6, 1=Mon → 0, ...
    function startOfWeek(d) {
        const dow = d.toDate(tz).getDay();
        const offset = (dow + 6) % 7;
        return d.subtract({ days: offset });
    }
    const endOfWeek   = (d) => startOfWeek(d).add({ days: 6 });
    const startOfYear = (d) => d.set({ month: 1, day: 1 });
    const endOfYear   = (d) => d.set({ month: 12, day: 31 });

    /**
     * Date presets — future-leaning набор для appointment date (вперёд + past-хвост).
     * Tasks-режим (если активен) — special preset с маркером range = { tasks: true },
     * его перехватываем в serialize.
     */
    const datePresets = [
        { label: BooklyL10n.dateRange.today,      range: { start: t,                                        end: t                                            } },
        { label: BooklyL10n.dateRange.tomorrow,   range: { start: t.add({ days: 1 }),                       end: t.add({ days: 1 })                           } },
        { label: BooklyL10n.dateRange.thisWeek,   range: { start: startOfWeek(t),                           end: endOfWeek(t)                                 } },
        { label: BooklyL10n.dateRange.next_7,     range: { start: t,                                        end: t.add({ days: 7 })                           } },
        { label: BooklyL10n.dateRange.next_30,    range: { start: t,                                        end: t.add({ days: 30 })                          } },
        { label: BooklyL10n.dateRange.thisMonth,  range: { start: startOfMonth(t),                          end: endOfMonth(t)                                } },
        { label: BooklyL10n.dateRange.nextMonth,  range: { start: startOfMonth(t.add({ months: 1 })),       end: endOfMonth(t.add({ months: 1 }))             } },
        { label: BooklyL10n.dateRange.thisYear,   range: { start: startOfYear(t),                           end: endOfYear(t)                                 } },
        // past-хвост для отчётности
        { label: BooklyL10n.dateRange.yesterday,  range: { start: t.subtract({ days: 1 }),                  end: t.subtract({ days: 1 })                      } },
        { label: BooklyL10n.dateRange.last_7,     range: { start: t.subtract({ days: 7 }),                  end: t                                            } },
        { label: BooklyL10n.dateRange.last_30,    range: { start: t.subtract({ days: 30 }),                 end: t                                            } },
        { label: BooklyL10n.dateRange.lastMonth,  range: { start: startOfMonth(t.subtract({ months: 1 })),  end: endOfMonth(t.subtract({ months: 1 }))        } },
    ];
    if (BooklyL10n.tasks.enabled) {
        datePresets.push({
            label: BooklyL10n.tasks.title,
            range: { tasks: true, start: t, end: t.add({ days: 1 }) },
        });
    }

    /**
     * Past-only набор для creation date — даты создания осмысленны только в прошлом.
     */
    const createdDatePresets = [
        { label: BooklyL10n.dateRange.today,       range: { start: t,                                        end: t                                           } },
        { label: BooklyL10n.dateRange.yesterday,   range: { start: t.subtract({ days: 1 }),                  end: t.subtract({ days: 1 })                     } },
        { label: BooklyL10n.dateRange.last_7,      range: { start: t.subtract({ days: 7 }),                  end: t                                           } },
        { label: BooklyL10n.dateRange.last_30,     range: { start: t.subtract({ days: 30 }),                 end: t                                           } },
        { label: BooklyL10n.dateRange.last_90,     range: { start: t.subtract({ days: 90 }),                 end: t                                           } },
        { label: BooklyL10n.dateRange.thisWeek,    range: { start: startOfWeek(t),                           end: endOfWeek(t)                                } },
        { label: BooklyL10n.dateRange.thisMonth,   range: { start: startOfMonth(t),                          end: endOfMonth(t)                               } },
        { label: BooklyL10n.dateRange.lastMonth,   range: { start: startOfMonth(t.subtract({ months: 1 })),  end: endOfMonth(t.subtract({ months: 1 }))       } },
        { label: BooklyL10n.dateRange.yearToDate,  range: { start: startOfYear(t),                           end: t                                           } },
    ];

    const ymd = (d) => d.year + '-' + String(d.month).padStart(2, '0') + '-' + String(d.day).padStart(2, '0');

    function serializeDate(value) {
        if (!value) return 'any';
        if (value.tasks) return 'null';
        if (value.start && value.end) return ymd(value.start) + ' - ' + ymd(value.end);
        return 'any';
    }

    /**
     * Restore initial values from saved settings or anchor.
     */
    let savedFilter = BooklyL10n.datatables.appointments.settings.filter || {};

    function tryParseSavedDate(saved, label) {
        if (label) {
            const preset = datePresets.find(p => p.label === label);
            if (preset) return preset.range;
        }
        if (typeof saved === 'string' && saved !== 'any' && saved !== 'null') {
            const parts = saved.split(' - ');
            if (parts.length === 2) {
                try { return { start: parseDate(parts[0].trim()), end: parseDate(parts[1].trim()) }; }
                catch (e) { return undefined; }
            }
            // URL-hash format from PHP: YYYY-MM-DD-YYYY-MM-DD (no spaces around dash).
            if (saved.length === 21 && saved[10] === '-') {
                try { return { start: parseDate(saved.slice(0, 10)), end: parseDate(saved.slice(11)) }; }
                catch (e) { return undefined; }
            }
        }
        if (saved === 'null' && BooklyL10n.tasks.enabled) {
            const tasksPreset = datePresets.find(p => p.range && p.range.tasks);
            if (tasksPreset) return tasksPreset.range;
        }
        return undefined;
    }

    if (urlParts.length > 1) {
        urlParts[1].split('&').forEach(function (part) {
            const params = part.split('=');
            switch (params[0]) {
                case 'appointment-date':
                    dateValue = tryParseSavedDate(params[1], null);
                    break;
                case 'tasks':
                    if (BooklyL10n.tasks.enabled) {
                        const p = datePresets.find(p => p.range && p.range.tasks);
                        if (p) dateValue = p.range;
                    }
                    break;
                case 'created-date':
                    createdDateValue = tryParseSavedDate(params[1], null);
                    break;
                case 'staff':    staffValue    = params[1]; break;
                case 'customer': customerValue = params[1]; break;
                case 'service':  serviceValue  = params[1]; break;
                case 'location': locationValue = params[1]; break;
                case 'status':
                    statusValue = (params[1] === 'any' || !params[1]) ? [] : params[1].split(',');
                    break;
            }
        });
    } else {
        dateValue        = tryParseSavedDate(savedFilter.date, savedFilter.date_label);
        createdDateValue = tryParseSavedDate(savedFilter.created_date, savedFilter.created_date_label);
        staffValue       = savedFilter.staff || '';
        customerValue    = savedFilter.customer || '';
        serviceValue     = savedFilter.service || '';
        locationValue    = savedFilter.location || '';
        if (Array.isArray(savedFilter.status) && savedFilter.status.length > 0) {
            statusValue = savedFilter.status;
        }
        // Default appointment date — this month, если ничего не задано.
        if (dateValue === undefined && !savedFilter.date) {
            dateValue = datePresets.find(p => p.label === BooklyL10n.dateRange.thisMonth)?.range;
        }
    }

    Ladda.bind($('button[type=submit]', $exportForm).get(0), {timeout: 2000});

    /**
     * Init table columns.
     */
    const table = 'appointments';

    $.each(BooklyL10n.datatables.appointments.settings.columns, function (column, show) {
        switch (column) {
            case 'customer_full_name':
                columns.push({data: 'customer.full_name', render: BooklyDatatables.escapeHtml()});
                break;
            case 'customer_phone':
                columns.push({
                    data: 'customer.phone',
                    parent: 'customer_full_name',
                    render: function (data, type, row, meta) {
                        if (isMobile) {
                            return '<a href="tel:' + window.booklyIntlTelInput.utils.formatNumber(BooklyDatatables.escapeHtml(data), null, window.booklyIntlTelInput.utils.numberFormat.INTERNATIONAL) + '">' + BooklyDatatables.escapeHtml(data) + '</a>';
                        } else {
                            return data ? '<span style="white-space: nowrap;">' + window.booklyIntlTelInput.utils.formatNumber(BooklyDatatables.escapeHtml(data), null, window.booklyIntlTelInput.utils.numberFormat.INTERNATIONAL) + '</span>' : '';
                        }
                    }
                });
                break;
            case 'customer_email':
                columns.push({data: 'customer.email', parent: 'customer_full_name', render: BooklyDatatables.escapeHtml()});
                break;
            case 'customer_address':
                columns.push({data: 'customer.address', render: BooklyDatatables.escapeHtml(), orderable: false});
                break;
            case 'customer_birthday':
                columns.push({data: 'customer.birthday', render: BooklyDatatables.escapeHtml()});
                break;
            case 'staff_name':
                columns.push({data: 'staff.name', render: BooklyDatatables.escapeHtml()});
                break;
            case 'start_date':
                columns.push({
                    data: 'start_date',
                    // Time of day rendered as a muted secondary line below the date.
                    // Both halves come pre-formatted from Ajax.php (WP date_format / time_format).
                    secondary: row => row.start_time || '',
                });
                break;
            case 'created_date':
                columns.push({
                    data: 'created_date',
                    secondary: row => row.created_time || '',
                });
                break;
            case 'service_title':
                columns.push({
                    data: 'service.title',
                    render: data => BooklyDatatables.escapeHtml(data),
                    // Each extra renders as a muted secondary line under the title.
                    secondary: row => (row.service.extras || []).map(e => BooklyDatatables.escapeHtml(e.title)),
                });
                break;
            case 'service_duration':
                // Rendered as a secondary line under Service in the cell, and as an
                // indented checkbox under Service in the View dialog. Stays in columns
                // array (export/print see it as a separate field) — Form.svelte just
                // skips top-level rendering for columns with `parent`.
                columns.push({
                    data: 'service.duration',
                    parent: 'service_title',
                });
                break;
            case 'payment':
                columns.push({
                    data: 'payment_amount',
                    // Two lines: amount (primary) + "gateway · status" (muted secondary).
                    secondary: row => row.payment_gateway || row.payment_status
                        ? [row.payment_gateway, row.payment_status].filter(Boolean).join(' · ')
                        : null,
                    render: function (data, type, row, meta) {
                        if (row.payment_id) {
                            return '<a type="button" data-action="show-payment" class="text-primary" data-payment_id="' + row.payment_id + '">' + data + '</a>';
                        }
                        return data || '';
                    }
                });
                break;
            case 'service_price':
                columns.push({data: 'service.price', class: 'bookly:text-right'});
                break;
            case 'attachments':
                columns.push({
                    data: 'attachment',
                    render: function (data, type, row, meta) {
                        if (data == '1') {
                            return '<button type="button" class="btn btn-link p-0" data-action="show-attachments" data-ca-id="' + row.ca_id + '" title="' + BooklyL10n.attachments + '"><i class="fas fa-fw fa-paperclip"></i></button>';
                        }
                        return '';
                    }
                });
                break;
            case 'rating':
                columns.push({
                    data: 'rating',
                    render: (data, type, row) => row.rating != null ? BooklyDatatables.escapeHtml(String(row.rating)) : '',
                    popover: row => row.rating_comment || null,
                    popoverLink: true,
                });
                break;
            case 'internal_note':
            case 'locations':
            case 'notes':
            case 'number_of_persons':
                columns.push({data: column, render: BooklyDatatables.escapeHtml()});
                break;
            case 'online_meeting':
                columns.push({
                    data: 'online_meeting_provider',
                    render: function (data, type, row, meta) {
                        switch (data) {
                            case 'zoom':
                                return '<a class="badge badge-primary" href="https://zoom.us/j/' + BooklyDatatables.escapeHtml(row.online_meeting_start_url) + '" target="_blank"><i class="fas fa-video fa-fw"></i> Zoom <i class="fas fa-external-link-alt fa-fw"></i></a>';
                            case 'google_meet':
                                return '<a class="badge badge-primary" href="' + BooklyDatatables.escapeHtml(row.online_meeting_start_url) + '" target="_blank"><i class="fas fa-video fa-fw"></i> Google Meet <i class="fas fa-external-link-alt fa-fw"></i></a>';
                            case 'jitsi':
                                return '<a class="badge badge-primary" href="' + BooklyDatatables.escapeHtml(row.online_meeting_start_url) + '" target="_blank"><i class="fas fa-video fa-fw"></i> Jitsi Meet <i class="fas fa-external-link-alt fa-fw"></i></a>';
                            case 'bbb':
                                return '<a class="badge badge-primary" href="' + BooklyDatatables.escapeHtml(row.online_meeting_start_url) + '" target="_blank"><i class="fas fa-video fa-fw"></i> BigBlueButton <i class="fas fa-external-link-alt fa-fw"></i></a>';
                            case 'teams':
                                return '<a class="badge badge-primary" href="' + BooklyDatatables.escapeHtml(row.online_meeting_start_url) + '" target="_blank"><i class="fas fa-video fa-fw"></i> Microsoft Teams <i class="fas fa-external-link-alt fa-fw"></i></a>';
                            default:
                                return '';
                        }
                    },
                });
                break;
            case 'id':
                columns.push({data: column, render: BooklyDatatables.escapeHtml()});
                break;
            case 'status':
                columns.push({
                    data: 'status',
                    badge: row => statusBadgeClass[row.status_code] || defaultBadgeClass,
                });
                break;
            default:
                if (column.startsWith('custom_fields_')) {
                    columns.push({
                        data: column.replace(/_([^_]*)$/, '.$1'),
                        render: BooklyDatatables.escapeHtml(),
                        orderable: false
                    });
                } else {
                    columns.push({data: column, render: BooklyDatatables.escapeHtml()});
                }
                break;
        }
        columns[columns.length - 1].title = BooklyL10n.datatables[table].titles[column] || column;
        columns[columns.length - 1].name = column;
        columns[columns.length - 1].show = show;
    });

    const filterOpts = BooklyL10n.filterOptions;
    const fl = BooklyL10n.filters;
    const searchPlaceholder = fl.searchPlaceholder;

    const filters = [
        {
            type: 'dateRange',
            name: 'date',
            label: fl.date,
            initialValue: dateValue,
            presets: datePresets,
            onChange: (v) => { dateValue = v; },
        },
        {
            type: 'dateRange',
            name: 'created_date',
            label: fl.created,
            initialValue: createdDateValue,
            presets: createdDatePresets,
            onChange: (v) => { createdDateValue = v; },
        },
        {
            type: 'select',
            name: 'staff',
            label: fl.staff,
            initialValue: staffValue,
            searchPlaceholder: searchPlaceholder,
            options: (filterOpts.staff || []).map(s => ({ value: String(s.id), label: s.full_name })),
            onChange: (v) => { staffValue = v; },
        },
    ];

    if (filterOpts.customers && filterOpts.customers.length > 0) {
        filters.push({
            type: 'select',
            name: 'customer',
            label: fl.customer,
            initialValue: customerValue,
            searchPlaceholder: searchPlaceholder,
            options: filterOpts.customers.map(c => ({ value: String(c.id), label: c.full_name })),
            onChange: (v) => { customerValue = v; },
        });
    }

    filters.push({
        type: 'select',
        name: 'service',
        label: fl.service,
        initialValue: serviceValue,
        searchPlaceholder: searchPlaceholder,
        options: [{ value: '0', label: 'Custom' }].concat((filterOpts.services || []).map(s => ({ value: String(s.id), label: s.title }))),
        onChange: (v) => { serviceValue = v; },
    });

    if (filterOpts.locations && filterOpts.locations.length > 0) {
        const locOptions = [{ value: 'w/o', label: fl.noLocation }].concat(filterOpts.locations.map(l => ({ value: String(l.id), label: l.name })));
        filters.push({
            type: 'select',
            name: 'location',
            label: fl.location,
            initialValue: locationValue,
            searchPlaceholder: searchPlaceholder,
            options: locOptions,
            onChange: (v) => { locationValue = v; },
        });
    }

    filters.push({
        type: 'checkboxGroup',
        name: 'status',
        label: fl.status,
        initialValue: statusValue,
        options: filterOpts.statuses || [],
        onChange: (v) => { statusValue = v; },
    });

    function activeDateLabel(value, presets) {
        if (!value) return null;
        for (const p of presets) {
            if (p.range && p.range.tasks && value.tasks) return p.label;
            if (p.range && p.range.start && value.start && p.range.start.compare(value.start) === 0 && p.range.end.compare(value.end) === 0) {
                return p.label;
            }
        }
        return null;
    }

    let options = {
        ajax: {
            url: ajaxurl,
            method: 'POST',
            data: function () {
                return {
                    action: 'bookly_get_appointments',
                    csrf_token: BooklyL10nGlobal.csrf_token,
                    filter: {
                        date: serializeDate(dateValue),
                        date_label: activeDateLabel(dateValue, datePresets),
                        created_date: serializeDate(createdDateValue),
                        created_date_label: activeDateLabel(createdDateValue, createdDatePresets),
                        staff: staffValue,
                        customer: customerValue,
                        service: serviceValue,
                        status: statusValue,
                        location: locationValue,
                    },
                };
            },
        },
        columns: columns,
        tableSettings: Object.assign({}, BooklyL10n.datatables[table], {l10n: Object.assign({}, BooklyL10n.datatables.l10n, {zeroRecords: BooklyL10n.zeroRecords})}),
        edit: function (row) {
            BooklyAppointmentDialog.showDialog(
                row.id,
                null,
                null,
                function (event) {
                    bt.reload();
                }
            )
        },
        checked: function (rows) {
            return [
                {
                    label: BooklyL10n.delete,
                    icon: 'trash',
                    variant: 'destructive',
                    click: function (selectedRows) {
                        const data = selectedRows.map(row => ({
                            ca_id: row.ca_id ? row.ca_id : 'null',
                            id: row.id,
                        }));
                        new BooklyConfirmDeletingAppointment({
                                action: 'bookly_delete_customer_appointments',
                                data: data,
                                csrf_token: BooklyL10nGlobal.csrf_token,
                            },
                            function (response) { bt.reload(); }
                        );
                    }
                }
            ];
        },
        getId(row) {
            return row.id + '-' + parseInt(row.ca_id);
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
        filters: filters,
        topToolbar: (function () {
            const buttons = [];
            if (BooklyL10n.proEnabled) {
                buttons.push({
                    label: BooklyL10n.export,
                    icon: 'download',
                    variant: 'outline',
                    click: function () {
                        let columnsHtml = '';
                        bt.getColumns().forEach(function (column, index) {
                            columnsHtml += '<div class="custom-control custom-checkbox"><input class="custom-control-input" id="bookly-ea-' + index + '" name="exp[' + column.name + ']" type="checkbox"' + (column.show ? 'checked' : '') + '><label class="custom-control-label" for="bookly-ea-' + index + '">' + column.title + '</label></div>';
                        });
                        $('.bookly-js-columns', $exportDialog).html(columnsHtml);
                        $exportDialog.booklyModal('show');
                    }
                });
                buttons.push({
                    label: BooklyL10n.print,
                    icon: 'printer',
                    variant: 'outline',
                    click: function () {
                        let columnsHtml = '';
                        bt.getColumns().forEach(function (column, index) {
                            columnsHtml += '<div class="custom-control custom-checkbox"><input class="custom-control-input" id="bookly-pa-' + index + '" value="' + index + '" type="checkbox"' + (column.show ? 'checked' : '') + '><label class="custom-control-label" for="bookly-pa-' + index + '">' + column.title + '</label></div>';
                        });
                        $('.bookly-js-columns', $printDialog).html(columnsHtml);
                        $printDialog.booklyModal('show');
                    }
                });
            }
            buttons.push({
                id: 'bookly-new-appointment',
                label: BooklyL10n.new_appointment,
                icon: 'plus',
                variant: 'default',
                click: function () {
                    BooklyAppointmentDialog.showDialog(
                        null,
                        null,
                        moment(),
                        function (event) {
                            bt.reload();
                        }
                    );
                }
            });
            return buttons;
        })(),
        searchFilter: {
            placeholder: BooklyL10n.search,
            name: 'filter[search]',
        },
    }
    options.datePicker = BooklyL10n.datePicker;
    let bt = BooklyDatatables.showForm('bookly-' + table + '-datatables', options);

    /**
     * Export form submit. Dialog opening is handled via topToolbar callback (see options.topToolbar).
     */
    $exportForm.on('submit', function () {
        $('[name="filter"]', $exportDialog).val(JSON.stringify({
            date: serializeDate(dateValue),
            created_date: serializeDate(createdDateValue),
            staff: staffValue,
            customer: customerValue,
            service: serviceValue,
            status: statusValue,
            location: locationValue,
        }));
        $exportDialog.booklyModal('hide');

        return true;
    });

    $exportSelectAll
        .on('click', function () {
            let checked = this.checked;
            $('.bookly-js-columns input', $exportDialog).each(function () {
                $(this).prop('checked', checked);
            });
        });

    $exportDialog
        .on('change', '.bookly-js-columns input', function () {
            $exportSelectAll.prop('checked', $('.bookly-js-columns input:checked', $exportDialog).length == $('.bookly-js-columns input', $exportDialog).length);
        });

    /**
     * Print confirm. Dialog opening is handled via topToolbar callback (see options.topToolbar).
     */
    $printButton.on('click', function () {
        let columns = [];
        $('.bookly-js-columns input:checked', $printDialog).each(function () {
            columns.push(parseInt(this.value));
        });
        bt.print(columns);
    });

    $printSelectAll
        .on('click', function () {
            let checked = this.checked;
            $('.bookly-js-columns input', $printDialog).each(function () {
                $(this).prop('checked', checked);
            });
        });

    $printDialog
        .on('change', '.bookly-js-columns input', function () {
            $printSelectAll.prop('checked', $('.bookly-js-columns input:checked', $printDialog).length == $('.bookly-js-columns input', $printDialog).length);
        });

    $appointmentsList
        .on('click', '[data-action=show-payment]', function () {
            BooklyPaymentDetailsDialog.showDialog({
                payment_id: $(this).data('payment_id'),
                done: function (event) {
                    bt.reload();
                }
            });
        });
});
