<?php


class Payop_Gateway extends WC_Payment_Gateway {

    private $public_key = '';
    private $secret_key = '';
    private $resultUrl = '';
    private $failPath = '';
    private $paymentMethod = '';
    private $language = 'en';

    /**
     * Class constructor, more about it in Step 3
     */
    public function __construct() {

        $this->id = Payop_API::NAME_GATEWAY;            // payment gateway plugin ID
        $this->icon = '';                               // URL of the icon that will be displayed on checkout page near your gateway name
        $this->has_fields = true;                       // in case you need a custom credit card form
        $this->method_title = Payop_API::NAME . ' Gateway';
        $this->method_description = 'Parameters '.Payop_API::NAME.' gateway'; // will be displayed on the options page

        // gateways can support subscriptions, refunds, saved payment methods, but in this tutorial we begin with simple payments
        $this->supports = array(
            'products'
        );

        // Method with all the options fields
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();
        $this->title = $this->get_option( 'title' );
        $this->enabled = $this->get_option( 'enabled' );
        $this->resultUrl = $this->get_option( Payop_API::NAME_GATEWAY . '_resultUrl' );
        $this->failPath = $this->get_option( Payop_API::NAME_GATEWAY . '_failPath' );
        $this->public_key = $this->get_option( Payop_API::NAME_GATEWAY . '_public_key' );
        $this->secret_key = $this->get_option( Payop_API::NAME_GATEWAY . '_secret_key' );
        $this->paymentMethod = $this->get_option( Payop_API::NAME_GATEWAY . '_paymentMethod' );
        $this->language = $this->get_option( Payop_API::NAME_GATEWAY . '_language' );

        // This action hook saves the settings
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // We need custom JavaScript to obtain a token
        add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
    }

    /**
     * Plugin options, we deal with it in Step 3 too
     */
    public function init_form_fields(){

        $this->form_fields = array(
            Payop_API::ID_GATEWAY . 'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable PayOp Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),

            Payop_API::ID_GATEWAY . '_title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'PayOp',
                'desc_tip'    => true,
            ),

            Payop_API::ID_GATEWAY . '_paymentMethod' => array(
                'title'       => 'Payment Method',
                'type'        => 'text',
            ),

            Payop_API::ID_GATEWAY . '_public_key' => array(
                'title'       => 'Public Key',
                'type'        => 'text',
            ),

            Payop_API::ID_GATEWAY . '_secret_key' => array(
                'title'       => 'Secret Key',
                'type'        => 'text',
            ),

            Payop_API::ID_GATEWAY . '_resultUrl' => array(
                'title'       => 'Result URL',
                'type'        => 'text'
            ),

            Payop_API::ID_GATEWAY . '_failPath' => array(
                'title'       => 'Fail URL',
                'type'        => 'text',
            ),

            Payop_API::ID_GATEWAY . '_language' => array(
                'title'       => 'Language',
                'type'        => 'text'
            ),
        );

    }

    /**
     * You will need it if you want your custom credit card form, Step 4 is about it
     */
    public function payment_fields() {

    }

    /*
     * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
     */
    public function payment_scripts() {

        // we need JavaScript to process a token only on cart/checkout pages, right?
        if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
            return;
        }

        // if our payment gateway is disabled, we do not have to enqueue JS too
        if ( 'no' === $this->enabled ) {
            return;
        }

        // no reason to enqueue JavaScript if API keys are not set
        if ( empty( $this->login ) || empty( $this->password ) ) {
            return;
        }


    }

    /*
      * Fields validation, more in Step 5
     */
    public function validate_fields() {
        return true;
    }

    /*
     * We're processing the payments here, everything about it is in Step 5
     */
    public function process_payment( $order_id ) {

        $order = new WC_Order( $order_id );

        $PayOp_API = new Payop_API($this->public_key, $this->secret_key);

        $invoice_parameters = [
            'id'        => (string) $order->get_id(),
            'amount'    => (string) $order->get_total(),
            'currency'  => $order->get_currency()
        ];

        //  Generate Signature for Invoice
        $signature = $PayOp_API->generateSignature($invoice_parameters);

        //  Generate Signature for Invoice
        $order_items_invoice = [];
        $items = $order->get_items();

        foreach ($items as $item){
            $order_items_invoice [] = [
                'id'    => $item->get_data()['id'],
                'name'  => $item->get_data()['name'],
                'price' => $item->get_data()['total'],
            ];
        }


        // create array for request invoice
        $request_order = [

            'publicKey' => $this->public_key,
            'order' => [
                'id' => (string) $order->get_id(),
                'amount'    => (string) $order->get_total(),
                'currency'  => $order->get_currency(),
                'items' => $order_items_invoice,
                'description' => ''
            ],

            "signature" => $signature,

            "payer" => [
                "email" => $order->get_billing_email(),
                "phone" => $order->get_billing_phone(),
                "name" => $order->get_billing_first_name(),
                "extraFields" => []
            ],

            "paymentMethod" => $this->paymentMethod,
            "language" => $this->language,
            "resultUrl" => $this->resultUrl,
            "failPath" => $this->failPath

        ];

        // create invoice by API PayOp
        $invoice_response = $PayOp_API->createInvoice($request_order);

        if (!isset($invoice_response->status) || $invoice_response->status != 1){
            wc_add_notice(  json_encode($invoice_response->message), 'error' );
            return;
        }


        global $woocommerce;
        if ($invoice_response->data && $invoice_response->status == 1) {

            // Mark as on-hold (we're awaiting the cheque)
            $order->update_status('pending-payment', __('Awaiting cheque payment', 'woocommerce'));

            // Remove cart
            $woocommerce->cart->empty_cart();

            return array(
                'result' => 'success',
                'redirect' => str_replace('{{locale}}', $this->language, PayOp_API::PROCESSING_URL) . $invoice_response->data,
            );

        }

        wc_add_notice(  "Unknown Error", 'error' );

        return;

    }

    /*
     * In case you need a webhook, like PayPal IPN etc
     */
    public function webhook() {

    }
}
