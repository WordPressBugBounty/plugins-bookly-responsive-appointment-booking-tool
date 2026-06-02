<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\Dialogs;
use Bookly\Backend\Components\Support;
use Bookly\Backend\Modules\Appointments\Proxy;

/** @var array $datatables */
?>
<div id="bookly-tbs" class="wrap bookly-css-root">
    <div class="form-row align-items-center mb-3">
        <h4 class="col m-0"><?php esc_html_e( 'Appointments', 'bookly' ) ?></h4>
        <?php Support\Buttons::render( $self::pageSlug() ) ?>
    </div>
    <div class="bookly:card">
        <div class="bookly:card-body">
            <div id="bookly-appointments-datatables"></div>
        </div>
    </div>

    <?php Proxy\Pro::renderExportDialog( $datatables['appointments'] ) ?>
    <?php Proxy\Pro::renderPrintDialog( $datatables['appointments'] ) ?>

    <?php Dialogs\Appointment\Delete\Dialog::render() ?>
    <?php Dialogs\TableSettings\Dialog::render() ?>
    <?php Dialogs\Appointment\Edit\Dialog::render() ?>
    <?php Dialogs\Queue\Dialog::render() ?>
    <?php Proxy\Shared::renderAddOnsComponents() ?>
</div>
