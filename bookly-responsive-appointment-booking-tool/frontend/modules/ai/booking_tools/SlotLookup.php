<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Lib;

class SlotLookup
{
    /**
     * Bookable start times for one staff/service/day ("Y-m-d H:i:s", WP time zone).
     *
     * Goes through Lib\Utils\Appointment::getDaySchedule() — the widget's own slot engine
     * (Lib\Slots\Finder), which also sees external calendar events, Bookly Events, padding,
     * the slot grid etc. — unlike Appointment::checkTime(), which only sees bookly_appointments.
     *
     * @param Lib\Entities\Service $service
     * @param int $staff_id
     * @param string $date "Y-m-d"
     * @param int|null $location_id
     * @param array $extras [extra_id => quantity], from ExtrasInput::parse()
     * @return string[]
     */
    public static function slotsForDay( Lib\Entities\Service $service, $staff_id, $date, $location_id = null, array $extras = array() )
    {
        $schedule = Lib\Utils\Appointment::getDaySchedule(
            array( (int) $staff_id ),
            $service->getId(),
            $date,
            0,
            $location_id ?: null,
            array( $extras ), // per-customer list, getDaySchedule() keeps the longest one
            1
        );

        $times = array();
        foreach ( $schedule['start'] as $option ) {
            if ( empty( $option['disabled'] ) ) {
                $times[] = $date . ' ' . $option['value'] . ':00';
            }
        }

        return $times;
    }

    /**
     * Whether the widget would offer a slot starting exactly at $start.
     *
     * @param Lib\Entities\Service $service
     * @param int $staff_id
     * @param \DateTime $start WP time zone
     * @param int|null $location_id
     * @param array $extras
     * @param string[] $day_times Out: all bookable starts that day, for suggesting alternatives
     * @return bool
     */
    public static function isBookable( Lib\Entities\Service $service, $staff_id, \DateTime $start, $location_id, array $extras, &$day_times = array() )
    {
        $day_times = self::slotsForDay( $service, $staff_id, $start->format( 'Y-m-d' ), $location_id, $extras );

        return in_array( $start->format( 'Y-m-d H:i:s' ), $day_times, true );
    }

    /**
     * Alternatives for a "not available" reply, capped to keep the tool result short.
     *
     * @param string[] $day_times
     * @param int $max
     * @return string
     */
    public static function describeAlternatives( array $day_times, $max = GetAvailableSlots::MAX_SLOTS_SHOWN )
    {
        if ( ! $day_times ) {
            return 'There are no bookable times that day at all — suggest a different date or staff member.';
        }

        $shown = array_slice( $day_times, 0, $max );
        $more  = count( $day_times ) - count( $shown );

        return 'Bookable start times that day are: ' . implode( ', ', $shown ) . ( $more > 0 ? ', and ' . $more . ' more later that day' : '' ) . '. Offer the customer one of these instead.';
    }
}
