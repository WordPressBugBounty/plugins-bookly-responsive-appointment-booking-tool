<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\PageHeader\Renderer as PageHeaderRenderer;
?>
<div id="bookly-tbs" class="wrap bookly-css-root bookly-main-page-wrap">
    <?php PageHeaderRenderer::render( $self::pageSlug(), __( 'Initial setup', 'bookly-responsive-appointment-booking-tool' ) ) ?>
    <div id="bookly-setup-form"></div>
</div>