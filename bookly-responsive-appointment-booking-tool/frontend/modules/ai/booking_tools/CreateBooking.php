<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Frontend\Modules\Ai\Checkout;
use Bookly\Lib;

class CreateBooking implements ToolInterface
{
    /** @var Lib\Entities\AiConversation|null */
    private $conversation;

    /**
     * @param Lib\Entities\AiConversation|null $conversation Passed by the worker only; schema building needs none.
     */
    public function __construct( $conversation = null )
    {
        $this->conversation = $conversation;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'create_booking';
    }

    /**
     * @inheritDoc
     */
    public function getSchema()
    {
        $properties = array(
            'service_id' => array( 'type' => 'integer', 'description' => 'Service id, from get_services.' ),
            'staff_id' => array( 'type' => 'integer', 'description' => 'Staff id, from get_staff.' ),
            'start_date' => array( 'type' => 'string', 'description' => 'Appointment start date/time, format "YYYY-MM-DD HH:MM:SS" (24-hour).' ),
        );

        // Only when the Locations addon is active, same as get_locations in Tools::all().
        if ( Lib\Config::locationsActive() ) {
            $properties['location_id'] = array( 'type' => 'integer', 'description' => 'Location id, from get_locations. Omit if this business has a single location.' );
        }

        $properties['full_name'] = array( 'type' => 'string', 'description' => "Customer's full name." );
        $properties['email'] = array( 'type' => 'string', 'description' => "Customer's email address. Provide this or phone (or both)." );
        $properties['phone'] = array( 'type' => 'string', 'description' => "Customer's phone number. Provide this or email (or both)." );
        $properties['notes'] = array( 'type' => 'string', 'description' => 'Optional free-text note from the customer about this booking.' );
        $properties['extras'] = array(
            'type' => 'array',
            'description' => 'Optional extras to include (from get_services\' "extras" list). Omit or pass an empty array if none.',
            'items' => array(
                'type' => 'object',
                'properties' => array(
                    'extra_id' => array( 'type' => 'integer', 'description' => 'Extra id, from get_services.' ),
                    'quantity' => array( 'type' => 'integer', 'description' => 'How many of this extra.' ),
                ),
                'required' => array( 'extra_id', 'quantity' ),
            ),
        );

        return array(
            'name' => $this->getName(),
            'description' => 'Book an appointment, optionally with extras. Only call this after a matching check_availability call confirmed the slot (and any extras) is available, and after you have collected the customer\'s full name and at least one contact method (email or phone) from the conversation — never invent these. Read the result carefully and tell the customer what it actually says.',
            'parameters' => array(
                'type' => 'object',
                'properties' => $properties,
                'required' => array( 'service_id', 'staff_id', 'start_date', 'full_name' ),
            ),
        );
    }

    /**
     * @inheritDoc
     */
    public function execute( array $arguments )
    {
        $service_id  = isset( $arguments['service_id'] ) ? (int) $arguments['service_id'] : 0;
        $staff_id    = isset( $arguments['staff_id'] ) ? (int) $arguments['staff_id'] : 0;
        $start_date  = isset( $arguments['start_date'] ) ? trim( (string) $arguments['start_date'] ) : '';
        $location_id = isset( $arguments['location_id'] ) ? (int) $arguments['location_id'] : 0;
        $full_name  = isset( $arguments['full_name'] ) ? trim( (string) $arguments['full_name'] ) : '';
        $email      = isset( $arguments['email'] ) ? trim( (string) $arguments['email'] ) : '';
        $phone      = isset( $arguments['phone'] ) ? trim( (string) $arguments['phone'] ) : '';
        $notes      = isset( $arguments['notes'] ) ? trim( (string) $arguments['notes'] ) : '';
        $extras_arg = isset( $arguments['extras'] ) && is_array( $arguments['extras'] ) ? $arguments['extras'] : array();

        if ( $full_name === '' ) {
            return 'Error: full_name is required.';
        }
        if ( $email === '' && $phone === '' ) {
            return 'Error: at least one of email or phone is required to create a booking.';
        }

        $service = Lib\Entities\Service::find( $service_id );
        if ( ! $service || $service->getType() !== Lib\Entities\Service::TYPE_SIMPLE || $service->getVisibility() !== Lib\Entities\Service::VISIBILITY_PUBLIC ) {
            return 'Error: unknown service_id. Call get_services to get a valid id.';
        }

        $staff = Lib\Entities\Staff::find( $staff_id );
        if ( ! $staff || $staff->getVisibility() !== 'public' ) {
            return 'Error: unknown staff_id. Call get_staff to get a valid id.';
        }

        if ( $location_id && ! Lib\Proxy\Locations::findById( $location_id ) ) {
            return 'Error: unknown location_id. Call get_locations to get a valid id.';
        }

        $link = new Lib\Entities\StaffService();
        $link->loadBy( array( 'staff_id' => $staff_id, 'service_id' => $service_id ) );
        if ( ! $link->isLoaded() ) {
            return 'Error: this staff member does not perform this service. Call get_staff with this service_id to see who does.';
        }

        try {
            $extras = ExtrasInput::parse( $service_id, $extras_arg );
        } catch ( \Exception $e ) {
            return 'Error: ' . $e->getMessage();
        }

        $start = \DateTime::createFromFormat( 'Y-m-d H:i:s', $start_date );
        if ( ! $start ) {
            return 'Error: start_date must be in the format YYYY-MM-DD HH:MM:SS.';
        }
        $end_date = ( clone $start )->modify( '+' . $service->getDuration() . ' seconds' )->format( 'Y-m-d H:i:s' );

        // Re-check the slot (the model may skip check_availability, and time passes between calls),
        // before the customer lookup so a refusal leaves no customer record behind.
        $day_times = array();
        if ( ! SlotLookup::isBookable( $service, $staff_id, $start, $location_id ?: null, $extras, $day_times ) ) {
            return 'Error: this slot is not available (anymore). ' . SlotLookup::describeAlternatives( $day_times, $start ) . ' Call check_availability with the new time before booking.';
        }

        // Find-or-create customer, same exact-match lookup as Validator::postValidateCustomer()
        // minus the WP-account/verification machinery (anonymous chat visitor).
        $customer = new Lib\Entities\Customer();
        if ( $email !== '' ) {
            $customer->loadBy( array( 'email' => $email ) );
        }
        if ( ! $customer->isLoaded() && $phone !== '' ) {
            $customer->loadBy( array( 'phone' => $phone ) );
        }
        if ( ! $customer->isLoaded() ) {
            $customer->setFullName( $full_name )->setEmail( $email )->setPhone( $phone )->save();
        }
        if ( ! $customer->getId() ) {
            return 'Error: could not save customer record.';
        }

        // The only rule SlotLookup can't check without a customer (Pro; no-op otherwise).
        if ( $service->appointmentsLimitReached( $customer->getId(), array( $start_date ) ) ) {
            return 'Error: this customer has reached the maximum number of appointments allowed for this service and cannot book another one.';
        }

        // Draft for the checkout to rebuild the booking later; nothing is written to the
        // appointment tables until Gateway::createIntent() (or book() below).
        $draft = array(
            'customer_id' => $customer->getId(),
            'service_id' => $service_id,
            'staff_id' => $staff_id,
            'location_id' => $location_id ?: null,
            'start_date' => $start_date,
            'extras' => $extras,
            'notes' => $notes,
            'number_of_persons' => 1,
        );

        // No conversation to attach a draft to - book outright.
        if ( ! $this->conversation ) {
            return self::book( $customer, $service, $staff, $draft, $end_date );
        }

        // No payment system switched on - nothing to charge.
        if ( Lib\Config::paymentStepDisabled() ) {
            return self::book( $customer, $service, $staff, $draft, $end_date );
        }

        if ( $this->conversation->checkoutInProgress() ) {
            return 'Error: the customer has not finished paying for the previous booking yet. Ask them to complete or cancel that payment first.';
        }

        $this->conversation
            ->setBookingDataArray( $draft )
            ->setBookingStatus( Lib\Entities\AiConversation::BOOKING_PENDING )
            ->setOrderId( null )
            ->save();

        $options = Checkout::getPaymentOptions( $this->conversation );

        if ( ! $options ) {
            $this->conversation->resetBooking()->save();

            return 'Error: this business accepts no payment method that works for this service and this staff member together. Suggest a different service or staff member.';
        }

        // Priced at zero after taxes/discounts - nothing to pay.
        if ( $options['pay_now_raw'] <= 0 ) {
            $this->conversation->resetBooking()->save();

            return self::book( $customer, $service, $staff, $draft, $end_date, $options['total'] );
        }

        // "Pay locally" as the only gateway needs no choice - same auto-select as the modern
        // booking form (ModernBookingForm\Lib\Request::checkStep()). Coupons/gift cards share
        // that step, so it still has to show while either is active.
        if ( count( $options['gateways'] ) === 1
            && $options['gateways'][0]['name'] === Lib\Entities\Payment::TYPE_LOCAL
            && ! Lib\Config::couponsActive()
            && ! Lib\Config::giftCardsActive()
        ) {
            $this->conversation->resetBooking()->save();

            return self::book( $customer, $service, $staff, $draft, $end_date, $options['total'] );
        }

        // Quote deposit and full price separately, or a deposit reads as a discount.
        $amount = $options['deposit']
            ? sprintf( '%s now (deposit) of %s total', $options['due_now'], $options['total'] )
            : $options['total'];

        return sprintf(
            'Payment required before this booking is confirmed: %s for %s with %s at %s. The customer now sees payment options in the chat — ask them to choose one and complete the payment there. Do not tell them the booking is confirmed, and do not call create_booking again for this appointment; the confirmation will appear on its own once the payment goes through.',
            $amount, $service->getTitle(), $staff->getFullName(), $start_date
        );
    }

    /**
     * Write the appointment straight away, with no payment attached.
     *
     * @param Lib\Entities\Customer $customer
     * @param Lib\Entities\Service  $service
     * @param Lib\Entities\Staff    $staff
     * @param array                 $draft
     * @param string                $end_date Service-only end; save() adds extras duration itself.
     * @param string|null           $total_price Formatted CartInfo total, when the caller already priced the cart
     * @return string
     */
    private static function book( $customer, $service, $staff, array $draft, $end_date, $total_price = null )
    {
        $customers = array( array(
            'id'                => $customer->getId(),
            'status'            => Lib\Config::getDefaultAppointmentStatus(),
            'number_of_persons' => 1,
            'extras'            => $draft['extras'],
            'custom_fields'     => array(),
            'notes'             => $draft['notes'],
            'payment_id'        => null,
            'payment_for'       => null,
            'payment_action'    => null,
            'series_id'         => null,
            'timezone'          => null,
        ) );

        // save() does not check the slot (execute() did) and adds extras duration itself,
        // so $end_date must stay the plain service-only end.
        $result = Lib\Utils\Appointment::save(
            0,                        // appointment_id — 0 = create new
            $draft['staff_id'],
            $draft['service_id'],
            null,                     // custom_service_name — always a real service_id
            null,                     // custom_service_price
            $draft['location_id'],    // location_id — null on single-location sites
            false,                    // skip_date
            $draft['start_date'],
            $end_date,
            array(),                  // repeat — no recurrence support
            null,                     // schedule
            null,                     // reschedule_type — irrelevant for a new appointment
            $customers,
            true,                     // notification — customer/staff get the normal new-booking emails
            'Created via AI chat',    // internal_note
            'frontend'                // created_from
        );

        if ( empty( $result['success'] ) || empty( $result['appointments'] ) ) {
            return 'Error: could not create the booking' . ( ! empty( $result['errors'] ) ? ' (' . wp_json_encode( $result['errors'] ) . ')' : '' ) . '.';
        }

        if ( ! empty( $result['queue']['token'] ) ) {
            Lib\Notifications\Routine::sendNotificationsAssociatedWithQueue( array_keys( $result['queue']['all'] ), 'all', $result['queue']['token'] );
        }

        $appointment = $result['appointments'][0];
        $extras = $draft['extras'];
        $extras_duration = ExtrasInput::totalDuration( $extras );
        $display_end = $extras_duration > 0
            ? date( 'Y-m-d H:i:s', strtotime( $end_date ) + $extras_duration )
            : $end_date;
        if ( $total_price === null ) {
            $total_price = Lib\Utils\Price::format( ServicePrice::forBooking( $service, $staff->getId(), $draft['location_id'], $draft['start_date'], $extras ) );
        }

        return sprintf(
            'Booking confirmed: appointment #%d — %s%s with %s, %s (ends %s), total price %s.',
            $appointment['id'], $service->getTitle(), ( $extras ? ' (with extras)' : '' ), $staff->getFullName(), $draft['start_date'], $display_end, $total_price
        );
    }
}
