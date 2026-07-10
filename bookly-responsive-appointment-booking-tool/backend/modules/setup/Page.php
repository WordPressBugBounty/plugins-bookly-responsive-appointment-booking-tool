<?php
namespace Bookly\Backend\Modules\Setup;

use Bookly\Lib;
use Bookly\Lib\Utils\DateTime;
use Bookly\Backend\Modules\Calendar\Page as CalendarPage;

class Page extends Lib\Base\Component
{
    /**
     * Render page.
     */
    public static function render()
    {
        $tel_input_enabled = get_option( 'bookly_cst_phone_default_country' ) != 'disabled';

        self::enqueueStyles( array(
            'frontend' => $tel_input_enabled
                ? array( 'css/intlTelInput.css' )
                : array(),
            'backend' => array( 'css/fontawesome-all.min.css' => array( 'bookly-backend-globals' ) ),
        ) );

        self::enqueueScripts( array(
            'frontend' => $tel_input_enabled
                ? array( 'js/intlTelInput.min.js' => array( 'jquery' ) )
                : array(),
            'bookly' => array( 'backend/components/cloud/account/resources/js/select-country.js' => array( 'bookly-backend-globals' ) ),
            'module' => array( 'js/setup.js' => array( 'bookly-backend-globals' ) ),
        ) );

        $durations = array();
        for ( $j = 15; $j <= 60; $j += 15 ) {
            $durations[] = array( 'title' => DateTime::secondsToInterval( $j * 60 ), 'value' => $j * 60 );
        }
        for ( $j = 60 * 2; $j <= 60 * 12; $j += 60 ) {
            $durations[] = array( 'title' => DateTime::secondsToInterval( $j * 60 ), 'value' => $j * 60 );
        }
        for ( $j = 60 * 24; $j <= 60 * 24 * 7; $j += 60 * 24 ) {
            $durations[] = array( 'title' => DateTime::secondsToInterval( $j * 60 ), 'value' => $j * 60 );
        }

        $timeslot_options = array();
        foreach ( Lib\Config::getTimeSlotLengthOptions() as $duration ) {
            $timeslot_options[] = array( $duration, Lib\Utils\DateTime::secondsToInterval( $duration * MINUTE_IN_SECONDS ) );
        }

        wp_localize_script( 'bookly-setup.js', 'BooklyL10nSetupForm', Proxy\Pro::prepareOptions( array(
            'step' => get_option( 'bookly_setup_step', 1 ),
            'intlTelInput' => array(
                'enabled' => $tel_input_enabled,
                'country' => get_option( 'bookly_cst_phone_default_country' ),
            ),
            'finish_url' => add_query_arg( array( 'page' => CalendarPage::pageSlug() ), admin_url( 'admin.php' ) ),
            'durations' => $durations,
            'timeslot_options' => $timeslot_options,
            'timeslot_length' => (int) get_option( 'bookly_gen_time_slot_length', 15 ),
            'currencies' => Lib\Utils\Price::getCurrencies(),
            'currency' => Lib\Config::getCurrency(),
            'moment_format_date' => DateTime::convertFormat( 'date', DateTime::FORMAT_MOMENT_JS ),
            'moment_format_time' => DateTime::convertFormat( 'time', DateTime::FORMAT_MOMENT_JS ),
            'color' => get_option( 'bookly_app_color', '#f4662f' ),
            'cloud_logged_in' => Lib\Cloud\API::getInstance()->account->loadProfile() ? esc_html( Lib\Cloud\API::getInstance()->account->getUserName() ) : false,
            'l10n' => array(
                'staff_name' => __( 'Full name', 'bookly-responsive-appointment-booking-tool' ),
                'staff_email' => __( 'Email', 'bookly-responsive-appointment-booking-tool' ),
                'staff_phone' => __( 'Phone', 'bookly-responsive-appointment-booking-tool' ),
                'service_title' => __( 'Title', 'bookly-responsive-appointment-booking-tool' ),
                'service_duration' => __( 'Duration', 'bookly-responsive-appointment-booking-tool' ),
                'new_service' => __( 'New service', 'bookly-responsive-appointment-booking-tool' ) . '…',
                'required' => __( 'Required', 'bookly-responsive-appointment-booking-tool' ),
                'continue' => __( 'Continue', 'bookly-responsive-appointment-booking-tool' ),
                'finish' => __( 'Finish', 'bookly-responsive-appointment-booking-tool' ),
                'skip' => __( 'Skip', 'bookly-responsive-appointment-booking-tool' ),
                'back' => __( 'Back', 'bookly-responsive-appointment-booking-tool' ),
                'cancel' => __( 'Cancel', 'bookly-responsive-appointment-booking-tool' ),
                'delete' => __( 'Delete', 'bookly-responsive-appointment-booking-tool' ) . '…',
                'sms_text' => __( 'Reduce no-shows, keep your staff and customers informed, and send timely reminders - use SMS Notifications. Send a free test message!', 'bookly-responsive-appointment-booking-tool' ),
                'sms_success_text' =>sprintf( '%s<br/>%s', __( 'Your message has been sent successfully. You can manage your messages in the \'SMS Notifications\' section.', 'bookly-responsive-appointment-booking-tool' ), __(' Give it a try!', 'bookly-responsive-appointment-booking-tool' ) ),
                'send_sms' => __( 'Send SMS', 'bookly-responsive-appointment-booking-tool' ),
                'welcome_title' => __( 'Welcome to Bookly!', 'bookly-responsive-appointment-booking-tool' ),
                'welcome_text' => sprintf( '%s<br/><br/>%s<br/><br/>%s',
                    __( 'As the ultimate appointment booking plugin for online scheduling, Bookly is designed to help you effortlessly manage your booking calendar, services, and client base.', 'bookly-responsive-appointment-booking-tool' ),
                    __( 'This introduction will guide you through the essential configuration steps to get you started quickly. ', 'bookly-responsive-appointment-booking-tool' ),
                    sprintf( __( 'You can optionally skip this wizard and refer to %s or watch our %s to learn the basics and get the most out of Bookly.', 'bookly-responsive-appointment-booking-tool' ), sprintf( '<a href="%s" target="_blank">%s</a>', 'https://hub.bookly.pro/go/bookly-help-center', __( 'Bookly Help Center', 'bookly-responsive-appointment-booking-tool' ) ), sprintf( '<a href="%s" target="_blank">%s</a>', 'https://hub.bookly.pro/go/bookly-youtube', __( 'Video Tutorials', 'bookly-responsive-appointment-booking-tool' ) ) )
                ),
                'business_hours' => __( 'Set your company business hours. This schedule will serve as a template for all new staff members.', 'bookly-responsive-appointment-booking-tool' ),
                'to' => __( 'to', 'bookly-responsive-appointment-booking-tool' ),
                'time_interval' => __( 'Select a time interval to be used as a step when creating all time slots in the system.', 'bookly-responsive-appointment-booking-tool' ),
                'currency' => __( 'Select the currency for the prices of your services.', 'bookly-responsive-appointment-booking-tool' ),
                'staff_text' => __( 'In Bookly, \'staff\' refers to any employee or resource that provides services to your clients, such as a consultant, therapist, or any other service provider. Adding staff members allows you to manage their schedules, assign services to them, and track their appointments.', 'bookly-responsive-appointment-booking-tool' ),
                'services_text' => __( 'In Bookly, a \'service\' refers to the various offerings or activities your business provides to clients, such as consultations, treatments, classes, or other services. By adding services, you can manage their duration, cost, and assign them to specific employees.', 'bookly-responsive-appointment-booking-tool' ),
                'cloud_text' => sprintf( '%s<br/><br/>%s<br/><br/>%s<br/><br/>%s',
                    __( 'Bookly Cloud is an integral part of the Bookly booking system, offering a range of additional products and features for efficient appointment management and automation.', 'bookly-responsive-appointment-booking-tool' ),
                    __( 'Features like SMS Notifications, Zapier integration, Stripe and Square Payments, Gift Cards, Voice and WhatsApp Notifications are designed to enhance your operations and boost your online business.', 'bookly-responsive-appointment-booking-tool' ),
                    sprintf( __( 'Discover the full range of <a href="%s" target="_blank">powerful tools here</a>.', 'bookly-responsive-appointment-booking-tool' ), 'https://hub.bookly.pro/go/bookly-cloud-overview' ),
                    __( 'Sign up today and enjoy a welcome bonus! ', 'bookly-responsive-appointment-booking-tool' )
                ),
                'create_account' => __( 'Create an account', 'bookly-responsive-appointment-booking-tool' ),
                'login' => __( 'Already registered', 'bookly-responsive-appointment-booking-tool' ),
                'email' => __( 'Email', 'bookly-responsive-appointment-booking-tool' ),
                'password' => __( 'Password', 'bookly-responsive-appointment-booking-tool' ),
                'confirm_password' => __( 'Confirm password', 'bookly-responsive-appointment-booking-tool' ),
                'country' => __( 'Country', 'bookly-responsive-appointment-booking-tool' ),
                'cloud_tos' => sprintf( __( 'I accept <a href="%1$s" target="_blank">Service Terms</a> and <a href="%2$s" target="_blank">Privacy Policy</a>', 'bookly-responsive-appointment-booking-tool' ), 'https://www.booking-wp-plugin.com/terms/', 'https://www.booking-wp-plugin.com/privacy/' ),
                'forgot_password' => __( 'Forgot password', 'bookly-responsive-appointment-booking-tool' ),
                'forgot_actions' => array(
                    __( 'Send recovery code', 'bookly-responsive-appointment-booking-tool' ),
                    __( 'Verify recovery code', 'bookly-responsive-appointment-booking-tool' ),
                    __( 'Set new password', 'bookly-responsive-appointment-booking-tool' ),
                ),
                'recovery_code' => __( 'Recovery code', 'bookly-responsive-appointment-booking-tool' ),
                'new_password' => __( 'New password', 'bookly-responsive-appointment-booking-tool' ),
                'logged_in' => __( 'You are currently logged into Bookly Cloud with the account', 'bookly-responsive-appointment-booking-tool' ),
                'steps' => array(
                    __( 'Greetings', 'bookly-responsive-appointment-booking-tool' ),
                    __( 'General', 'bookly-responsive-appointment-booking-tool' ),
                    __( 'Staff', 'bookly-responsive-appointment-booking-tool' ),
                    __( 'Service', 'bookly-responsive-appointment-booking-tool' ),
                    __( 'Cloud', 'bookly-responsive-appointment-booking-tool' ),
                    __( 'SMS', 'bookly-responsive-appointment-booking-tool' ),
                    __( 'Done', 'bookly-responsive-appointment-booking-tool' ),
                ),
                'off' => __( 'OFF', 'bookly-responsive-appointment-booking-tool' ),
                'done' => array(
                    'string_1' => __( 'The initial setup is complete, and you can now continue using Bookly in the admin panel.', 'bookly-responsive-appointment-booking-tool' ),
                    'string_2' => __( 'You can create staff members and services, add appointments to the calendar, and manage them directly from the backend.', 'bookly-responsive-appointment-booking-tool' ),
                    'string_3' => __( 'To start receiving appointments via the front-end booking form, follow these steps:', 'bookly-responsive-appointment-booking-tool' ),
                    'string_4' => __( 'Create a new page in WordPress.', 'bookly-responsive-appointment-booking-tool' ),
                    'string_5' => sprintf( __( 'Insert the following shortcode: %s', 'bookly-responsive-appointment-booking-tool' ), '<input type="text" class="form-control d-inline" value="[bookly-form]" readonly style="max-width: 124px;">' ),
                    'string_6' => __( 'Save the page and visit it to see your booking form in action.', 'bookly-responsive-appointment-booking-tool' ),
                    'string_7' => sprintf( __( 'Have questions? Visit our %s or reach out to our %s.', 'bookly-responsive-appointment-booking-tool' ), sprintf( '<a href="https://support.booking-wp-plugin.com/hc/en-us/articles/212800185">%s</a>', __( 'Help center', 'bookly-responsive-appointment-booking-tool' ) ), sprintf( '<a href="https://hub.bookly.pro/go/bookly-request-support">%s</a>', __( 'Support Team', 'bookly-responsive-appointment-booking-tool' ) ) ),
                ),
            ),
        ) ) );

        self::renderTemplate( 'index' );
    }
}