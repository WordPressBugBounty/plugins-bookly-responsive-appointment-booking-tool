<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Lib;

class CreateBooking implements ToolInterface
{
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
            'start_date' => array( 'type' => 'string', 'description' => 'Appointment start date/time, format "YYYY-MM-DD HH:MM:SS" (24-hour) — must be a slot check_availability already confirmed.' ),
        );

        // Only offered when the Locations addon is active — see Tools::all()'s
        // own conditional registration of get_locations.
        if ( Lib\Config::locationsActive() ) {
            $properties['location_id'] = array( 'type' => 'integer', 'description' => 'Location id, from get_locations — must match the location check_availability already confirmed. Omit if this business has a single location.' );
        }

        $properties['full_name'] = array( 'type' => 'string', 'description' => "Customer's full name." );
        $properties['email'] = array( 'type' => 'string', 'description' => "Customer's email address. Provide this or phone (or both)." );
        $properties['phone'] = array( 'type' => 'string', 'description' => "Customer's phone number. Provide this or email (or both)." );
        $properties['notes'] = array( 'type' => 'string', 'description' => 'Optional free-text note from the customer about this booking.' );
        $properties['extras'] = array(
            'type' => 'array',
            'description' => 'Optional extras to include (from get_services\' "extras" list), same set already confirmed via check_availability. Omit or pass an empty array if none.',
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
            'description' => 'Create a real appointment booking, optionally with extras. Only call this after a matching check_availability call confirmed the slot (and any extras) is available, and after you have collected the customer\'s full name and at least one contact method (email or phone) from the conversation — never invent these.',
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

        // Same grid guard as CheckAvailability — defense in depth in case
        // create_booking is ever called without a matching check_availability
        // first (model behavior isn't guaranteed): reject an arbitrary
        // off-grid minute the real booking widget would never offer.
        if ( ! SlotGrid::isAligned( $service, $staff, $start, $location_id ?: null ) ) {
            return 'Error: this business only takes bookings at fixed time slots. Call check_availability with a slot-aligned start_date first.';
        }

        // Find-or-create customer — replicates the exact-match lookup shape of
        // Validator::postValidateCustomer() (lib/Validator.php) without the
        // WP-account/verification-code machinery that function is entangled
        // with, since this is an anonymous chat visitor, not a logged-in user.
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

        $customers = array( array(
            'id'                => $customer->getId(),
            'status'            => Lib\Config::getDefaultAppointmentStatus(),
            'number_of_persons' => 1,
            'extras'            => $extras,
            'custom_fields'     => array(),
            'notes'             => $notes,
            'payment_id'        => null,
            'payment_for'       => null,
            'payment_action'    => null,
            'series_id'         => null,
            'timezone'          => null,
        ) );

        // Defense in depth: the model should have called check_availability
        // first, but its behavior is never guaranteed — re-validate right
        // before writing to the DB.
        $check = Lib\Utils\Appointment::checkTime( 0, $start_date, $end_date, $staff_id, $service_id, $location_id ?: null, $customers );
        if ( $check['date_interval_not_available'] || $check['interval_not_in_staff_schedule']
            || $check['interval_not_in_service_schedule'] || $check['staff_reaches_working_time_limit']
            || ! empty( $check['customers_appointments_limit'] ) ) {
            return 'Error: this slot is no longer available. Call check_availability again to find a working time.';
        }

        // $end_date here stays the plain service-only end on purpose — save()
        // adds extras duration internally (from $customers[0]['extras']) into
        // its own Appointment.extras_duration column; passing an already-
        // extended end_date here would double-count it.
        $result = Lib\Utils\Appointment::save(
            0,                        // appointment_id — 0 = create new
            $staff_id,
            $service_id,
            null,                     // custom_service_name — always a real service_id
            null,                     // custom_service_price
            $location_id ?: null,     // location_id — null on single-location sites
            false,                    // skip_date
            $start_date,
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
        $extras_duration = ExtrasInput::totalDuration( $extras );
        $display_end = $extras_duration > 0
            ? ( clone $start )->modify( '+' . ( $service->getDuration() + $extras_duration ) . ' seconds' )->format( 'Y-m-d H:i:s' )
            : $end_date;
        $total_price = ExtrasInput::totalPriceFormatted( $service, $extras );

        return sprintf(
            'Booking confirmed: appointment #%d — %s%s with %s, %s (ends %s), total price %s.',
            $appointment['id'], $service->getTitle(), ( $extras ? ' (with extras)' : '' ), $staff->getFullName(), $start_date, $display_end, $total_price
        );
    }
}
