<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\Support;
use Bookly\Backend\Components\Dialogs\TableSettings;
use Bookly\Backend\Components\Cloud;
use Bookly\Lib\Utils\DateTime;
/**
 * @var Bookly\Lib\Cloud\SMS $sms
 * @var int $undelivered_count
 */
?>
<div id="bookly-tbs" class="wrap bookly-css-root">
    <div class="form-row align-items-center mb-3">
        <h4 class="col m-0"><?php esc_html_e( 'SMS Notifications', 'bookly' ) ?></h4>
        <?php Support\Buttons::render( $self::pageSlug() ) ?>
    </div>
    <div class="bookly:card">
        <div class="bookly:card-body">
            <div class="row bookly:pb-2 bookly:mb-2 bookly:border-b bookly:border-slate-200">
                <div class="col-auto ml-auto">
                    <?php Cloud\Account\Panel::render() ?>
                </div>
            </div>
            <ul class="bookly:nav bookly:nav-tabs bookly:mb-3" id="sms_tabs">
                <li class="bookly:nav-item"><a class="bookly:nav-link bookly:active" href="#notifications"><?php esc_html_e( 'Notifications', 'bookly' ) ?></a></li>
                <li class="bookly:nav-item"><a class="bookly:nav-link" href="#campaigns"><?php esc_html_e( 'Campaigns', 'bookly' ) ?></a></li>
                <li class="bookly:nav-item"><a class="bookly:nav-link" href="#mailing"><?php esc_html_e( 'Mailing lists', 'bookly' ) ?></a></li>
                <li class="bookly:nav-item"><a class="bookly:nav-link" href="#sms_details"><?php esc_html_e( 'SMS Details', 'bookly' ); if ( $undelivered_count ) : ?> <span class="badge bg-danger"><?php echo esc_html( $undelivered_count ) ?></span><?php endif ?></a></li>
                <li class="bookly:nav-item"><a class="bookly:nav-link" href="#price_list"><?php esc_html_e( 'Price list', 'bookly' ) ?></a></li>
                <li class="bookly:nav-item"><a class="bookly:nav-link" href="#sender_id"><?php esc_html_e( 'Sender ID', 'bookly' ) ?></a></li>
            </ul>
            <div class="tab-content mt-3" id="sms_tabs_content">
                <div class="bookly:tab-pane bookly:active" id="notifications"><?php include '_notifications.php' ?></div>
                <div class="bookly:tab-pane" id="campaigns"><?php include '_campaigns.php' ?></div>
                <div class="bookly:tab-pane" id="mailing"><?php include '_mailing.php' ?></div>
                <div class="bookly:tab-pane" id="sms_details"><?php include '_sms_details.php' ?></div>
                <div class="bookly:tab-pane" id="price_list"><?php include '_price.php' ?></div>
                <div class="bookly:tab-pane" id="sender_id"><?php include '_sender_id.php' ?></div>
            </div>
        </div>
    </div>

    <?php TableSettings\Dialog::render() ?>
</div>