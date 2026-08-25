<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Lib;

class GetLocations implements ToolInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'get_locations';
    }

    /**
     * @inheritDoc
     *
     * "search" required-but-may-be-empty for the same Cloud-side reason as
     * GetServices::getSchema()'s "search" — see that class's doc block.
     */
    public function getSchema()
    {
        return array(
            'name'        => $this->getName(),
            'description' => 'List this business\'s locations: id, name, and address/info. If there is more than one, ask the customer which one before calling get_staff/get_available_slots/check_availability/create_booking, and pass its id as location_id to all of them. If this returns exactly one location, just use it without asking.',
            'parameters'  => array(
                'type'       => 'object',
                'properties' => array(
                    'search' => array(
                        'type'        => 'string',
                        'description' => 'Substring to filter locations by name (e.g. what the customer asked about). Pass an empty string to list all locations.',
                    ),
                ),
                'required'   => array( 'search' ),
            ),
        );
    }

    /**
     * @inheritDoc
     */
    public function execute( array $arguments )
    {
        $search = isset( $arguments['search'] ) ? trim( (string) $arguments['search'] ) : '';
        $locations = Lib\Proxy\Locations::getAll() ?: array();

        $list = array();
        foreach ( $locations as $location ) {
            $name = Lib\Utils\Common::getTranslatedString( 'location_' . $location['id'], $location['name'] );
            if ( $search !== '' && stripos( $name, $search ) === false ) {
                continue;
            }
            $list[] = array(
                'id'   => (int) $location['id'],
                'name' => $name,
                'info' => Lib\Utils\Common::getTranslatedString( 'location_' . $location['id'] . '_info', $location['info'] ),
            );
        }

        return wp_json_encode( $list );
    }
}
