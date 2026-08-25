<?php
namespace Bookly\Frontend\Modules\Ai;

use Bookly\Lib;

class SystemPrompt
{
    const BASE = 'Discovery: when get_staff returns exactly one match, use it without asking; ask only when it returns more than one.

Scheduling: if check_availability finds the requested slot unavailable, call get_available_slots again or suggest another day/staff member.

Dates: the current date/time in this business\'s local time zone is stated with the customer\'s latest message — resolve relative dates ("tomorrow", "next Friday") yourself into exact YYYY-MM-DD HH:MM:SS (YYYY-MM-DD for get_available_slots); never ask the customer what today\'s date is.';

    /**
     * Everything here has to stay stable for the whole conversation: Bookly
     * Cloud marks the end of the system prompt as a prompt-cache breakpoint,
     * so any value that changes between two calls (a clock, above all) would
     * invalidate the cached prefix on every single request and quietly turn a
     * ~10% cache read back into a full-price charge. The current time is
     * therefore attached to the customer's message instead - see
     * ::dateLine() and Ajax::buildCompletionPayload().
     *
     * @return string
     */
    public static function build()
    {
        $system = self::BASE;

        if ( Lib\Config::serviceExtrasActive() ) {
            $system .= ' If the chosen service has optional extras, proactively ask the customer which (if any) before proceeding — never invent extras outside the list get_services returned.';
        }

        return $system;
    }

    /**
     * The "now" the model should reason from, pinned to when the customer
     * actually sent the message rather than to when this payload is built.
     *
     * That distinction matters twice: one customer turn is 3-5 model calls
     * (tool loop), so a fresh clock would make the message text differ on
     * every call and defeat caching of the conversation prefix; and the
     * message is what the model is answering, so its own timestamp is the
     * honest reference point for "tomorrow".
     *
     * @param string|null $created_at MySQL datetime of the message, in the business's time zone
     * @return string
     */
    public static function dateLine( $created_at = null )
    {
        $point = $created_at ? Lib\Slots\DatePoint::fromStr( $created_at ) : Lib\Slots\DatePoint::now();

        return sprintf(
            '(Current date and time in this business\'s local time zone: %s, a %s.)',
            $point->format( 'Y-m-d H:i:s' ),
            $point->format( 'l' )
        );
    }
}
