<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Lib;

class GetStaff implements ToolInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'get_staff';
    }

    /**
     * @inheritDoc
     *
     * "service_id" is required-but-may-be-empty (string, not integer) for
     * the same reason as GetServices::getSchema()'s "search": a tool called
     * with a genuinely empty arguments object gets corrupted on Cloud's own
     * JSON round-trip on the next /complete call, independent of how this
     * plugin encodes its outgoing payload (reproduced directly). Forcing a
     * required string argument (empty string = no filter) guarantees the
     * model never sends empty arguments here. See GetServices for the full
     * writeup; the underlying bug is Cloud-side, not fixable from here.
     */
    public function getSchema()
    {
        $description = 'List staff members available for booking, with id and full name. Pass service_id (from get_services) to list only staff who perform that service; pass an empty string to list everyone.';

        $properties = array(
            'service_id' => array(
                'type'        => 'string',
                'description' => 'Service id (from get_services) to filter staff who can perform it, as a string. Pass an empty string to list all staff.',
            ),
        );

        // location_id only makes sense (and is only offered) when the
        // Locations addon is active — see Tools::all()'s own conditional
        // registration of get_locations.
        if ( Lib\Config::locationsActive() ) {
            $description .= ' If this business has multiple locations (see get_locations), pass location_id to list only staff who work at that location.';
            $properties['location_id'] = array(
                'type'        => 'string',
                'description' => 'Location id (from get_locations) to filter staff who work at that location, as a string. Pass an empty string (or omit) if this business has a single location.',
            );
        }

        return array(
            'name' => $this->getName(),
            'description' => $description,
            'parameters' => array(
                'type' => 'object',
                'properties' => $properties,
                'required' => array( 'service_id' ),
            ),
        );
    }

    /**
     * @inheritDoc
     */
    public function execute( array $arguments )
    {
        $service_id  = isset( $arguments['service_id'] ) ? (int) $arguments['service_id'] : 0;
        $location_id = isset( $arguments['location_id'] ) ? (int) $arguments['location_id'] : 0;

        $query = Lib\Entities\Staff::query( 's' )->where( 's.visibility', 'public' );

        if ( $service_id ) {
            $query
                ->innerJoin( 'StaffService', 'ss', 'ss.staff_id = s.id' )
                ->where( 'ss.service_id', $service_id )
                ->groupBy( 's.id' );
        }

        /** @var Lib\Entities\Staff[] $staff */
        $staff = $query->sortBy( 's.full_name' )->find();

        if ( ! $staff ) {
            return '[]';
        }

        $list = array();
        foreach ( $staff as $member ) {
            // Lib\Proxy\Locations::findByStaffId() returns null (addon
            // inactive — nothing to filter by) or a Location[] (possibly
            // empty: a staff member with no bookly_staff_locations row
            // works at zero locations, same rule
            // BooklyLocations\Lib\ProxyProviders\Local::addServices() uses
            // to decide which locations a staff/service pair is offered at).
            if ( $location_id ) {
                $staff_locations = Lib\Proxy\Locations::findByStaffId( $member->getId() );
                if ( $staff_locations !== null ) {
                    $at_location = false;
                    foreach ( $staff_locations as $location ) {
                        // Loose comparison on purpose — Location::getId()
                        // returns a string (reproduced live: entity
                        // hydration doesn't coerce to int despite the '%d'
                        // schema format), so === against our (int)
                        // $location_id always misses.
                        if ( $location->getId() == $location_id ) {
                            $at_location = true;
                            break;
                        }
                    }
                    if ( ! $at_location ) {
                        continue;
                    }
                }
            }

            $list[] = array(
                'id'        => $member->getId(),
                'full_name' => $member->getFullName(),
            );
        }

        return wp_json_encode( $list );
    }
}
