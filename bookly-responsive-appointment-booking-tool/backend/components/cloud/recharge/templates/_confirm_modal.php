<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\Controls\Buttons;
?>
<div class="bookly-modal bookly-fade" tabindex="-1" role="dialog" id="bookly-js-disable-auto-recharge-modal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php esc_html_e( 'Disable Auto-Recharge', 'bookly-responsive-appointment-booking-tool' ) ?></h5>
                <button type="button" class="close" data-dismiss="bookly-modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-danger"><?php esc_html_e( 'Are you sure you want to disable Auto-Recharge?', 'bookly-responsive-appointment-booking-tool' ) ?></p>
                <?php esc_html_e( 'Amount' ) ?>: $<span class="bookly-js-amount"></span>
            </div>
            <div class="modal-footer">
                <?php Buttons::render( 'bookly-js-auto-recharge-disable', 'btn-danger', __( 'Disable', 'bookly-responsive-appointment-booking-tool' ) ) ?>
                <?php Buttons::renderCancel( __( 'Close', 'bookly-responsive-appointment-booking-tool' ) ) ?>
            </div>
        </div>
    </div>
</div>