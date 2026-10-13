<?php
namespace Bookly\Frontend\Modules\Ai\BookingTools;

use Bookly\Frontend\Modules\Ai\Checkout;
use Bookly\Lib;

class GetBookingStatus implements ToolInterface
{
    /** @var Lib\Entities\AiConversation|null */
    protected $conversation;

    /**
     * @param Lib\Entities\AiConversation|null $conversation
     */
    public function __construct( $conversation = null )
    {
        $this->conversation = $conversation;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'get_booking_status';
    }

    /**
     * @inheritDoc
     */
    public function getSchema()
    {
        return array(
            'name'        => $this->getName(),
            'description' => 'The booking made in this conversation and its payment, as they stand right now. Call it whenever the customer asks whether they are booked, whether their payment went through, or to check again - what the chat said earlier may be out of date.',
            'parameters'  => array(
                'type'       => 'object',
                'properties' => array(
                    'question' => array(
                        'type'        => 'string',
                        'description' => 'What the customer asked, in a few words.',
                    ),
                ),
                'required'   => array( 'question' ),
            ),
        );
    }

    /**
     * @inheritDoc
     */
    public function execute( array $arguments )
    {
        $booking = $this->conversation ? Checkout::describeBooking( $this->conversation ) : null;
        if ( $booking !== null ) {
            return 'Booking made in this conversation: ' . $booking . '.';
        }
        if ( $this->conversation && $this->conversation->getBookingStatus() === Lib\Entities\AiConversation::BOOKING_PENDING ) {
            return 'Not booked yet: the payment card is shown and nothing has been paid on it.';
        }

        return 'No booking has been made in this conversation.';
    }
}
