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

add_action('wp_enqueue_scripts', 'action_function_name_7714');
function action_function_name_7714()
{
	if (is_page('checkout')) {
		wp_enqueue_script('credit-card-script', plugin_dir_url(__FILE__) . '/assets/js/credit-card.js', []);
		wp_enqueue_script('imask-script', '//cdnjs.cloudflare.com/ajax/libs/imask/3.4.0/imask.min.js', []);
		wp_enqueue_style('credit-card-style', plugin_dir_url(__FILE__) . '/assets/css/credit-card.css', []);

		wp_localize_script('credit-card-script', 'payop_ajax',
			array(
				'url' => admin_url('admin-ajax.php'),
				'check_invoice_status' => 'check_invoice_status',
			)
		);
	}
}

/*add_filter('page_template', 'wc_payop_page_template');
function wc_payop_page_template($page_template)
{
	if (is_page('payment-credit-card') || is_page('checkout')) {
		$page_template = dirname(__FILE__) . '/template/card-form.php';
	}
	return $page_template;
}*/

add_action('wp_ajax_check_invoice_status', 'callback_check_invoice_status');
add_action('wp_ajax_nopriv_check_invoice_status', 'callback_check_invoice_status');
function callback_check_invoice_status()
{
	$gateway = new WC_Payment_Gateways();
	$server = $gateway->get_available_payment_gateways() [Payop_Settings::NAME_GATEWAY]->settings[Payop_Settings::NAME_GATEWAY . '_server'];
	$serverServer = new Payop_ServerToServer($server);
	$response = $serverServer->checkInvoiceStatus($_POST['invoice']);
	wp_send_json($response, 200);
	wp_die();
}

add_action('wp_ajax_credit_card_form', 'callback_credit_card_form');
add_action('wp_ajax_nopriv_credit_card_form', 'callback_credit_card_form');
function callback_credit_card_form()
{

	if (!isset($_POST['credit_card_form'])
		|| !wp_verify_nonce($_POST['credit_card_form'], 'credit_card_form_action')
	) {
		wp_send_json(['message' => 'Sorry, your nonce did not verify.'], 400);
		wp_die();
	}

	$gateway = new WC_Payment_Gateways();
	$server = $gateway->get_available_payment_gateways() [Payop_Settings::NAME_GATEWAY]->settings[Payop_Settings::NAME_GATEWAY . '_server'];

	$serverServer = new Payop_ServerToServer($server);

	$card = [
		'holderName' => $_POST['name'],
		'pan' => preg_replace('/\s+/', '', $_POST['cardnumber']),
		'expirationDate' => $_POST['expirationdate'],
		'cvv' => $_POST['securitycode'],
	];

	$order_id = $_POST['order_id'];
	$order = new WC_Order($order_id);
	if (!$order){
		wp_send_json(['message' => 'Order not found'], 400);
		wp_die();
	}
	$customer = [
		'name' => $order->get_billing_first_name() ,
		'email' => $order->get_billing_email(),
		'ip' => $order->get_customer_ip_address(),
	];

	if (isset($_POST['seon_session']) && $_POST['seon_session']) {
		$customer['seon_session'] = $_POST['seon_session'];
	}

	$bankCardToken = $serverServer->createBankCardToken($_POST['invoice'], $card);

//	var_dump($bankCardToken);
	/* TODO: Check  bankCardToken on Errors */
	if (!isset($bankCardToken['token'])) {
		wp_send_json(($bankCardToken), 400);
		wp_die();
	}
	/* Create checkoutTransaction */
	$checkoutTransaction = $serverServer->createCheckoutTransaction($_POST['invoice'], $customer, '/thankyou', false, false, $bankCardToken['token']);

	/* Check status invoice after transaction */
	$statusInvoice = $serverServer->checkInvoiceStatus($_POST['invoice']);

	$PayopOrder = new Payop_Order($order_id);
	$PayopOrder->updateStatusOrderAfterTransaction($statusInvoice);

	/* TODO: Check  checkoutTransaction on Errors */
	if (isset($checkoutTransaction['data']) && isset($checkoutTransaction['status']) && $checkoutTransaction['status']) {
		wp_send_json($checkoutTransaction['data'], 200);
	} elseif (isset($checkoutTransaction['message'])) {
		$response = [];
		if (isset($checkoutTransaction['message']))
			$response = $checkoutTransaction['message'];
		else
			$response = $checkoutTransaction;

		wp_send_json($response, 400);
	}

	wp_die();
}

