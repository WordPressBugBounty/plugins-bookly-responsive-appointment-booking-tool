<?php
namespace Bookly\Backend\Components\Dashboard\Appointments;

use Bookly\Lib;
use Bookly\Backend\Modules;

class Ajax extends Lib\Base\Ajax
{
    /**
     * Combined dashboard-page endpoint — KPI + trend chart + (Pro) analytics in ONE
     * round-trip. The page loads everything from here; the native WP dashboard widget keeps
     * using the lightweight chart-only endpoint (getAppointmentsDataForDashboard).
     */
    public static function getDashboardData()
    {
        $range       = self::parameter( 'range' );
        $based_on    = self::parameter( 'based_on' ) === 'start_date' ? 'start_date' : 'created_at';
        $compared_to = self::parameter( 'compared_to' ) === 'previous_year' ? 'previous_year' : 'previous_period';
        $filter      = self::parameter( 'filter', array() );
        $staff       = isset( $filter['staff'] ) ? $filter['staff'] : array();
        $services    = isset( $filter['services'] ) ? $filter['services'] : array();

        // Persist the filter bar state once for the whole page (dedicated core 'dashboard'
        // key — works in free, never touches the analytics table's own settings).
        Lib\Utils\Tables::updateSettings( 'dashboard', null, null, array(
            'range'       => $range,
            'based_on'    => $based_on,
            'compared_to' => $compared_to,
            'staff'       => array_map( 'intval', (array) $staff ),
            'services'    => array_map( 'intval', (array) $services ),
        ) );

        wp_send_json_success( array(
            'kpi'       => self::buildKpiData( $range, $based_on, $compared_to, $staff, $services ),
            'chart'     => self::buildChartData( $range, $based_on, $staff, $services ),
            'analytics' => Modules\Dashboard\Proxy\Pro::getDashboardAnalytics( $range, $based_on, $filter ),
        ) );
    }

    public static function getAppointmentsDataForDashboard()
    {
        $filter = self::parameter( 'filter', array() );
        wp_send_json_success( self::buildChartData(
            self::parameter( 'range' ),
            self::parameter( 'based_on', 'created_at' ),
            isset( $filter['staff'] ) ? $filter['staff'] : array(),
            isset( $filter['services'] ) ? $filter['services'] : array()
        ) );
    }

    /**
     * Build the trend-chart payload (totals + per-day series + deep links). Shared by the
     * widget endpoint and the combined dashboard endpoint (getDashboardData).
     */
    public static function buildChartData( $range, $based_on = 'created_at', $staff = array(), $services = array() )
    {
        list ( $start, $end ) = explode( ' - ', $range );
        $start = date_create( $start );
        $end = date_create( $end );
        $day = array(
            'total' => 0,
            'revenue' => 0,
        );
        $data = array(
            'totals' => array(
                'approved' => 0,
                'pending' => 0,
                'total' => 0,
                'revenue' => 0,
            ),
            'filters' => array(
                'created_at' => array(
                    'approved' => sprintf( '%s#created-date=%s-%s&appointment-date=any&status=%s', Lib\Utils\Common::escAdminUrl( Modules\Appointments\Page::pageSlug() ), $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ), 'approved' ),
                    'pending' => sprintf( '%s#created-date=%s-%s&appointment-date=any&status=%s', Lib\Utils\Common::escAdminUrl( Modules\Appointments\Page::pageSlug() ), $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ), 'pending' ),
                    'total' => sprintf( '%s#created-date=%s-%s&appointment-date=any&status=any', Lib\Utils\Common::escAdminUrl( Modules\Appointments\Page::pageSlug() ), $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) ),
                    'revenue' => sprintf( '%s#created-date=%s-%s&appointment-date=any', Lib\Utils\Common::escAdminUrl( Modules\Payments\Page::pageSlug() ), $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) ),
                ),
                'start_date' => array(
                    'approved' => sprintf( '%s#created-date=any&appointment-date=%s-%s&status=%s', Lib\Utils\Common::escAdminUrl( Modules\Appointments\Page::pageSlug() ), $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ), 'approved' ),
                    'pending' => sprintf( '%s#created-date=any&appointment-date=%s-%s&status=%s', Lib\Utils\Common::escAdminUrl( Modules\Appointments\Page::pageSlug() ), $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ), 'pending' ),
                    'total' => sprintf( '%s#created-date=any&appointment-date=%s-%s&status=any', Lib\Utils\Common::escAdminUrl( Modules\Appointments\Page::pageSlug() ), $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) ),
                    'revenue' => sprintf( '%s#created-date=any&appointment-date=%s-%s', Lib\Utils\Common::escAdminUrl( Modules\Payments\Page::pageSlug() ), $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) ),
                ),
            ),
            'days' => array(),
            'labels' => array(),
        );
        $end->modify( '+1 day' );
        $period = new \DatePeriod( $start, \DateInterval::createFromDateString( '1 day' ), $end );
        /** @var \DateTime $dt */
        foreach ( $period as $dt ) {
            $data['labels'][] = date_i18n( 'M j', $dt->getTimestamp() );
            $data['days'][ $dt->format( 'Y-m-d' ) ] = $day;
        }

        $query = Lib\Entities\CustomerAppointment::query( 'ca' )
            ->select( 'COUNT(1) AS quantity, p.paid AS revenue, ca.status, p.id' )
            ->leftJoin( 'Payment', 'p', 'p.id = ca.payment_id' )
            ->leftJoin( 'Appointment', 'a', 'a.id = ca.appointment_id' );

        switch ( $based_on ) {
            case 'start_date':
                update_option( 'bookly_dashboard_based_on_appointment', 'start_date' );
                $query->addSelect( 'DATE(a.start_date) AS group_date' )
                    ->whereBetween( 'a.start_date', $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) )
                    ->groupBy( 'DATE(a.start_date), p.id, ca.status' );
                break;
            case 'created_at':
            default:
                update_option( 'bookly_dashboard_based_on_appointment', 'created_at' );
                $query->addSelect( 'DATE(ca.created_at) AS group_date' )
                    ->whereBetween( 'ca.created_at', $start->format( 'Y-m-d' ), $end->format( 'Y-m-d' ) )
                    ->groupBy( 'DATE(ca.created_at), p.id, ca.status' );
        }

        self::applyStaffServiceFilter( $query, $staff, $services );

        $records = $query->fetchArray();

        $custom_statuses = Lib\Proxy\CustomStatuses::getAll() ?: array();
        // Consider payment for all appointments only 1 time
        $payment_ids = array();
        foreach ( $records as $record ) {
            $group_key = $record['group_date'];
            $quantity = $record['quantity'];
            $status = $record['status'];
            if ( in_array( $record['id'], $payment_ids ) ) {
                $revenue = 0;
            } else {
                $payment_ids[] = $record['id'];
                $revenue = $record['revenue'];
            }
            if ( array_key_exists( $status, $data['totals'] ) ) {
                $data['totals'][ $status ] += $quantity;
            } elseif ( isset ( $custom_statuses[ $status ] ) && $custom_statuses[ $status ]->getBusy() ) {
                // Consider as APPROVED.
                $data['totals']['approved'] += $quantity;
            }
            $data['totals']['total'] += $quantity;
            $data['totals']['revenue'] += $revenue;
            $data['days'][ $group_key ]['total'] += $quantity;
            $data['days'][ $group_key ]['revenue'] += $revenue;
        }
        $data['totals']['revenue'] = Lib\Utils\Price::format( $data['totals']['revenue'] );

        return $data;
    }

    /**
     * Build the KPI payload (revenue, appointments by status, new/returning customers
     * for the current range AND the equal-length previous period, for deltas). Consumed
     * by the combined dashboard endpoint (getDashboardData).
     */
    public static function buildKpiData( $range, $based_on = 'created_at', $compared_to = 'previous_period', $staff = array(), $services = array() )
    {
        list ( $start, $end ) = array_pad( explode( ' - ', $range ), 2, null );
        $start = date_create( $start ?: 'today' );
        $end   = date_create( $end ?: 'today' );
        $length = (int) $start->diff( $end )->days + 1; // inclusive day count

        // Current: [start 00:00, end+1day 00:00).
        $cur_from = clone $start;
        $cur_to   = ( clone $end )->modify( '+1 day' );
        // Previous baseline depends on the chosen "compared to" mode.
        if ( $compared_to === 'previous_year' ) {
            $prev_from = ( clone $start )->modify( '-1 year' );
            $prev_to   = ( clone $cur_to )->modify( '-1 year' );
        } else {
            $prev_to   = clone $start;
            $prev_from = ( clone $start )->modify( sprintf( '-%d day', $length ) );
        }
        $prev_end = ( clone $prev_to )->modify( '-1 day' ); // inclusive end

        // Deep links to the Appointments page with the period + status preset (same hash
        // contract the page parses: created-date / appointment-date / comma-separated status).
        $appts_url = Lib\Utils\Common::escAdminUrl( Modules\Appointments\Page::pageSlug() );
        $s = $start->format( 'Y-m-d' );
        $e = $end->format( 'Y-m-d' );
        $date_hash = $based_on === 'start_date'
            ? sprintf( 'created-date=any&appointment-date=%s-%s', $s, $e )
            : sprintf( 'created-date=%s-%s&appointment-date=any', $s, $e );

        return array(
            'current'    => self::computeStats( $cur_from, $cur_to, $based_on, $staff, $services ),
            'previous'   => self::computeStats( $prev_from, $prev_to, $based_on, $staff, $services ),
            'comparison' => array(
                'from'  => $prev_from->format( 'Y-m-d' ),
                'to'    => $prev_end->format( 'Y-m-d' ),
                'label' => Lib\Utils\DateTime::formatDate( $prev_from->format( 'Y-m-d' ) ) . ' – ' . Lib\Utils\DateTime::formatDate( $prev_end->format( 'Y-m-d' ) ),
            ),
            'links'      => array(
                'revenue'      => sprintf( '%s#%s', Lib\Utils\Common::escAdminUrl( Modules\Payments\Page::pageSlug() ), $date_hash ),
                'appointments' => sprintf( '%s#%s&status=any', $appts_url, $date_hash ),
                'newCustomers' => Lib\Utils\Common::escAdminUrl( Modules\Customers\Page::pageSlug() ),
                'pending'      => sprintf( '%s#%s&status=pending', $appts_url, $date_hash ),
                'lost'         => sprintf( '%s#%s&status=cancelled,rejected', $appts_url, $date_hash ),
            ),
        );
    }

    /**
     * Apply the global staff / service filter to a CustomerAppointment query (alias ca,
     * joined Appointment alias a). Empty / 'all' means no restriction.
     *
     * @param Lib\Query $query
     * @param mixed     $staff    'all' | int[]
     * @param array     $services int[] (0 = "Custom" = NULL service)
     */
    private static function applyStaffServiceFilter( $query, $staff, $services )
    {
        if ( $staff !== 'all' && is_array( $staff ) && $staff ) {
            $query->whereIn( 'a.staff_id', array_map( 'intval', $staff ) );
        }
        if ( is_array( $services ) && $services ) {
            $ints = array_map( 'intval', $services );
            $ids = array_values( array_filter( $ints, function ( $x ) { return $x > 0; } ) );
            $has_custom = in_array( 0, $ints, true );
            if ( $has_custom && $ids ) {
                $query->whereRaw( '(a.service_id IS NULL OR a.service_id IN (' . implode( ',', $ids ) . '))', array() );
            } elseif ( $has_custom ) {
                $query->whereRaw( 'a.service_id IS NULL', array() );
            } elseif ( $ids ) {
                $query->whereIn( 'a.service_id', $ids );
            }
        }
    }

    /**
     * Compute KPI aggregates for a half-open period [$from, $to).
     *
     * @param \DateTime $from
     * @param \DateTime $to
     * @param string    $based_on 'start_date' | 'created_at'
     * @param mixed     $staff
     * @param array     $services
     * @return array
     */
    private static function computeStats( \DateTime $from, \DateTime $to, $based_on, $staff = array(), $services = array() )
    {
        $date_col = $based_on === 'start_date' ? 'a.start_date' : 'ca.created_at';
        $from_s = $from->format( 'Y-m-d H:i:s' );
        $to_s   = $to->format( 'Y-m-d H:i:s' );

        $query = Lib\Entities\CustomerAppointment::query( 'ca' )
            ->select( 'ca.status, p.id AS payment_id, p.paid' )
            ->leftJoin( 'Appointment', 'a', 'a.id = ca.appointment_id' )
            ->leftJoin( 'Payment', 'p', 'p.id = ca.payment_id' )
            ->whereGte( $date_col, $from_s )
            ->whereLt( $date_col, $to_s );
        self::applyStaffServiceFilter( $query, $staff, $services );
        $rows = $query->fetchArray();

        $custom_statuses = Lib\Proxy\CustomStatuses::getAll() ?: array();
        $appointments = array(
            'total' => 0, 'approved' => 0, 'pending' => 0,
            'cancelled' => 0, 'rejected' => 0, 'waitlisted' => 0,
        );
        $payments = array();
        foreach ( $rows as $row ) {
            switch ( $row['status'] ) {
                case Lib\Entities\CustomerAppointment::STATUS_PENDING:
                    ++ $appointments['pending'];
                    break;
                case Lib\Entities\CustomerAppointment::STATUS_APPROVED:
                case Lib\Entities\CustomerAppointment::STATUS_DONE: // done ≈ approved (optional/automated status)
                    ++ $appointments['approved'];
                    break;
                case Lib\Entities\CustomerAppointment::STATUS_CANCELLED:
                    ++ $appointments['cancelled'];
                    break;
                case Lib\Entities\CustomerAppointment::STATUS_REJECTED:
                    ++ $appointments['rejected'];
                    break;
                case Lib\Entities\CustomerAppointment::STATUS_WAITLISTED:
                    ++ $appointments['waitlisted'];
                    break;
                default:
                    if ( isset( $custom_statuses[ $row['status'] ] ) ) {
                        $custom_statuses[ $row['status'] ]->getBusy()
                            ? ++ $appointments['approved']
                            : ++ $appointments['cancelled'];
                    }
            }
            ++ $appointments['total'];
            if ( $row['payment_id'] ) {
                $payments[ $row['payment_id'] ] = $row['paid'];
            }
        }

        list ( $customers_total, $customers_new ) = self::countCustomers( $from_s, $to_s, $based_on, $staff, $services );

        return array(
            'revenue'      => (float) array_sum( $payments ),
            'appointments' => $appointments,
            'customers'    => array(
                'total'     => $customers_total,
                'new'       => $customers_new,
                'returning' => max( 0, $customers_total - $customers_new ),
            ),
        );
    }

    /**
     * Distinct customers in the period and how many of them are new
     * (their first-ever appointment falls inside the period).
     *
     * @param string $from_s
     * @param string $to_s
     * @param string $based_on
     * @return array [ total, new ]
     */
    private static function countCustomers( $from_s, $to_s, $based_on, $staff = array(), $services = array() )
    {
        $col = $based_on === 'start_date' ? 'a.start_date' : 'ca.created_at';

        // Distinct customers with an appointment in the period (one group per customer).
        $total_q = Lib\Entities\CustomerAppointment::query( 'ca' )
            ->leftJoin( 'Appointment', 'a', 'a.id = ca.appointment_id' )
            ->whereGte( $col, $from_s )
            ->whereLt( $col, $to_s )
            ->groupBy( 'ca.customer_id' );
        self::applyStaffServiceFilter( $total_q, $staff, $services );
        $total = $total_q->count( true );

        // Of those, customers whose first (matching) appointment falls inside the period (= new).
        // No WHERE on the date — MIN() must see the customer's whole matching history.
        $new_q = Lib\Entities\CustomerAppointment::query( 'ca' )
            ->leftJoin( 'Appointment', 'a', 'a.id = ca.appointment_id' )
            ->groupBy( 'ca.customer_id' )
            ->havingRaw( sprintf( 'MIN(%1$s) >= %%s AND MIN(%1$s) < %%s', $col ), array( $from_s, $to_s ) );
        self::applyStaffServiceFilter( $new_q, $staff, $services );
        $new = $new_q->count( true );

        return array( $total, $new );
    }
}