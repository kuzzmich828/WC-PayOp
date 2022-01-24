<form action="" data-url="payment_processing" method="post" id="payment_processing_form">
    <div class="field-container">
        <input id="invoice" type="hidden"
               value="<?php if ($invoice) echo $invoice; ?>">
        <?php wp_nonce_field('payment_processing_action', 'payment_processing'); ?>
        <input id="seon_session" type="hidden" value="">
        <input id="order_id" type="hidden" value="<?= $order_id; ?>">
    </div>
    <div class="payment-title">
        <button type="submit" id="submit-pay"><?= __('Payment', 'wc-payop'); ?></button>
    </div>
</form>