<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\Dialogs;
use Bookly\Backend\Components\Controls\Buttons;
/** @var array $datatable */
?>
<div id="bookly-sms_mailing_lists-datatables"></div>

<?php Dialogs\Mailing\CreateList\Dialog::render() ?>