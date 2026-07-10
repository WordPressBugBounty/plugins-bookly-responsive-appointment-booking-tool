<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\PageHeader\Renderer as PageHeaderRenderer;
use Bookly\Lib;
?>
<style>
    .bookly-css-root {
        --bookly-color: <?php echo esc_attr( get_option( 'bookly_app_color', '#f4662f' ) ) ?>;
    }
</style>
<div id="bookly-tbs" class="wrap bookly-css-root bookly-main-page-wrap">
    <?php PageHeaderRenderer::render( $self::pageSlug(), __( 'Add-ons', 'bookly-responsive-appointment-booking-tool' ) ) ?>
    <div id="bookly-addons-form"></div>
    <?php Lib\Proxy\Pro::renderLicenseDialog() ?>
</div>