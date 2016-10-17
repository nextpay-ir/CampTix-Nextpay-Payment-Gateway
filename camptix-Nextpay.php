<?php
/**
 * Created by NextPay.ir
 * Created by NextPay.ir
 * author: Nextpay Company
 * ID: @nextpay
 * Date: 09/22/2016
 * Time: 5:05 PM
 * Website: NextPay.ir
 * Email: info@nextpay.ir
 * @copyright 2016
 * @package NextPay_Gateway
 * @version 1.0
 * Plugin Name: CampTix Nextpay Payment Gateway
 * Plugin URI: http://www.nextpay.ir
 * Description: Nextpay Payment Gateway for CampTix
 * Version: 1.0
 * Author URI: http://www.nextpay.ir
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Add IRR currency
add_filter( 'camptix_currencies', 'camptix_add_irr_currency' );
function camptix_add_irr_currency( $currencies ) {
	$currencies['IRR'] = array(
		'label' => __( 'ریال ایران', 'camptix' ),
		'format' => 'ریال %s',
	);
	return $currencies;
}

add_action( 'camptix_load_addons', 'camptix_Nextpay_load_payment_method' );
function camptix_Nextpay_load_payment_method() {
	if ( ! class_exists( 'CampTix_Payment_Method_Nextpay' ) )
		require_once plugin_dir_path( __FILE__ ) . 'classes/class-camptix-payment-method-Nextpay.php';
	camptix_register_addon( 'CampTix_Payment_Method_Nextpay' );
}

?>