<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\PageHeader\Renderer as PageHeaderRenderer;
use Bookly\Backend\Components\Cloud;
/**
 * @var string $title
 * @var string $slug
 */
?>
<div id="bookly-tbs" class="wrap bookly-css-root bookly-main-page-wrap">
    <?php PageHeaderRenderer::render( $slug, $title ) ?>
    <div id="bookly-login-required" class="bookly:card mb-4" style="min-height: 600px;">
        <div class="bookly:card-body">
            <div class="row pb-3">
                <div class="col">
                </div>
                <div class="col-auto">
                    <?php Cloud\Account\Panel::render() ?>
                </div>
            </div>
        </div>
    </div>
</div>