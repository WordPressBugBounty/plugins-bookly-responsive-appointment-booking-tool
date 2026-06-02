<?php
namespace Bookly\Backend\Modules\Payments;

use Bookly\Lib;
use Bookly\Backend\Modules\Appointments\Proxy as AppProxy;
use Bookly\Backend\Modules\Payments\Proxy;

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
            'module' => array( 'js/payments.js' => array( 'bookly-backend-globals' ) ),
            'backend' => array( 'js/bookly-datatables.js' => array( 'bookly-backend-globals' ) ),
        ) );

        $datatables = Lib\Utils\Tables::getSettings( Lib\Utils\Tables::PAYMENTS );

        $types = Lib\Entities\Payment::getTypes();

        $providers = Lib\Entities\Staff::query()->select( 'id, full_name' )->sortBy( 'full_name' )->whereNot( 'visibility', 'archive' )->fetchArray();
        $services = Lib\Entities\Service::query()->select( 'id, title' )->sortBy( 'title' )->fetchArray();
        $customers = Lib\Entities\Customer::query()->count() < Lib\Entities\Customer::REMOTE_LIMIT
            ? array_map( function( $row ) {
                return array(
                    'id' => $row['id'],
                    'full_name' => $row['full_name'],
                );
            }, Lib\Entities\Customer::query( 'c' )->select( 'c.id, c.full_name' )->fetchArray() )
            : array();

        $type_options = array();
        foreach ( $types as $type ) {
            $type_options[] = array( 'value' => $type, 'label' => Lib\Entities\Payment::typeToString( $type ) );
        }

        $status_options = array();
        foreach ( array(
            Lib\Entities\Payment::STATUS_COMPLETED,
            Lib\Entities\Payment::STATUS_PENDING,
            Lib\Entities\Payment::STATUS_REJECTED,
            Lib\Entities\Payment::STATUS_REFUNDED,
        ) as $st ) {
            $status_options[] = array( 'value' => $st, 'label' => Lib\Entities\Payment::statusToString( $st ) );
        }

        wp_localize_script( 'bookly-payments.js', 'BooklyL10n', array(
            'datePicker' => Lib\Utils\DateTime::datePickerOptions(),
            'dateRange' => Lib\Utils\DateTime::dateRangeOptions( array( 'lastMonth' => __( 'Last month', 'bookly' ), 'appAtAnyTime' => __( 'Appointment at any time', 'bookly' ), 'anyTime' => __( 'Any time', 'bookly' ), ) ),
            'zeroRecords' => __( 'No payments for selected period and criteria.', 'bookly' ),
            'emptyTable' => __( 'No data available in table', 'bookly' ),
            'areYouSure' => __( 'Are you sure?', 'bookly' ),
            'no_result_found' => __( 'No results found', 'bookly' ),
            'searching' => __( 'Searching', 'bookly' ),
            'multiple' => __( 'See details for more items', 'bookly' ),
            'delete' => __( 'Delete', 'bookly' ) . '…',
            'rowsPerPage' => __( 'Rows per page', 'bookly' ),
            'search' => __( 'Quick search by no., customer, provider, service', 'bookly' ) . '…',
            'filters' => array(
                'id' => __( 'ID', 'bookly' ),
                'date' => __( 'Date', 'bookly' ),
                'appointment_date' => __( 'Appointment date', 'bookly' ),
                'type' => __( 'Type', 'bookly' ),
                'customer' => __( 'Customer', 'bookly' ),
                'staff' => __( 'Provider', 'bookly' ),
                'service' => __( 'Service', 'bookly' ),
                'status' => __( 'Status', 'bookly' ),
                'searchPlaceholder' => __( 'Search', 'bookly' ),
            ),
            'filterOptions' => array(
                'types' => $type_options,
                'statuses' => $status_options,
                'staff' => $providers,
                'services' => array_map( function ( $s ) { return array( 'id' => $s['id'], 'full_name' => $s['title'] ); }, $services ),
                'customers' => $customers,
            ),
            'datatables' => $datatables,
            'invoice' => array(
                'enabled' => (int) Lib\Config::invoicesActive(),
                'button' => __( 'Invoice', 'bookly' ),
                'download' => __( 'Download invoices', 'bookly' ),
                'downloadAll' => __( 'Download all invoices', 'bookly' ),
                'action' => Proxy\Invoices::getDownloadUrl() ?: '',
            ),
            'tasks' => array(
                'enabled' => Lib\Config::tasksActive(),
                'title' => AppProxy\Tasks::getFilterText(),
            ),
        ) );

        self::renderTemplate( 'index', compact( 'datatables' ) );
    }
}