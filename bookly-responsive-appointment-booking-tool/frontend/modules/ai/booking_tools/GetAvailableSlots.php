<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Lib;

/**
 * Lists real bookable start times for one staff/service/day, so the model
 * has actual data to offer the customer instead of guessing round numbers
 * (09:00, 10:00, 11:00 ...) that then fail check_availability — reproduced
 * live: with only get_services/get_staff/check_availability/create_booking
 * registered, the model had no source for candidate times and fabricated
 * plausible-looking ones to ask "does this work?", wasting a round trip
 * whenever the guess was wrong.
 *
 * Wraps the same Lib\Slots\Finder/Generator engine the public booking widget
 * itself uses (see frontend/modules/booking/Ajax.php::renderTime()) instead
 * of reimplementing schedule/break/booking-conflict logic a second time — a
 * fresh, unsaved Lib\UserBookingData is built in memory for this one lookup
 * only (sessionSave() is never called), so this tool has no session/cart
 * side effects and never touches the customer's own in-progress booking
 * session (if any).
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

        // Only offered when the Locations addon is active — see Tools::all()'s
        // own conditional registration of get_locations.
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

        // Same validation as CheckAvailability — keep both tools agreeing on
        // what a "valid" service/staff/link looks like.
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

        // Throwaway, unsaved booking session — built in memory for this one
        // lookup and discarded at the end of the request. fillData()'s keys
        // mirror frontend/modules/booking/Ajax.php::_setDataForSkippedServiceStep(),
        // the same helper the public widget uses to seed a chain when a step
        // is skipped.
        $userData = new Lib\UserBookingData( 'ai-tool-slots-' . uniqid(), 0 );
        $userData->chain->clear();
        $chain_item = new Lib\ChainItem();
        $chain_item
            ->setServiceId( $service_id )
            ->setStaffIds( array( $staff_id ) )
            ->setNumberOfPersons( 1 )
            ->setQuantity( 1 )
            ->setUnits( $service->getUnitsMin() ?: 1 )
            ->setLocationId( $location_id ?: null );
        if ( $extras ) {
            $chain_item->setExtras( $extras );
        }
        $userData->chain->add( $chain_item );

        $userData->fillData( array(
            'date_from'      => $date,
            'days'           => array( 1, 2, 3, 4, 5, 6, 7 ),
            'time_from'      => null,
            'time_to'        => null,
            'slots'          => array(),
            'edit_cart_keys' => array(),
        ) );

        // Bound the search to just the requested day — prepare()'s $end_date
        // becomes Finder::client_end_dp, and Finder's own default break
        // callback (_breakDefault) stops the generator once it's reached.
        $end_of_day = Lib\Slots\DatePoint::fromStr( $date . ' 00:00:00' )->modify( '+1 days' );

        $finder = new Lib\Slots\Finder(
            $userData,
            function ( Lib\Slots\DatePoint $client_dp ) {
                return $client_dp->format( 'Y-m-d' );
            }, // single group: the requested day itself
            function () {
                return 0; // never stop early — the day boundary above already bounds the search
            },
            false, // waiting_list_enabled — a waiting-list-only opening isn't a real bookable time to offer
            array(),
            false, // show_blocked_slots — never offer a slot that's actually taken
            false
        );
        $finder->prepare( $end_of_day )->load();

        $times = array();
        foreach ( $finder->getSlots() as $group_slots ) {
            /** @var Lib\Slots\Range $slot */
            foreach ( $group_slots as $slot ) {
                if ( $slot->notFullyBooked() ) {
                    $times[] = $slot->start()->format( 'Y-m-d H:i:s' );
                }
            }
        }

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
