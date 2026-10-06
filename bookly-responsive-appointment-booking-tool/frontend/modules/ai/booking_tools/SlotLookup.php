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
     * The first day after $date with bookable start times, up to the booking horizon.
     *
     * @param Lib\Entities\Service $service
     * @param int $staff_id
     * @param string $date "Y-m-d"
     * @param int|null $location_id
     * @param array $extras [extra_id => quantity], from ExtrasInput::parse()
     * @return string|null "Y-m-d"
     */
    public static function nextDayWithSlots( Lib\Entities\Service $service, $staff_id, $date, $location_id = null, array $extras = array() )
    {
        $chain_item = new Lib\ChainItem();
        $chain_item
            ->setStaffIds( array( (int) $staff_id ) )
            ->setServiceId( $service->getId() )
            ->setLocationId( $location_id ?: null )
            ->setNumberOfPersons( 1 )
            ->setQuantity( 1 )
            ->setUnits( 1 )
            ->setExtras( $extras );

        $userData = new Lib\UserBookingData( null );
        $userData->resetChain();
        $userData->chain = new Lib\Chain();
        $userData->chain->add( $chain_item );
        $userData->setDays( array( 1, 2, 3, 4, 5, 6, 7 ) );

        $next_day = date_create( $date )->modify( '+1 day' )->format( 'Y-m-d' );
        $slots = Lib\Slots\Finder::forCustomer( $userData, $next_day )->getSlots();

        return $slots ? key( $slots ) : null;
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
     * One day's start times, all of them, as "HH:MM, HH:MM, ...". The date is
     * said once by the caller rather than on every time: a day at a 15-minute
     * grid is some forty times, and only the whole day lets the model answer
     * "something after lunch" - the first dozen would all be morning.
     *
     * @param string[] $day_times "Y-m-d H:i:s", one day, in order
     * @return string
     */
    public static function listTimes( array $day_times )
    {
        return implode( ', ', array_map( function ( $time ) {
            return substr( $time, 11, 5 );
        }, $day_times ) );
    }

    /**
     * Alternatives for a "not available" reply: the bookable times right before
     * and after the one asked for, then the whole day. The nearest ones come
     * first because a customer who asked for 15:00 means "around 15:00", not
     * the first free time in the morning.
     *
     * @param string[] $day_times
     * @param \DateTime $wanted The start the customer asked for
     * @return string
     */
    public static function describeAlternatives( array $day_times, \DateTime $wanted )
    {
        if ( ! $day_times ) {
            return 'There are no bookable times that day at all — suggest a different date or staff member.';
        }

        // "Y-m-d H:i:s" strings of one day compare in time order.
        $wanted = $wanted->format( 'Y-m-d H:i:s' );
        $before = null;
        $after  = null;
        foreach ( $day_times as $time ) {
            if ( $time < $wanted ) {
                $before = $time;
            } elseif ( $after === null ) {
                $after = $time;
            }
        }
        $nearest = array_values( array_filter( array( $before, $after ) ) );

        return 'Nearest bookable start times that day: ' . self::listTimes( $nearest ) . '. All bookable start times that day: ' . self::listTimes( $day_times )
            . '. Offer the customer the nearest ones, or others that fit what they asked for.';
    }
}
