<?php
namespace Bookly\Frontend\Modules\Ai;

use Bookly\Backend\Modules\Appearance\ModernAppearance;
use Bookly\Backend\Modules\Appearance\Proxy as AppearanceProxy;
use Bookly\Frontend\Modules\Booking\Proxy as BookingProxy;
use Bookly\Lib;
use Bookly\Lib\Entities;

/**
 * Turns the booking draft a conversation carries into the objects the regular payment flow
 * expects, so the AI chat can hand the customer to Lib\Base\Gateway exactly like the booking
 * form does.
 *
 * The draft itself is nothing but the arguments create_booking was called with (see
 * AiConversation::getBookingDataArray()). Nothing here writes an appointment - that happens
 * inside Gateway::createIntent(), where the booking form does it too, and is undone by
 * Gateway::fail() if the customer never pays.
 */
class Checkout
{
    protected static $supported = array(
        Entities\Payment::TYPE_LOCAL,
        Entities\Payment::TYPE_CLOUD_STRIPE,
        Entities\Payment::TYPE_PAYPAL,
        Entities\Payment::TYPE_MOLLIE,
        Entities\Payment::TYPE_2CHECKOUT,
        Entities\Payment::TYPE_PAYUBIZ,
        Entities\Payment::TYPE_PAYULATAM,
        Entities\Payment::TYPE_PAYSON,
        Entities\Payment::TYPE_SQUARE,
        Entities\Payment::TYPE_WORLDPAY,
        Entities\Payment::TYPE_WOOCOMMERCE,
    );

    /**
     * Build a session-less UserBookingData from a conversation's draft.
     *
     * Mirrors the two existing session-less builders - the modern-form branch of
     * Frontend\Modules\Payment\Request::getUserData() and BooklyPro's checkoutFormPay() -
     * rather than loading a FormSession: the chat visitor never had one, and the request
     * that pays is not the request that built the draft.
     *
     * @param Entities\AiConversation $conversation
     * @return Lib\UserBookingData|false false when the draft is unusable (service, staff or
     *                                   customer disappeared since create_booking ran)
     */
    public static function buildUserData( Entities\AiConversation $conversation )
    {
        $draft = $conversation->getBookingDataArray();
        if ( ! $draft ) {
            return false;
        }

        $customer = Entities\Customer::find( $draft['customer_id'] );
        if ( ! $customer ) {
            return false;
        }

        $service = Entities\Service::find( $draft['service_id'] );
        $staff = Entities\Staff::find( $draft['staff_id'] );
        if ( ! $service || ! $staff ) {
            return false;
        }

        // current_user_id = 0, not null: whoever happens to be logged into WordPress in this
        // browser is not the person the chat collected a name and an email from.
        $userData = new Lib\UserBookingData( null, 0 );

        $location_id = empty( $draft['location_id'] ) ? null : (int) $draft['location_id'];
        $slot = array( array(
            (int) $draft['service_id'],
            (int) $draft['staff_id'],
            $draft['start_date'],
            $location_id,
        ) );

        $cart_item = new Lib\CartItem();
        $cart_item
            ->setType( Lib\CartItem::TYPE_APPOINTMENT )
            ->setServiceId( (int) $draft['service_id'] )
            ->setStaffIds( array( (int) $draft['staff_id'] ) )
            ->setLocationId( $location_id )
            ->setNumberOfPersons( isset( $draft['number_of_persons'] ) ? (int) $draft['number_of_persons'] : 1 )
            ->setUnits( 1 )
            ->setExtras( isset( $draft['extras'] ) && is_array( $draft['extras'] ) ? $draft['extras'] : array() )
            ->setCustomFields( array() )
            ->setSlots( $slot );

        $userData->cart->add( $cart_item );
        $userData->setSlots( array( $slot ) );

        $userData
            ->setCustomer( $customer )
            ->setFullName( $customer->getFullName() )
            ->setFirstName( $customer->getFirstName() )
            ->setLastName( $customer->getLastName() )
            ->setEmail( $customer->getEmail() )
            ->setPhone( $customer->getPhone() );

        if ( ! empty( $draft['notes'] ) ) {
            $userData->fillData( array( 'notes' => $draft['notes'] ) );
        }

        return $userData;
    }

    /**
     * Gateways this cart can actually be paid with, in the order configured under
     * Settings -> Payments.
     *
     * Same three filters the booking form applies in Booking\Ajax::getGateways(): the gateway
     * has to be enabled, allowed for the customer's group, and allowed for every service and
     * every staff member in the cart - the last one is Service::getGateways() /
     * Staff::getGateways(), applied by BookingProxy\Pro::filterGateways().
     *
     * @param Lib\UserBookingData $userData
     * @return array[] [ [ name, title, logo_url ], ... ]
     */
    public static function getGateways( Lib\UserBookingData $userData )
    {
        $gateways = array();

        if ( Lib\Config::payLocallyEnabled() ) {
            $gateways[ Entities\Payment::TYPE_LOCAL ] = array(
                'label_option_name' => 'bookly_l10n_label_pay_locally',
                'title' => Entities\Payment::typeToString( Entities\Payment::TYPE_LOCAL ),
                'logo_url' => null,
            );
        }

        if ( Lib\Config::stripeCloudEnabled() ) {
            $gateways[ Entities\Payment::TYPE_CLOUD_STRIPE ] = array(
                'label_option_name' => 'bookly_l10n_label_pay_cloud_stripe',
                'title' => Entities\Payment::typeToString( Entities\Payment::TYPE_CLOUD_STRIPE ),
                'logo_url' => plugins_url( 'frontend/resources/images/payments.svg', Lib\Plugin::getMainFile() ),
            );
        }

        // Every payment add-on registers itself here with a title and a logo - the same
        // source the appearance editor and Utils\Common::getGateways() read. WooCommerce is
        // the one entry here whose "is it on" flag is not bookly_woocommerce_enabled - it is
        // Config::wooCommerceEnabled() (bookly_wc_enabled + a product configured).
        foreach ( AppearanceProxy\Shared::paymentGateways( array() ) as $type => $gateway ) {
            $enabled = $type === Entities\Payment::TYPE_WOOCOMMERCE
                ? Lib\Config::wooCommerceEnabled()
                : get_option( 'bookly_' . $type . '_enabled' );
            if ( ! isset( $gateways[ $type ] ) && $enabled ) {
                $gateways[ $type ] = $gateway + array( 'label_option_name' => null, 'logo_url' => null );
            }
        }

        foreach ( array_keys( $gateways ) as $type ) {
            if ( ! in_array( $type, self::$supported )
                || BookingProxy\CustomerGroups::allowedGateway( $type, $userData ) === false
            ) {
                unset( $gateways[ $type ] );
            }
        }

        $gateways = BookingProxy\Pro::filterGateways( $gateways, $userData );
        $l10n = ModernAppearance::getAppearance( Entities\Form::TYPE_AI_ASSISTANT, null );
        $l10n = isset( $l10n['l10n'] ) ? $l10n['l10n'] : array();

        $list = array();
        foreach ( self::orderGateways( array_keys( $gateways ) ) as $type ) {
            $alias = isset( $l10n[ 'payment_system_' . $type ] ) ? $l10n[ 'payment_system_' . $type ] : '';
            $label = $gateways[ $type ]['label_option_name']
                ? Lib\Utils\Common::getTranslatedOption( $gateways[ $type ]['label_option_name'] )
                : '';
            $logo_url = $gateways[ $type ]['logo_url'];
            if ( $type === Entities\Payment::TYPE_WOOCOMMERCE ) {
                $image = Entities\Payment::typeToImage( $type );
                $logo_url = $image['src'];
            } elseif ( $logo_url === 'default' ) {
                $logo_url = plugins_url( 'frontend/resources/images/payments.svg', Lib\Plugin::getMainFile() );
            }

            $list[] = array(
                'name' => $type,
                'title' => $alias !== '' ? $alias : ( $label !== '' ? $label : $gateways[ $type ]['title'] ),
                'logo_url' => $logo_url,
            );
        }

        return $list;
    }

    /**
     * Whether the AI chat could ever offer a payment gateway on this site - the cart-less,
     * site-wide question SystemPrompt::build() needs to decide whether the Payment paragraph
     * belongs in the prompt at all. Runs before a service/staff is picked, so there is no
     * UserBookingData yet to run the rest of getGateways()'s filters (customer group, per-
     * service/staff restrictions) against; those only matter once a specific booking exists.
     *
     * @return bool
     */
    public static function anyGatewayConfigured()
    {
        if ( Lib\Config::payLocallyEnabled() || Lib\Config::stripeCloudEnabled() || Lib\Config::wooCommerceEnabled() ) {
            return true;
        }

        foreach ( AppearanceProxy\Shared::paymentGateways( array() ) as $type => $gateway ) {
            if ( in_array( $type, self::$supported, true )
                && $type !== Entities\Payment::TYPE_WOOCOMMERCE
                && get_option( 'bookly_' . $type . '_enabled' )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * What the chat shows the customer: the priced draft plus the gateways to choose from.
     * The price comes from CartInfo, so it is the same number the payment system will be
     * asked for - taxes, deposit and any discounts included.
     *
     * @param Entities\AiConversation $conversation
     * @return array|false
     */
    public static function getPaymentOptions( Entities\AiConversation $conversation )
    {
        $userData = self::buildUserData( $conversation );
        if ( ! $userData ) {
            return false;
        }

        $gateways = self::getGateways( $userData );
        if ( ! $gateways ) {
            return false;
        }

        $cart_info = $userData->cart->getInfo();
        $draft = $conversation->getBookingDataArray();
        $service = Entities\Service::find( $draft['service_id'] );
        $staff = Entities\Staff::find( $draft['staff_id'] );

        // With deposit payments on, what is charged now is not the price of the service.
        // Showing only one of the two numbers is how a customer ends up surprised either at
        // checkout or at the appointment, so the card carries both.
        $total = $cart_info->getTotal();
        $pay_now = $cart_info->getPayNow();

        return array(
            'title' => $service->getTranslatedTitle(),
            'staff' => $staff->getTranslatedName(),
            'start_date' => $draft['start_date'],
            // Raw, for callers that compare it - a zero total has no checkout to run.
            'pay_now_raw' => $pay_now,
            'total' => Lib\Utils\Price::format( $total ),
            'due_now' => Lib\Utils\Price::format( $pay_now ),
            'deposit' => $pay_now < $total,
            'gateways' => $gateways,
        );
    }

    /**
     * What the chat says once the payment system has answered.
     *
     * Composed here, not asked of the model: the plugin knows exactly what was booked, and a
     * round trip to Cloud would only cost money and give the model a chance to get the
     * details wrong. Wording follows the appearance texts so a site can translate it.
     *
     * @param Entities\AiConversation $conversation
     * @param string                  $status Lib\Base\Gateway::STATUS_*
     * @return string
     */
    public static function getResultText( Entities\AiConversation $conversation, $status )
    {
        if ( $status !== Lib\Base\Gateway::STATUS_COMPLETED && $status !== Lib\Base\Gateway::STATUS_PROCESSING ) {
            return self::getText( 'paymentFailed' );
        }

        $draft = $conversation->getBookingDataArray();
        $service = isset( $draft['service_id'] ) ? Entities\Service::find( $draft['service_id'] ) : null;
        $staff = isset( $draft['staff_id'] ) ? Entities\Staff::find( $draft['staff_id'] ) : null;

        // "Pay locally" ends the checkout successfully but takes no money: the payment stays
        // pending until the customer turns up. Saying "payment received" there would be a
        // plain untruth, so that case gets its own line.
        //
        // No dedicated payment_id column: Gateway::createIntent() puts at most one Payment
        // against an order (Gateway::fail() removes the order along with it on any failed
        // attempt), so the order_id already on the conversation resolves it on its own.
        $paid = true;
        if ( $conversation->getOrderId() ) {
            $payment = new Entities\Payment();
            $paid = $payment->loadBy( array( 'order_id' => $conversation->getOrderId() ) )
                && $payment->getStatus() === Entities\Payment::STATUS_COMPLETED;
        }

        if ( $status === Lib\Base\Gateway::STATUS_PROCESSING ) {
            // The gateway took the customer through but has not told us about the money
            // yet - it will, through its webhook. The booking itself is real, so this says
            // so, and does not claim a payment that may still fail.
            $key = 'paymentAwaiting';
        } else {
            $key = $paid ? 'paymentCompleted' : 'paymentPending';
        }

        return strtr( self::getText( $key ), array(
            '{service_name}' => $service ? $service->getTranslatedTitle() : '',
            '{staff_name}' => $staff ? $staff->getTranslatedName() : '',
            '{appointment_date}' => isset( $draft['start_date'] )
                ? Lib\Utils\DateTime::formatDate( $draft['start_date'] )
                : '',
            '{appointment_time}' => isset( $draft['start_date'] )
                ? Lib\Utils\DateTime::formatTime( $draft['start_date'] )
                : '',
        ) );
    }

    /**
     * One of the chat's own lines (the appearance's `l10n` texts), as the owner wrote it.
     *
     * Single source of truth for the wording: the same defaults the appearance settings
     * form shows and pre-fills, so a site that never touched these fields and a site that
     * cleared one back to blank see identical text.
     *
     * @param string $key
     * @return string
     */
    public static function getText( $key )
    {
        $l10n = ModernAppearance::getAppearance( Entities\Form::TYPE_AI_ASSISTANT, null );
        if ( isset( $l10n['l10n'][ $key ] ) && $l10n['l10n'][ $key ] !== '' ) {
            return $l10n['l10n'][ $key ];
        }

        $defaults = ModernAppearance::getAiAssistantDefaults();

        return isset( $defaults['l10n'][ $key ] ) ? $defaults['l10n'][ $key ] : '';
    }

    /**
     * @param array $gateways
     * @return array
     */
    protected static function orderGateways( array $gateways )
    {
        $ordered = array();
        foreach ( (array) Lib\Config::getGatewaysPreference() as $type ) {
            if ( in_array( $type, $gateways ) ) {
                $ordered[] = $type;
            }
        }
        foreach ( $gateways as $type ) {
            if ( ! in_array( $type, $ordered ) ) {
                $ordered[] = $type;
            }
        }

        return $ordered;
    }
}
