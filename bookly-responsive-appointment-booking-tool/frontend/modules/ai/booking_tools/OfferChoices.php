<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Lib;

/**
 * Not a lookup: the model calls this to put the options it is offering the
 * customer under its reply as cards - services with duration and price, services and staff
 * with their photo, start times as a grid. A click sends the option's label as
 * an ordinary customer message, so the model reads the choice the same way as
 * if it had been typed - the ids behind it are already in the history from the
 * get_* call that produced them.
 *
 * Options are built here from the ids, never taken from the model: a card
 * always shows a real service/staff member/location/time, and an id the model
 * got wrong is dropped instead of shown.
 *
 * What the model gets back is just ids and labels. The details a card shows
 * are looked up when the browser asks for them (present(), via Ajax::aiPoll()):
 * image URLs and prices would only be tokens the model has no use for.
 *
 * When the model calls nothing but this tool alongside its text, the turn ends
 * right after it (Ajax::aiWorker()) - there is nothing left for the model to
 * say, and another /complete round would only cost time and tokens.
 */
class OfferChoices implements ToolInterface
{
    const NAME = 'offer_choices';

    const TYPE_SERVICE  = 'service';
    const TYPE_STAFF    = 'staff';
    const TYPE_LOCATION = 'location';
    const TYPE_SLOT     = 'slot';

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @inheritDoc
     */
    public function getSchema()
    {
        $types = array( self::TYPE_SERVICE, self::TYPE_STAFF, self::TYPE_SLOT );
        if ( Lib\Config::locationsActive() ) {
            $types[] = self::TYPE_LOCATION;
        }

        return array(
            'name'        => $this->getName(),
            'description' => 'Show services, staff members, ' . ( Lib\Config::locationsActive() ? 'locations ' : '' ) . 'or start times as clickable cards under your reply. Call it in the same reply as your text whenever that text would name two or more of them — whether answering a question about them or asking the customer to pick one — with exactly the ones you mean. The cards already show each one with its details (duration and price of a service, a staff member\'s photo, the date of a time), so do not list them in your text — write just a lead-in or the question. A click sends the option\'s label back as the customer\'s next message.',
            'parameters'  => array(
                'type'       => 'object',
                'properties' => array(
                    'type' => array(
                        'type'        => 'string',
                        'enum'        => $types,
                        'description' => 'What the options are.',
                    ),
                    'ids'  => array(
                        'type'        => 'array',
                        'items'       => array( 'type' => 'string' ),
                        'description' => 'Ids exactly as get_services/get_staff' . ( Lib\Config::locationsActive() ? '/get_locations' : '' ) . ' returned them, or for "slot" start times from get_available_slots with their date (YYYY-MM-DD HH:MM:SS). In the order to show.',
                    ),
                    'staff_id' => array(
                        'type'        => 'string',
                        'description' => 'For "service" only: the staff member these services are for, when the customer has chosen or asked about one - the cards then show that staff member\'s prices. Leave empty otherwise; the cards then show each service\'s price range.',
                    ),
                ),
                'required'   => array( 'type', 'ids' ),
            ),
        );
    }

    /**
     * @inheritDoc
     */
    public function execute( array $arguments )
    {
        $type = isset( $arguments['type'] ) ? (string) $arguments['type'] : '';
        $ids  = isset( $arguments['ids'] ) && is_array( $arguments['ids'] ) ? $arguments['ids'] : array();

        switch ( $type ) {
            case self::TYPE_SERVICE:
                $options = self::serviceOptions( $ids );
                break;
            case self::TYPE_STAFF:
                $options = self::staffOptions( $ids );
                break;
            case self::TYPE_LOCATION:
                $options = Lib\Config::locationsActive() ? self::locationOptions( $ids ) : array();
                break;
            case self::TYPE_SLOT:
                $options = self::slotOptions( $ids );
                break;
            default:
                return 'Error: unknown type "' . $type . '".';
        }

        // Two ids resolving to the same label would be two cards sending the
        // same message - keep the first.
        $unique = array();
        foreach ( $options as $option ) {
            if ( ! isset( $unique[ $option['label'] ] ) ) {
                $unique[ $option['label'] ] = $option;
            }
        }

        if ( ! $unique ) {
            return 'Error: none of the ids is valid for type "' . $type . '", nothing was shown.';
        }

        $result = array(
            'type'             => $type,
            'shown_as_buttons' => array_values( $unique ),
        );

        // Kept in the result for present(), which prices the cards for this
        // staff member. An id that is no staff member is ignored - the cards
        // fall back to the price range rather than fail.
        $staff_id = isset( $arguments['staff_id'] ) ? (int) $arguments['staff_id'] : 0;
        if ( $type === self::TYPE_SERVICE && $staff_id && Lib\Entities\Staff::find( $staff_id ) ) {
            $result['staff_id'] = (string) $staff_id;
        }

        return wp_json_encode( $result );
    }

    /**
     * Appended to a get_* result that lists more than one thing. The tool
     * description and system prompt say the same, but a model that has just
     * read a list tends to answer with a list - and not only when it asks the
     * customer to pick: answering a question about a staff member's services
     * is a list too. The result is the last thing it reads before it answers.
     *
     * @param string $type One of self::TYPE_*
     * @param int $count How many options the result lists
     * @return string
     */
    public static function hint( $type, $count )
    {
        return $count > 1
            ? ' Whenever your reply names two or more of these, show them by calling ' . self::NAME . ' (type "' . $type . '") with their ids instead of listing them in your text.'
            : '';
    }

    /**
     * The cards a finished offer_choices call put in front of the customer, read
     * back from its stored result - see Ajax::aiPoll(). Details are looked up
     * now rather than stored: a price or photo changed since is what the
     * customer should see.
     *
     * @param string|null $result The 'tool' message content execute() returned
     * @return array|null {type, items: [{label, meta?, image?, group?, time?}]}, null when the call failed
     */
    public static function present( $result )
    {
        $data = json_decode( (string) $result, true );
        if ( empty( $data['type'] ) || empty( $data['shown_as_buttons'] ) || ! is_array( $data['shown_as_buttons'] ) ) {
            return null;
        }

        $items = array();
        foreach ( $data['shown_as_buttons'] as $option ) {
            if ( ! isset( $option['id'], $option['label'] ) ) {
                continue;
            }
            $item = array( 'label' => (string) $option['label'] );

            switch ( $data['type'] ) {
                case self::TYPE_SERVICE:
                    $service = Lib\Entities\Service::find( (int) $option['id'] );
                    if ( $service ) {
                        $staff_id     = isset( $data['staff_id'] ) ? (int) $data['staff_id'] : null;
                        $item['meta'] = Lib\Utils\DateTime::secondsToInterval( $service->getDuration() ) . ' · ' . ServicePrice::formatted( $service, $staff_id );
                        $item['image'] = $service->getImageUrl( 'thumbnail' );
                    }
                    break;
                case self::TYPE_STAFF:
                    $staff = Lib\Entities\Staff::find( (int) $option['id'] );
                    if ( $staff ) {
                        $item['image'] = $staff->getImageUrl( 'thumbnail' );
                    }
                    break;
                case self::TYPE_SLOT:
                    // Several days: the card shows the time under its date's
                    // heading, but a click still has to say which day it means.
                    if ( isset( $option['group'] ) ) {
                        $item['group'] = (string) $option['group'];
                        $item['time']  = (string) $option['time'];
                    }
                    break;
            }

            $items[] = $item;
        }

        return $items ? array( 'type' => (string) $data['type'], 'items' => $items ) : null;
    }

    /**
     * Same visibility rules as GetServices, so nothing the model couldn't have
     * listed turns into a card.
     *
     * @param array $ids
     * @return array[]
     */
    private static function serviceOptions( array $ids )
    {
        $options = array();
        foreach ( $ids as $id ) {
            $service = Lib\Entities\Service::find( (int) $id );
            if ( $service && $service->getType() === Lib\Entities\Service::TYPE_SIMPLE && $service->getVisibility() === Lib\Entities\Service::VISIBILITY_PUBLIC ) {
                $options[] = array( 'id' => (string) $service->getId(), 'label' => $service->getTitle() );
            }
        }

        return $options;
    }

    /**
     * @param array $ids
     * @return array[]
     */
    private static function staffOptions( array $ids )
    {
        $options = array();
        foreach ( $ids as $id ) {
            $staff = Lib\Entities\Staff::find( (int) $id );
            if ( $staff && $staff->getVisibility() === 'public' ) {
                $options[] = array( 'id' => (string) $staff->getId(), 'label' => $staff->getFullName() );
            }
        }

        return $options;
    }

    /**
     * Named the way GetLocations names them.
     *
     * @param array $ids
     * @return array[]
     */
    private static function locationOptions( array $ids )
    {
        $names = array();
        foreach ( Lib\Proxy\Locations::getAll() ?: array() as $location ) {
            $names[ (int) $location['id'] ] = Lib\Utils\Common::getTranslatedString( 'location_' . $location['id'], $location['name'] );
        }

        $options = array();
        foreach ( $ids as $id ) {
            if ( isset( $names[ (int) $id ] ) ) {
                $options[] = array( 'id' => (string) (int) $id, 'label' => $names[ (int) $id ] );
            }
        }

        return $options;
    }

    /**
     * Times in the business's own format and time zone, the one
     * get_available_slots lists them in. When the options span more than one
     * day the label carries the date too (it is what a click sends), and the
     * card groups the times under their dates; otherwise the day is already in
     * the text and "10:00" alone is what the customer would type.
     *
     * Availability is not re-checked here: the model still has to run
     * check_availability on whatever comes back before booking it.
     *
     * @param array $ids
     * @return array[]
     */
    private static function slotOptions( array $ids )
    {
        $points = array();
        foreach ( $ids as $id ) {
            $id = trim( (string) $id );
            $point = \DateTime::createFromFormat( 'Y-m-d H:i:s', $id ) ?: \DateTime::createFromFormat( 'Y-m-d H:i', $id );
            if ( $point ) {
                $points[] = $point->format( 'Y-m-d H:i:s' );
            }
        }

        $days = array_unique( array_map( function ( $point ) {
            return substr( $point, 0, 10 );
        }, $points ) );

        $options = array();
        foreach ( $points as $point ) {
            if ( count( $days ) > 1 ) {
                $options[] = array(
                    'id'    => $point,
                    'label' => Lib\Utils\DateTime::formatDateTime( $point ),
                    'group' => Lib\Utils\DateTime::formatDate( $point ),
                    'time'  => Lib\Utils\DateTime::formatTime( $point ),
                );
            } else {
                $options[] = array( 'id' => $point, 'label' => Lib\Utils\DateTime::formatTime( $point ) );
            }
        }

        return $options;
    }
}
