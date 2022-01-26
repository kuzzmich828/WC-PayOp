<?php
/*
 * Listening Pingback from PAYOP
 */

add_action('template_redirect', function () {

    if (is_page('callback-ipn') || is_page('refund-ipn')) {
	    $json = file_get_contents('php://input');
    	wp_remote_post(get_site_url() .  '?wc-api=wc_payop&payop=result',[
    		'body'=>$json
	    ]);
    	wp_die();
    }

});