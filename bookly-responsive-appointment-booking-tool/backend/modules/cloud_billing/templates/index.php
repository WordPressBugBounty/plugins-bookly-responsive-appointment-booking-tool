<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\Support;
use Bookly\Backend\Components\Dialogs\TableSettings;
use Bookly\Backend\Components\Cloud;
use Bookly\Lib\Utils;
/** @var array $datatables */
?>
<div id="bookly-tbs" class="wrap bookly-css-root">
    <div class="form-row align-items-center mb-3">
        <h4 class="col m-0"><?php esc_html_e( 'Bookly Cloud Billing', 'bookly' ) ?></h4>
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
            <div id="bookly-cloud_purchases-datatables"></div>
        </div>
    </div>
    <?php TableSettings\Dialog::render() ?>
</div>
