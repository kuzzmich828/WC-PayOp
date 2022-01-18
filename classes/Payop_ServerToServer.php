<?php


class Payop_ServerToServer extends Abstract_Payop_Helper
{

	const CREATE_CARD_TOKEN_URL = 'v1/payment-tools/card-token/create';
	const CREATE_CHECKOUT_TRANSACTION_URL = 'v1/checkout/create';

//	private string $server;

	public function __construct($server)
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

		return false;
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

}
