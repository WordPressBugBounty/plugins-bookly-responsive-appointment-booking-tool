<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly
use Bookly\Backend\Components\PageHeader\Renderer as PageHeaderRenderer;
use Bookly\Backend\Components\Cloud;
use Bookly\Backend\Components\Controls\Buttons;
use Bookly\Backend\Components\Controls\Inputs;
use Bookly\Lib;
/**
 * @var Lib\Cloud\API $cloud
 */
$invoice = $cloud->account->getInvoiceData();
$auto_recharge_limit = $cloud->account->getAutoRechargeLimit();
$intervals = array( 1, 2, 3, 6, 12, 24, 48, 168 );
if ( ! in_array( $auto_recharge_limit['interval'], $intervals ) ) {
    $intervals[] = $auto_recharge_limit['interval'];
    sort( $intervals );
}
?>
<div id="bookly-tbs" class="wrap bookly-css-root bookly-main-page-wrap">
    <?php PageHeaderRenderer::render( $self::pageSlug(), __( 'Bookly Cloud Settings', 'bookly-responsive-appointment-booking-tool' ) ) ?>
    <div class="bookly:card mb-4">
        <div class="bookly:card-body py-3">
            <div class="row">
                <div class="col">
                </div>
                <div class="col-auto">
                    <?php Cloud\Account\Panel::render() ?>
                </div>
            </div>
        </div>
    </div>

    <div class="form-row">
        <div id="bookly-sidebar" class="col-12 col-sm-auto">
            <div class="nav flex-column nav-pills mb-2 mb-sm-0" role="tablist">
                <a class="nav-link active" data-toggle="bookly-pill" href="#bookly-invoice-tab"><?php esc_html_e( 'Invoice', 'bookly-responsive-appointment-booking-tool' ) ?></a>
                <a class="nav-link mt-2 bookly-js-auto-recharge-tab<?php echo $cloud->account->autoRechargeEnabled() ? '' : ' d-none' ?>" data-toggle="bookly-pill" href="#bookly-auto-recharge-tab"><?php esc_html_e( 'Auto-recharge', 'bookly-responsive-appointment-booking-tool' ) ?></a>
                <a class="nav-link mt-2" data-toggle="bookly-pill" href="#bookly-account-notifications-tab"><?php esc_html_e( 'Notifications', 'bookly-responsive-appointment-booking-tool' ) ?></a>
                <a class="nav-link mt-2" data-toggle="bookly-pill" href="#bookly-country-tab"><?php esc_html_e( 'Country', 'bookly-responsive-appointment-booking-tool' ) ?></a>
                <a class="nav-link mt-2" data-toggle="bookly-pill" href="#bookly-change-password-tab"><?php esc_html_e( 'Change password', 'bookly-responsive-appointment-booking-tool' ) ?></a>
            </div>
        </div>

        <div id="bookly_settings_controls" class="col">
            <div class="bookly:card">
                <div class="bookly:card-body">
                    <div class="tab-content">
                        <div class="tab-pane active" id="bookly-invoice-tab">
                            <div class="alert alert-warning"><?php esc_html_e( 'These settings are deprecated and have no effect on current invoice templates.', 'bookly-responsive-appointment-booking-tool' ) ?></div>
                            <form>
                                <div class="form-group">
                                    <label for="bookly_sms_invoice_company_name"><?php esc_html_e( 'Company name', 'bookly-responsive-appointment-booking-tool' ) ?>*</label>
                                    <input name="invoice[company_name]" type="text" class="form-control" id="bookly_sms_invoice_company_name" required value="<?php echo esc_attr( $invoice['company_name'] ) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="bookly_sms_invoice_company_address"><?php esc_html_e( 'Company address', 'bookly-responsive-appointment-booking-tool' ) ?>*</label>
                                    <input name="invoice[company_address]" type="text" class="form-control" id="bookly_sms_invoice_company_address" required value="<?php echo esc_attr( $invoice['company_address'] ) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="bookly_sms_invoice_company_address_l2"><?php esc_html_e( 'Company address line 2', 'bookly-responsive-appointment-booking-tool' ) ?></label>
                                    <input name="invoice[company_address_l2]" type="text" class="form-control" id="bookly_sms_invoice_company_address_l2" value="<?php echo esc_attr( $invoice['company_address_l2'] ) ?>">
                                </div>
                                <div class="form-group bookly-js-invoice-country">
                                    <div class="bookly-js-label"><?php esc_html_e( 'N/A', 'bookly-responsive-appointment-booking-tool' ) ?></div>
                                    <small class="form-text text-muted mb-2"><?php _e( 'You can change the country <a href="#">here</a>', 'bookly-responsive-appointment-booking-tool' ) ?></small>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="bookly_sms_invoice_company_code"><?php esc_html_e( 'Company number', 'bookly-responsive-appointment-booking-tool' ) ?></label>
                                        <input name="invoice[company_code]" type="text" class="form-control" id="bookly_sms_invoice_company_code" value="<?php echo esc_attr( $invoice['company_code'] ) ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="bookly_sms_invoice_company_vat"><?php esc_html_e( 'VAT / Tax number', 'bookly-responsive-appointment-booking-tool' ) ?></label>
                                        <input name="invoice[company_vat]" type="text" class="form-control" id="bookly_sms_invoice_company_vat" value="<?php echo esc_attr( $invoice['company_vat'] ) ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="bookly_sms_invoice_company_add_text"><?php esc_html_e( 'Additional text to include in invoice', 'bookly-responsive-appointment-booking-tool' ) ?></label>
                                    <textarea name="invoice[company_add_text]" class="form-control" rows="3" id="bookly_sms_invoice_company_add_text"><?php echo $invoice['company_add_text'] === null ? '' : esc_textarea( $invoice['company_add_text'] ) ?></textarea>
                                </div>
                                <div class="form-group border-top pt-2">
                                    <input name="invoice[send]" value="0" class="hidden"/>
                                    <?php Inputs::renderCheckBox( __( 'Send invoice', 'bookly-responsive-appointment-booking-tool' ), 1, $invoice['send'], array( 'name' => 'invoice[send]' ) ) ?>
                                    <small class="text-muted"><?php printf( __( 'The invoice will be sent to <a href="mailto:%1$s">%1$s</a>', 'bookly-responsive-appointment-booking-tool' ), $cloud->account->getUserName() ) ?></small>
                                </div>
                                <div class="border-left ml-4 pl-3">
                                    <div class="form-group">
                                        <?php Inputs::renderCheckBox( __( 'Copy invoice to another email(s)', 'bookly-responsive-appointment-booking-tool' ), 1, $invoice['send_copy'], array( 'name' => 'invoice[send_copy]' ) ) ?>
                                        <input name="invoice[cc]" type="text" class="form-control mt-2" value="<?php echo esc_attr( $invoice['cc'] ) ?>">
                                        <small class="form-text text-muted"><?php esc_html_e( 'Enter one or more email addresses separated by commas.', 'bookly-responsive-appointment-booking-tool' ) ?></small>
                                    </div>
                                </div>
                                <?php Buttons::renderSubmit( 'bookly-save-invoice', null, __( 'Save invoice settings', 'bookly-responsive-appointment-booking-tool' ) ) ?>
                            </form>
                        </div>
                        <div class="tab-pane" id="bookly-auto-recharge-tab" style="min-height: 200px;">
                            <div class="form-group">
                                <label for="bookly-auto-recharge-limit"><?php esc_html_e( 'Auto-Recharge limit', 'bookly-responsive-appointment-booking-tool' ) ?></label>
                                <select class="form-control custom-select" id="bookly-auto-recharge-limit">
                                    <option value="0"><?php esc_html_e( 'Disabled', 'bookly-responsive-appointment-booking-tool' ) ?></option>
                                    <option value="1" <?php selected( $auto_recharge_limit['enabled'] ) ?>><?php esc_html_e( 'Enabled', 'bookly-responsive-appointment-booking-tool' ) ?></option>
                                </select>
                                <small class="form-text text-muted"><?php esc_html_e( 'Sets the maximum number of Auto-Recharges that pay as you go products can initiate during the specified time period. Once the limit is reached, further Auto-Recharges will be skipped until the period ends.', 'bookly-responsive-appointment-booking-tool' ) ?></small>
                            </div>
                            <div class="border-left ml-4 pl-3 bookly-js-auto-recharge-limit">
                                <div class="form-group">
                                    <label for="bookly-auto-recharge-max-count"><?php esc_html_e( 'Perform no more than', 'bookly-responsive-appointment-booking-tool' ) ?></label>
                                    <input type="number" min="1" step="1" class="form-control" id="bookly-auto-recharge-max-count" value="<?php echo esc_attr( $auto_recharge_limit['max_count'] ) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="bookly-auto-recharge-interval"><?php esc_html_e( 'Auto-Recharges per', 'bookly-responsive-appointment-booking-tool' ) ?></label>
                                    <select class="form-control custom-select" id="bookly-auto-recharge-interval">
                                        <?php foreach ( $intervals as $hours ) : ?>
                                            <option value="<?php echo esc_attr( $hours ) ?>" <?php selected( $hours, $auto_recharge_limit['interval'] ) ?>><?php
                                                echo esc_html( $hours % 24 === 0
                                                    ? sprintf( _n( '%d day', '%d days', $hours / 24, 'bookly-responsive-appointment-booking-tool' ), $hours / 24 )
                                                    : sprintf( _n( '%d hour', '%d hours', $hours, 'bookly-responsive-appointment-booking-tool' ), $hours ) )
                                            ?></option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                            </div>
                            <?php Buttons::renderSubmit( 'bookly-save-auto-recharge-limit' ) ?>
                        </div>
                        <div class="tab-pane" id="bookly-account-notifications-tab" style="min-height: 200px;">
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input class="custom-control-input" type="checkbox" id="bookly_cloud_badge_consider_sms" name="bookly_cloud_badge_consider_sms" <?php checked( get_option( 'bookly_cloud_badge_consider_sms' ) ) ?>>
                                    <label class="custom-control-label" for="bookly_cloud_badge_consider_sms"><span><?php esc_html_e( 'Show SMS notification icon', 'bookly-responsive-appointment-booking-tool' ) ?></span></label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input class="custom-control-input" type="checkbox" id="bookly_cloud_notify_low_balance" name="bookly_cloud_notify_low_balance" <?php checked( get_option( 'bookly_cloud_notify_low_balance' ) ) ?>>
                                    <label class="custom-control-label" for="bookly_cloud_notify_low_balance"><span><?php esc_html_e( 'Send email notification to administrators at low balance', 'bookly-responsive-appointment-booking-tool' ) ?></span></label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input class="custom-control-input" type="checkbox" id="notify_summary" name="notify_summary" <?php checked( $cloud->account->getNotifySummary() ) ?>>
                                    <label class="custom-control-label" for="notify_summary"><span><?php esc_html_e( 'Send weekly summary', 'bookly-responsive-appointment-booking-tool' ) ?></span></label>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane" id="bookly-country-tab" style="min-height: 200px;">
                            <div class="form-group">
                                <select id="bookly-country"></select>
                                <small class="text-muted"><?php esc_html_e( 'Your country is the location from where you consume Bookly SMS services and is used to provide you with the payment methods available in that country.', 'bookly-responsive-appointment-booking-tool' ) ?></small>
                            </div>
                            <?php Buttons::renderSubmit( 'bookly-update-country', null, __( 'Update country', 'bookly-responsive-appointment-booking-tool' ) ) ?>
                        </div>
                        <div class="tab-pane" id="bookly-change-password-tab">
                            <form>
                                <div class="form-group">
                                    <label for="old_password"><?php esc_html_e( 'Old password', 'bookly-responsive-appointment-booking-tool' ) ?></label>
                                    <input type="password" class="form-control" id="old_password" name="old_password" placeholder="<?php esc_attr_e( 'Old password', 'bookly-responsive-appointment-booking-tool' ) ?>" required/>
                                </div>
                                <div class="form-group">
                                    <label for="new_password"><?php esc_html_e( 'New password', 'bookly-responsive-appointment-booking-tool' ) ?></label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" placeholder="<?php esc_attr_e( 'New password', 'bookly-responsive-appointment-booking-tool' ) ?>" required/>
                                </div>
                                <div class="form-group">
                                    <label for="new_password_repeat"><?php esc_html_e( 'Repeat new password', 'bookly-responsive-appointment-booking-tool' ) ?></label>
                                    <input type="password" class="form-control" id="new_password_repeat" placeholder="<?php esc_attr_e( 'Repeat new password', 'bookly-responsive-appointment-booking-tool' ) ?>" required/>
                                </div>
                                <?php Buttons::renderSubmit( 'bookly-change-password', null, __( 'Change password', 'bookly-responsive-appointment-booking-tool' ) ) ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>