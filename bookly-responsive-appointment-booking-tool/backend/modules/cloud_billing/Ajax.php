<?php
namespace Bookly\Backend\Modules\CloudBilling;

use Bookly\Lib;

class Ajax extends Lib\Base\Ajax
{
    /**
     * Get purchases list.
     */
    public static function getPurchasesList()
    {
        $filter = self::parameter( 'filter' );
        $range  = $filter['range'];

        if ( $range === 'any' ) {
            $start = Lib\Utils\DateTime::applyTimeZoneOffset( date( 'Y-m-d', strtotime( '-100 year' ) ), 0 );
            $end   = Lib\Utils\DateTime::applyTimeZoneOffset( date( 'Y-m-d', strtotime( '+1 day' ) ), 0 );
        } else {
            $dates = explode( ' - ', $range, 2 );
            $start = Lib\Utils\DateTime::applyTimeZoneOffset( $dates[0], 0 );
            $end   = Lib\Utils\DateTime::applyTimeZoneOffset( date( 'Y-m-d', strtotime( '+1 day', strtotime( $dates[1] ) ) ), 0 );
        }

        $result = Lib\Cloud\API::getInstance()->account->getPurchasesList( $start, $end );
        $list   = isset( $result['list'] ) ? $result['list'] : array();

        wp_send_json( array(
            'draw'            => (int) self::parameter( 'draw' ),
            'recordsTotal'    => count( $list ),
            'recordsFiltered' => count( $list ),
            'data'            => $list,
        ) );
    }
}
