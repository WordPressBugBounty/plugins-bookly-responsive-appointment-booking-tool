<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\Dialogs;
use Bookly\Backend\Components\Notices;
use Bookly\Backend\Components\Controls\Buttons;
/** @var array $datatables */
?>
<div id="campaigns">
    <div id="bookly-sms_mailing_campaigns-datatables" class="bookly:mb-4"></div>

    <?php Notices\Cron\Notice::render() ?>
    <?php Dialogs\Mailing\Campaign\Dialog::render() ?>
</div>

