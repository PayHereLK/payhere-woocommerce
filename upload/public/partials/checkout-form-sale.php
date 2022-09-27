<?php
if (!$onsite_checkout_enabled) {
    ?>
    <form action="<?php echo esc_url($payment_url) ?>" method="post" id="payhere_payment_form">
        <?php echo implode('', $payhere_args_array) ?>
    </form>
    <?php
}
?>

<div class="pay-button-wrapper">
    <?php
    if (!empty($customer_token) && !isset($payhere_args['recurrence']) && $enable_token) {
        ?>
        <button type="button" class="payhere-button" id="show_payhere_charge_now"
                onclick="payhere_chage_call(<?php echo $order->get_id() ?>)">
            <?php echo __('Pay with ' . substr($card_info->card_no, -8), 'woo_payhere') ?>
        </button>
        <?php
        if (isset($card_info->card_holder_name) && false) {
            ?>
            <p><?php echo esc_html($card_info->card_holder_name) ?></p>
            <p><?php echo esc_html($card_info->card_no) ?></p>
            <p><?php echo esc_html($card_info->card_expiry) ?></p>
            <p><?php echo esc_html($card_info->method) ?></p>
            <?php
        }
        ?>
        <?php
    }
    if (!is_user_logged_in()) {
        ?>
        <a class="payhere-button-alt" target="_blank" href="<?php echo site_url('/my-account/'); ?>">
            <?php echo __('Login to Continue', 'woo_payhere') ?>
        </a>
        <?php
    } ?>
    <br/>

    <button type="button" class="payhere-button" id="show_payhere_payment_onsite" onclick="payhere_submit_trigger()">
        <?php echo __('Pay via Payhere', 'woo_payhere') ?>
    </button>
    <?php
    if ($save_card_active && !isset($payhere_args['recurrence'])) {
        ?>
        <label class="checkbox">
            <input type="checkbox" value="1" id="save-card" checked>
            Save Card
        </label>
        <?php
    }
    ?>
</div>

<div class="payhere-overlay" style="display: none">
    <div class="payhere-content">
        <img style="display: none" src="<?php echo plugins_url('payhere-payment-gateway/assets/img/check (2).svg') ?>"/>
        <h3>Processing...</h3>
        <p>Please Wait.</p>
    </div>
</div>