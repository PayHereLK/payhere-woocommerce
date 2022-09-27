<?php

/**
 * Gateway class
 */

class WCGatewayPayHere extends WC_Payment_Gateway
{

    const SUB_PROCESS_STATUS_NOT_SUBSCRIPTION = 0;
    const SUB_PROCESS_STATUS_SUBSCRIPTION_OK = 1;
    const SUB_PROCESS_STATUS_SUBSCRIPTION_ERROR = -1;

    const SUB_PROCESS_ERR_UNKNOWN = "Unknown error";
    const SUB_PROCESS_ERR_MULT_SUBS = "Too many subscriptions at checkout";
    const SUB_PROCESS_ERR_MIXED_PRODUCTS = "Order contains mixed products";
    const SUB_PROCESS_ERR_INC_PERIOD = "Incompatible subscription billing and trial periods";
    const SUB_PROCESS_ERR_TRIAL_LONG = "Subscription's trial period is larger than one billing period";
    const SUB_PROCESS_ERR_SYNCED = "Synchronized subscriptions are not supported yet";
    const SUB_PROCESS_ERR_INV_PERIOD = "Unsupported billing period";
    const SUB_PROCESS_ERR_FREE_TRIAL = "Free trials cannot be processed without a sign-up fee";

    const WOO_VERSION_CHECK = '2.0.0';

    public $merchant_id;
    public $secret;
    public $app_id;
    public $enable_tokenizer;
    public $app_secret;
    public $redirect_page;
    private $payment_action;
    public $msg;
    private $gatewayUtilities;

    public function __construct()
    {
        $this->id = 'payhere';
        $this->icon = 'https://payherestorage.blob.core.windows.net/payhere-resources/plugins/payhere_long_banner.png';
        $this->method_title = 'PayHere';
        $this->method_description = 'The eCommerce Payment Service Provider of Sri Lanka';

        // Checkout has fields?
        $this->has_fields = false;
        $this->gatewayUtilities = new GatewayUtilities();

        $this->init_form_fields();
        $this->init_settings();

        $supports_array = array('subscriptions', 'subscription_cancellation', 'products');
        $this->supports = $supports_array;

        // Special settigns if gateway is on Test Mode
        $test_title = '';
        $test_description = '';

        if ($this->settings['test_mode'] == 'yes') {
            $test_title = '';
            $test_description = '<br/><br/>(Sandbox Mode is Active. You will not be charged.)<br/>';
        }

        if ($this->settings['onsite_checkout'] == 'yes') {
            $this->onsite_checkout_enabled = true;
        } else {
            $this->onsite_checkout_enabled = false;
        }

        // Title as displayed on Frontend
        $this->title = $this->settings['title'] . $test_title;
        // Description as displayed on Frontend
        $this->description = $this->settings['description'] . $test_description;
        $this->merchant_id = $this->settings['merchant_id'];
        $this->payment_action = $this->settings['payment_action'];
        $this->secret = $this->settings['secret'];
        $this->app_id = $this->settings['app_id'];
        $this->enable_tokenizer = $this->settings['enable_tokenizer'] == 'yes';
        $this->app_secret = $this->settings['app_secret'];
        $this->redirect_page = $this->settings['redirect_page'];

        $this->msg['message'] = '';
        $this->msg['class'] = '';

        add_action('init', array(&$this, 'check_payhere_response'));
        add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_payhere_response')); //update for woocommerce >2.0
        //
        //        add_action('woocommerce_gateway_icon', array($this, 'modify_gateway_icon_css'), 10, 2);
        //        add_action('admin_enqueue_scripts', array($this, 'payhere_enqueue_admin_scripts'));
        //        add_action('wp_enqueue_scripts', array($this, 'payhere_enqueue_scripts'));
        //
        //        add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'unsubscribe_from_payhere_api'));
        //

        if (version_compare(WOOCOMMERCE_VERSION, self::WOO_VERSION_CHECK, '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options')); //update for woocommerce >2.0
        } else {
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options')); // WC-1.6.6
        }
        add_action('woocommerce_receipt_payhere', array(&$this, 'receipt_page'));
        add_filter('the_title', array(&$this, 'order_received_title'), 10, 2);

        add_action('woocommerce_thankyou', array(&$this, 'remove_order_from_thankyou'), 10, 1);
    }


    /**
     * @description Returns HTML template for custom settings type info_box
     * @param string $key Settings API Key from Gateway settings
     * @param array $data Settings for key value
     * @return HTML template
     */
    public function generate_info_box_html($key, $data)
    {
        return $this->gatewayUtilities->generate_info_box_html($data);
    }

    /**
     * Initialise Settings Form Fields - Settings for PayHere Gateway
     */
    public function init_form_fields()
    {
        $this->form_fields = $this->gatewayUtilities->get_form_fields();
    }

    /**
     * @description  Modify Payment Gateway Icon element css class
     * @param $icon_html
     * @param $payment_gateway_id
     * @return string|string[]|null
     */
    public function modify_gateway_icon_css($icon_html, $payment_gateway_id)
    {
        if ($payment_gateway_id != $this->id) {
            return $icon_html;
        }

        $new_css = 'class="ph-logo-style" src';
        $icon_html = preg_replace('/(src)/', $new_css, $icon_html);

        return $icon_html;
    }

    /**
     * Receipt Page
     **/
    public function receipt_page($order)
    {
        if ($this->onsite_checkout_enabled) {
            echo '<p><strong>' . __('Thank you for your order.', 'woo_payhere') . '</strong>
				</br>' . __('Click the below button to checkout with PayHere.', 'woo_payhere') . '
				</p>';
        } else {
            echo '<p><strong>' . __('Thank you for your order.', 'woo_payhere') . '</strong><br/>' . __('The payment page will open soon.', 'woo_payhere') . '</p>';
        }
        echo $this->generate_payhere_form($order);
    }

    /**
     * Generate button link
     **/
    private function generate_payhere_form($_order_id)
    {
        global $woocommerce;
        $order_id = sanitize_text_field($_order_id);
        $order = new WC_Order($order_id);
        $gateway_utilities = new GatewayUtilities();

        $redirect_url = $order->get_checkout_order_received_url();

        $notify_url = add_query_arg('wc-api', get_class($this), $redirect_url);

        //        add when test in localhost
        // $notify_url = str_replace('localhost', '3a21-119-235-8-202.ngrok.io', $notify_url);

        $product_info = "Order $order_id";

        $effective_merchant_id = apply_filters('payhere_filter_merchant_id', $this->merchant_id);
        $effective_merchant_secret = apply_filters('payhere_filter_merchant_secret', $this->secret, $order_id, $effective_merchant_id);
        $effective_test_mode = apply_filters('payhere_filter_test_mode', $this->settings['test_mode'], $effective_merchant_id);

        $payhere_args = array(
            'merchant_id' => $effective_merchant_id,
            'return_url' => $redirect_url,
            'cancel_url' => $redirect_url,
            'notify_url' => $notify_url,

            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'address' => $order->get_billing_address_1() . (
                ($order->get_billing_address_2() != "") ? ', ' . $order->get_billing_address_2() : ''
            ),
            'address1' => $order->get_billing_address_1(),
            'address2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'country' => $order->get_billing_country(),

            'order_id' => $order_id,
            'items' => $product_info,
            'currency' => get_woocommerce_currency(),
            'amount' => number_format(str_replace(',', '', $order->get_total()), 2, '.', ''),

            "delivery_firstname" => $order->get_shipping_first_name(),
            "delivery_lastname" => $order->get_shipping_last_name(),
            "delivery_address" => $order->get_shipping_address_1() . (($order->get_shipping_address_2() != null) ? ', ' . $order->get_shipping_address_2() : ''),
            "delivery_zip" => $order->get_shipping_postcode(),
            "delivery_city" => $order->get_shipping_city(),
            "delivery_country" => $order->get_shipping_country(),

            'platform' => 'woocommerce'
        );

        $gateway_utilities->get_line_items($payhere_args, $order);

        if (apply_filters('payhere_filter_hash_verification_required', true, $order_id, $effective_merchant_id)) {

            /* Frontend Hash */

            $payhere_args['hash'] = $gateway_utilities->generate_frontend_hash(
                $effective_merchant_id,
                $effective_merchant_secret,
                $order_id,
                $payhere_args['amount'],
                $payhere_args['currency']
            );
        }


        //      Start  Process as recurring payment
        $subscription_process_status = null;
        $subscription_err = null;
        $this->process_as_subscription_if_needed(
            $payhere_args,
            $subscription_process_status,
            $subscription_err,
            $order,
            $effective_merchant_secret
        );

        if ($subscription_process_status == self::SUB_PROCESS_STATUS_SUBSCRIPTION_ERROR) {
            $target_err_text = self::SUB_PROCESS_ERR_UNKNOWN;
            if (!empty($subscription_err)) {
                $target_err_text = $subscription_err;
            }

            return sprintf(
                '<ul class="woocommerce-error" role="alert"><li><b>Cannot Process Payment</b><br>%s</li></ul>',
                $target_err_text
            );
        }
        //      End  Process as recurring payment

        $payment_obj = array();

        foreach ($payhere_args as $key => $value) {
            $payment_obj[$key] = $value;
        }

        if ($effective_test_mode == 'yes') {
            $payment_obj['sandbox'] = true;
        } else {
            $payment_obj['sandbox'] = false;
        }

        $can_use_charging_api = false;
        $effective_app_id = apply_filters('payhere_filter_app_id', $this->app_id, $effective_merchant_id);
        $effective_app_secret = apply_filters('payhere_filter_app_secret', $this->app_secret, $effective_merchant_id);
        $enable_token = $this->enable_tokenizer;
        if (!empty($effective_app_id) && !empty($effective_app_secret)) {
            $can_use_charging_api = true;
        }

        if (isset($payhere_args['recurrence'])) {
            $can_use_charging_api = false;
        }

        $payhere_args_array['preapprove'] = false;

        //      For From Submit
        $onsite_checkout_enabled = $this->onsite_checkout_enabled;


        $payhere_args_array = array();
        foreach ($payhere_args as $key => $value) {
            $payhere_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
        }

        $payment_url = $gateway_utilities->get_payhere_checkout_url($effective_test_mode);
        $pre_approve_url = $gateway_utilities->get_payhere_preapprove_url($effective_test_mode);
        $authorize_url = $gateway_utilities->get_payhere_authorize_url($effective_test_mode);

        //        Frontend Template Data
        $customer_token = '';
        $save_card_active = false;
        $customer_token = get_user_meta(get_current_user_id(), 'payhere_customer_token', true);
        $_card_info = get_user_meta(get_current_user_id(), 'payhere_customer_data', true);
        $card_info = json_decode($_card_info);
        if (empty($customer_token) && is_user_logged_in() && $enable_token && $can_use_charging_api) {
            $save_card_active = true;
        }

        $payhere_js_data = array(
            'admin_ajax' => admin_url('admin-ajax.php'),
            'payment_action' => $this->payment_action,
            'onsite_enabled' => $onsite_checkout_enabled,
            'can_run_preapproval' => isset($_GET['preapproval']) && $_GET['preapproval'] == 'yes',
            'payhere_args' => $payhere_args,
            'payhere_obj' => $payment_obj,
            'url_preapprove' => $pre_approve_url,
            'url_payment' => $payment_url,
            'url_authorize' => $authorize_url,
            'save_card_active' => !$save_card_active && empty($customer_token),
        );

        ob_start();
        if ($this->payment_action == 'authorization' &&  !isset($payhere_js_data['payhere_args']['recurrence'])) {
            if ($payhere_js_data['onsite_enabled']) {
                $payhere_js_data['payhere_args']['authorize'] = true;
                $payhere_js_data['payhere_obj']['authorize'] = true;
            }
            wp_enqueue_script('payhere-checkout', plugin_dir_url(__FILE__) . '../public/js/payhere-checkout-auth.js', array('jquery'), PAYHERE_VERSION, false);
            wp_localize_script('payhere-checkout', 'payhere_config', $payhere_js_data);

            include plugin_dir_path(dirname(__FILE__)) . 'public/partials/checkout-form-auth.php';
        } else {
            wp_enqueue_script('payhere-checkout', plugin_dir_url(__FILE__) . '../public/js/payhere-checkout-sale.js', array('jquery'), PAYHERE_VERSION, false);
            wp_localize_script('payhere-checkout', 'payhere_config', $payhere_js_data);
            include plugin_dir_path(dirname(__FILE__)) . 'public/partials/checkout-form-sale.php';
        }

        return ob_get_clean();
        // $this->load_payhere_views_and_scripts($payhere_js_data);
    }


    private function load_payhere_views_and_scripts($payhere_js_data)
    {
    }

    /**
     * Modify the PayHere Arguments and notify whether - not modified
     * @param array &$payhere_args PayHere Payment Parameters
     * @param int &$process_status Constant containing a 'SUB_PROCESS_STATUS_'
     * @param string &$subscription_err Error occured (optional)
     * @param WC_Order $order WooCommerce Order Object
     * @param string $effective_merchant_secret PayHere Merchant Secret
     */
    private function process_as_subscription_if_needed(&$payhere_args, &$process_status, &$subscription_err, $order, $effective_merchant_secret)
    {

        if (!class_exists('WC_Subscriptions')) {
            $process_status = self::SUB_PROCESS_STATUS_NOT_SUBSCRIPTION;
            return;
        }

        $process_status = self::SUB_PROCESS_STATUS_SUBSCRIPTION_ERROR;
        if (!wcs_order_contains_subscription($order)) {
            $process_status = self::SUB_PROCESS_STATUS_NOT_SUBSCRIPTION;
            return;
        }

        $subscriptions = wcs_get_subscriptions_for_order($order);
        $supported_periods = array('day', 'week', 'year', 'month');

        if (count($subscriptions) > 1) {
            $process_status = self::SUB_PROCESS_ERR_MULT_SUBS;
            return;
        }

        // We only support one subscription per order.
        $subscription = $subscriptions[array_keys($subscriptions)[0]];

        $sub_price_per_period = $subscription->get_total();
        $sub_sign_up_fee = $subscription->get_sign_up_fee();
        $sub_billing_period = $subscription->get_billing_period();
        $sub_billing_interval = $subscription->get_billing_interval();
        $sub_trial_period = $subscription->get_trial_period();
        $sub_billing_length = '';
        $sub_trial_length = '';

        // Determine billing length

        $start_timestamp = $subscription->get_time('date_created');
        $trial_end_timestamp = $subscription->get_time('trial_end');
        $next_payment_timestamp = $subscription->get_time('next_payment');
        $is_synced_subscription = WC_Subscriptions_Synchroniser::subscription_contains_synced_product($subscription->get_id());

        if ($is_synced_subscription) {
            $length_from_timestamp = $next_payment_timestamp;
        } elseif ($trial_end_timestamp > 0) {
            $length_from_timestamp = $trial_end_timestamp;
        } else {
            $length_from_timestamp = $start_timestamp;
        }

        $sub_billing_length = wcs_estimate_periods_between($length_from_timestamp, $subscription->get_time('end'), $sub_billing_period);
        $sub_trial_length = wcs_estimate_periods_between($start_timestamp, $length_from_timestamp, $sub_trial_period);


        // Guard Errors
        $order_product_types = array();
        foreach ($order->get_items() as $item) {
            $product_type = WC_Product_Factory::get_product_type($item['product_id']);
            $order_product_types[$product_type] = true;
        }
        $order_product_types = array_keys($order_product_types);
        if (count($order_product_types) > 1 && array_search('subscription', $order_product_types) !== FALSE) {
            $subscription_err = self::SUB_PROCESS_ERR_MIXED_PRODUCTS;
            return;
        }

        if ($sub_trial_length > 0 && $sub_billing_period != $sub_trial_period) {
            $subscription_err = self::SUB_PROCESS_ERR_INC_PERIOD;
            return;
        }

        if ($sub_trial_length > 0 && $sub_trial_length != 1) {
            $subscription_err = self::SUB_PROCESS_ERR_TRIAL_LONG;
            return;
        }

        if ($is_synced_subscription) {
            $subscription_err = self::SUB_PROCESS_ERR_SYNCED;
            return;
        }

        if (array_search(strtolower($sub_billing_period), $supported_periods) === FALSE) {
            $subscription_err = self::SUB_PROCESS_ERR_INV_PERIOD;
            return;
        }

        if ($sub_trial_length > 0 && $sub_sign_up_fee == 0) {
            $subscription_err = self::SUB_PROCESS_ERR_FREE_TRIAL;
            return;
        }

        // Modify PayHere Args

        $startup_fee = $sub_sign_up_fee;


        $recurrence = $sub_billing_interval . ' ' . ucfirst($sub_billing_period);
        $duration = $sub_billing_length . ' ' . ucfirst($sub_billing_period);

        // Handle Forever Billing Periods

        if ($sub_billing_length == 0) {
            $duration = 'Forever';
        }

        $amount = $sub_price_per_period;

        $payhere_args['startup_fee'] = $startup_fee;
        $payhere_args['recurrence'] = $recurrence;
        $payhere_args['duration'] = $duration;
        $payhere_args['amount'] = str_replace(',', '', $amount);

        if (isset($payhere_args['hash'])) {
            $payhere_args['hash'] = $this->gatewayUtilities->generate_frontend_hash(
                $payhere_args['merchant_id'],
                $effective_merchant_secret,
                $order->get_id(),
                number_format(doubleval($payhere_args['amount']) + doubleval($startup_fee), 2, '.', ''),
                $payhere_args['currency']
            );
        }

        $process_status = self::SUB_PROCESS_STATUS_SUBSCRIPTION_OK;
    }


    private function validate_subscription()
    {
    }




    public function order_received_title($title, $id)
    {
        if (is_order_received_page() && get_the_ID() === $id) {
            global $wp;

            $order_id  = apply_filters('woocommerce_thankyou_order_id', absint($wp->query_vars['order-received']));
            $order_key = apply_filters('woocommerce_thankyou_order_key', empty($_GET['key']) ? '' : wc_clean($_GET['key']));

            if ($order_id > 0) {
                $order = wc_get_order($order_id);
                if ($order->get_order_key() != $order_key) {
                    $order = false;
                }
            }

            if (isset($order) && $order->get_payment_method() == $this->id) {
                if ($order->get_status() == 'completed' || $order->get_status() == 'on-hold') {
                    $title = 'Order received';
                } else {
                    $title = 'Payment pending';
                }
            }
        }
        return $title;
    }

    public function remove_order_from_thankyou($order_id)
    {
        $order = new WC_Order(wc_sanitize_order_id(wp_unslash($order_id)));
        if ($order->get_payment_method() == $this->id) {

            ob_clean();

            $message = $order->get_meta('payhere_gateway_message', true);
            if ($order && $order->get_status() !== 'completed' || $order->get_status() !== 'on-hold') {
?>
                <p style="margin : 10px 0"><?php echo $message ? $message : 'Payment not complete. Please try again.' ?></p>
                <div>
                    <?php
                    if ($order->needs_payment()) {
                        printf(
                            '<a class="ph-btn blue" href="%s">%s</a>',
                            esc_url($order->get_checkout_payment_url()),
                            __('Try Again', 'payhere')
                        );
                    }
                    ?>
                    <a href="<?php echo site_url() ?>" class="ph-btn gray">Return to shop</a>
                </div>
<?php
            }
        }
    }


    /**
     * Process the payment and return the result
     **/
    public function process_payment($order_id)
    {

        $order = new WC_Order($order_id);

        if (version_compare(WOOCOMMERCE_VERSION, '2.1.0', '>=')) { // For WC 2.1.0
            $checkout_payment_url = $order->get_checkout_payment_url(true);
        } else {
            $checkout_payment_url = get_permalink(get_option('woocommerce_pay_page_id'));
        }

        return array(
            'result' => 'success',
            'redirect' => add_query_arg(
                'order',
                $order->get_id(),
                add_query_arg(
                    'key',
                    $order->order_key,
                    $checkout_payment_url
                )
            )
        );
    }


    /**
     * Check for valid gateway server callback
     **/
    public function check_payhere_response()
    {
        global $woocommerce;

        //            Redirect if access url directly
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($this->redirect_page == '' || $this->redirect_page == 0) {
                $redirect_url = get_permalink(get_option('woocommerce_myaccount_page_id'));
            } else {
                $redirect_url = get_permalink($this->redirect_page);
            }
            wp_redirect($redirect_url);
            return false;
        }

        $is_subscription = isset($_POST['subscription_id']) && !empty($_POST['subscription_id']);
        $is_authorization = isset($_POST['authorization_token']) && !empty($_POST['authorization_token']);

        $gateway_utilities = new GatewayUtilities();
        $gateway_utilities->_log('PAYHERE_RESPONSE', $_POST);

        if (!isset($_REQUEST['order_id']) || !isset($_REQUEST['payment_id'])) {
            $gateway_utilities->_log('PAYHERE_RESPONSE', 'Order id Not Found.');
            return false;
        }
        $order_id = wc_sanitize_order_id($_REQUEST['order_id']);
        if (empty($order_id)) {
            $gateway_utilities->_log('PAYHERE_RESPONSE', 'Order id Not Found after sanitize');
            return false;
        }
        try {
            $order = new WC_Order($order_id);
            $status = sanitize_text_field($_REQUEST['status_code']);

            $verified = $gateway_utilities->verify_hash($this->secret);

            if (($order->get_status() !== 'completed' && !$is_subscription) || ($is_subscription)) {
                if (!$verified) {
                    $this->msg['class'] = 'error';
                    $this->msg['message'] = "Security Error. Illegal access detected.";
                    $order->add_order_note('Checksum ERROR: ' . json_encode($_REQUEST));
                    $gateway_utilities->_log('PAYHERE_RESPONSE', 'Illegal HASH paramter');
                    return false;
                }

                $status = strtolower($status);
                $order_util = new PayHereOrderUtilities($order, $is_subscription);

                $order->add_meta_data('payhere_gateway_message', sanitize_text_field($_REQUEST['status_message']), true);

                if ($status == "2") {
                    if (isset($_REQUEST['customer_token'])) {
                        $order_util->update_user_token($_REQUEST);
                    }

                    $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
                    $this->msg['class'] = 'woocommerce-message';
                    $order_util->update_order($_REQUEST);
                    $gateway_utilities->_log('PAYHERE_RESPONSE', 'Order Updated : Successfull');
                } else if ($status == "0") {

                    $this->msg['message'] = "Thank you for shopping with us. Right now your payment status is pending. We will keep you posted regarding the status of your order through eMail";
                    $this->msg['class'] = 'woocommerce-info';
                    $order->add_order_note('PayHere payment status is pending<br/>PayHere Payment ID: ' . sanitize_text_field($_REQUEST['payment_id']));
                    $order->update_status('on-hold');
                    $woocommerce->cart->empty_cart();

                    $gateway_utilities->_log('PAYHERE_RESPONSE', 'Order Updated : Pending');
                    if ($is_subscription) {
                        WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($order);
                        $gateway_utilities->_log('PAYHERE_RESPONSE', 'Order Updated Subscription : Successfull');
                    }
                } else if ($status == "-2") {

                    $order->update_status('failed');
                    $order->add_order_note(sanitize_text_field($_REQUEST['status_message']));
                    $gateway_utilities->_log('PAYHERE_RESPONSE', 'Order Updated : Failed');
                } else {

                    $this->msg['class'] = 'woocommerce-error';
                    $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                    $order->add_order_note('Transaction ERROR. Status Code: ' . $status);
                    $gateway_utilities->_log('PAYHERE_RESPONSE', 'Order Updated : Failed : ' . $status);
                }

                if ($is_subscription) {
                    $order->add_order_note(sprintf(
                        'Subscription Message = %s, Subscription Status = %s, Next Recurring Date %s',
                        sanitize_text_field($_REQUEST['message_type']),
                        sanitize_text_field($_REQUEST['item_rec_status']),
                        sanitize_text_field($_REQUEST['item_rec_date_next'])
                    ));
                }
            }
        } catch (Exception $e) {
            // $errorOccurred = true;
            $gateway_utilities->_log('PAYHERE_RESPONSE', 'ERROR : ' . $e->getMessage());
        }

        if ($is_authorization && isset($_REQUEST['order_id'])) {
            $order_id = wc_sanitize_order_id($_REQUEST['order_id']);
            $verified = $gateway_utilities->verify_hash($this->secret);
            if ($verified) {
                $order = new WC_Order($order_id);
                $order_util = new PayHereOrderUtilities($order, $is_subscription);
                $order_util->authorize_order($_POST);
                $gateway_utilities->_log('PAYHERE_RESPONSE', 'Order Updated : Autorizarion : ' . $status);
            }
        }
    }


    /**
     * Charge via PayHere Charging API
     */
    public function charge_payment()
    {
        $json = [];
        $order_id = wc_sanitize_order_id($_REQUEST['order_id']);

        $effective_merchant_id = apply_filters('payhere_filter_merchant_id', $this->merchant_id);
        $effective_test_mode = apply_filters('payhere_filter_test_mode', $this->settings['test_mode'], $effective_merchant_id);
        $effective_app_id = apply_filters('payhere_filter_app_id', $this->app_id, $effective_merchant_id);
        $effective_app_secret = apply_filters('payhere_filter_app_secret', $this->app_secret, $effective_merchant_id);


        $customer_token = get_user_meta(get_current_user_id(), 'payhere_customer_token', true);
        $json['customer_token'] = $customer_token;
        $json['user_id'] = get_current_user_id();
        if (empty($customer_token)) {
            $json['type'] = 'ERR';
            $json['message'] = 'Can\'t make the payment. Card did not Accepted.';
        } else {

            $charge_payment = new ChargePayment($effective_app_id, $effective_app_secret, $effective_test_mode);
            $order = new WC_Order($order_id);

            $this->gatewayUtilities->_log('ORDER_FOUND', ($order instanceof WC_Order));
            $json = $charge_payment->charge_payment($order, $customer_token);

            $this->gatewayUtilities->_log('CHARGE', $json);

            echo json_encode($json);
            exit();
        }
    }

    public function capture_payment()
    {
        $json = [];
        $order_id = wc_sanitize_order_id($_REQUEST['order_id']);

        $effective_merchant_id = apply_filters('payhere_filter_merchant_id', $this->merchant_id);
        $effective_test_mode = apply_filters('payhere_filter_test_mode', $this->settings['test_mode'], $effective_merchant_id);
        $effective_app_id = apply_filters('payhere_filter_app_id', $this->app_id, $effective_merchant_id);
        $effective_app_secret = apply_filters('payhere_filter_app_secret', $this->app_secret, $effective_merchant_id);


        $payhere_authorize_token = get_post_meta($order_id, 'payhere_auth_token', true) ? get_post_meta($order_id, 'payhere_auth_token', true) : '';
        $payhere_authorize_amount = get_post_meta($order_id, 'payhere_auth_amount', true) ? get_post_meta($order_id, 'payhere_auth_amount', true) : '';


        $capture = new PayHereCapturePayment($effective_app_id, $effective_app_secret, $effective_test_mode);
        $order = new WC_Order($order_id);
        $json = $capture->capture_payment_payhere($order, $payhere_authorize_token, $payhere_authorize_amount);

        $this->gatewayUtilities->_log('CHARGE', $json);

        echo json_encode($json);
        exit();
    }
}
