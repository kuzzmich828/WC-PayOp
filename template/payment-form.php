<div class="body-card">
    <div class="payment-title">
        <h1>Payment by Wallet</h1>
    </div>

    <div class="payment-title" id="message-container" style="margin: 20px 0; display: none;">
        <code style="font-size: 16px;" id="error-message"></code>
    </div>

    <?php
        if (!$serverServer->is_card_method($paymentMethod, json_decode($this->info_methods, true))){
            include_once __DIR__ . '/wallet-form.php';
        } else {
            include_once __DIR__ . '/card-form.php';
        }
    ?>

</div>
