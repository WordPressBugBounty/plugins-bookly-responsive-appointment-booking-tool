<?php
namespace Bookly\Frontend\Modules\Ai;

use Bookly\Backend\Modules\Appearance\ModernAppearance;
use Bookly\Lib;

class SystemPrompt
{
    const BASE = 'Discovery: when get_staff returns exactly one match, use it without asking; ask only when it returns more than one.

Scheduling: if check_availability finds the requested slot unavailable, call get_available_slots again or suggest another day/staff member.';

    const PAYMENT = 'Payment: create_booking does not always confirm a booking. When this business takes payment online it prices the booking and shows the customer payment options in the chat, and its result says so — in that case tell the customer the amount and ask them to pick a payment method there, never that the booking is done, and do not call create_booking again for the same appointment. Once they pay, the confirmation appears on its own.';

    const DATES = 'Dates: each customer message ends with the date/time it was sent, in this business\'s local time zone — treat the one on the latest message as now, and resolve relative dates ("tomorrow", "next Friday") yourself into exact YYYY-MM-DD HH:MM:SS (YYYY-MM-DD for get_available_slots); never ask the customer what today\'s date is.';

    /**
     * Everything here has to stay stable for the whole conversation: Bookly
     * Cloud marks the end of the system prompt as a prompt-cache breakpoint,
     * so any value that changes between two calls (a clock, above all) would
     * invalidate the cached prefix on every single request and quietly turn a
     * ~10% cache read back into a full-price charge. The current time is
     * therefore attached to the customer's message instead - see
     * ::dateLine() and Ajax::buildCompletionPayload().
     *
     * @param string|null $form_slug Token of the AI assistant appearance the widget was
     *                                rendered with; null falls back to the first one.
     * @return string
     */
    public static function build( $form_slug = null )
    {
        $parts = array();

        $business = self::businessDescription( $form_slug );
        if ( $business !== '' ) {
            $parts[] = $business;
        }

        $parts[] = self::BASE;

        if ( Checkout::anyGatewayConfigured() ) {
            $parts[] = self::PAYMENT;
        }

        $parts[] = self::DATES;

        $system = implode( "\n\n", $parts );

        if ( Lib\Config::serviceExtrasActive() ) {
            $system .= ' If the chosen service has optional extras, proactively ask the customer which (if any) before proceeding — never invent extras outside the list get_services returned.';
        }

        return $system;
    }

    /**
     * The business description the owner saved on this widget's appearance.
     *
     * Stays per-form (a site can publish several assistants, e.g. one per
     * location, each with its own description) and the token travels with the
     * request instead of being stored on the conversation - see
     * Ajax::aiSendMessage()/spawnWorker(). Without a token this resolves to the
     * first AI assistant form, which is what ModernAppearance::getAppearance()
     * does for the shortcode too.
     *
     * Safe for Cloud's prompt cache: the text only changes when the owner edits
     * and saves the appearance.
     *
     * @param string|null $form_slug
     * @return string
     */
    private static function businessDescription( $form_slug )
    {
        $appearance = ModernAppearance::getAppearance( Lib\Entities\Form::TYPE_AI_ASSISTANT, $form_slug ?: null );

        return isset( $appearance['business_description'] )
            ? trim( (string) $appearance['business_description'] )
            : '';
    }

    /**
     * The timestamp attached to a customer message: when it was actually
     * sent, never when this payload is built.
     *
     * That distinction matters twice: one customer turn is 3-5 model calls
     * (tool loop) and a conversation is many turns, so a fresh clock — or a
     * line that moves from message to message — would rewrite text the model
     * has already seen and defeat caching of the conversation prefix; and
     * the message is what the model is answering, so its own timestamp is
     * the honest reference point for "tomorrow" in it.
     *
     * The customer's own time zone is mentioned only when it actually differs
     * from the business's — by UTC offset at the moment of the message, not by
     * name: Europe/Kyiv and Europe/Helsinki are different names for the same
     * clock, and the model must not be left to make that comparison itself.
     * Cloud's booking prompt (v2+) stays silent about time zones unless this
     * line says they differ; older plugins never send it, so the bot never
     * brings the subject up on its own.
     *
     * @param string|null $created_at MySQL datetime of the message, in the business's time zone
     * @param string|null $customer_time_zone IANA name from the customer's browser, null when unknown
     * @return string
     */
    public static function dateLine( $created_at = null, $customer_time_zone = null )
    {
        $point = $created_at ? Lib\Slots\DatePoint::fromStr( $created_at ) : Lib\Slots\DatePoint::now();

        $customer = '';
        if ( $customer_time_zone ) {
            try {
                $customer_point = new \DateTime( '@' . $point->format( 'U' ) );
                $customer_point->setTimezone( new \DateTimeZone( $customer_time_zone ) );
                if ( $customer_point->format( 'Z' ) !== $point->format( 'Z' ) ) {
                    $customer = sprintf(
                        ' Customer\'s time zone differs: %s, UTC%s.',
                        $customer_time_zone,
                        $customer_point->format( 'P' )
                    );
                }
            } catch ( \Exception $e ) {
                // Unknown zone name — say nothing rather than guess.
            }
        }

        return sprintf(
            '(Date/time: %s, %s.%s)',
            $point->format( 'Y-m-d H:i:s' ),
            $point->format( 'l' ),
            $customer
        );
    }
}
