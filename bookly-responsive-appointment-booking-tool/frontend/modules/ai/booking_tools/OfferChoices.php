<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Frontend\Modules\Ai;
use Bookly\Lib;

/**
 * Not a lookup: the model calls this to put the options it is offering the
 * customer under its reply as cards - services with duration and price, services and staff
 * with their photo, start times as a grid. A click sends the option's label as
 * an ordinary customer message, and notes the option's id along with it
 * (notePick()): two staff members or services can share a name, and the label
 * alone would leave the model to guess which one was meant.
 *
 * Options are built here from the ids, never taken from the model: a card
 * always shows a real service/staff member/location/time, and an id the model
 * got wrong is dropped instead of shown. Options sharing a label all show,
 * told apart on the cards (present()).
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
        $locations = Ai\Concepts::forSite()->has( Ai\Concepts::LOCATION );
        $types = array( self::TYPE_SERVICE, self::TYPE_STAFF, self::TYPE_SLOT );
        if ( $locations ) {
            $types[] = self::TYPE_LOCATION;
        }

        return array(
            'name'        => $this->getName(),
            'description' => 'Show services, staff members, ' . ( $locations ? 'locations ' : '' ) . 'or start times as clickable choice cards under your reply. Call it in the same reply as your text whenever that text would name two or more of them — whether answering a question about them or asking the customer to pick one — with exactly the ones you mean. The cards already show each one with its details (duration and price of a service, a staff member\'s photo, the date of a time), so do not list them in your text — write just a lead-in or the question. A click sends the option\'s label back as the customer\'s next message, with a note of the id they picked. When the customer names one in their own words and you cannot tell for sure which one they mean - two share that name, or the words fit more than one - do not pick one yourself: show exactly the ones that fit and ask them to tap the one they mean.',
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
                        'description' => 'Ids from the catalog, written as strings ("62"), or for "slot" start times from get_available_slots with their date (YYYY-MM-DD HH:MM:SS). In the order to show.',
                    ),
                    'staff_id' => array(
                        'type'        => 'integer',
                        'description' => 'For "service": the staff member these services are for, when the customer has chosen or asked about one - the cards then show that staff member\'s prices; omit otherwise, the cards then show each service\'s price range. For "slot": the staff member of these start times, required.',
                    ),
                    'service_id' => array(
                        'type'        => 'integer',
                        'description' => 'For "staff": the service the customer has chosen, if any - staff members sharing a name are then told apart by their price for it. For "slot": the service of these start times, required.',
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
                $options = Ai\Concepts::forSite()->has( Ai\Concepts::LOCATION ) ? self::locationOptions( $ids ) : array();
                break;
            case self::TYPE_SLOT:
                $service = isset( $arguments['service_id'] ) ? Lib\Entities\Service::find( (int) $arguments['service_id'] ) : null;
                $staff_id = isset( $arguments['staff_id'] ) ? (int) $arguments['staff_id'] : 0;
                if ( ! $service || ! $staff_id ) {
                    return 'Error: for "slot" pass service_id and staff_id - the start times are checked against them. Nothing was shown.';
                }
                $options = self::slotOptions( $ids, $service, $staff_id );
                break;
            default:
                return 'Error: unknown type "' . $type . '".';
        }

        // The same id twice would be the same card twice - keep the first. Options
        // that only share a label all stay: they are different services or people,
        // present() tells them apart on the cards and a click names its own id.
        $unique = array();
        foreach ( $options as $option ) {
            if ( ! isset( $unique[ $option['id'] ] ) ) {
                $unique[ $option['id'] ] = $option;
            }
        }

        if ( ! $unique ) {
            return $type === self::TYPE_SLOT
                ? 'Error: none of these start times can be booked with this staff member, nothing was shown. Take start times from get_available_slots.'
                : 'Error: none of the ids is valid for type "' . $type . '", nothing was shown.';
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
        // Same for staff cards: what each of them charges for this service.
        $service_id = isset( $arguments['service_id'] ) ? (int) $arguments['service_id'] : 0;
        if ( $type === self::TYPE_STAFF && $service_id && Lib\Entities\Service::find( $service_id ) ) {
            $result['service_id'] = (string) $service_id;
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
     * @return array|null {type, items: [{id, label, meta?, image?, group?, time?}]}, null when the call failed
     */
    public static function present( $result )
    {
        $data = json_decode( (string) $result, true );
        if ( empty( $data['type'] ) || empty( $data['shown_as_buttons'] ) || ! is_array( $data['shown_as_buttons'] ) ) {
            return null;
        }

        $items = array();
        $labels = array();
        foreach ( $data['shown_as_buttons'] as $option ) {
            if ( isset( $option['label'] ) ) {
                $labels[] = (string) $option['label'];
            }
        }
        $label_counts = array_count_values( $labels );

        foreach ( $data['shown_as_buttons'] as $option ) {
            if ( ! isset( $option['id'], $option['label'] ) ) {
                continue;
            }
            $item = array( 'id' => (string) $option['id'], 'label' => (string) $option['label'] );
            // Shares its label with another card: what tells them apart goes under it.
            $namesake = $label_counts[ $item['label'] ] > 1;

            switch ( $data['type'] ) {
                case self::TYPE_SERVICE:
                    $service = Lib\Entities\Service::find( (int) $option['id'] );
                    if ( $service ) {
                        $staff_id     = isset( $data['staff_id'] ) ? (int) $data['staff_id'] : null;
                        $item['meta'] = Lib\Utils\DateTime::secondsToInterval( $service->getDuration() ) . ' · ' . ServicePrice::formatted( $service, $staff_id );
                        $item['image'] = $service->getImageUrl( 'thumbnail' );
                        $category = $namesake && $service->getCategoryId() ? Lib\Entities\Category::find( $service->getCategoryId() ) : null;
                        if ( $category ) {
                            $item['meta'] .= ' · ' . $category->getTranslatedName();
                        }
                    }
                    break;
                case self::TYPE_STAFF:
                    $staff = Lib\Entities\Staff::find( (int) $option['id'] );
                    if ( $staff ) {
                        $item['image'] = $staff->getImageUrl( 'thumbnail' );
                        if ( $namesake ) {
                            $details = array();
                            $service = isset( $data['service_id'] ) ? Lib\Entities\Service::find( (int) $data['service_id'] ) : null;
                            if ( $service ) {
                                $details[] = ServicePrice::formatted( $service, $staff->getId() );
                            }
                            // null with the Locations add-on off
                            foreach ( Lib\Proxy\Locations::findByStaffId( $staff->getId() ) ?: array() as $location ) {
                                $details[] = Lib\Utils\Common::getTranslatedString( 'location_' . $location->getId(), $location->getName() );
                            }
                            if ( $details ) {
                                $item['meta'] = implode( ' · ', $details );
                            }
                        }
                    }
                    break;
                case self::TYPE_LOCATION:
                    // Its address/info, as the catalog gives it to the model, in one line.
                    if ( $namesake ) {
                        $info = wp_strip_all_tags( self::locationInfo( $option['id'] ), true );
                        if ( $info !== '' ) {
                            $item['meta'] = $info;
                        }
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

        // Namesakes nothing else tells apart - the same photo, price, category,
        // location - get their id: two identical cards would be a guess either way.
        $looks = array();
        foreach ( $items as $item ) {
            $looks[] = $item['label'] . "\n" . ( isset( $item['meta'] ) ? $item['meta'] : '' ) . "\n" . ( isset( $item['image'] ) ? $item['image'] : '' );
        }
        $look_counts = array_count_values( $looks );
        foreach ( $items as $i => &$item ) {
            if ( $look_counts[ $looks[ $i ] ] > 1 ) {
                $item['meta'] = ( isset( $item['meta'] ) ? $item['meta'] . ' · ' : '' ) . 'ID ' . $item['id'];
            }
        }
        unset( $item );

        return $items ? array( 'type' => (string) $data['type'], 'items' => $items ) : null;
    }

    /**
     * Note which card the customer clicked, for the model to read with the click's
     * label (Ajax::buildCompletionPayload()). Only an option of the latest offer counts:
     * that is the one the widget leaves open, every earlier one is closed. Anything
     * else - a typed answer, an id not in the offer - notes nothing, and the model
     * reads the message as it is.
     *
     * @param Lib\Entities\AiConversation $conversation
     * @param string $id Option id as present() gave it to the widget
     */
    public static function notePick( Lib\Entities\AiConversation $conversation, $id )
    {
        if ( $id === '' || ! $conversation->getId() ) {
            return;
        }

        /** @var Lib\Entities\AiMessage $offer */
        $offer = Lib\Entities\AiMessage::query()
            ->where( 'conversation_id', $conversation->getId() )
            ->where( 'role', Lib\Entities\AiMessage::ROLE_TOOL )
            ->where( 'tool_name', self::NAME )
            ->sortBy( 'id' )
            ->order( Lib\Query::ORDER_DESCENDING )
            ->limit( 1 )
            ->findOne();
        $data = $offer ? json_decode( (string) $offer->getContent(), true ) : null;
        if ( empty( $data['shown_as_buttons'] ) || ! is_array( $data['shown_as_buttons'] ) ) {
            return;
        }

        foreach ( $data['shown_as_buttons'] as $option ) {
            if ( isset( $option['id'], $option['label'] ) && (string) $option['id'] === $id ) {
                $event = new Lib\Entities\AiMessage();
                $event
                    ->setConversationId( $conversation->getId() )
                    ->setRole( Lib\Entities\AiMessage::ROLE_EVENT )
                    ->setContent( sprintf( 'Clicked on the %s cards: id %s, "%s".', self::NAME, $option['id'], $option['label'] ) )
                    ->save();

                return;
            }
        }
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
     * Named the way the catalog names them (Locations::entries()).
     *
     * @param array $ids
     * @return array[]
     */
    private static function locationOptions( array $ids )
    {
        // The locations the catalog lists - not one no bookable staff member works at.
        $names = array();
        foreach ( Locations::entries() as $location ) {
            $names[ (int) $location['id'] ] = $location['name'];
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
     * @param string $id
     * @return string The location's info, translated; '' when it has none
     */
    private static function locationInfo( $id )
    {
        foreach ( Lib\Proxy\Locations::getAll() ?: array() as $location ) {
            if ( $location['id'] == $id ) {
                return Lib\Utils\Common::getTranslatedString( 'location_' . $location['id'] . '_info', $location['info'] );
            }
        }

        return '';
    }

    /**
     * Times in the business's own format and time zone, the one
     * get_available_slots lists them in. When the options span more than one
     * day the label carries the date too (it is what a click sends), and the
     * card groups the times under their dates; otherwise the day is already in
     * the text and "10:00" alone is what the customer would type.
     *
     * create_booking still checks the time that comes back before it books it,
     * with the extras and location chosen.
     *
     * @param array                $ids
     * @param Lib\Entities\Service $service
     * @param int                  $staff_id
     * @return array[]
     */
    private static function slotOptions( array $ids, Lib\Entities\Service $service, $staff_id )
    {
        // Only start times the slot engine offers that day - checked without extras and
        // location, which can only take times away: a card never shows a time made up.
        $bookable = array();
        $points = array();
        foreach ( $ids as $id ) {
            $id = trim( (string) $id );
            $point = \DateTime::createFromFormat( 'Y-m-d H:i:s', $id ) ?: \DateTime::createFromFormat( 'Y-m-d H:i', $id );
            if ( ! $point ) {
                continue;
            }
            $day = $point->format( 'Y-m-d' );
            if ( ! isset( $bookable[ $day ] ) ) {
                // Room for the fewest persons this staff member takes - a group service has no
                // times for one; times with room for more are among these.
                list( $min_persons ) = SlotLookup::personsRange( $service, array( $staff_id ) );
                $bookable[ $day ] = SlotLookup::slotsForDay( $service, $staff_id, $day, null, array(), $min_persons );
            }
            if ( in_array( $point->format( 'Y-m-d H:i:s' ), $bookable[ $day ], true ) ) {
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
