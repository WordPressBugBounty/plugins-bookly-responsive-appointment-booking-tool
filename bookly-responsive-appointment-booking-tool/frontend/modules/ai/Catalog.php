<?php
namespace Bookly\Frontend\Modules\Ai;

use Bookly\Lib;

/**
 * What the business offers, given to the model with the system prompt instead of being asked
 * for tool by tool: services with prices and extras, who provides each of them, locations.
 *
 * Without it a conversation spends its first two or three rounds on get_services/get_staff.
 * The volume is the same either way: get_services with an empty search puts the whole
 * catalog into the history anyway.
 *
 * How a booking is paid is deliberately not here: it depends on the service, the staff
 * member and the customer's group together (Checkout::getGateways()), and is only known
 * once create_booking has found the customer - its description and result say so.
 *
 * Safe for Cloud's prompt cache: it changes only when the owner edits the catalog, and the
 * system prompt of a site is unique to that site already.
 */
class Catalog
{
    // About 5k tokens. Past it the model gets every service's id and title and the details
    // of as many as fit, and the get_* tools for the rest - the case get_services's search
    // is there for. A catalog that big is rare; most sites stay far below.
    const MAX_LENGTH = 20000;

    /**
     * @return string
     */
    public static function build()
    {
        /** @var Lib\Entities\Service[] $services */
        $services = Lib\Entities\Service::query( 's' )
            ->where( 's.type', Lib\Entities\Service::TYPE_SIMPLE )
            ->where( 's.visibility', Lib\Entities\Service::VISIBILITY_PUBLIC )
            ->sortBy( 's.title' )
            ->find();

        if ( ! $services ) {
            return '';
        }

        $entries = array();
        $staff = array();
        foreach ( $services as $service ) {
            $entry = BookingTools\GetServices::describe( $service );
            $by_staff = BookingTools\ServicePrice::byStaff( $service );
            $entry['staff_ids'] = array_keys( $by_staff );
            $entries[] = $entry;

            foreach ( $by_staff as $staff_id => $data ) {
                $staff[ $staff_id ] = $data['full_name'];
            }
        }

        $catalog = array();
        list( $catalog['services'], $shown ) = self::fit( $entries );
        $locations = Lib\Config::locationsActive() ? BookingTools\GetLocations::entries() : array();
        $catalog['staff'] = self::staff( $staff, (bool) $locations );
        if ( $locations ) {
            $catalog['locations'] = $locations;
        }

        $notes = array(
            'Catalog of this business, current as of this conversation. Use its ids directly; call get_services, get_staff' . ( $locations ? ' or get_locations' : '' ) . ' only for what it does not detail.'
                . ( Lib\Config::locationsActive() && ! $locations ? ' Bookings here are not tied to a location: never pass location_id.' : '' ),
            'Services have the fields get_services describes, and "staff_ids": the staff members who provide it.'
                . ( $locations ? ' A staff member\'s "location_ids" are the locations they work at.' : '' ),
        );
        if ( $shown < count( $entries ) ) {
            $notes[] = sprintf( 'Details are given for %d of %d services; the rest are listed with id and title only - call get_services for them.', $shown, count( $entries ) );
        }
        if ( Lib\Config::paymentStepDisabled() ) {
            $notes[] = 'No payment is taken in the chat: bookings are confirmed right away.';
        } else {
            $notes[] = 'Whether a booking is paid in the chat and which ways it can be paid depends on the service, the staff member and the customer together, and only create_booking finds that out. If the customer asks how they can pay before that, say the ways to pay are shown on a card once the booking details are collected; never name payment methods yourself.';
        }

        return implode( ' ', $notes ) . "\n" . wp_json_encode( $catalog );
    }

    /**
     * All services in full while the catalog fits MAX_LENGTH; past it, as many in full as fit
     * and the rest with id and title only.
     *
     * @param array[] $entries
     * @return array [ entries to show, how many of them are in full ]
     */
    private static function fit( array $entries )
    {
        if ( strlen( wp_json_encode( $entries ) ) <= self::MAX_LENGTH ) {
            return array( $entries, count( $entries ) );
        }

        $short = array();
        foreach ( $entries as $entry ) {
            $short[] = array( 'id' => $entry['id'], 'title' => $entry['title'] );
        }

        $length = strlen( wp_json_encode( $short ) );
        $shown = 0;
        foreach ( $entries as $i => $entry ) {
            $length += strlen( wp_json_encode( $entry ) ) - strlen( wp_json_encode( $short[ $i ] ) );
            if ( $length > self::MAX_LENGTH ) {
                break;
            }
            $short[ $i ] = $entry;
            $shown++;
        }

        return array( $short, $shown );
    }

    /**
     * @param array $staff [ staff_id => full_name ]
     * @param bool  $with_locations Whether the catalog lists any locations to point to
     * @return array[]
     */
    private static function staff( array $staff, $with_locations )
    {
        asort( $staff );

        $list = array();
        foreach ( $staff as $staff_id => $full_name ) {
            $member = array( 'id' => $staff_id, 'full_name' => $full_name );

            // Null with the addon off. An empty list is a staff member tied to no location:
            // bookable without one, not at any listed location.
            $locations = $with_locations ? Lib\Proxy\Locations::findByStaffId( $staff_id ) : null;
            if ( $locations !== null ) {
                $member['location_ids'] = array();
                foreach ( $locations as $location ) {
                    $member['location_ids'][] = (int) $location->getId();
                }
            }

            $list[] = $member;
        }

        return $list;
    }
}
