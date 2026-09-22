<?php
namespace Bookly\Frontend\Modules\Ai;

use Bookly\Lib;

class Tools
{
    /**
     * @param Lib\Entities\AiConversation|null $conversation The chat the tool call belongs to.
     *   create_booking needs it to park its priced draft somewhere the browser's own request
     *   can pick it up; every other tool is read-only and ignores it. Omitted when this is
     *   called just to collect the schemas for the /complete payload.
     * @return BookingTools\ToolInterface[] keyed by tool name
     */
    public static function all( $conversation = null )
    {
        $tools = array(
            new BookingTools\GetServices(),
            new BookingTools\GetStaff(),
            new BookingTools\GetAvailableSlots(),
            new BookingTools\CheckAvailability(),
            new BookingTools\CreateBooking( $conversation ),
        );

        // Single-location sites (the common case) have no use for this —
        // the other tools' location_id argument stays optional/unused
        // (behaves like the pre-Locations MVP, a bare null) whether or not
        // the model ever calls it.
        if ( Lib\Config::locationsActive() ) {
            $tools[] = new BookingTools\GetLocations();
        }

        $by_name = array();
        foreach ( $tools as $tool ) {
            $by_name[ $tool->getName() ] = $tool;
        }

        return $by_name;
    }
}
