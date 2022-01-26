jQuery(document).ready(function () {

    jQuery("#woocommerce_payop_payop_info_methods").parents('tr').hide();
    jQuery("#woocommerce_payop_payop_paymentType").change(function (){
        switch (jQuery("#woocommerce_payop_payop_paymentType").val()){
            case '1': jQuery("#woocommerce_payop_payop_paymentMultiMethods").parents('tr').hide(); jQuery("#woocommerce_payop_payop_paymentMethod").parents('tr').show(); break;
            case '2': jQuery("#woocommerce_payop_payop_paymentMethod").parents('tr').hide();  jQuery("#woocommerce_payop_payop_paymentMultiMethods").parents('tr').show(); break;
        }
    });

});