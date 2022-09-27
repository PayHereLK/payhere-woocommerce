<?php
global $post;
$order = wc_get_order($post->ID);
$payhere_authorize_token = get_post_meta($post->ID, 'payhere_auth_token', true) ? get_post_meta($post->ID, 'payhere_auth_token', true) : '';
$payhere_authorize_amount = get_post_meta($post->ID, 'payhere_auth_amount', true) ? get_post_meta($post->ID, 'payhere_auth_amount', true) : '';
$payhere_acpture_amount = get_post_meta($post->ID, 'payhere_acpture_amount', true) ? get_post_meta($post->ID, 'payhere_auth_amount', true) : '';
$payhere_capture_date = get_post_meta($post->ID, 'payhere_acpture_date', true) ? get_post_meta($post->ID, 'payhere_acpture_date', true) : '';
add_thickbox();

wp_enqueue_script('payhere-capture', plugin_dir_url(__FILE__) . '../js/payhere-admin-capture.js', array('jquery'), '2.0.0');
wp_localize_script('payhere-capture', 'payhere_capture_data', array(
    'admin_ajax' => admin_url('admin-ajax.php'),
    'capture_token' => $payhere_authorize_token,
    'authorize_amount' => $payhere_authorize_amount,
    'order_id' => $order->get_id()
));

$order_amount = $order->get_total();
if ($payhere_authorize_token != '' && in_array($order->get_status(),array('phauthorized','processing')) ) {
    if($order->get_status() == 'phauthorized'){
        ?>
        <div class="payhere-data-wrapper">
            <div class="payhere-data-row">
                <div>Payment Status :</div>
                <div><?php echo $payhere_authorize_token != '' ? __('Authorised','payhere') : '' ?></div>
            </div>
            <div class="payhere-data-row">
                <div>Authorized Amount :</div>
                <div><?php echo $payhere_authorize_amount ?></div>
            </div>
            <div class="payhere-data-row">
                <div></div>
                <div>
                    <div class="payhere-capture-initiater">
                        <a href="#TB_inline?width=600&height=250&inlineId=payhere-capture-window&modal=true"
                            title="PayHere Capture Payment" class="thickbox">Capture Payment</a>
                    </div>
                </div>
            </div>
        </div>
        <div id="payhere-capture-window" style="display:none;">
            <div class="payhere-modal-header">PayHere Capture Payment</div>
            <div class="capture-window">
                <p>Processed by Payhere Payment Gateway</p>
                <p class="text-warning">You can only capture once with PayHere.</p>
                <div class="payhere-data-row">
                    <div>Amount to capture</div>
                    <div>
                        <div class="input-wrapper"><span><?php echo $order->get_currency() ?></span><input
                                    id="payhere-capture-amount" type="number"
                                    max="<?php echo $payhere_authorize_amount ?>"
                                    value="<?php echo $order_amount >= $payhere_authorize_amount ? $payhere_authorize_amount:$order_amount  ?>"/>
                        </div>
                        <span id="info-div"></span>
                    </div>
                </div>
                <div class="payhere-data-row">
                    <div></div>
                    <div>
                        <button class="capture-button" id="payhere_capture_initiator"><img
                                    src="<?php echo plugin_dir_url(__FILE__) . '../images/ajax-loader.gif' ?>"
                                    style="vertical-align: middle;margin-right: 3px;display: none" height="20"/><span>Capture Payment</span>
                        </button>
                        <button class="button button-default" id="cancel-button" onclick="tb_remove()">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }else{
    ?>
        <div class="payhere-data-wrapper">
            <div class="payhere-data-row">
                <div>Payment Status :</div>
                <div><?php
                    switch ($order->get_status()) {
                        case   'processing':
                        case   'completed':
                            echo "Payment Complete";
                            break;
                        case   'pending':
                            echo "Payment pending";
                            break;
                        default:
                            echo "Payment Failed";
                            break;
                    }
                    ?>
                </div>
            </div>
            <div class="payhere-data-row">
                <div>Payment Captured on :</div>
                <div><?php echo $payhere_capture_date; ?></div>
            </div>
            <div class="payhere-data-row">
                <div>Payment Captured Amount :</div>
                <div><?php echo $payhere_acpture_amount; ?></div>
            </div>
        </div>
    <?php

    }
} else {
    ?>
    <div class="payhere-data-wrapper">
        <div class="payhere-data-row">
            <div>Payment Status :</div>
            <div><?php
                switch ($order->get_status()) {
                    case   'processing':
                    case   'completed':
                        echo "Payment Complete";
                        break;
                    case   'pending':
                        echo "Payment pending";
                        break;
                    default:
                        echo "Payment Failed";
                        break;
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}