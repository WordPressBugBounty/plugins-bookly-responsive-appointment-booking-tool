<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
/**
 * @var Bookly\Lib\Cloud\SMS $sms
 * @var $datatables
 */
?>
<div class="alert alert-info"><?php esc_html_e( 'Please take into account that not all countries by law allow custom SMS sender ID. Please check if particular country supports custom sender ID in our price list. Also please note that prices for messages with custom sender ID are usually 20% - 25% higher than normal message price.', 'bookly' ) ?></div>


<div id="bookly-sms_sender-datatables"></div>

<div id="bookly-sender-id-modal-root"></div>
