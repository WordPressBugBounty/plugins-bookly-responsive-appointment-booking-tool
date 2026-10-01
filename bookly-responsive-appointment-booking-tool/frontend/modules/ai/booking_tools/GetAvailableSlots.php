<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Lib;

/**
 * Real bookable start times for one staff/service/day, so the model offers actual
 * slots instead of guessing round hours that then fail check_availability.
 */
class GetAvailableSlots implements ToolInterface
{
    const MAX_SLOTS_SHOWN = 12;

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'get_available_slots';
    }

    /**
     * @inheritDoc
     */
    public function getSchema()
    {
        $properties = array(
            'service_id' => array( 'type' => 'integer', 'description' => 'Service id, from get_services.' ),
            'staff_id' => array( 'type' => 'integer', 'description' => 'Staff id, from get_staff.' ),
            'date' => array( 'type' => 'string', 'description' => 'Date to search, in the business\'s local time zone, format "YYYY-MM-DD".' ),
        );

        // Only when the Locations addon is active, same as get_locations in Tools::all().
        if ( Lib\Config::locationsActive() ) {
            $properties['location_id'] = array( 'type' => 'integer', 'description' => 'Location id, from get_locations. Omit if this business has a single location.' );
        }

        $properties['extras'] = array(
            'type' => 'array',
            'description' => 'Optional extras to include (from get_services\' "extras" list for this service) — affects the duration/spacing of the returned slots. Omit or pass an empty array if the customer didn\'t ask for any.',
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
            'description' => 'List real bookable start times for a service with a specific staff member on a specific date. Call this before suggesting candidate times to the customer — never guess or invent times yourself. Still call check_availability with the exact time the customer picks before promising it or calling create_booking (a slot can be taken by someone else between this call and the booking).',
            'parameters' => array(
                'type' => 'object',
                'properties' => $properties,
                'required' => array( 'service_id', 'staff_id', 'date' ),
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
        $date        = isset( $arguments['date'] ) ? trim( (string) $arguments['date'] ) : '';
        $location_id = isset( $arguments['location_id'] ) ? (int) $arguments['location_id'] : 0;
        $extras_arg  = isset( $arguments['extras'] ) && is_array( $arguments['extras'] ) ? $arguments['extras'] : array();

        // Same validation as CheckAvailability.
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

        $day = \DateTime::createFromFormat( 'Y-m-d', $date );
        if ( ! $day || $day->format( 'Y-m-d' ) !== $date ) {
            return 'Error: date must be in the format YYYY-MM-DD.';
        }

        $times = SlotLookup::slotsForDay( $service, $staff_id, $date, $location_id ?: null, $extras );

        $who_what = $staff->getFullName() . ' performing "' . $service->getTitle() . '"' . ( $extras ? ' with the requested extras' : '' );

        if ( ! $times ) {
            return 'No available slots for ' . $who_what . ' on ' . $date . '. Try a different date or staff member.';
        }

        $shown = array_slice( $times, 0, self::MAX_SLOTS_SHOWN );
        $more  = count( $times ) - count( $shown );

        return 'Available start times for ' . $who_what . ' on ' . $date . ' (business\'s local time zone): '
            . implode( ', ', $shown ) . ( $more > 0 ? ', and ' . $more . ' more later that day' : '' )
            . '. Offer some of these to the customer, then call check_availability with the exact one they pick before confirming or booking.';
    }
}
