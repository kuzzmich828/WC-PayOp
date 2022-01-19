<?php

class Payop_HostedPage extends Abstract_Payop_Helper
{
	const CREATE_INVOICE_URL = 'v1/invoices/create';

	private $secretKey;
	private $publicKey;

	public function __construct($secretKey, $publicKey, $server)
	{
		$this->publicKey = $publicKey;
		$this->secretKey = $secretKey;
		$this->server = $server;
		return $this;
	}

	public function getProcessingUrl()
	{
		switch ($this->server) {
			case 'Stage':
				return str_replace('https://', 'https://', Payop_Settings::STAGE_URL) . '{{locale}}/payment/invoice-preprocessing/';
			case 'PROD':
			default:
				return str_replace('https://', 'https://checkout.', Payop_Settings::PROD_URL) . '{{locale}}/payment/invoice-preprocessing/';
		}
	}

	public function createInvoice($order, $paymentMethod, $language, $resultUrl, $failPath)
	{

		$order_items_invoice = [];
		$items = $order->get_items();

		foreach ($items as $item) {
			$order_items_invoice [] = [
				'id' => $item->get_data()['id'],
				'name' => $item->get_data()['name'],
				'price' => $item->get_data()['total'],
			];
		}

		$signature = $this->generateSignature($order);

		// create array for request invoice
		$request_order = [

			'publicKey' => $this->publicKey,
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

			"paymentMethod" => $paymentMethod,
			"language" => $language,
			"resultUrl" => $resultUrl,
			"failPath" => $failPath,

		];


		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => Payop_Settings::SERVERS_URL[$this->server] . self::CREATE_INVOICE_URL,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => json_encode($request_order),
			CURLOPT_HTTPHEADER => array(
				"cache-control: no-cache",
				"content-type: application/json",
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			echo "cURL Error #:" . $err;
		} else {
			return json_decode($response);
		}

		return json_decode($response);

	}

	public function generateSignature($order)
	{

		$invoice_parameters = [
			'id' => (string)$order->get_id(),
			'amount' => (string)$order->get_total(),
			'currency' => (string)$order->get_currency(),
		];

		ksort($invoice_parameters, SORT_STRING);
		$dataSet = array_values($invoice_parameters);
		$dataSet[] = $this->secretKey;
		return hash('sha256', implode(':', $dataSet));
	}

}
