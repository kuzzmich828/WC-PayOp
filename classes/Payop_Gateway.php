<?php


class Payop_Gateway extends WC_Payment_Gateway
{

	private string $application;
	private string $public_key;
	private string $secret_key;
	private string $resultUrl;
	private string $failPath;
	private int $paymentMethod;
	private array $paymentMultiMethods;
	private int $paymentType;
	private string $server = 'PROD';
	private string $language = 'en';
	private string $jwtToken;
	private string $info_methods;

	/**
	 * Class constructor, more about it in Step 3
	 */
	public function __construct()
	{

		$this->id = Payop_Settings::NAME_GATEWAY;            // payment gateway plugin ID
		$this->icon = '';                               // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields = true;                       // in case you need a custom credit card form
		$this->method_title = Payop_Settings::NAME . ' ' . __('Gateway', 'wc-payop');
		$this->method_description = __('Parameters') . ' ' . Payop_Settings::NAME . ' ' . __('gateway'); // will be displayed on the options page

		// gateways can support subscriptions, refunds, saved payment methods, but in this tutorial we begin with simple payments
		$this->supports = array(
			'products',
		);

		// Load the settings.
		$this->init_settings();
		$this->title = $this->get_option('title');
		$this->enabled = $this->get_option('enabled');
		$this->resultUrl = $this->get_option(Payop_Settings::NAME_GATEWAY . '_resultUrl');
		$this->failPath = $this->get_option(Payop_Settings::NAME_GATEWAY . '_failPath');
		$this->public_key = $this->get_option(Payop_Settings::NAME_GATEWAY . '_public_key');
		$this->secret_key = $this->get_option(Payop_Settings::NAME_GATEWAY . '_secret_key');
		$this->paymentMethod = (int)$this->get_option(Payop_Settings::NAME_GATEWAY . '_paymentMethod');
		$this->paymentType = (int)$this->get_option(Payop_Settings::NAME_GATEWAY . '_paymentType');
		$this->paymentMultiMethods = (array)$this->get_option(Payop_Settings::NAME_GATEWAY . '_paymentMultiMethods');
		$this->server = $this->get_option(Payop_Settings::NAME_GATEWAY . '_server');
		$this->language = $this->get_option(Payop_Settings::NAME_GATEWAY . '_language');
		$this->jwtToken = $this->get_option(Payop_Settings::NAME_GATEWAY . '_jwtToken');
		$this->info_methods = $this->get_option(Payop_Settings::NAME_GATEWAY . '_info_methods');

		if ($this->public_key)
			$this->application = str_replace('application-', '', $this->public_key);

		// Method with all the options fields
		$this->init_form_fields();


		//Payment listner/API hook
		add_action('woocommerce_api_wc_' . $this->id, [$this, 'listener_ipn']);
		add_action('template_redirect', [$this, 'listener_ipn']);
		// This action hook saves the settings
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_checkout_update_order_meta', array($this, 'custom_payment_update_order_meta'));
		add_action('admin_enqueue_scripts', array($this, 'payop_admin_scripts'));

		add_action('woocommerce_thankyou', [$this, 'receipt_page'], 99, 1);
		add_filter('woocommerce_thankyou_order_received_text', [$this, 'order_complete'], 99, 2);

//		add_action('woocommerce_receipt_' . Payop_Settings::NAME_GATEWAY, [$this, 'receipt_page'], 99, 1);
//		add_action('woocommerce_receipt', [$this, 'receipt_page'], 99, 1);
//		add_action('woocommerce_thankyou_'. Payop_Settings::NAME_GATEWAY, [$this, 'receipt_page'], 99, 1);

	}

	public function payop_admin_scripts()
	{
		wp_enqueue_script('admin_payop', plugin_dir_url(__DIR__) . 'assets/js/payop-admin.js', ['jquery']);
	}

	public static function getOption($option)
	{
		return WC_Payment_Gateways::instance()->get_available_payment_gateways()[Payop_Settings::NAME_GATEWAY]->get_option(Payop_Settings::NAME_GATEWAY . $option);
	}

	public function get_info_methods()
	{
		return json_decode($this->info_methods, true);
	}

	public function get_server()
	{
		return $this->server;
	}

	/**
	 * Plugin options, we deal with it in Step 3 too
	 */
	public function init_form_fields()
	{

		$aviablePayMethods = Payop_Settings::getAviableMethods(Payop_Settings::SERVERS_URL[$this->server], $this->application, $this->jwtToken);
		$serverPaymentMethods = [];
		$hostedPaymentMethods = [];

		$descMethods = __('Available payment methods for your application', 'wc-payop');
		if (!is_array($aviablePayMethods) || !$aviablePayMethods) {
			$descMethods = '<span style="color:red;">' . __('No methods available or invalid "Public Key" ', 'wc-payop') . '</span>';
		} else {
			foreach ($aviablePayMethods as $aviablePayMethod) {
				$hostedPaymentMethods [$aviablePayMethod['identifier']] = $aviablePayMethod['title'];
			}

			$serverPaymentMethods = $hostedPaymentMethods;
			$hostedPaymentMethods[0] = __('All', 'wc-payop');
			ksort($hostedPaymentMethods);
			ksort($serverPaymentMethods);
		}

		$wp_pages = get_posts(['post_type' => 'page', 'post_status' => 'publish']);
		$pages = [];
		foreach ($wp_pages as $wp_page) {
			$pages [$wp_page->ID] = $wp_page->post_title;
		}

		$this->form_fields = array(

			Payop_Settings::ID_GATEWAY . 'enabled' => array(
				'title' => __('Enable/Disable', 'wc-payop'),
				'label' => __('Enable PayOp Gateway', 'wc-payop'),
				'type' => 'checkbox',
				'description' => '',
				'default' => 'no',
			),
			Payop_Settings::ID_GATEWAY . '_title' => array(
				'title' => 'Title',
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'wc-payop'),
				'default' => 'PayOp',
				'desc_tip' => true,
			),
			Payop_Settings::ID_GATEWAY . '_server' => array(
				'title' => __('Server', 'wc-payop'),
				'type' => 'select',
				'options' => Payop_Settings::SERVERS_TYPE,
			),
			Payop_Settings::ID_GATEWAY . '_public_key' => array(
				'title' => __('Public Key', 'wc-payop'),
				'type' => 'text',
			),
			Payop_Settings::ID_GATEWAY . '_secret_key' => array(
				'title' => __('Secret Key', 'wc-payop'),
				'type' => 'text',
			),
			Payop_Settings::ID_GATEWAY . '_jwtToken' => array(
				'title' => __('JWT Token', 'wc-payop'),
				'type' => 'text',
			),
			Payop_Settings::ID_GATEWAY . '_paymentType' => array(
				'title' => __('Payment Type', 'wc-payop'),
				'type' => 'select',
				'options' => Payop_Settings::PAYMENTS_TYPE,
			),

			Payop_Settings::ID_GATEWAY . '_paymentMultiMethods' => array(
				'title' => __('Payment Methods', 'wc-payop'),
				'id' => 'paymentMultiMethods',
				'description' => $descMethods,
				'type' => 'multiselect',
				'options' => $serverPaymentMethods,
			),
			Payop_Settings::ID_GATEWAY . '_paymentMethod' => array(
				'title' => __('Payment Method', 'wc-payop'),
				'id' => 'paymentMethod',
				'description' => $descMethods,
				'css' => 'mix-height: 100px;',
				'type' => 'select',
				'options' => $hostedPaymentMethods,
			),
			Payop_Settings::ID_GATEWAY . '_resultUrl' => array(
				'title' => __('Result URL', 'wc-payop'),
				'type' => 'text',
			),
			Payop_Settings::ID_GATEWAY . '_failPath' => array(
				'title' => __('Fail URL', 'wc-payop'),
				'type' => 'text',
			),
			Payop_Settings::ID_GATEWAY . '_language' => array(
				'title' => __('Language', 'wc-payop'),
				'type' => 'text',
			),
			Payop_Settings::ID_GATEWAY . '_info_methods' => array(
				'title' => __('All Types', 'wc-payop'),
				'type' => 'hidden',
				'default' => json_encode($aviablePayMethods),
			),

		);

	}

	public function custom_payment_update_order_meta($order_id)
	{
		if (!$_POST['paymentMethod'])
			return;
		$payOrder = new Payop_Order($order_id);
		$payOrder->setOrderPaymentMethod($_POST['paymentMethod']);
	}

	/**
	 * You will need it if you want your custom credit card form, Step 4 is about it
	 */
	public function payment_fields()
	{
		?>
        <p style="margin: 20px 0;">
            <label>
                <img src="https://payop.com/assets/images/landing/logos/logo_color.svg" style="max-height:32px;"/>
            </label>
        </p>
		<?php

		if ($this->paymentType == Payop_Settings::PAYMENT_TYPE_HOSTED_PAGE) {
			return true;
		}

		$avableMethods = Payop_Settings::getAviableMethods(Payop_Settings::SERVERS_URL[$this->server], $this->application, $this->jwtToken);
		if (!is_array($avableMethods) && ($avableMethods)) {
			return false;
		}
		$checked = 'checked';
		foreach ($avableMethods as $method):
			if (!in_array($method['identifier'], $this->paymentMultiMethods))
				continue;
			?>
            <div id="input_payop_methods">
                <p class="form-row">
                    <label>
                        <input type="radio" name="paymentMethod"
                               value="<?= $method['identifier']; ?>" <?php if ($checked) echo $checked; ?> />
						<?= $method['title']; ?>
                        <img src="<?= $method['logo']; ?>" style="max-height:32px;"/>
                    </label>
                </p>
            </div>
			<?php
			$checked = false;
		endforeach;

	}

	/*
	* Fields validation, more in Step 5
	*/
	public function validate_fields()
	{
		return true;
	}

	/*
	* We're processing the payments here, everything about it is in Step 5
	*/
	public function process_payment($order_id)
	{

		global $woocommerce;

		$order = new WC_Order($order_id);
		$payOrder = new Payop_Order($order_id);
		$paymentMethod = ($this->paymentType == Payop_Settings::PAYMENT_TYPE_HOSTED_PAGE) ? $this->paymentMethod : $payOrder->getOrderPaymentMethod();

		$PayopHostedPage = new Payop_HostedPage($this->secret_key, $this->public_key . '', $this->server);

		// create invoice by API PayOp
		$invoice = $PayopHostedPage->createInvoice($order, $paymentMethod, $this->language, $this->resultUrl, $this->failPath);

		if ((!isset($invoice['status']) || $invoice['status'] != 1) && isset($invoice['message'])) {
			wc_add_notice(json_encode($invoice['message']), 'error');
			return false;
		}

		if (isset($invoice['data']) && $invoice['data'] && $invoice['status'] == 1) {

			// Mark as on-hold (we're awaiting the cheque)
			$order->update_status('pending-payment', __('Awaiting cheque payment', 'woocommerce'));
			// Remove cart
			$woocommerce->cart->empty_cart();

			$order->add_meta_data('invoice', $invoice['data'], true);
			$order->save_meta_data();
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order),
			);
		}

		wc_add_notice("Unknown Error", 'error');

		return false;

	}

	public function order_complete($title, $order)
	{
		if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
			return 'Thank You. Order is Completed.';
		}
	}

	public function receipt_page($order_id)
	{

		$order = new WC_Order($order_id);

		if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
			return;
		}

		$PayopHostedPage = new Payop_HostedPage($this->secret_key, $this->public_key, $this->server);
		$invoice = $order->get_meta('invoice');

		switch ($this->paymentType) {

			case Payop_Settings::PAYMENT_TYPE_SERVER_SERVER:
			{

				$payOrder = new Payop_Order($order_id);
				$info_method_Order = $payOrder->info_method_Order($this);
				if (!isset($info_method_Order['identifier'])) {
					echo '<p style="color:red; font-weight: bold;">' . __('Payment method selection error', 'payop-woocommerce') . '</p>';
					break;
				}
				$paymentMethod = $info_method_Order['identifier'];
				$serverServer = new Payop_ServerToServer($this->server);

				include_once __DIR__ . '/../template/payment-form.php';
				break;

			}

			case Payop_Settings::PAYMENT_TYPE_HOSTED_PAGE:
			{
				echo '<p>' . __('Thank you for your order, please click the button below to pay', 'payop-woocommerce') . '</p>';
				echo '<form action="' . str_replace('{{locale}}', $this->language, $PayopHostedPage->getProcessingUrl()) . $invoice . '" method="GET" id="payop_payment_form" xmlns="http://www.w3.org/1999/html">' . "\n" .
					'<input type="submit" class="button" id="submit_payop_payment_form" value="' . __('Pay', 'payop-woocommerce') . '">' . "\n" .
					'<a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Refuse payment & return to cart', 'payop-woocommerce') . '</a></input>' . "\n" .
					'</form>';
				break;
			}

		}
	}

	/*
	* In case you need a webhook, like PayPal IPN etc
	*/
	public function webhook()
	{

	}

	public function listener_ipn()
	{

		echo "<h1>IPN</h1>";

		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			$postedData = json_decode(file_get_contents('php://input'), true);
			if (!is_array($postedData)) {
				$postedData = [];
			}
		} else {
			return;
		}

		$f = fopen(__DIR__ . '/log.json', 'a');
		fwrite($f, "[" . date('d/m/Y H:i:s') . "]\t");
		fwrite($f, json_encode($postedData));
		fwrite($f, "\n");
		fclose($f);

		@ob_clean();

		$postedData = wp_unslash($postedData);
		$valid = $this->check_ipn_request_is_valid($postedData);

		switch ($valid) {
			case 'V2':
			{
				if ($postedData['transaction']['state'] === 4) {
					wp_die('Status wait', 'Status wait', 200);
				}
				$orderId = $postedData['transaction']['order']['id'];
				$order = new WC_Order($orderId);

				if ($postedData['transaction']['state'] === 2) {
					$order->update_status('completed', __('Payment Successfully.', 'payop-woocommerce'));
					wp_die('Status success', 'Status success', 200);
				} elseif ($postedData['transaction']['state'] === 3 || $postedData['transaction']['state'] === 5) {
					if ($order->get_status() == 'completed') {
						return;
					}

					$error_message = isset($postedData['transaction']['error']['message']) ? $postedData['transaction']['error']['message'] : '';
					$order->update_status('failed', __('Payment Failed.', 'payop-woocommerce') . $error_message . '.');
					wp_die('Status fail', 'Status fail', 200);
				}
				break;
			}

			case 'V1':
			{
				if ($postedData['status'] === 'wait') {
					wp_die('Status wait', 'Status wait', 200);
				}

				$orderId = $postedData['orderId'];
				$order = new WC_Order($orderId);

				if ($postedData['status'] === 'success') {
					$order->update_status('completed', __('Payment successfully paid', 'payop-woocommerce'));
					wp_die('Status success', 'Status success', 200);
				} elseif ($postedData['status'] === 'error') {
					$order->update_status('failed', __('Payment not paid', 'payop-woocommerce'));
					wp_die('Status fail', 'Status fail', 200);
				}
				break;
			}
		}

		return;

	}

	public function check_ipn_request_is_valid($posted)
	{
		$invoiceId = !empty($posted['invoice']['id']) ? $posted['invoice']['id'] : null;
		$txId = !empty($posted['invoice']['txid']) ? $posted['invoice']['txid'] : null;
		$orderId = !empty($posted['transaction']['order']['id']) ? $posted['transaction']['order']['id'] : null;
		$signature = !empty($posted['signature']) ? $posted['signature'] : null;

		// check IPN V1
		if (!$invoiceId) {
			if (!$signature) {
				return 'Empty invoice id';
			} else {
				$orderId = !empty($posted['orderId']) ? $posted['orderId'] : null;
				if (!$orderId) {
					return 'Empty order id V1';
				}
				$order = new WC_Order($orderId);
				$currency = $order->get_currency();
				$amount = number_format($order->get_total(), 4, '.', '');

				$status = $posted['status'];

				if ($status !== 'success' && $status !== 'error') {
					return 'Status is not valid';
				}

				$o = ['id' => $orderId, 'amount' => $amount, 'currency' => $currency];

				ksort($o, SORT_STRING);

				$dataSet = array_values($o);

				if ($status) {
					array_push($dataSet, $status);
				}

				array_push($dataSet, $this->secret_key);

				if ($posted['signature'] === hash('sha256', implode(':', $dataSet))) {
					return 'V1';
				}
				return 'Invalid signature';
			}
		}
		if (!$txId) {
			return 'Empty transaction id';
		}
		if (!$orderId) {
			return 'Empty order id V2';
		}

		$order = new WC_Order($orderId);
		$currency = $order->get_currency();
		$state = $posted['transaction']['state'];
		if (!(1 <= $state && $state <= 5)) {
			return 'State is not valid';
		}
		return 'V2';
	}

}