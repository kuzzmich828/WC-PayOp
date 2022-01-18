<?php

class Payop_HostedPage
{
	const CREATE_INVOICE_URL = 'v1/invoices/create';

	private $secretKey;
	private $server;

	public function __construct($secretKey, $server)
	{
		$this->secretKey = $secretKey;
		$this->server = $server;
		return $this;
	}

	public function getProcessingUrl()
	{
		switch ($this->server) {
			case 'PROD':
				return str_replace('https://', 'https://checkout.', Payop_Settings::PROD_URL) . '{{locale}}/payment/invoice-preprocessing/';
			case 'Stage':
				return str_replace('https://', 'https://', Payop_Settings::STAGE_URL) . '{{locale}}/payment/invoice-preprocessing/';
			default:
				return str_replace('https://', 'https://checkout.', Payop_Settings::PROD_URL) . '{{locale}}/payment/invoice-preprocessing/';
		}
	}

	public function createInvoice($order)
	{

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => Payop_Settings::SERVERS_URL[$this->server] . self::CREATE_INVOICE_URL,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => json_encode($order),
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

	public function generateSignature($invoice_parameters)
	{
		ksort($invoice_parameters, SORT_STRING);
		$dataSet = array_values($invoice_parameters);
		$dataSet[] = $this->secretKey;
		return hash('sha256', implode(':', $dataSet));
	}

}
