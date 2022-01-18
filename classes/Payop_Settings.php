<?php

final class Payop_Settings
{

	const STAGE_URL = 'https://app.stage.payop.com/';
	const PROD_URL = 'https://payop.com/';
	const ID_GATEWAY = 'payop';
	const NAME_GATEWAY = 'payop';
	const NAME = 'PayOp';
	const PAYMENT_TYPE_HOSTED_PAGE = 1;
	const PAYMENT_TYPE_SERVER_SERVER = 2;

	const SERVERS_URL = [
		'PROD' => self::PROD_URL,
		'Stage' => self::STAGE_URL,
	];

	const SERVERS_TYPE = [
		'PROD' => 'PROD',
		'Stage' => 'Stage',
	];

	const PAYMENTS_TYPE = [self::PAYMENT_TYPE_HOSTED_PAGE => 'Hosted Page', self::PAYMENT_TYPE_SERVER_SERVER => 'Server to Server'];


	public static function getAviableMethods($server, $application, $jwtToken)
	{
		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => $server."v1/instrument-settings/payment-methods/available-for-application/$application",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "GET",
			CURLOPT_HTTPHEADER => array(
				"authorization: Bearer $jwtToken",
				"cache-control: no-cache",
				"content-type: application/json",
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		curl_close($curl);

		if ($err) {
			return 'Server Internal Error';
		} else {
			$methods = [];
			$response_obj = json_decode($response);
			if (isset($response_obj->message))
				return (string) $response_obj->message;

			foreach ($response_obj->data as $obj){
				$methods[$obj->identifier]= $obj->title;
			}
			return $methods;
		}

	}
}
