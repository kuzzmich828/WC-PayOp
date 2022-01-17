<?php

class Payop_Order {

    private $uuid;
    public $order = false;

    public function __construct($uuid){
        $this->uuid = $uuid;
        return $this->order = $this->getOrder($this->uuid);
    }

    public function getOrder($uuid){

        return wc_get_order($uuid);

    }

    public function setStatusOrder($status = null){

        if (!$status || !$this->order) {
            return false;
        }

        if ($this->order->get_status() != $status) {
            return $this->order->update_status($status);
        }
        return false;
    }

}
