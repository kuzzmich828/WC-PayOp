<?php
/*
 * Plugin Name: WooCommerce PayOp
 * Plugin URI:
 * Description: Gateway "PayOp" for WooCommerce
 * Author: kuzzmich
 * Author URI:
 * Version: 0.1
 */

require_once __DIR__ . '/classes/Abstract_Payop_Helper.php';
require_once __DIR__ . '/classes/Payop_Settings.php';
require_once __DIR__ . '/classes/Payop_HostedPage.php';
require_once __DIR__ . '/classes/Payop_ServerToServer.php';
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


		wp_localize_script('credit-card-script', 'payop_ajax',
			array(
				'url' => admin_url('admin-ajax.php'),
			)
		);


	}
	return $page_template;
}


add_action('wp_ajax_credit_card_form', 'callback_credit_card_form');
add_action('wp_ajax_nopriv_credit_card_form', 'callback_credit_card_form');
function callback_credit_card_form()
{
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

	if (!isset($_POST['credit_card_form'])
		|| !wp_verify_nonce($_POST['credit_card_form'], 'credit_card_form_action')
	) {
		print 'Sorry, your nonce did not verify.';
		exit;
	}



	$payop = new Payop_ServerToServer('Stage');

	$card = [
		'holderName' => $_POST['name'],
		'pan' => preg_replace('/\s+/', '', $_POST['cardnumber']),
		'expirationDate' => $_POST['expirationdate'],
		'cvv' => $_POST['securitycode'],
	];

	$customer = [
		'name' => 'Name name',
		'email' => 'email@mail.co',
		'ip' => '127.0.0.1',
	];

	if (isset($_POST['seon_session']) && $_POST['seon_session']){
		$customer['seon_session'] = $_POST['seon_session'];
	}

	$bankCardToken = $payop->createBankCardToken($_POST['invoice'], $card);

	$checkoutTransaction = $payop->createCheckoutTransaction($_POST['invoice'], $customer, 'https://the-web.space/', false, false, $bankCardToken['token']);

	print_r($bankCardToken);

	print_r($checkoutTransaction);

	wp_die();
}

