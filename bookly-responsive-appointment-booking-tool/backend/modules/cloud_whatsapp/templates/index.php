<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\Support;
use Bookly\Backend\Components\Dialogs;
use Bookly\Backend\Components\Cloud;
use Bookly\Backend\Components\Dialogs\TableSettings;
use Bookly\Backend\Components\Controls\Buttons;
/**
 * @var \Bookly\Lib\Cloud\WhatsApp $whatsapp
 */
?>
<div id="bookly-tbs" class="wrap bookly-css-root">
    <div class="form-row align-items-center mb-3">
        <h4 class="col m-0"><?php esc_html_e( 'WhatsApp Notifications', 'bookly' ) ?></h4>
        <?php Support\Buttons::render( $self::pageSlug() ) ?>
    </div>
    <div class="bookly:card">
        <div class="bookly:card-body">
            <div class="row bookly:pb-2 bookly:mb-2 bookly:border-b bookly:border-slate-200">
                <div class="col"></div>
                <div class="col-auto">
                    <?php Cloud\Account\Panel::render() ?>
                </div>
            </div>
            <ul class="bookly:nav bookly:nav-tabs bookly:mb-3" id="whatsapp_tabs">
                <li class="bookly:nav-item"><a class="bookly:nav-link bookly:active" href="#notifications"><?php esc_html_e( 'Notifications', 'bookly' ) ?></a></li>
                <li class="bookly:nav-item"><a class="bookly:nav-link" href="#details"><?php esc_html_e( 'Details', 'bookly' ) ?></a></li>
                <li class="bookly:nav-item"><a class="bookly:nav-link" href="#settings"><?php esc_html_e( 'Settings', 'bookly' ) ?></a></li>
            </ul>
            <div class="tab-content mt-3" id="whatsapp_tabs_content">
                <div class="bookly:tab-pane bookly:active" id="notifications"><?php include '_notifications.php' ?></div>
                <div class="bookly:tab-pane" id="details"><div id="bookly-whatsapp_details-datatables"></div></div>
                <div class="bookly:tab-pane" id="settings"><?php include '_settings.php' ?></div>
            </div>
        </div>
        <div class="bookly:card-footer bookly:justify-end bookly-js-whatsapp-settings-footer" hidden>
            <?php Buttons::renderSubmit( null, 'bookly-js-whatsapp-settings-save', __( 'Save', 'bookly' ) ) ?>
        </div>
    </div>

    <?php TableSettings\Dialog::render() ?>
</div>
