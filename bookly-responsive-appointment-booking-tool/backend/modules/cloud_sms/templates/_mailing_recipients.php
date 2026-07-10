<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\Dialogs;
use Bookly\Backend\Components\Controls\Buttons;
/** @var array $datatable */
?>
<div class='row mb-2'>
    <div class='col'>
        <strong><?php esc_html_e( 'Current mailing list', 'bookly-responsive-appointment-booking-tool' ) ?>:</strong> <span id="bookly-js-mailing-list-name"></span>
    </div>
</div>
<div id="bookly-sms_mailing_recipients_list-datatables"></div>
<?php Dialogs\Mailing\AddRecipients\Dialog::render() ?>