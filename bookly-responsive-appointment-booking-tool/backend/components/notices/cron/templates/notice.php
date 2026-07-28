<?php defined( 'ABSPATH' ) || exit; // Exit if accessed directly ?>
<div class="bookly:alert bookly:alert-info bookly:mt-4 <?php echo esc_attr( $class ?? 'bookly:mb-0' ) ?>">
    <svg class="bookly:alert-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
    <div class="bookly:alert-content">
        <?php printf( __( 'To allow scheduled actions, please activate <a href="%s" target="_blank">Bookly Cloud Cron</a> or follow <a href="%s" target="_blank">the instructions</a> about cron setup', 'bookly-responsive-appointment-booking-tool' ),
                \Bookly\Lib\Utils\Common::escAdminUrl( \Bookly\Backend\Modules\CloudProducts\Page::pageSlug() ),
                'https://support.booking-wp-plugin.com/hc/en-us/articles/360015017400-How-can-I-configure-CRON-to-send-the-Bookly-reminders' ) ?>
    </div>
</div>
