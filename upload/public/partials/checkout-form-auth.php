<?php

if(isset($payhere_args['recurrence'])){

}

if (!$onsite_checkout_enabled) {
    ?>
    <form action="<?php echo esc_url($authorize_url) ?>" method="post" id="payhere_payment_form">
        <?php echo implode('', $payhere_args_array) ?>
    </form>
    <?php
}
?>


<div class="pay-button-wrapper">
    <button type="button" class="payhere-button" id="show_payhere_payment_onsite" onclick="payhere_submit_trigger()">
        <?php echo __('Pay via Payhere Auth', 'woo_payhere') ?>
    </button>
</div>

