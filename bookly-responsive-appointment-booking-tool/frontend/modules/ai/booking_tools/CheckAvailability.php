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
            'start_date' => array( 'type' => 'string', 'description' => 'Requested start date/time, format "YYYY-MM-DD HH:MM:SS" (24-hour).' ),
        );

        // Only when the Locations addon is active, same as get_locations in Tools::all().
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
            'description' => 'Check whether a specific service (with optional extras) can be booked with a specific staff member starting at a specific date/time, and what it costs. Do not tell the customer a slot is free unless this tool confirmed it.',
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

        // SlotLookup rejects "too soon" slots as well, but can't tell the model why.
        $min_prior = (int) Lib\Proxy\Pro::getMinimumTimePriorBooking( $service_id );
        if ( $min_prior > 0 && Lib\Slots\DatePoint::now()->gte( Lib\Slots\DatePoint::fromStr( $start_date )->modify( -$min_prior ) ) ) {
            return 'Not available: this slot is too soon — this service requires at least ' . round( $min_prior / 3600, 1 ) . ' hour(s) advance notice. Find a later time with get_available_slots.';
        }

        $day_times = array();
        if ( ! SlotLookup::isBookable( $service, $staff_id, $start, $location_id ?: null, $extras, $day_times ) ) {
            return 'Not available: ' . $staff->getFullName() . ' cannot take "' . $service->getTitle() . '" at ' . $start_date . '. '
                . SlotLookup::describeAlternatives( $day_times, $start );
        }

        $extras_duration = ExtrasInput::totalDuration( $extras );
        $display_end = ( clone $start )->modify( '+' . ( $service->getDuration() + $extras_duration ) . ' seconds' )->format( 'Y-m-d H:i:s' );
        $total_price = Lib\Utils\Price::format( ServicePrice::forBooking( $service, $staff_id, $location_id, $start_date, $extras ) );

        return 'Available: ' . $staff->getFullName() . ' can perform "' . $service->getTitle() . '"'
            . ( $extras ? ' with the requested extras' : '' ) . ' starting ' . $start_date
            . ' (ends ' . $display_end . '), total price ' . $total_price . '.';
    }
}
