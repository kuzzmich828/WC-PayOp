jQuery(document).ready(function () {
    jQuery("#payment_processing_form").submit(function (event) {

        event.preventDefault();

        jQuery(this).find("#submit-pay").hide();
        jQuery("div.spanner").addClass("show");
        jQuery("#message-wait").html("Wait for the end of the transaction...");
        jQuery('#message-error').fadeIn(300);

        var action = jQuery(this).attr('data-url');
        var invoice = jQuery(this).find("#invoice").val();
        var order_id = jQuery(this).find("#order_id").val();
        var payment_processing = jQuery(this).find("input[name=payment_processing]").val();

        var ajax_data = {
            action: action,
            payment_processing: payment_processing,
            order_id: order_id,
            invoice: invoice
        };

        if (jQuery(this).find("#cardnumber").length > 0) {
            ajax_data.name = jQuery(this).find("#name").val();
            ajax_data.cardnumber = jQuery(this).find("#cardnumber").val();
            ajax_data.expirationdate = jQuery(this).find("#expirationdate").val();
            ajax_data.securitycode = jQuery(this).find("#securitycode").val();
            ajax_data.seon_session = jQuery(this).find("#seon_session").val();
        }

        jQuery.ajax({
            async: true,
            url: payop_ajax.url,
            type: "POST",
            dataType: "json",
            data: ajax_data,
            error: function (response) {
                if (typeof response.responseJSON.message !== 'undefined') {
                    jQuery('#message-error').html(response.responseJSON.message);
                    jQuery('#message-error').fadeIn(300);
                } else {
                    jQuery('#message-error').html(response.responseText.replace(/[{}."\[\]]+/g, "").replaceAll(",", "<br/>"));
                    jQuery('#message-error').fadeIn(300);
                }
                jQuery("#submit-pay").show();
                jQuery("div.spanner").removeClass("show");
            },
            success: function (response) {

                jQuery("#payment_processing_form").hide();
                jQuery(".container-card").hide();

                var i = 0;

                var refreshId = setInterval(function (invoice) {

                    var transaction = checkTransaction(invoice);
                    if (typeof transaction.data.form.url !== 'undefined' && transaction.data.form.url) {
                        clearInterval(refreshId);
                        window.location.href = transaction.data.form.url;
                    }

                    switch (transaction.status) {
                        case 'fail': {
                            clearInterval(refreshId);
                            jQuery('#message-error').html(transaction.data.message ).fadeIn(300);
                            jQuery("div.spanner").removeClass("show");
                            jQuery("#submit-pay").show();
                            break;
                        }
                        case 'pending': {
                            jQuery('#message-error').html("Pending... <br/>" + transaction.data.message ).fadeIn(300);
                            jQuery('#message-wait').html("Pending... " + transaction.data.message );
                            jQuery("div.spanner").removeClass("show");
                            jQuery("#submit-pay").show();
                            break;
                        }
                        case 'success': {
                            jQuery("div.spanner").removeClass("show");
                            jQuery('#message-error').html("<h4 style='color:black !important;'>Success Payment</h4>").fadeIn(300);
                            clearInterval(refreshId);
                            window.location.href = payop_ajax.success_url
                            break;
                        }
                    }

                    if (i >= 4) {
                        clearInterval(refreshId);
                    }
                    i++;


                    console.log(i, transaction);
                }, 3000, invoice);



            }
        });

    });
});

function checkTransaction(invoice) {

    var status = false;
    var data = {message: ''};

    jQuery.ajax({
        async: false,
        url: payop_ajax.url,
        type: "POST",
        dataType: "json",
        data: {
            action: payop_ajax.check_invoice_status,
            invoice: invoice,
        },
        error: function (response) {
            console.log(response);

        },
        success: function (response) {
            console.log(response.data);
            if (typeof response.data.status !== 'undefined') {
                data = response.data;
                status = response.data.status;
            }
        }
    });

    return {status: status, data: data};

}