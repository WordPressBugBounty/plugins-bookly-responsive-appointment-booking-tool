<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Lib;

class CheckAvailability implements ToolInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'check_availability';
    }

    /**
     * @inheritDoc
     */
    public function getSchema()
    {
        $properties = array(
            'service_id' => array( 'type' => 'integer', 'description' => 'Service id, from get_services.' ),
            'staff_id' => array( 'type' => 'integer', 'description' => 'Staff id, from get_staff.' ),
            'start_date' => array( 'type' => 'string', 'description' => 'Requested start date/time in the business\'s local time zone, format "YYYY-MM-DD HH:MM:SS" (24-hour).' ),
        );

        // Only offered when the Locations addon is active — see Tools::all()'s
        // own conditional registration of get_locations.
        if ( Lib\Config::locationsActive() ) {
            $properties['location_id'] = array( 'type' => 'integer', 'description' => 'Location id, from get_locations. Omit if this business has a single location.' );
        }

        $properties['extras'] = array(
            'type' => 'array',
            'description' => 'Optional extras to include (from get_services\' "extras" list for this service). Omit or pass an empty array if the customer didn\'t ask for any.',
            'items' => array(
                'type' => 'object',
                'properties' => array(
                    'extra_id' => array( 'type' => 'integer', 'description' => 'Extra id, from get_services.' ),
                    'quantity' => array( 'type' => 'integer', 'description' => 'How many of this extra (within its min/max_quantity from get_services).' ),
                ),
                'required' => array( 'extra_id', 'quantity' ),
            ),
        );

        return array(
            'name' => $this->getName(),
            'description' => 'Check whether a specific service (with optional extras) can be booked with a specific staff member starting at a specific date/time. Call this before create_booking — do not tell the customer a slot is free unless this tool confirmed it.',
            'parameters' => array(
                'type' => 'object',
                'properties' => $properties,
                'required' => array( 'service_id', 'staff_id', 'start_date' ),
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
        $extras_arg  = isset( $arguments['extras'] ) && is_array( $arguments['extras'] ) ? $arguments['extras'] : array();

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

        // Parity with the public widget's own "too soon to book" rule (lib/Cart.php:474),
        // not otherwise covered by checkTime(). Resolves to 0 (no-op) when Pro is
        // inactive — Proxy::__callStatic() (lib/base/Proxy.php) returns null for a
        // "get*" method when the backing addon isn't active.
        $min_prior = (int) Lib\Proxy\Pro::getMinimumTimePriorBooking( $service_id );
        if ( $min_prior > 0 && Lib\Slots\DatePoint::now()->gte( Lib\Slots\DatePoint::fromStr( $start_date )->modify( -$min_prior ) ) ) {
            return 'Not available: this slot is too soon — this service requires at least ' . round( $min_prior / 3600, 1 ) . ' hour(s) advance notice.';
        }

        // checkTime() below validates overlap/schedule/duration but not
        // whether this exact minute is one the real booking widget would
        // ever offer (its slot picker only ever shows times on a fixed
        // grid, e.g. every 15 minutes from the staff's schedule start) — a
        // customer asking for an arbitrary time like 11:11 must not be
        // treated as bookable just because nothing else conflicts with it.
        if ( ! SlotGrid::isAligned( $service, $staff, $start, $location_id ?: null ) ) {
            return 'Not available: this business only takes bookings at fixed time slots (not arbitrary minutes) — offer the customer the nearest slot times instead.';
        }

        $customers = array( array(
            'id'                => 0,
            'status'            => Lib\Entities\CustomerAppointment::STATUS_APPROVED,
            'number_of_persons' => 1,
            'extras'            => $extras,
        ) );

        // $end_date passed to checkTime()/save() below is always the plain
        // service-only end — both already add extras duration internally
        // from $customers[0]['extras'] for their own validation/persistence
        // (Lib\Utils\Appointment::save() sets Appointment.extras_duration as
        // its own column, it never extends end_date itself). Only what THIS
        // tool reports back to the model needs the extras-inclusive time.
        $result = Lib\Utils\Appointment::checkTime( 0, $start_date, $end_date, $staff_id, $service_id, $location_id ?: null, $customers );

        if ( $result['date_interval_not_available'] ) {
            return 'Not available: ' . $staff->getFullName() . ' already has an appointment at that time.';
        }
        if ( $result['interval_not_in_staff_schedule'] ) {
            return 'Not available: ' . $staff->getFullName() . ' is not scheduled to work at that time.';
        }
        if ( $result['interval_not_in_service_schedule'] ) {
            return 'Not available: this service cannot be booked at that time.';
        }
        if ( $result['staff_reaches_working_time_limit'] ) {
            return 'Not available: this would exceed ' . $staff->getFullName() . '\'s working-time limit.';
        }

        $extras_duration = ExtrasInput::totalDuration( $extras );
        $display_end = $extras_duration > 0
            ? ( clone $start )->modify( '+' . ( $service->getDuration() + $extras_duration ) . ' seconds' )->format( 'Y-m-d H:i:s' )
            : $end_date;
        $total_price = ExtrasInput::totalPriceFormatted( $service, $extras );

        return 'Available: ' . $staff->getFullName() . ' can perform "' . $service->getTitle() . '"'
            . ( $extras ? ' with the requested extras' : '' ) . ' starting ' . $start_date
            . ' (ends ' . $display_end . '), total price ' . $total_price . '.';
    }
}
