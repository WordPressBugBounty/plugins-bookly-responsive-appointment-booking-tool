<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\Cloud\Recharge\Amounts;
use Bookly\Lib\Cloud\Account;

$amounts = Amounts::getInstance();
// Served for the current account only while it is eligible (no top-ups yet)
$promotions = get_option( 'bookly_cloud_promotions' );
$first_recharge = is_array( $promotions ) && isset( $promotions['first_recharge'] ) ? $promotions['first_recharge'] : null;
?>
<div class="text-center mt-3">
    <div class="btn-group">
        <button type="button" class="btn btn-bookly btn-lg bookly-js-auto-recharges-btn" style="box-shadow: none;"><?php esc_html_e( 'Auto-Recharge' ) ?></button>
        <button type="button" class="btn btn-default btn-lg bookly-js-manual-recharges-btn" style="box-shadow: none;"><?php esc_html_e( 'One-time payment' ) ?></button>
    </div>
</div>

<div class="bookly-js-auto-recharge-text">
    <?php if ( ! $cloud->account->autoRechargeEnabled() ) : ?>
        <h4 class="text-center mt-3"><?php esc_html_e( 'Please select an amount and enable Auto-Recharge', 'bookly-responsive-appointment-booking-tool' ) ?></h4>
    <?php endif ?>
    <div class="mb-3 mt-4">
        <div class="text-center">
            <a class="text-muted" style="text-decoration:underline dotted" data-toggle="bookly-collapse" href="#how-auto-recharge-works">
                <?php esc_html_e( 'How it works', 'bookly-responsive-appointment-booking-tool' ) ?> <i class="fas fa-question-circle"></i>
            </a>
        </div>
        <div class="bookly-collapse alert alert-info text-justify mx-5" id="how-auto-recharge-works">
            <?php printf( __( 'Your account will be topped up with the selected amount <b>now</b> if your balance is less than %1$s, and <b>automatically later</b> when the balance falls below %1$s.', 'bookly-responsive-appointment-booking-tool' ), '$' . Account::AUTO_RECHARGE_THRESHOLD ) ?>
        </div>
    </div>
    <?php if ( ! $cloud->account->autoRechargeEnabled() ) : ?>
        <?php // Authorization is asked only when the recurring charge is about to be set up ?>
        <div class="mx-5 mb-4">
            <div class="custom-control custom-checkbox">
                <input class="custom-control-input" type="checkbox" id="bookly-js-auto-recharge-consent"/>
                <label class="custom-control-label" for="bookly-js-auto-recharge-consent">
                    <?php printf( esc_html__( 'I authorize Bookly to automatically charge my payment method with the amount I choose below when my balance drops below %s.', 'bookly-responsive-appointment-booking-tool' ), '$' . Account::AUTO_RECHARGE_THRESHOLD ) ?>
                </label>
            </div>
            <div class="text-danger mt-1 bookly-js-consent-error" style="display: none;">
                <?php esc_html_e( 'Please confirm the authorization to continue.', 'bookly-responsive-appointment-booking-tool' ) ?>
            </div>
        </div>
    <?php endif ?>
</div>

<div class="bookly-js-manual-recharge-text">
    <h4 class="text-center mt-3 mb-4"><?php esc_html_e( 'Please select an amount and recharge your account', 'bookly-responsive-appointment-booking-tool' ) ?></h4>
</div>

<div class="form-row bookly-js-manual-recharges mt-4" style="display: none;">
    <?php foreach ( $amounts->getItems( Amounts::RECHARGE_TYPE_MANUAL ) as $recharge ) : ?>
        <div class="col-12 col-md-6 col-lg-4">
            <?php self::renderTemplate( '_button', array( 'recharge' => $recharge, 'type' => Amounts::RECHARGE_TYPE_MANUAL, 'first_recharge' => $first_recharge ) ) ?>
        </div>
    <?php endforeach ?>
</div>

<div class="form-row bookly-js-auto-recharges mt-4">
    <?php foreach ( $amounts->getItems( Amounts::RECHARGE_TYPE_AUTO ) as $recharge ) : ?>
        <div class="col-12 col-md-6 col-lg-4">
            <?php self::renderTemplate( '_button', array( 'recharge' => $recharge, 'type' => Amounts::RECHARGE_TYPE_AUTO, 'cloud' => $cloud, 'first_recharge' => $first_recharge ) ) ?>
        </div>
    <?php endforeach ?>
</div>
<div class="row my-3 text-center" style="color:#595959">
    <div class="col"><i class="fab fa-2x fa-cc-paypal"></i></div>
    <div class="col"><i class="fab fa-2x fa-cc-mastercard"></i></div>
    <div class="col"><i class="fab fa-2x fa-cc-visa"></i></div>
    <div class="col"><i class="fab fa-2x fa-cc-amex"></i></div>
    <div class="col"><i class="fab fa-2x fa-cc-discover"></i></div>
</div>