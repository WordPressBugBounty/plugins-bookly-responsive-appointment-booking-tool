<?php
namespace Bookly\Backend\Components\Dialogs\Mailing\Campaign;

use Bookly\Lib;
use Bookly\Backend\Components\Controls\Buttons;

class Dialog extends Lib\Base\Component
{
    /**
     * Render campaign dialog.
     */
    public static function render()
    {
        self::enqueueStyles( array(
            'backend' => array( 'css/fontawesome-all.min.css' => array( 'bookly-backend-globals' ), ),
            'bookly' => array( 'backend/components/ace/resources/css/ace.css', ),
        ) );

        self::enqueueScripts( array(
            'bookly' => array(
                'backend/components/ace/resources/js/ace.js' => array(),
                'backend/components/ace/resources/js/ext-language_tools.js' => array(),
                'backend/components/ace/resources/js/mode-bookly.js' => array(),
                'backend/components/ace/resources/js/editor.js' => array( 'bookly-campaign-dialog.js' ),
            ),
            'module' => array( 'js/campaign-dialog.js' => array( 'bookly-backend-globals' ), ),
        ) );


        wp_localize_script( 'bookly-campaign-dialog.js', 'BooklyL10nCampaignDialog', array(
            'datePicker' => Lib\Utils\DateTime::datePickerOptions(),
            'moment_format_date' => Lib\Utils\DateTime::convertFormat( 'date', Lib\Utils\DateTime::FORMAT_MOMENT_JS ),
            'moment_format_time' => Lib\Utils\DateTime::convertFormat( 'time', Lib\Utils\DateTime::FORMAT_MOMENT_JS ),
            'codes' => json_encode( array(
                'client_name' => array( 'description' => __( 'Full name of client', 'bookly-responsive-appointment-booking-tool' ), 'if' => true ),
                'client_first_name' => array( 'description' => __( 'First name of client', 'bookly-responsive-appointment-booking-tool' ), 'if' => true ),
                'client_last_name' => array( 'description' => __( 'Last name of client', 'bookly-responsive-appointment-booking-tool' ), 'if' => true ),
                'client_phone' => array( 'description' => __( 'Phone of client', 'bookly-responsive-appointment-booking-tool' ), 'if' => true ),
                'company_address' => array( 'description' => __( 'Address of company', 'bookly-responsive-appointment-booking-tool' ), 'if' => true ),
                'company_name' => array( 'description' => __( 'Name of company', 'bookly-responsive-appointment-booking-tool' ), 'if' => true ),
                'company_phone' => array( 'description' => __( 'Company phone', 'bookly-responsive-appointment-booking-tool' ), 'if' => true ),
                'company_website' => array( 'description' => __( 'Company web-site address', 'bookly-responsive-appointment-booking-tool' ), 'if' => true ),
            ) ),
            'l10n' => array(
                'new_campaign' => __( 'New campaign', 'bookly-responsive-appointment-booking-tool' ),
                'edit_campaign' => __( 'Edit campaign', 'bookly-responsive-appointment-booking-tool' ),
                'save' => __( 'Save', 'bookly-responsive-appointment-booking-tool' ),
                'create' => __( 'Create', 'bookly-responsive-appointment-booking-tool' ),
                'cancel' => __( 'Cancel', 'bookly-responsive-appointment-booking-tool' ),
                'close' => __( 'Close', 'bookly-responsive-appointment-booking-tool' ),
                'name' => __( 'Name', 'bookly-responsive-appointment-booking-tool' ),
                'start_campaign' => __( 'Start campaign', 'bookly-responsive-appointment-booking-tool' ),
                'manual' => __( 'Manual', 'bookly-responsive-appointment-booking-tool' ),
                'start_sending_at' => __( 'Start sending messages at', 'bookly-responsive-appointment-booking-tool' ),
                'start_sending_help' => __( 'Set the time when the mailing will start', 'bookly-responsive-appointment-booking-tool' ),
                'start_time' => __( 'Start time', 'bookly-responsive-appointment-booking-tool' ),
                'recipients' => __( 'Recipients', 'bookly-responsive-appointment-booking-tool' ),
                'sms_text' => __( 'Sms text', 'bookly-responsive-appointment-booking-tool' ),
                'campaign' => __( 'Campaign', 'bookly-responsive-appointment-booking-tool' ),
                'cancel_campaign' => __( 'Cancel campaign', 'bookly-responsive-appointment-booking-tool' ) . '…',
                'are_you_sure' => __( 'Are you sure?', 'bookly-responsive-appointment-booking-tool' ),
                'start_now_text' => __( 'You\'re about to send an SMS Campaign. If you\'re sure about the setup, click \'Start Now\'. Otherwise, please take a moment to review the details.', 'bookly-responsive-appointment-booking-tool' ),
                'run' => __( 'Start Now', 'bookly-responsive-appointment-booking-tool' ),
                'doc_hint' => sprintf( __( 'Start typing "{" to see the available codes. For more information, see the <a href="%s" target="_blank">documentation</a> page', 'bookly-responsive-appointment-booking-tool' ), 'https://hub.bookly.pro/go/bookly-sms-campaigns' ),
            ),
        ) );
    }

    /**
     * Render button
     */
    public static function renderNewCampaignButton()
    {
        print '<div class="col-auto">';
        Buttons::renderAdd( 'bookly-js-new-campaign', 'btn-success', __( 'New campaign', 'bookly-responsive-appointment-booking-tool' ) );
        print '</div>';
    }
}