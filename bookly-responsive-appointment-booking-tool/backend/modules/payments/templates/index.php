<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\PageHeader\Renderer as PageHeaderRenderer;
use Bookly\Backend\Components\Dialogs;

/** @var array $datatables */
?>
<div id="bookly-tbs" class="wrap bookly-css-root bookly-main-page-wrap">
    <?php PageHeaderRenderer::render( $self::pageSlug(), __( 'Payments', 'bookly-responsive-appointment-booking-tool' ) ) ?>
    <div class="bookly:card">
        <div class="bookly:card-body">
            <div id="bookly-payments-datatables"></div>
            <?php if ( array_key_exists( 'paid', $datatables['payments']['settings']['columns'] ) && $datatables['payments']['settings']['columns']['paid'] ) : ?>
                <div class="bookly:flex bookly:justify-end bookly:mt-2 bookly:text-sm">
                    <span><?php esc_html_e( 'Total', 'bookly-responsive-appointment-booking-tool' ) ?>: <strong id="bookly-payment-total"></strong></span>
                </div>
            <?php endif ?>
        </div>

        <?php Dialogs\Payment\Dialog::render() ?>
        <?php Dialogs\TableSettings\Dialog::render() ?>
    </div>
</div>
