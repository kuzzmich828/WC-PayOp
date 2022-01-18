<?php


class Payop_ServerToServer
{

	const CREATE_CARD_TOKEN_URL = 'v1/payment-tools/card-token/create';

	private int $server;

	public function __construct($server)
	{
		$this->server = $server;
	}

	public function createBankCardToken(int $server, array $card, string $invoiceID)
	{
		$card ['invoiceIdentifier'] = $invoiceID;
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => Payop_Settings::SERVERS_URL[$this->server] . self::CREATE_CARD_TOKEN_URL,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => json_encode($card),
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

}
