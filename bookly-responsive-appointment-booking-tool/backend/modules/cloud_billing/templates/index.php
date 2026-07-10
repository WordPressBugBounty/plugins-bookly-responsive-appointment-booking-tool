<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\PageHeader\Renderer as PageHeaderRenderer;
use Bookly\Backend\Components\Dialogs\TableSettings;
use Bookly\Backend\Components\Cloud;
use Bookly\Lib\Utils;
/** @var array $datatables */
?>
<div id="bookly-tbs" class="wrap bookly-css-root bookly-main-page-wrap">
    <?php PageHeaderRenderer::render( $self::pageSlug(), __( 'Bookly Cloud Billing', 'bookly-responsive-appointment-booking-tool' ) ) ?>
    <div class="bookly:card">
        <div class="bookly:card-body">
            <div class="row bookly:pb-4 bookly:mb-2 bookly:border-b bookly:border-slate-200">
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
