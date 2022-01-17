<?php
/*
 * Listening Pingback from PAYOP
 */

add_action('template_redirect', function (){

    if (is_page('callback-ipn')){

        $json = file_get_contents('php://input');

        // Log IPN from payment system
            $file = fopen("ipn.log", "a");
            if (trim($json)) {
                fwrite($file, date("d.m.Y H:i:s") . "\t" . $json);
                fwrite($file, "\n");
                fclose($file);
            }

        $ipn = json_decode($json);

        if (isset($ipn) && $ipn->order) {

            $order = new Payop_Order($ipn->order);

            if (!$order) {
                wp_redirect('/404/');
                exit;
            }

            switch ($ipn->status){
                case 'payment': $status = 'completed';  break;
                case 'failed':    $status = 'failed';     break;
            }

            $order->setStatusOrder($status);

            echo "OK";

        }else{
            wp_redirect('/404/');
            exit;
        }

        exit;
        return;
    }

    if (is_page('refund-ipn')){

        // TO DO

        echo ("Refund IPN");
        exit;
    }

});