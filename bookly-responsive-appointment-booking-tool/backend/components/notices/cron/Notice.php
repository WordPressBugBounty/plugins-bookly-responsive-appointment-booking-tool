<?php
namespace Bookly\Backend\Components\Notices\Cron;

use Bookly\Lib;

class Notice extends Lib\Base\Component
{
    /**
     * Render configure cron notice.
     *
     * @param string $class Extra CSS classes for the notice wrapper. Defaults to bookly:mb-0
     *   (no bottom margin — fits card contexts where the card padding provides the gap). Form
     *   contexts where the notice sits between fields should pass a bottom margin, e.g. bookly:mb-4.
     */
    public static function render( $class = 'bookly:mb-0' )
    {
        if ( ! Lib\Cloud\API::getInstance()->account->productActive( 'cron' ) ) {
            return self::renderTemplate( 'notice', compact( 'class' ) );
        }
    }
}