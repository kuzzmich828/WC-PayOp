<?php

class Payop_Order
{

	private $uuid;
	public $order = false;

	public function __construct($uuid)
	{
		$this->uuid = $uuid;
		return $this->order = $this->getOrder($this->uuid);
	}

	public function getOrder($uuid)
	{
		return wc_get_order($uuid);
	}

	public function setOrderPaymentMethod($method){
		$paymentMethod = sanitize_text_field($method);
		update_post_meta($this->order->get_id(), 'paymentMethod', $paymentMethod);
	}

	public function getOrderPaymentMethod(){
		return get_post_meta($this->order->get_id(), 'paymentMethod', true);
	}

	public function is_card_method_Order(): bool
	{
		$orderMethod = $this->getOrderPaymentMethod();
		$gatewayMethods = Payop_Settings::getAviableMethods(Payop_Settings::SERVERS_URL[Payop_Gateway::getOption('_server')], str_replace('application-', '', Payop_Gateway::getOption('_public_key')), Payop_Gateway::getOption('_jwtToken'));

		foreach ($gatewayMethods as $gatewayMethod) {
			if ($orderMethod == $gatewayMethod['identifier']) {
				if ($gatewayMethod['formType'] == 'cards') {
					return true;
				} else {
					return false;
				}
			}
		}
		return false;
	}

	public function info_method_Order(Payop_Gateway $gateway): array
	{
		$orderMethod = (int) $this->getOrderPaymentMethod();
		$gatewayMethods = Payop_Settings::getAviableMethods(Payop_Settings::SERVERS_URL[$gateway->get_server()], $gateway->get_application(), $gateway->get_jwtToken());
		if ($gatewayMethods && $orderMethod)
			foreach ($gatewayMethods as $gatewayMethod) {
				if ($orderMethod == $gatewayMethod['identifier']) {
					return $gatewayMethod;
				}
			}
		return $gatewayMethods;
	}

	public function updateStatusOrderAfterTransaction($invoiceStatus)
	{
		$wc_status = false;
		$wc_note = false;

		if (!isset($invoiceStatus['data']['status']))
			return false;

		switch ($invoiceStatus['data']['status']) {
			// Invoice success
			case 'success':
				$wc_status = 'completed';
				break;
			// Invoice pending
			case 'pending':
				$wc_status = 'processing';
				break;
			// Invoice failed
			case 'fail':
				$wc_status = 'failed';
				$wc_note = isset($invoiceStatus['data']['message']) ? $invoiceStatus['data']['message'] : false;
				break;
		}

		if (!$wc_status || !$this->order) {
			return false;
		}

		if ($this->order->get_status() != $wc_status) {
			$this->order->update_status($wc_status);
			if ($wc_note) {
				$this->order->add_order_note($wc_note);
			}
			return true;
		}
	}

	public function updateStatusOrder($invoice, $transaction)
	{

		$wc_status = false;
		$wc_note = false;

		switch ((int)$invoice['status']) {
			// New invoice
			case 0:
				$wc_status = 'new';
				break;
			// Invoice was paid successfully
			case 1:
				$wc_status = 'completed';
				break;
			// Invoice pending
			case 4:
				$wc_status = 'pending';
				break;
			// Invoice failed
			case 5:
				$wc_status = 'failed';
				$wc_note = isset($transaction['error']['message']) ? $transaction['error']['message'] : false;
				break;
			default:
				$wc_status = false;
		}


		if (!$wc_status || !$this->order) {
			return false;
		}

		if ($wc_note) {
			$this->order->add_order_note($wc_note);
		}

		if ($this->order->get_status() != $wc_status) {
			$this->order->update_status($wc_status);
			return true;
		}
		return false;
	}

}
