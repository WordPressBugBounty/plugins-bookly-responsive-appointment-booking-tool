<?php
namespace Bookly\Frontend\Modules\Ai;

use Bookly\Lib;

class Tools
{
    /**
     * @return BookingTools\ToolInterface[] keyed by tool name
     */
    public static function all()
    {
        $tools = array(
            new BookingTools\GetServices(),
            new BookingTools\GetStaff(),
            new BookingTools\GetAvailableSlots(),
            new BookingTools\CheckAvailability(),
            new BookingTools\CreateBooking(),
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
