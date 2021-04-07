<style>
    .payhere-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: rgba(0, 0, 0, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 999;
    }

    .content {
        padding: 10px;
        background-color: transparent;
        color: #efefef;
        text-align: center;
    }

    .content h3 {
        color: #efefef;
    }

    .pay-button-wrapper {
        display: flex;
        align-items: center;
    }

    #show_payhere_charge_now {
        margin-right: 15px;
    }

    .button-alt {
        margin-left: 15px;
    }

    .payhere-overlay img {
        width: 60px;
        margin: auto;
    }

</style>

<?php
if (!$onsite_checkout_enabled) {
    ?>
    <form action="<?php echo $effective_url ?>" method="post" id="payhere_payment_form">
        <?php echo implode('', $payhere_args_array) ?>
    </form>
    <?php
}
$contains_sunscription_item = false;
if (function_exists('wcs_order_contains_subscription')) {
    $contains_sunscription_item = wcs_order_contains_subscription($order->get_id());
}
if ($can_use_charging_api) {
    $can_use_charging_api = $contains_sunscription_item == true ? false : true;
}
?>

<div class="pay-button-wrapper">
    <?php
    $customer_token = '';
    if (is_user_logged_in() && $can_use_charging_api) {
        $customer_token = get_user_meta(get_current_user_id(), 'payhere_customer_token', true);
        $_card_info = get_user_meta(get_current_user_id(), 'payhere_customer_data', true);
        $card_info = json_decode($_card_info);
        if (!empty($customer_token)) {
            ?>
            <button type="button" class="button-alt" id="show_payhere_charge_now"
                    onclick="payhere_chage_call(<?php echo $order->get_id() ?>)">
                <?php echo __('Pay with ' . substr($card_info->card_no, -8), 'woo_payhere') ?>
            </button>
            <?php
            if (isset($card_info->card_holder_name) && false) {
                ?>
                <p><?php echo $card_info->card_holder_name ?></p>
                <p><?php echo $card_info->card_no ?></p>
                <p><?php echo $card_info->card_expiry ?></p>
                <p><?php echo $card_info->method ?></p>
                <?php
            }
            ?>
            <br/>
            <?php
        }
        ?>

    <?php }
    if (!is_user_logged_in()) {
        ?>
        <a class="button-alt" target="_blank" href="<?php echo site_url('/my-account/'); ?>">
            <?php echo __('Login to Continue', 'woo_payhere') ?>
        </a>
        <br/>
        <?php
    } ?>
    <br/>

    <button type="button" class="button-alt" id="show_payhere_payment_onsite" onclick="payhere_submit_trigger()">
        <?php echo __('Pay via Payhere', 'woo_payhere') ?>
    </button>
    <?php
    $save_card_active = false;
    if (empty($customer_token) && is_user_logged_in() && $enable_tokenizer && $can_use_charging_api) {
        $save_card_active = true;
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
    <div class="content">
        <img style="display: none" src="<?php echo plugins_url('payhere-payment-gateway/assets/img/check (2).svg') ?>"/>
        <h3>Processing...</h3>
        <p>Please Wait.</p>
    </div>
</div>

<script type="text/javascript" src="https://www.payhere.lk/lib/payhere.js"></script>
<script>

    let hash_one = '<?php echo $hash_1; ?>';
    let hash = '<?php echo $hash; ?>';
    let on_site_checkout_enabled = <?php echo $onsite_checkout_enabled ? 'true' : 'false'; ?>;
    payhere.onCompleted = function onCompleted(orderId) {
        let preapproval = jQuery("#save-card").is(":checked");
        if (preapproval) {
            payhere_chage_call(orderId);
        } else {
            window.location = "<?php echo $payhere_args['return_url']?>";
        }
    };

    payhere.onError = function onError(error) {
        alert("An error occured while making the payment. Error: " + error);
    };

    function payhere_chage_call(orderId) {
        jQuery(".payhere-overlay").find('h3').html('Processing').css({color: '#fff'});
        jQuery('.payhere-overlay').show();
        jQuery.ajax({
            url: '<?php echo admin_url('admin-ajax.php') ?>',
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'payhere_charge',
                order_id: orderId
            },
            success: function (data) {
                if (data.type == 'OK') {

                    jQuery(".payhere-overlay").find('img').show();
                    jQuery(".payhere-overlay").find('h3').html(data.message).css({color: '#36d004'});
                    jQuery(".payhere-overlay").find('p').html('Redirecting...');
                    setTimeout(function () {
                        window.location = "<?php echo $payhere_args['return_url']?>";
                    }, 1500);
                } else {
                    jQuery(".payhere-overlay").find('h3').html(data.message).css({color: '#d00404'});
                    setTimeout(function () {
                        jQuery(".payhere-overlay").hide();
                    }, 3000);
                }
            }
        });
    }
    <?php
    if(isset($_GET['preapproval']) && $_GET['preapproval'] == 'yes'){
    ?>
        payhere_chage_call(<?php echo $order->get_id() ?>);
    <?php
        }else{
            if(!$save_card_active){
            ?>
                payhere_submit_trigger();
            <?php
            }
        }
    ?>

    function payhere_submit_trigger() {
        let preapproval = jQuery("#save-card").is(":checked");
        if (on_site_checkout_enabled) {
            let payment = <?php echo json_encode($payment_obj) ?>;
            if (preapproval) {
                payment.preapprove = true;
                payment.hash = hash_one;
            } else {
                payment.preapprove = false;
            }
            payhere.startPayment(payment);
        } else {
            if (preapproval) {
                jQuery("input[name='hash']").val(hash_one);
                jQuery("input[name='preapprove']").val(true);
                let url = window.location.href + '&preapproval=yes'
                jQuery("input[name='return_url']").val(url);
                jQuery("#payhere_payment_form").attr({action: '<?php echo $pre_approve_url ?>'});

            } else {
                jQuery("input[name='preapprove']").val(false);
                jQuery("input[name='hash']").val(hash);
                let url = jQuery("input[name='cancel_url']").val();
                jQuery("input[name='return_url']").val(url);

                jQuery("#payhere_payment_form").attr({action: '<?php echo $payment_url ?>'});
            }
            jQuery("#payhere_payment_form").trigger('submit');
        }
    }
</script>