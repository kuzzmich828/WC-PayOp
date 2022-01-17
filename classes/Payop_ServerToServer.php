<?php

class Payop_ServerToServer
{
    const CREATE_INVOICE_URL = 'https://payop.com/v1/invoices/create';
    const PROCESSING_URL = 'https://checkout.payop.com/{{locale}}/payment/invoice-preprocessing/';
    const ID_GATEWAY = 'payop';
    const NAME_GATEWAY = 'payop';
    const NAME = 'PayOp';

    private $public_key;
    private $secretKey;

    public function __construct($public_key, $secretKey){
        $this->public_key = $public_key;
        $this->secretKey = $secretKey;
        return $this;
    }

    public function createInvoice($order){

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => self::CREATE_INVOICE_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($order),
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/json"
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

    public function generateSignature($invoice_parameters){
        ksort($invoice_parameters, SORT_STRING);
        $dataSet = array_values($invoice_parameters);
        $dataSet[] = $this->secretKey;
        return hash('sha256', implode(':', $dataSet));
    }

}
