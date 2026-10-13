<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Lib;

/**
 * Real bookable start times for one staff/service/day, so the model offers actual
 * slots instead of guessing round hours that then fail check_availability.
 */
class GetAvailableSlots implements ToolInterface
{
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
            'date' => array( 'type' => 'string', 'description' => 'Date to search, format "YYYY-MM-DD".' ),
        );

        // Only when the Locations addon is active, same as get_locations in Tools::all().
        if ( Lib\Config::locationsActive() ) {
            $properties['location_id'] = array( 'type' => 'integer', 'description' => GetLocations::ARGUMENT );
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
            'description' => 'List real bookable start times for a service with a specific staff member on a specific date, and the price; if that date has none, it returns the nearest later date that has, with its times. Call this before suggesting candidate times to the customer — never guess or invent times yourself.',
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
        $not_there = GetLocations::checkStaff( $staff, $location_id );
        if ( $not_there !== null ) {
            return $not_there;
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

        $prefix = '';
        if ( ! $times ) {
            $why = SlotLookup::describeWindow( $service, $date );
            $next_day = SlotLookup::nextDayWithSlots( $service, $staff_id, $date, $location_id ?: null, $extras );
            if ( ! $next_day ) {
                $next_day = SlotLookup::previousDayWithSlots( $service, $staff_id, $date, $location_id ?: null, $extras );
            }
            $times = $next_day ? SlotLookup::slotsForDay( $service, $staff_id, $next_day, $location_id ?: null, $extras ) : array();
            if ( ! $times ) {
                return $why
                    ? 'No available slots on ' . $date . ': ' . $why . '.'
                    : 'No available slots for ' . $who_what . ' on any date up to ' . SlotLookup::lastBookableDate()
                        . ', the last date this business takes bookings for. Try a different staff member.';
            }
            $prefix = 'No available slots on ' . $date . ( $why ? ': ' . $why : '' ) . '; the nearest date with available slots is ' . $next_day . ( $next_day < $date ? ' (earlier than asked, nothing is free after it)' : '' ) . '. ';
            $date = $next_day;
        }

        return $prefix . 'All available start times for ' . $who_what . ' on ' . $date . ': ' . SlotLookup::listTimes( $times ) . '.'
            . ' ' . self::describePrice( $service, $staff_id, $location_id, $extras, $times )
            . ' As an id for ' . OfferChoices::NAME . ' or create_booking a time goes with its date: "' . $times[0] . '".'
            . OfferChoices::hint( OfferChoices::TYPE_SLOT, count( $times ) );
    }

    /**
     * What a booking at these times costs, priced the way check_availability and the checkout
     * price it. Special hours can make a time cost more or less, so with them each price
     * gets its own times; without them it is one price for the day.
     *
     * @param Lib\Entities\Service $service
     * @param int                  $staff_id
     * @param int                  $location_id
     * @param array                $extras
     * @param string[]             $times
     * @return string
     */
    private static function describePrice( Lib\Entities\Service $service, $staff_id, $location_id, array $extras, array $times )
    {
        $label = $extras ? 'Total price with the requested extras' : 'Total price';

        if ( ! Lib\Config::specialHoursActive() ) {
            return $label . ': ' . Lib\Utils\Price::format( ServicePrice::forBooking( $service, $staff_id, $location_id, $times[0], $extras ) ) . '.';
        }

        $by_price = array();
        foreach ( $times as $time ) {
            $by_price[ Lib\Utils\Price::format( ServicePrice::forBooking( $service, $staff_id, $location_id, $time, $extras ) ) ][] = $time;
        }
        if ( count( $by_price ) === 1 ) {
            return $label . ': ' . key( $by_price ) . '.';
        }

        $parts = array();
        foreach ( $by_price as $price => $price_times ) {
            $parts[] = $price . ' at ' . SlotLookup::listTimes( $price_times );
        }

        return $label . ' depends on the time: ' . implode( '; ', $parts ) . '.';
    }
}
