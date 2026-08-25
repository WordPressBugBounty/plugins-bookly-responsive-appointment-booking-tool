<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Lib;

/**
 * Not a tool itself — a shared helper for CheckAvailability/CreateBooking.
 *
 * Lib\Utils\Appointment::checkTime() validates overlap/schedule-window/
 * duration but never checks whether a start time actually falls on the same
 * slot grid the public booking widget generates (e.g. every 15 minutes from
 * the staff's schedule start) — reproduced live: a customer typing an
 * arbitrary time like 11:11 passes checkTime() cleanly as long as staff is
 * scheduled and nothing else overlaps, something the real widget's slot
 * picker (Lib\Slots\Finder/Generator) would never even offer as an option.
 * Without this, an AI-chat booking could land off-grid in a way a normal
 * booking never could.
 *
 * This intentionally does not replicate every edge case Lib\Slots\Finder
 * handles (special days override the regular weekly schedule entirely, and
 * a night schedule crossing midnight anchors to the previous day) — those
 * are rare enough, and the cost of a false rejection (a valid special-day
 * slot getting blocked) is worse than the cost of an occasional off-grid
 * slot slipping through on those edge cases. The common case — the
 * business's regular weekly schedule, split into sub-windows by breaks — is
 * covered.
 */
class SlotGrid
{
    /**
     * @param Lib\Entities\Service $service
     * @param Lib\Entities\Staff   $staff
     * @param \DateTime            $start
     * @param int|null             $location_id Same value passed to
     *   Lib\Utils\Appointment::checkTime()/save() for this booking — null on
     *   sites without the Locations add-on. Resolved through
     *   Lib\Proxy\Locations::prepareStaffScheduleLocationId() below, exactly
     *   like checkTime() itself does (lib/utils/Appointment.php ~599),
     *   before reading the staff's schedule — a staff member with a
     *   per-location custom schedule (StaffLocation::custom_schedule) has a
     *   different weekly grid at each location, so looking up
     *   getScheduleItems() with the wrong (or no) location would silently
     *   grid-check against the wrong day's hours.
     * @return bool
     */
    public static function isAligned( Lib\Entities\Service $service, Lib\Entities\Staff $staff, \DateTime $start, $location_id = null )
    {
        $slot_length = self::resolveSlotLength( $service );
        if ( $slot_length <= 0 ) {
            // Can't determine a grid — don't block on something we can't check.
            return true;
        }

        // Staff::getScheduleItems() keys its result 1..7 with 1 = Sunday,
        // matching date('w') (0 = Sunday) shifted by one — same mapping
        // Lib\Utils\Appointment::checkTime() itself uses.
        $day_index = (int) $start->format( 'w' ) + 1;
        $schedule_location_id = Lib\Proxy\Locations::prepareStaffScheduleLocationId( $location_id, $staff->getId() ) ?: null;
        $schedule_items = $staff->getScheduleItems( $schedule_location_id );
        if ( ! isset( $schedule_items[ $day_index ] ) || ! $schedule_items[ $day_index ]->getStartTime() ) {
            // No regular schedule this weekday (e.g. a special-day override
            // is what actually makes this slot valid) — checkTime() already
            // confirmed the window itself is valid; skip the grid check
            // rather than risk rejecting a legitimate special-day slot.
            return true;
        }

        $item = $schedule_items[ $day_index ];
        $start_of_day = ( clone $start )->setTime( 0, 0, 0 );
        $start_seconds = $start->getTimestamp() - $start_of_day->getTimestamp();

        // Each working sub-window (schedule start, and the end of every
        // break) is its own grid anchor — matches how Generator splits an
        // available Range into slots per contiguous sub-range.
        $anchors = array( Lib\Utils\DateTime::timeToSeconds( $item->getStartTime() ) );
        foreach ( $item->getBreaksList() as $break ) {
            $anchors[] = Lib\Utils\DateTime::timeToSeconds( $break['end_time'] );
        }

        foreach ( $anchors as $anchor_seconds ) {
            if ( ( $start_seconds - $anchor_seconds ) % $slot_length === 0 ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolved slot length in seconds — mirrors the precedence
     * Lib\Slots\Finder itself uses (lib/slots/Finder.php ~174-188): a
     * per-service override, falling back to the global setting (or the
     * service's own duration, if the site is configured to use that as the
     * slot length).
     *
     * @param Lib\Entities\Service $service
     * @return int
     */
    private static function resolveSlotLength( Lib\Entities\Service $service )
    {
        $slot_length = $service->getSlotLength();

        if ( $slot_length === Lib\Entities\Service::SLOT_LENGTH_DEFAULT ) {
            return Lib\Config::useServiceDurationAsSlotLength() ? $service->getDuration() : Lib\Config::getTimeSlotLength();
        }
        if ( $slot_length === Lib\Entities\Service::SLOT_LENGTH_AS_SERVICE_DURATION ) {
            return $service->getDuration();
        }

        return (int) $slot_length * MINUTE_IN_SECONDS;
    }
}
