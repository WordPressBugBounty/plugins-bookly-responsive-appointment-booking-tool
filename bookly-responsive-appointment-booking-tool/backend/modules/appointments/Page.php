<?php
namespace Bookly\Backend\Modules\Appointments;

use Bookly\Lib;
use Bookly\Lib\Entities\CustomerAppointment;

class Page extends Lib\Base\Component
{
    /**
     * Render page.
     */
    public static function render()
    {
        self::enqueueStyles( array(
            'alias' => array( 'bookly-backend-globals', ),
            'backend' => array( 'tailwind/tailwind.css' ),
        ) );

        self::enqueueScripts( array(
            'module' => array( 'js/appointments.js' => array( 'bookly-backend-globals' ) ),
            'frontend' => array( 'js/intlTelInput.min.js' => array( 'bookly-backend-globals' ) ),
            'backend' => array( 'js/bookly-datatables.js' => array( 'bookly-backend-globals' ) ),
        ) );

        $datatables = Lib\Utils\Tables::getSettings( Lib\Utils\Tables::APPOINTMENTS );

        // Filter options data
        $staff_members = Lib\Entities\Staff::query( 's' )->select( 's.id, s.full_name' )->whereNot( 'visibility', 'archive' )->fetchArray();
        // When the customer base is large we don't preload the whole list into the
        // filter dropdown — it switches to remote (typeahead) mode backed by the
        // bookly_get_customers_list AJAX endpoint. Otherwise the static list is used.
        $customers_remote = Lib\Entities\Customer::query()->count() >= Lib\Entities\Customer::REMOTE_LIMIT;
        $customers = $customers_remote
            ? array()
            : Lib\Entities\Customer::query( 'c' )->select( 'c.id, c.full_name' )->fetchArray();
        $services = Lib\Entities\Service::query( 's' )->select( 's.id, s.title' )->where( 'type', Lib\Entities\Service::TYPE_SIMPLE )->fetchArray();

        $locations = Proxy\Locations::getFilterOptions();
        if ( ! is_array( $locations ) ) {
            $locations = array();
        }

        $statuses = array();
        foreach ( CustomerAppointment::getStatuses() as $status ) {
            $statuses[] = array(
                'value' => $status,
                'label' => CustomerAppointment::statusToString( $status ),
            );
        }

        wp_localize_script( 'bookly-appointments.js', 'BooklyL10n', array(
            'datePicker' => Lib\Utils\DateTime::datePickerOptions(),
            'dateRange' => Lib\Utils\DateTime::dateRangeOptions( array( 'anyTime' => __( 'Any time', 'bookly' ), 'createdAtAnyTime' => __( 'Created at any time', 'bookly' ), ) ),
            'are_you_sure' => __( 'Are you sure?', 'bookly' ),
            'search' => __( 'Quick search by ID, customer, staff, service', 'bookly' ) . '…',
            'zeroRecords' => __( 'No appointments for selected period.', 'bookly' ),
            'processing' => __( 'Processing', 'bookly' ) . '…',
            'edit' => __( 'Edit', 'bookly' ) . '…',
            'no_result_found' => __( 'No results found', 'bookly' ),
            'new_appointment' => __( 'New appointment', 'bookly' ) . '…',
            'searching' => __( 'Searching', 'bookly' ),
            'attachments' => __( 'Attachments', 'bookly' ),
            'tasks' => array(
                'enabled' => Lib\Config::tasksActive(),
                'title' => Proxy\Tasks::getFilterText(),
            ),
            'filters' => array(
                'date' => __( 'Date', 'bookly' ),
                'created' => __( 'Created', 'bookly' ),
                'status' => __( 'Status', 'bookly' ),
                'customer' => __( 'Customer', 'bookly' ),
                'staff' => __( 'Employee', 'bookly' ),
                'service' => __( 'Service', 'bookly' ),
                'location' => __( 'Location', 'bookly' ),
                'searchPlaceholder' => __( 'Search', 'bookly' ). '…',
                'noLocation' => __( 'W/o location', 'bookly' ),
            ),
            'filterOptions' => array(
                'staff' => $staff_members,
                'customers' => $customers,
                'customersRemote' => $customers_remote,
                'services' => $services,
                'locations' => $locations,
                'statuses' => $statuses,
            ),
            'delete' => __( 'Delete', 'bookly' ) . '…',
            'export' => __( 'Export', 'bookly' ) . '…',
            'print' => __( 'Print', 'bookly' ) . '…',
            'reorder' => _x( 'Reorder', 'order of elements', 'bookly' ),
            'proEnabled' => Lib\Config::proActive(),
            'datatables' => $datatables,
        ) );

        self::renderTemplate( 'index', compact( 'datatables' ) );
    }
}