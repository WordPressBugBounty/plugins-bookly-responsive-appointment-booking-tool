<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\Controls;
use Bookly\Backend\Components\Dialogs;
use Bookly\Backend\Components\Support;
use Bookly\Lib;

/** @var array $datatables */
?>
<div id="bookly-tbs" class="wrap bookly-css-root">
    <div class="form-row align-items-center mb-3">
        <?php if ( Lib\Utils\Common::isCurrentUserAdmin() ) : ?>
            <h4 class="col m-0 text-nowrap">
                <?php esc_html_e( 'Staff Members', 'bookly' ) ?>
            </h4>
        <?php else : ?>
            <h4 class="col m-0">
                <?php esc_html_e( 'Profile', 'bookly' ) ?>
            </h4>
        <?php endif ?>
        <?php Support\Buttons::render( $self::pageSlug() ) ?>
    </div>
    <div class="bookly:card">
        <div class="bookly:card-body">
            <div id="bookly-staff_members-datatables"></div>
        </div>
    </div>

    <?php Dialogs\Common\CascadeDelete::render() ?>
    <?php Dialogs\Common\UnsavedChanges::render() ?>
    <?php Dialogs\Staff\Proxy\Pro::renderCategoriesDialog() ?>
    <?php Dialogs\Staff\Proxy\Pro::renderDuplicateDialog() ?>
    <?php Dialogs\Staff\Edit\Dialog::render() ?>
    <?php Dialogs\Staff\Order\Dialog::render() ?>
    <?php Dialogs\Staff\Edit\Proxy\Packages::renderStaffServicesTip() ?>
    <?php Dialogs\TableSettings\Dialog::render() ?>
</div>