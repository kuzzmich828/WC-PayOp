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
		$this->paymentMultiMethods = (array) $this->get_option(Payop_Settings::NAME_GATEWAY . '_paymentMultiMethods');
		$this->server = $this->get_option(Payop_Settings::NAME_GATEWAY . '_server');
		$this->language = $this->get_option(Payop_Settings::NAME_GATEWAY . '_language');
		$this->jwtToken = $this->get_option(Payop_Settings::NAME_GATEWAY . '_jwtToken');

		if ($this->public_key)
			$this->application = str_replace('application-', '', $this->public_key);
		// Method with all the options fields
		$this->init_form_fields();

//		add_action('woocommerce_receipt_' . Payop_Settings::NAME_GATEWAY, [$this, 'receipt_page'], 99, 1);
//		add_action('woocommerce_receipt', [$this, 'receipt_page'], 99, 1);
//		add_action('woocommerce_thankyou_'. Payop_Settings::NAME_GATEWAY, [$this, 'receipt_page'], 99, 1);
		add_action('woocommerce_thankyou', [$this, 'receipt_page'], 99, 1);

		add_filter('woocommerce_thankyou_order_received_text', [$this, 'order_complete'], 100, 2);

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

		$aviablePayMethods = Payop_Settings::getAviableMethods(Payop_Settings::SERVERS_URL[$this->server], $this->application, $this->jwtToken);

		$aviableMethods = [];
		$descMethods = __('Available payment methods for your application', 'wc-payop');
		if (!is_array($aviablePayMethods)) {
			$descMethods = __('Methods not aviable.', 'wc-payop') . '<br/>' . $aviableMethods;
		} else {
			foreach ($aviablePayMethods as $aviablePayMethod) {
				$aviableMethods [$aviablePayMethod['identifier']] = $aviablePayMethod['title'];
			}
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

			Payop_Settings::ID_GATEWAY . '_paymentMultiMethods' => array(
				'title' => __('Payment Methods', 'wc-payop'),
				'id' => 'paymentMultiMethods',
				'description' => $descMethods,
				'type' => 'multiselect',
				'options' => $aviableMethods,
			),

			Payop_Settings::ID_GATEWAY . '_paymentMethod' => array(
				'title' => __('Payment Method', 'wc-payop'),
				'id' => 'paymentMethod',
				'description' => $descMethods,
				'css' => 'mix-height: 100px;',
				'type' => 'select',
				'options' => array_merge(['All'=>'All'],$aviableMethods),
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
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		error_reporting(E_ALL);
		$avableMethods = Payop_Settings::getAviableMethods(Payop_Settings::SERVERS_URL[$this->server], $this->application, $this->jwtToken);

		if (!is_array($avableMethods) && ($avableMethods))
			return;
        $checked = 'checked';
		foreach ($avableMethods as $method):
            if (!in_array($method['identifier'], $this->paymentMultiMethods))
                continue;
			?>
            <div id="input_payop_methods">
                <p class="form-row">
                    <label>
                        <input type="radio" name="paymentMethod" value="<?= $method['identifier']; ?>" <?php  if ($checked) echo $checked; ?> />
						<?= $method['title']; ?>
                        <img src="<?= $method['logo']; ?>" style="max-height:32px;" />
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

		$PayopHostedPage = new Payop_HostedPage($this->secret_key, $this->public_key . '', $this->server);

        // create invoice by API PayOp
		$invoice = $PayopHostedPage->createInvoice($order, $this->paymentMethod, $this->language, $this->resultUrl, $this->failPath);


		if ((!$invoice || !isset($invoice->status) || $invoice->status != 1) && isset($invoice->message)) {
			wc_add_notice(json_encode($invoice->message), 'error');
			return false;
		}

        // Mark as on-hold (we're awaiting the cheque)
		$order->update_status('pending-payment', __('Awaiting cheque payment', 'woocommerce'));

        // Remove cart
		$woocommerce->cart->empty_cart();

		if (isset($invoice['data']) && $invoice['data'] && $invoice['status'] == 1) {
			$order->add_meta_data('invoice', $invoice['data'], true);
			$order->save_meta_data();
			return array(
				'result' => 'success',
				'redirect' => add_query_arg('invoice', $invoice['data'], $this->get_return_url($order)),
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
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		error_reporting(E_ALL);
		$order = new WC_Order($order_id);

		if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
			return;
		}

		$PayopHostedPage = new Payop_HostedPage($this->secret_key, $this->public_key, $this->server);

		$invoice = $order->get_meta('invoice');

		switch ($this->paymentType) {

			case Payop_Settings::PAYMENT_TYPE_SERVER_SERVER:
			{
				include_once __DIR__ . '/../template/card-form.php';
				break;
			}

			case Payop_Settings::PAYMENT_TYPE_HOSTED_PAGE:
			{

				echo '<p>' . __('Thank you for your order, please click the button below to pay', 'payop-woocommerce') . '</p>';
				echo '
<form action="' . str_replace('{{locale}}', $this->language, $PayopHostedPage->getProcessingUrl()) . $invoice . '"
      method="GET" id="payop_payment_form">' . "\n" .
					'<input type="submit" class="button" id="submit_payop_payment_form" value="' . __('Pay', 'payop-woocommerce') . '"/>
    <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Refuse payment & return to cart',
						'payop-woocommerce') . '</a>' . "\n" .
					'
</form>';
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

}
