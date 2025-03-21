<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Lib\Utils\Common;

/**
 * @var array $product
 */
$accept_pc = isset( $product['accept_pc'] ) && $product['accept_pc'];
?>
<div class="mb-3">
    <div class="h5"><?php echo Common::stripScripts( $product['texts']['price'] ) ?></div>
    <?php if ( isset ( $product['prices'] ) || $accept_pc ) : ?>
        <div class="bookly-dropdown">
            <button class="bookly-js-product-price-dropdown btn btn-default bookly-dropdown-toggle d-flex align-items-center w-100" type="button" data-toggle="bookly-dropdown">
                <span class="bookly-js-product-price flex-grow-1">&nbsp;</span>
            </button>
            <div class="bookly-dropdown-menu bookly-dropdown-menu-compact bookly-dropdown-menu-right text-right w-100 shadow">
                <?php if ( isset ( $product['prices'] ) ) : ?>
                    <?php foreach ( $product['prices'] as $price ) : ?>
                        <li class="bookly-dropdown-item" data-product-price-id="<?php echo esc_attr( $price['id'] ) ?>">
                            <?php if ( in_array( 'best_offer', $price['tags'] ) ) : ?>
                                <span class="bookly-js-best-offer badge badge-warning"><small><strong><?php esc_html_e( 'best offer', 'bookly' ) ?></strong></small></span>
                            <?php endif ?>
                            <?php if ( in_array( 'users_choice', $price['tags'] ) ) : ?>
                                <span class="bookly-js-users-choice badge badge-danger"><small><strong><?php esc_html_e( 'users choice', 'bookly' ) ?></strong></small></span>
                            <?php endif ?>
                            <span class="text-wrap"><?php echo Common::stripScripts( $price['caption'] ) ?></span>
                        </li>
                    <?php endforeach ?>
                <?php endif ?>
                <?php if ( $accept_pc ) : ?>
                    <li class="bookly-dropdown-item" data-product-price-id="0" data-type="purchase_code">
                        <?php esc_html_e( 'Purchase code', 'bookly' ) ?>
                    </li>
                <?php endif ?>
            </div>
        </div>
        <?php if ( $accept_pc ) : ?>
            <div class="bookly-js-product-purchase-code-wrap mt-2" style="display:none;">
                <input type="text" class="form-control" placeholder="<?php echo esc_attr__( 'Purchase code', 'bookly' ) ?>"/>
            </div>
        <?php endif ?>
    <?php endif ?>
    <?php if ( isset ( $product['next_billing_date'] ) ) : ?>
        <div class="mt-2"><?php echo isset ( $product['cancel_on_renewal'] ) && ! $product['cancel_on_renewal'] ? esc_html__( 'Next billing date', 'bookly' ) : esc_html__( 'Deactivation date', 'bookly' ) ?>: <?php echo Common::stripScripts( $product['next_billing_date'] ) ?></div>
    <?php endif ?>
    <?php if ( isset ( $product['usage'] ) ) : ?>
        <div class="small"><?php echo esc_html( $product['usage'] ) ?></div>
    <?php endif ?>
</div>
