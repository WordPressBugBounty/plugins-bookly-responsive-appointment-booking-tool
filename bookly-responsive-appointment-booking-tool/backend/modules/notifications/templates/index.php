<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Modules\Notifications;
use Bookly\Backend\Components\Dialogs;
use Bookly\Backend\Components\Support;
use Bookly\Backend\Components\Controls\Buttons;

?>
<div id="bookly-tbs" class="wrap bookly-css-root">
    <div class="form-row align-items-center mb-3">
        <h4 class="col m-0"><?php esc_html_e( 'Email notifications', 'bookly' ) ?></h4>
        <?php Support\Buttons::render( $self::pageSlug() ) ?>
    </div>
    <div class="bookly:card">
        <div class="bookly:card-body">
            <ul class="bookly:nav bookly:nav-tabs bookly:mb-3 bookly-js-notifications-tabs" role="tablist">
                <li class="bookly:nav-item">
                    <a class="bookly:nav-link<?php if ( $tab === 'notifications' ) : ?> bookly:active<?php endif ?>" href="<?php echo add_query_arg( array( 'page' => Notifications\Page::pageSlug() ), admin_url( 'admin.php' ) ) ?>" data-toggle="bookly-tab" data-tab="notifications"><?php esc_html_e( 'Notifications', 'bookly' ) ?></a>
                </li>
                <?php Notifications\Proxy\Pro::renderLogsTab( $tab ) ?>
                <li class="bookly:nav-item">
                    <a class="bookly:nav-link<?php if ( $tab === 'settings' ) : ?> bookly:active<?php endif ?>" href="<?php echo add_query_arg( array( 'page' => Notifications\Page::pageSlug(), 'tab' => 'settings' ), admin_url( 'admin.php' ) ) ?>" data-toggle="bookly-tab" data-tab="settings"><?php esc_html_e( 'Settings', 'bookly' ) ?></a>
                </li>
            </ul>
            <div class="bookly-js-notifications-wrap">
            </div>
        </div>
        <div class="bookly:card-footer bookly:justify-end bookly-js-notifications-footer" hidden>
            <?php Buttons::renderSubmit( null, 'bookly-js-save', __( 'Save', 'bookly' ) ) ?>
        </div>
    </div>
    <?php Dialogs\Notifications\Dialog::render() ?>
    <?php Dialogs\TableSettings\Dialog::render() ?>
</div>