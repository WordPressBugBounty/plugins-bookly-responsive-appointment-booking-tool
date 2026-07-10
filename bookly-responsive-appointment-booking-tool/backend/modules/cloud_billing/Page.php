<?php
namespace Bookly\Backend\Modules\CloudBilling;

use Bookly\Lib;
use Bookly\Backend\Components;

class Page extends Lib\Base\Component
{
    /**
     * Render page.
     */
    public static function render()
    {
        $cloud = Lib\Cloud\API::getInstance();
        if ( ! $cloud->account->loadProfile() ) {
            Components\Cloud\LoginRequired\Page::render( __( 'Bookly Cloud Billing', 'bookly-responsive-appointment-booking-tool' ), self::pageSlug() );
        } else {
            self::enqueueStyles( array(
                'alias'   => array( 'bookly-backend-globals', ),
            ) );

            self::enqueueScripts( array(
                'module'  => array( 'js/cloud-billing.js' => array( 'bookly-backend-globals' ), ),
            ) );

            $datatables = Lib\Utils\Tables::getSettings( Lib\Utils\Tables::CLOUD_PURCHASES );

            $invoice_data = Lib\Cloud\API::getInstance()->account->getInvoiceData();

            wp_localize_script( 'bookly-cloud-billing.js', 'BooklyL10n', array(
                'zeroRecords' => __( 'No records for selected period.', 'bookly-responsive-appointment-booking-tool' ),
                'search'      => __( 'Quick search', 'bookly-responsive-appointment-booking-tool' ),
                'datePicker'  => Lib\Utils\DateTime::datePickerOptions(),
                'dateRange'   => Lib\Utils\DateTime::dateRangeOptions( array( 'lastMonth' => __( 'Last month', 'bookly-responsive-appointment-booking-tool' ), ) ),
                'filters'     => array(
                    'date' => __( 'Date', 'bookly-responsive-appointment-booking-tool' ),
                ),
                'invoice'     => array(
                    'button' => __( 'Invoice', 'bookly-responsive-appointment-booking-tool' ),
                    'alert'  => __( 'To generate an invoice you should fill in company information in Bookly Cloud settings -> Invoice', 'bookly-responsive-appointment-booking-tool' ),
                    'link'   => $cloud->account->getInvoiceLink(),
                    'valid'  => isset ( $invoice_data['company_name'], $invoice_data['company_address'] ) && $invoice_data['company_name'] != '' && $invoice_data['company_address'] != '',
                ),
                'datatables'  => $datatables,
            ) );

            self::renderTemplate( 'index', compact( 'datatables' ) );
        }
    }
}
