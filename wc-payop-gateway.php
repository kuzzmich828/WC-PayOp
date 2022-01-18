<?php
/*
 * Plugin Name: WooCommerce PayOp
 * Plugin URI:
 * Description: Gateway "PayOp" for WooCommerce
 * Author: kuzzmich
 * Author URI:
 * Version: 0.1
 */

require_once __DIR__ . '/classes/Payop_Settings.php';
require_once __DIR__ . '/classes/Payop_HostedPage.php';
require_once __DIR__ . '/classes/Payop_Order.php';
require_once __DIR__ . '/ipn.php';

/*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter('woocommerce_payment_gateways', 'wc_payop_add_gateway_class');
function wc_payop_add_gateway_class($gateways)
{
	$gateways[] = Payop_Settings::NAME_GATEWAY . '_Gateway';
	return $gateways;
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'wc_payop_init_gateway_class');

function wc_payop_init_gateway_class()
{
	require_once(__DIR__ . '/classes/Payop_Gateway.php');
}

add_filter('page_template', 'wc_payop_page_template');
function wc_payop_page_template($page_template)
{
	if (is_page('payment-credit-card')) {
		$page_template = dirname(__FILE__) . '/template/card-form.php';
		wp_enqueue_script('credit-card-script', plugin_dir_url(__FILE__) . '/assets/js/credit-card.js', []);
		wp_enqueue_script('imask-script', '//cdnjs.cloudflare.com/ajax/libs/imask/3.4.0/imask.min.js', []);
		wp_enqueue_style('credit-card-style', plugin_dir_url(__FILE__) . '/assets/css/credit-card.css', []);
	}
	return $page_template;
}