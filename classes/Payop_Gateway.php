<?php


class Payop_Gateway extends WC_Payment_Gateway
{

	private string $application;
	private string $public_key;
	private string $secret_key;
	private string $resultUrl;
	private string $failPath;
	private int $paymentMethod;
	private int $paymentType;
	private int $paymentPage;
	private string $server = 'PROD';
	private string $language = 'en';
	private string $jwtToken;

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
		$this->paymentPage = (int)$this->get_option(Payop_Settings::NAME_GATEWAY . '_paymentPage');
		$this->server = $this->get_option(Payop_Settings::NAME_GATEWAY . '_server');
		$this->language = $this->get_option(Payop_Settings::NAME_GATEWAY . '_language');
		$this->jwtToken = $this->get_option(Payop_Settings::NAME_GATEWAY . '_jwtToken');

		if ($this->public_key)
			$this->application = str_replace('application-', '', $this->public_key);
		// Method with all the options fields
		$this->init_form_fields();

		// This action hook saves the settings
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		// We need custom JavaScript
		wp_enqueue_script('payop-script', plugin_dir_url(__DIR__) . '/assets/js/payop.js');

	}

	/**
	 * Plugin options, we deal with it in Step 3 too
	 */
	public function init_form_fields()
	{

		$aviableMethods = Payop_Settings::getAviableMethods(Payop_Settings::SERVERS_URL[$this->server], $this->application, $this->jwtToken);

		$descMethods = __('Available payment methods for your application', 'wc-payop');
		if (!is_array($aviableMethods)) {
			$descMethods = __('Methods not aviable.', 'wc-payop') . '<br/>' . $aviableMethods;
			$aviableMethods = [];
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

			Payop_Settings::ID_GATEWAY . '_paymentType' => array(
				'title' => __('Payment Type', 'wc-payop'),
				'type' => 'select',
				'options' => Payop_Settings::PAYMENTS_TYPE,
			),

			Payop_Settings::ID_GATEWAY . '_jwtToken' => array(
				'title' => __('JWT Token', 'wc-payop'),
				'type' => 'text',
			),

			Payop_Settings::ID_GATEWAY . '_paymentMethod' => array(
				'title' => __('Payment Method', 'wc-payop'),
				'id' => 'paymentMethod',
				'description' => $descMethods,
				'type' => 'select',
				'options' => $aviableMethods,
			),

			Payop_Settings::ID_GATEWAY . '_paymentPage' => array(
				'title' => __('Payment Page', 'wc-payop'),
				'type' => 'select',
				'options' => $pages,

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

		);

	}

	/**
	 * You will need it if you want your custom credit card form, Step 4 is about it
	 */
	public function payment_fields()
	{

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

		$order = new WC_Order($order_id);

		$Payop_HostedPage = new Payop_HostedPage($this->secret_key, $this->server);

		$invoice_parameters = [
			'id' => (string)$order->get_id(),
			'amount' => (string)$order->get_total(),
			'currency' => (string)$order->get_currency(),
		];

		//  Generate Signature for Invoice
		$signature = $Payop_HostedPage->generateSignature($invoice_parameters);

		//  Generate Signature for Invoice
		$order_items_invoice = [];
		$items = $order->get_items();

		foreach ($items as $item) {
			$order_items_invoice [] = [
				'id' => $item->get_data()['id'],
				'name' => $item->get_data()['name'],
				'price' => $item->get_data()['total'],
			];
		}


		// create array for request invoice
		$request_order = [

			'publicKey' => $this->public_key,
			'order' => [
				'id' => (string)$order->get_id(),
				'amount' => (string)$order->get_total(),
				'currency' => $order->get_currency(),
				'items' => $order_items_invoice,
				'description' => '',
			],

			"signature" => $signature,

			"payer" => [
				"email" => (string)$order->get_billing_email(),
				"phone" => (string)$order->get_billing_phone(),
				"name" => (string)$order->get_billing_first_name(),
				"extraFields" => [],
			],

			"paymentMethod" => $this->paymentMethod,
			"language" => $this->language,
			"resultUrl" => $this->resultUrl,
			"failPath" => $this->failPath,

		];

		// create invoice by API PayOp
		$invoice_response = $Payop_HostedPage->createInvoice($request_order);

		if (!isset($invoice_response->status) || $invoice_response->status != 1) {
			wc_add_notice(json_encode($invoice_response->message), 'error');
			return;
		}


		global $woocommerce;
		if ($invoice_response->data && $invoice_response->status == 1) {

			// Mark as on-hold (we're awaiting the cheque)
//            $order->update_status('pending-payment', __('Awaiting cheque payment', 'woocommerce'));

			// Remove cart
//            $woocommerce->cart->empty_cart();

			switch ($this->paymentType) {
				case Payop_Settings::PAYMENT_TYPE_HOSTED_PAGE:
				{
					return array(
						'result' => 'success',
						'redirect' => str_replace('{{locale}}', $this->language, $Payop_HostedPage->getProcessingUrl()) . $invoice_response->data,
					);
				}

				case Payop_Settings::PAYMENT_TYPE_SERVER_SERVER:
				{
					return array(
						'result' => 'success',
						'redirect' => get_the_permalink($this->paymentPage) . '?invoice='.$invoice_response->data,
					);
				}
			}


		}

		wc_add_notice("Unknown Error", 'error');

		return;

	}

	/*
	 * In case you need a webhook, like PayPal IPN etc
	 */
	public function webhook()
	{

	}

}
