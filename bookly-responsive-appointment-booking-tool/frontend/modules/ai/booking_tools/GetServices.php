<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Lib;

class GetServices implements ToolInterface
{
    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'get_services';
    }

    /**
     * @inheritDoc
     *
     * "search" is required (not optional) even though an empty string means
     * "no filter" — verified live against Cloud that a tool called with a
     * genuinely empty arguments object ("{}") gets echoed back into the next
     * /complete call's history as "arguments":[] (PHP's own empty-array/
     * empty-object ambiguity survives Cloud's own JSON round-trip, no matter
     * how this plugin encodes its own outgoing payload — reproduced with
     * "{}", with the key omitted entirely, all three fail identically), and
     * Anthropic then rejects that echoed call with "tool_use.input: Input
     * should be an object". Forcing a required (possibly-empty-string)
     * argument means the model always sends a non-empty arguments object,
     * sidestepping the bug entirely. The bug itself is Cloud-side, not
     * fixable from here — flagged separately for bookly-cloud.
     */
    public function getSchema()
    {
        $description = 'List the services this business offers online booking for: id, title, duration (minutes), and price.'
            . ( Lib\Config::serviceExtrasActive()
                ? ' Also includes any optional extras (add-ons) available for a service, when it has any.'
                : '' )
            . ' Call this first to discover valid service_id values for get_staff/check_availability/create_booking'
            . ( Lib\Config::serviceExtrasActive() ? ', and valid extra_id values to pass to check_availability/create_booking.' : '.' );

        return array(
            'name'        => $this->getName(),
            'description' => $description,
            'parameters'  => array(
                'type'       => 'object',
                'properties' => array(
                    'search' => array(
                        'type'        => 'string',
                        'description' => 'Substring to filter services by title (e.g. what the customer asked about). Pass an empty string to list all services.',
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

        $query = Lib\Entities\Service::query( 's' )
            ->where( 's.type', Lib\Entities\Service::TYPE_SIMPLE )
            ->where( 's.visibility', Lib\Entities\Service::VISIBILITY_PUBLIC );

        if ( $search !== '' ) {
            $query->whereLike( 's.title', '%' . Lib\Query::escape( $search ) . '%' );
        }

        /** @var Lib\Entities\Service[] $services */
        $services = $query->sortBy( 's.title' )->find();

        if ( ! $services ) {
            return '[]';
        }

        $list = array();
        foreach ( $services as $service ) {
            $entry = array(
                'id'               => $service->getId(),
                'title'            => $service->getTitle(),
                'duration_minutes' => round( $service->getDuration() / 60 ),
                'price'            => Lib\Utils\Price::format( $service->getPrice() ),
            );

            if ( Lib\Config::serviceExtrasActive() ) {
                $extras = array();
                foreach ( Lib\Proxy\ServiceExtras::findByServiceId( $service->getId() ) ?: array() as $extra ) {
                    if ( $extra->getMaxQuantity() <= 0 ) {
                        // Same "hidden" convention the booking widget itself
                        // uses (no dedicated active/visibility column exists
                        // on ServiceExtra — see bookly-addon-service-extras'
                        // own booking-step code).
                        continue;
                    }
                    $extras[] = array(
                        'id'               => $extra->getId(),
                        'title'            => $extra->getTranslatedTitle(),
                        'price'            => Lib\Utils\Price::format( $extra->getPrice() ),
                        'duration_minutes' => round( $extra->getDuration() / 60 ),
                        'min_quantity'     => max( 0, $extra->getMinQuantity() ),
                        'max_quantity'     => $extra->getMaxQuantity(),
                    );
                }
                if ( $extras ) {
                    $entry['extras'] = $extras;
                }
            }

            $list[] = $entry;
        }

        return wp_json_encode( $list );
    }
}
