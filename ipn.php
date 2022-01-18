<?php
/*
 * Listening Pingback from PAYOP
 */

add_action('template_redirect', function () {

    if (is_page('callback-ipn')) {

        $json = file_get_contents('php://input');

        // Log IPN from payment system
        $file = fopen("ipn.log", "a");
        if (trim($json)) {
            fwrite($file, date("d.m.Y H:i:s") . "\t" . $json);
            fwrite($file, "\n");
            fclose($file);
        }

        $ipn = json_decode($json, true);

        if (isset($ipn['invoice']['status']) && $ipn['invoice']['status']) {

            $order = new Payop_Order($ipn['transaction']['order']['id']);

            if (!$order) {
                wp_redirect('/404/');
                exit;
            }

            $order->updateStatusOrder($ipn['invoice'], $ipn['transaction']);

            echo "OK";

        } else {
            wp_redirect('/404/');
            exit;
        }

        exit;
    }

    if (is_page('refund-ipn')) {
        // TO DO
        echo("Refund IPN");
        exit;
    }

});