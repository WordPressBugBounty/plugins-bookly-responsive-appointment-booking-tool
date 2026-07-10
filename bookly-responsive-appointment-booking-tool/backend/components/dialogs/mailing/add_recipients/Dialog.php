<?php
namespace Bookly\Backend\Components\Dialogs\Mailing\AddRecipients;

use Bookly\Lib;
use Bookly\Backend\Components\Controls\Buttons;

class Dialog extends Lib\Base\Component
{
    /**
     * Render add recipients dialog.
     */
    public static function render()
    {
        self::enqueueScripts( array(
            'module' => array( 'js/add-recipients.js' => array( 'bookly-backend-globals' ), ),
        ) );

        $range = array(
            array( -365, __( 'year', 'bookly-responsive-appointment-booking-tool' ) . ' ' . __( 'ago', 'bookly-responsive-appointment-booking-tool' ) ),
            array( -122, sprintf( _n( '%d month', '%d months', 4, 'bookly-responsive-appointment-booking-tool' ), 4 ) . ' ' . __( 'ago', 'bookly-responsive-appointment-booking-tool' ) ),
            array( -92, sprintf( _n( '%d month', '%d months', 3, 'bookly-responsive-appointment-booking-tool' ), 3 ) . ' ' . __( 'ago', 'bookly-responsive-appointment-booking-tool' ) ),
            array( -61, sprintf( _n( '%d month', '%d months', 2, 'bookly-responsive-appointment-booking-tool' ), 2 ) . ' ' . __( 'ago', 'bookly-responsive-appointment-booking-tool' ) ),
        );
        foreach ( array_merge( array( -28, -21 ), range( -14, -1 ) ) as $days ) {
            $range[] = array( $days, Lib\Utils\DateTime::secondsToInterval( abs( $days ) * DAY_IN_SECONDS ) . ' ' . __( 'ago', 'bookly-responsive-appointment-booking-tool' ) );
        }
        $range[] = array( 0, __( 'Any', 'bookly-responsive-appointment-booking-tool' ) );
        foreach ( array_merge( range( 1, 14 ), array( 21, 28 ) ) as $days ) {
            $range[] = array( $days, __( 'in', 'bookly-responsive-appointment-booking-tool' ) . ' ' . Lib\Utils\DateTime::secondsToInterval( $days * DAY_IN_SECONDS ) );
        }
        $range[] = array( 61, sprintf( __( 'in', 'bookly-responsive-appointment-booking-tool' ) . ' ' . _n( '%d month', '%d months', 2, 'bookly-responsive-appointment-booking-tool' ), 2 ) );
        $range[] = array( 91, __( 'in', 'bookly-responsive-appointment-booking-tool' ) . ' ' . sprintf( _n( '%d month', '%d months', 3, 'bookly-responsive-appointment-booking-tool' ), 3 ) );
        $range[] = array( 122, __( 'in', 'bookly-responsive-appointment-booking-tool' ) . ' ' . sprintf( _n( '%d month', '%d months', 4, 'bookly-responsive-appointment-booking-tool' ), 4 ) );
        $range[] = array( 365, __( 'in', 'bookly-responsive-appointment-booking-tool' ) . ' ' . __( 'year', 'bookly-responsive-appointment-booking-tool' ) );

        wp_localize_script( 'bookly-add-recipients.js', 'BooklyL10nAddRecipientsDialog', array(
            'service' => Lib\Utils\Common::getServiceDataForDropDown( 's.type = "simple"' ),
            'staff' => Lib\Config::proActive() ? Lib\Proxy\Pro::getStaffDataForDropDown() : array( array( 'name' => '', 'items' => Lib\Entities\Staff::query()->select( 'id, full_name' )->whereNot( 'visibility', 'archive' )->sortBy( 'position, id' )->fetchArray(), ), ),
            'range' => $range,
            'l10n' => array(
                'recipients' => __( 'Recipients', 'bookly-responsive-appointment-booking-tool' ),
                'add_recipients' => __( 'Add recipients', 'bookly-responsive-appointment-booking-tool' ),
                'cancel' => __( 'Cancel', 'bookly-responsive-appointment-booking-tool' ),
                'automatic' => __( 'Automatic selection', 'bookly-responsive-appointment-booking-tool' ),
                'manual' => __( 'Manual selection', 'bookly-responsive-appointment-booking-tool' ),
                'recipients_placeholder' => __( 'Add phone numbers using international phone format, one number per line.', 'bookly-responsive-appointment-booking-tool' ) . PHP_EOL . __( 'E.g', 'bookly-responsive-appointment-booking-tool' ) . ':' . PHP_EOL . '+12021111111' . PHP_EOL . '+12021111112',
                'manual_help' => sprintf( __( 'You can add no more than %s contacts', 'bookly-responsive-appointment-booking-tool' ), 500 ),
                'automatic_help' => __( 'Please note that only customers who meet all of the conditions will be added to the list. You can find more information in our documentation', 'bookly-responsive-appointment-booking-tool' ),
                'sum_of_payments' => __( 'Total sum of payments, greater or equal than', 'bookly-responsive-appointment-booking-tool' ),
                'count_of_appointments' => __( 'Total number of appointments, greater or equal than', 'bookly-responsive-appointment-booking-tool' ),
                'providers' => __( 'Providers', 'bookly-responsive-appointment-booking-tool' ),
                'services' => __( 'Services', 'bookly-responsive-appointment-booking-tool' ),
                'last_appointment' => __( 'Last appointment', 'bookly-responsive-appointment-booking-tool' ),
                'all_services' => __( 'All services', 'bookly-responsive-appointment-booking-tool' ),
                'all_staff' => __( 'All staff', 'bookly-responsive-appointment-booking-tool' ),
                'no_service_selected' => __( 'No service selected', 'bookly-responsive-appointment-booking-tool' ),
                'no_staff_selected' => __( 'No staff selected', 'bookly-responsive-appointment-booking-tool' ),
                'custom' => __( 'Custom', 'bookly-responsive-appointment-booking-tool' ),
            ),
        ) );
    }

    /**
     * Render button
     */
    public static function renderAddRecipientsButton()
    {
        print '<div class="col-auto">';
        Buttons::renderAdd( 'bookly-js-add-recipients', 'btn-success', __( 'Add recipients', 'bookly-responsive-appointment-booking-tool' ) );
        print '</div>';
    }
}