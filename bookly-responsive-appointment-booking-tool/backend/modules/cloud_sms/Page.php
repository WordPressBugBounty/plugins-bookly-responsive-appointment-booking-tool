<?php
namespace Bookly\Backend\Modules\CloudSms;

use Bookly\Lib;
use Bookly\Backend\Modules\CloudProducts\Page as CloudProducts;
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
            Components\Cloud\LoginRequired\Page::render( __( 'SMS Notifications', 'bookly-responsive-appointment-booking-tool' ), self::pageSlug() );
        } else {
            self::enqueueStyles( array(
                'frontend' => array( 'css/intlTelInput.css' => array( 'bookly-backend-globals', ) ),
            ) );

            self::enqueueScripts( array(
                'frontend' => get_option( 'bookly_cst_phone_default_country' ) == 'disabled'
                    ? array()
                    : array( 'js/intlTelInput.min.js' => array( 'jquery' ) ),
                'bookly' => array( 'backend/components/cloud/account/resources/js/select-country.js' => array( 'bookly-backend-globals' ) ),
                'module' => array(
                    'js/notifications-list.js' => array( 'bookly-backend-globals', 'bookly-notification-dialog.js', ),
                    'js/sender-id-modal.js' => array( 'bookly-backend-globals' ),
                    'js/sms.js' => array( 'bookly-notifications-list.js', 'bookly-select-country.js', 'bookly-sender-id-modal.js' ),
                ),
            ) );

            // Prepare tables settings.
            $datatables = Lib\Utils\Tables::getSettings( array(
                Lib\Utils\Tables::SMS_NOTIFICATIONS,
                Lib\Utils\Tables::SMS_DETAILS,
                Lib\Utils\Tables::SMS_PRICES,
                Lib\Utils\Tables::SMS_SENDER,
                Lib\Utils\Tables::SMS_MAILING_LISTS,
                Lib\Utils\Tables::SMS_MAILING_RECIPIENTS_LIST,
                Lib\Utils\Tables::SMS_MAILING_CAMPAIGNS
            ) );

            $current_tab = self::hasParameter( 'tab' ) ? self::parameter( 'tab' ) : 'notifications';

            // Number of undelivered sms.
            $undelivered_count = Lib\Cloud\SMS::getUndeliveredSmsCount();

            wp_localize_script( 'bookly-sms.js', 'BooklyL10n',
                array(
                    'moment_format_date_time' => Lib\Utils\DateTime::convertFormat( 'date', Lib\Utils\DateTime::FORMAT_MOMENT_JS ) . ' ' . Lib\Utils\DateTime::convertFormat( 'time', Lib\Utils\DateTime::FORMAT_MOMENT_JS ),
                    'areYouSure' => __( 'Are you sure?', 'bookly-responsive-appointment-booking-tool' ),
                    'acceptable_characters' => __( 'Acceptable characters are', 'bookly-responsive-appointment-booking-tool' ) . ': a-z A-Z 0-9 . & @ - + _ ! % # [space] *',
                    'country' => $cloud->account->getCountry(),
                    'current_tab' => $current_tab,
                    'intlTelInput' => array(
                        'country' => get_option( 'bookly_cst_phone_default_country' ),
                        'enabled' => get_option( 'bookly_cst_phone_default_country' ) != 'disabled',
                    ),
                    'datePicker' => Lib\Utils\DateTime::datePickerOptions(),
                    'dateRange' => Lib\Utils\DateTime::dateRangeOptions( array( 'lastMonth' => __( 'Last month', 'bookly-responsive-appointment-booking-tool' ), ) ),
                    'sender_id' => array(
                        'sent' => __( 'Sender ID request is sent.', 'bookly-responsive-appointment-booking-tool' ),
                        'set_default' => __( 'Sender ID is reset to default.', 'bookly-responsive-appointment-booking-tool' ),
                        'select_country' => __( 'Select country', 'bookly-responsive-appointment-booking-tool' ),
                        'invalid' => __( 'Acceptable characters are', 'bookly-responsive-appointment-booking-tool' ) . ': a-z A-Z 0-9 . & @ - + _ ! % # [space] *',
                        'request' => __( 'Request Sender ID', 'bookly-responsive-appointment-booking-tool' ) . '…',
                        'cancel' => __( 'Cancel request', 'bookly-responsive-appointment-booking-tool' ) . '…',
                        'cancel_sender_id' => __( 'Cancel', 'bookly-responsive-appointment-booking-tool' ) . '…',
                        'status_pending' => __( 'Pending', 'bookly-responsive-appointment-booking-tool' ),
                        'status_approved' => __( 'Approved', 'bookly-responsive-appointment-booking-tool' ),
                        'status_declined' => __( 'Declined', 'bookly-responsive-appointment-booking-tool' ),
                        'status_cancelled' => __( 'Cancelled', 'bookly-responsive-appointment-booking-tool' ),
                    ),
                    'sender_id_modal' => array(
                        'title' => __( 'Request Sender ID', 'bookly-responsive-appointment-booking-tool' ),
                        'sender_id_label' => __( 'Sender ID', 'bookly-responsive-appointment-booking-tool' ),
                        'sender_id_placeholder' => __( 'E.g', 'bookly-responsive-appointment-booking-tool' ) . ' SpaCenter',
                        'sender_id_hint' => __( 'Can only contain letters or digits (up to 11 characters).', 'bookly-responsive-appointment-booking-tool' ) . ' ' . __( 'Acceptable characters are', 'bookly-responsive-appointment-booking-tool' ) . ': a-z A-Z 0-9 . & @ - + _ ! % # [space] *',
                        'country_label' => __( 'Destination country', 'bookly-responsive-appointment-booking-tool' ),
                        'country_placeholder' => __( 'Search and select country', 'bookly-responsive-appointment-booking-tool' ) . '…',
                        'country_search_placeholder' => __( 'Type country name', 'bookly-responsive-appointment-booking-tool' ) . '…',
                        'documents_legend' => __( 'Countries marked with this icon require extra steps.', 'bookly-responsive-appointment-booking-tool' ),
                        'no_country_found' => __( 'No country found.', 'bookly-responsive-appointment-booking-tool' ),
                        'noreg_desc' => __( 'Once approved, your Sender ID will work in %1$s and %2$d other countries.', 'bookly-responsive-appointment-booking-tool' ),
                        'see_all_countries' => __( 'See all %d countries', 'bookly-responsive-appointment-booking-tool' ),
                        'hide_list' => __( 'Hide list', 'bookly-responsive-appointment-booking-tool' ),
                        'confirmation_code_label' => __( 'Confirmation code', 'bookly-responsive-appointment-booking-tool' ),
                        'cancel' => __( 'Cancel', 'bookly-responsive-appointment-booking-tool' ),
                        'request' => __( 'Request', 'bookly-responsive-appointment-booking-tool' ),
                        'error' => __( 'Request failed. Please try again.', 'bookly-responsive-appointment-booking-tool' ),
                    ),
                    'zeroRecords' => __( 'No records for selected period.', 'bookly-responsive-appointment-booking-tool' ),
                    'zeroRecordsAlt' => __( 'No matching records found', 'bookly-responsive-appointment-booking-tool' ),
                    'noResults' => __( 'No records.', 'bookly-responsive-appointment-booking-tool' ),
                    'emptyTable' => __( 'No data available in table', 'bookly-responsive-appointment-booking-tool' ),
                    'quick_search' => __( 'Quick search by name', 'bookly-responsive-appointment-booking-tool' ) . '…',
                    'processing' => __( 'Processing', 'bookly-responsive-appointment-booking-tool' ) . '…',
                    'state' => array( __( 'Disabled', 'bookly-responsive-appointment-booking-tool' ), __( 'Enabled', 'bookly-responsive-appointment-booking-tool' ) ),
                    'action' => array( __( 'enable', 'bookly-responsive-appointment-booking-tool' ), __( 'disable', 'bookly-responsive-appointment-booking-tool' ) ),
                    'edit' => __( 'Edit', 'bookly-responsive-appointment-booking-tool' ),
                    'run' => __( 'Start Now', 'bookly-responsive-appointment-booking-tool' ),
                    'manual' => __( 'Manual', 'bookly-responsive-appointment-booking-tool' ),
                    'settingsSaved' => __( 'Settings saved.', 'bookly-responsive-appointment-booking-tool' ),
                    'na' => __( 'N/A', 'bookly-responsive-appointment-booking-tool' ),
                    'campaign' => array(
                        'pending' => __( 'Pending', 'bookly-responsive-appointment-booking-tool' ),
                        'waiting' => __( 'Ready to send', 'bookly-responsive-appointment-booking-tool' ),
                        'in_progress' => __( 'In progress', 'bookly-responsive-appointment-booking-tool' ),
                        'completed' => __( 'Completed', 'bookly-responsive-appointment-booking-tool' ),
                        'canceled' => __( 'Canceled', 'bookly-responsive-appointment-booking-tool' ),
                    ),
                    'new_notification' => __( 'New notification', 'bookly-responsive-appointment-booking-tool' ) . '…',
                    'new_campaign' => __( 'New campaign', 'bookly-responsive-appointment-booking-tool' ) . '…',
                    'new_mailing_list' => __( 'New list', 'bookly-responsive-appointment-booking-tool' ) . '…',
                    'new_recipients' => __( 'Add recipients', 'bookly-responsive-appointment-booking-tool' ) . '…',
                    'back_to_lists' => __( 'Back to lists', 'bookly-responsive-appointment-booking-tool' ),
                    'delete' => __( 'Delete', 'bookly-responsive-appointment-booking-tool' ) . '…',
                    'enable' => __( 'Enable', 'bookly-responsive-appointment-booking-tool' ),
                    'disable' => __( 'Disable', 'bookly-responsive-appointment-booking-tool' ),
                    'rowsPerPage' => __( 'Rows per page', 'bookly-responsive-appointment-booking-tool' ),
                    'filters' => array(
                        'date' => __( 'Date', 'bookly-responsive-appointment-booking-tool' ),
                        'status' => __( 'Status', 'bookly-responsive-appointment-booking-tool' ),
                    ),
                    'resend' => __( 'Resend', 'bookly-responsive-appointment-booking-tool' ),
                    'gateway' => 'sms',
                    'default' => __( 'Default', 'bookly-responsive-appointment-booking-tool' ),
                    'datatables' => $datatables,
                )
            );
            $sms = $cloud->getProduct( Lib\Cloud\Account::PRODUCT_SMS_NOTIFICATIONS );
            self::renderTemplate( 'index', compact( 'sms', 'datatables', 'undelivered_count' ) );
        }
    }

    /**
     * Show 'SMS Notifications' submenu with counter inside Bookly main menu.
     */
    public static function addBooklyMenuItem()
    {
        $sms = __( 'SMS Notifications', 'bookly-responsive-appointment-booking-tool' );

        $cloud = Lib\Cloud\API::getInstance();

        $promotion = $cloud->general->getPromotionForNotice();
        if ( $promotion ) {
            $title = sprintf( '%s <span class="update-plugins"><span class="update-count">$</span></span>', $sms );
        } else {
            $count = get_option( 'bookly_cloud_badge_consider_sms' ) ? Lib\Cloud\SMS::getUndeliveredSmsCount() : 0;
            $title = $count ? sprintf( '%s <span class="update-plugins"><span class="update-count">%d</span></span>', $sms, $count ) : $sms;
        }

        $page = $cloud->getToken() && $cloud->account->productActive( Lib\Cloud\Account::PRODUCT_SMS_NOTIFICATIONS ) ? self::pageSlug() : CloudProducts::pageSlug();

        add_submenu_page(
            'bookly-menu',
            $sms,
            '<span id="bookly-js-sms-menu-redirect">' . $title . '</span><script>document.getElementById("bookly-js-sms-menu-redirect").parentNode.href+="=' . $page . '";</script>',
            Lib\Utils\Common::getRequiredCapability(),
            '',
            function() { Page::render(); }
        );
    }

    /**
     * Show 'SMS Notifications' submenu with counter inside Bookly Cloud main menu.
     *
     * @param array $product
     */
    public static function addBooklyCloudMenuItem( $product )
    {
        $sms = $product['texts']['title'];

        $count = get_option( 'bookly_cloud_badge_consider_sms' ) ? Lib\Cloud\SMS::getUndeliveredSmsCount() : 0;
        $title = $count ? sprintf( '%s <span class="update-plugins"><span class="update-count">%d</span></span>', $sms, $count ) : $sms;

        add_submenu_page(
            'bookly-cloud-menu',
            $sms,
            $title,
            Lib\Utils\Common::getRequiredCapability(),
            self::pageSlug(),
            function() { Page::render(); }
        );
    }
}