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

add_action('wp_enqueue_scripts', 'enqueue_card_scripts');
function enqueue_card_scripts()
{
	if (is_order_received_page()  ) {

		$order = new Payop_Order(get_query_var('order-received'));
		if ($order->is_card_method_Order()){
			wp_enqueue_script('imask-script', 'https://cdnjs.cloudflare.com/ajax/libs/imask/3.4.0/imask.min.js', ['jquery']);
			wp_enqueue_script('seon-agent', 'https://cdn.seon.io/js/v4/agent.js', ['jquery']);
			wp_enqueue_script('credit-card-script', plugin_dir_url(__FILE__) . 'assets/js/credit-card.js', ['jquery']);
			wp_enqueue_style('credit-card-style', plugin_dir_url(__FILE__) . 'assets/css/credit-card.css', []);
		}

		$Payop_Gateway = new Payop_Gateway();

		wp_enqueue_script('payop-script', plugin_dir_url(__FILE__) . 'assets/js/payop.js', ['jquery']);
		wp_localize_script('jquery', 'payop_ajax',
			array(
				'url' => admin_url('admin-ajax.php'),
				'check_invoice_status' => 'check_invoice_status',
				'success_url' => $Payop_Gateway->get_resultUrl(),
				'fail_url' => $Payop_Gateway->get_failPath(),
			)
		);

	}
}

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

add_action('wp_ajax_payment_processing', 'callback_payment_processing');
add_action('wp_ajax_nopriv_payment_processing', 'callback_payment_processing');
function callback_payment_processing()
{

	// ********** WP Verify Nonce ***************
	if (!isset($_POST['payment_processing'])
		|| !wp_verify_nonce($_POST['payment_processing'], 'payment_processing_action')
	) {
		wp_send_json(['message' => 'Sorry, your nonce did not verify.'], 400);
		wp_die();
	}


	// ********** Get Server Option ***************
	$payopGateway = new Payop_Gateway();
	$server = $payopGateway->get_server();
	$serverServer = new Payop_ServerToServer($server);

	// ********** Get Order ***************
	$order_id = $_POST['order_id'];
	$order = new WC_Order($order_id);
	if (!$order) {
		wp_send_json(['message' => 'Order not found'], 400);
		wp_die();
	}

	$order->update_status('processing');

	$payopOrder = new Payop_Order($order_id);
	$cardToken = false;

	if ($payopOrder->is_card_method_Order()) {
		/* ************* FOR CARD METHOD ***************** */
		$card = [
			'holderName' => $_POST['name'],
			'pan' => preg_replace('/\s+/', '', $_POST['cardnumber']),
			'expirationDate' => $_POST['expirationdate'],
			'cvv' => $_POST['securitycode'],
		];
		$bankCardToken = $serverServer->createBankCardToken($_POST['invoice'], $card);
		/* TODO: Check  bankCardToken on Errors */
		if (!isset($bankCardToken['token'])) {
			wp_send_json(($bankCardToken), 400);
			wp_die();
		}
		$cardToken = $bankCardToken['token'];
		/* ************* FOR CARD METHOD ***************** */
	}

	/* ************* Get Customer Option ***************** */
	$customer = [
		'name' => $order->get_billing_first_name(),
		'email' => $order->get_billing_email(),
		'ip' => $order->get_customer_ip_address(),
	];

	if (isset($_POST['seon_session']) && $_POST['seon_session']) {
		$customer['seon_session'] = $_POST['seon_session'];
	}

	/* Create checkoutTransaction */
	$checkoutTransaction = $serverServer->createCheckoutTransaction($_POST['invoice'], $customer, $payopGateway->get_resultUrl(), false, false, $cardToken);

	sleep(2);
	/* Check status invoice after transaction */
	$statusInvoice = $serverServer->checkInvoiceStatus($_POST['invoice']);

	$payopOrder->updateStatusOrderAfterTransaction($statusInvoice);

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

