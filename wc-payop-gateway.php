<?php
/*
 * Plugin Name: WooCommerce PayOp
 * Plugin URI:
 * Description: Gateway "PayOp" for WooCommerce
 * Author: kuzzmich
 * Author URI:
 * Version: 0.1
 */

require_once __DIR__.'/classes/Payop_API.php';
require_once __DIR__.'/classes/Payop_Order.php';
require_once __DIR__.'/ipn.php';

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'wc_payop_add_gateway_class' );
function wc_payop_add_gateway_class( $gateways ) {

    $gateways[] = Payop_API::NAME_GATEWAY . '_Gateway';
    return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'wc_payop_init_gateway_class' );

function wc_payop_init_gateway_class() {
    require_once (__DIR__.'/classes/Payop_Gateway.php');
}