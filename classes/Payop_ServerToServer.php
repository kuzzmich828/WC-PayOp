<?php


class Payop_ServerToServer extends Abstract_Payop_Helper
{

	const CREATE_CARD_TOKEN_URL = 'v1/payment-tools/card-token/create';
	const CREATE_CHECKOUT_TRANSACTION_URL = 'v1/checkout/create';
	const CHECK_INVOICE_STATUS_URL = 'v1/checkout/check-invoice-status/';

	public function __construct(string $server)
	{
		$this->server = $server;
	}

	public function createBankCardToken(string $invoiceID, array $card)
	{
		$card ['invoiceIdentifier'] = $invoiceID;
		$response = $this->curlPOST(self::CREATE_CARD_TOKEN_URL, $card);

		if (isset($response['message'])) {
			return $response['message'];
		}

		if (isset($response['data'])) {
			return $response['data'];
		}

		return $response;
	}

	public function checkInvoiceStatus(string $invoiceID)
	{
		return $this->curlGET(self::CHECK_INVOICE_STATUS_URL . $invoiceID);
	}

	public function createCheckoutTransaction(string $invoiceID, array $customer, string $checkStatusUrl, string $payCurrency = '', int $paymentMethod = 0, string $cardToken = '')
	{

		$params = [
			'invoiceIdentifier' => $invoiceID,
			'customer' => $customer,
			'checkStatusUrl' => $checkStatusUrl,
		];

		if ($cardToken) {
			$params['cardToken'] = $cardToken;
		}

		if ($paymentMethod) {
			$params['paymentMethod'] = $paymentMethod;
		}

		if ($payCurrency) {
			$params['payCurrency'] = $payCurrency;
		}

		return $this->curlPOST(self::CREATE_CHECKOUT_TRANSACTION_URL, $params);

	}

	public function is_card_method($method, $all_methods) : bool
	{
		foreach ($all_methods as $method){
			/* TODO check methods */
		}
	}
}
