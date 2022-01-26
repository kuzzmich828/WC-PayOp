<?php

abstract class Abstract_Payop_Helper
{

	public string $server = '';

	public function __construct()
	{

	}

	public function curlPOST($url, $fields)
	{
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => Payop_Settings::SERVERS_URL[$this->server] . $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => json_encode($fields),
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
			return json_decode($response, true);
		}

		return json_decode($response, true);
	}

	public function curlGET($url)
	{
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => Payop_Settings::SERVERS_URL[$this->server] . $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_POST => false,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "GET",
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
			return json_decode($response, true);
		}

		return json_decode($response, true);
	}


}
