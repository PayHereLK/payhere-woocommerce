<?php
/*
Plugin Name: WooCommerce PayHere Payment Gateway
Plugin URI: https://www.payhere.lk
Description: PayHere Payment Gateway allows you to accept payment on your Woocommerce store via Visa, MasterCard, AMEX, eZcash, mCash & Internet banking services.
Version: 1.0.10
Author: PayHere (Private) Limited
Author URI: https://www.payhere.lk
*/

add_action('plugins_loaded', 'woocommerce_gateway_payhere_init', 0);
define('payhere_IMG', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');
include 'subscription_restrictions_actions.php';
function woocommerce_gateway_payhere_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    /**
     * Gateway class
     */
    class WC_Gateway_PayHere extends WC_Payment_Gateway
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

        /**
         * Make __construct()
         **/
        public function __construct()
        {

            $this->id = 'payhere';
            $this->icon = 'https://www.payhere.lk/downloads/images/payhere_long_banner.png';
            $this->method_title = 'PayHere';
            $this->method_description = 'The eCommerce Payment Service Provider of Sri Lanka';

            // Checkout has fields?
            $this->has_fields = false;

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
            $this->secret = $this->settings['secret'];
            $this->app_id = $this->settings['app_id'];
            $this->app_secret = $this->settings['app_secret'];
            // Define the Redirect Page.
            $this->redirect_page = $this->settings['redirect_page'];

            $this->msg['message'] = '';
            $this->msg['class'] = '';

            add_action('init', array(&$this, 'check_payhere_response'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_payhere_response')); //update for woocommerce >2.0
            add_action('woocommerce_gateway_icon', array($this, 'modify_gateway_icon_css'), 10, 2);
            add_action('admin_enqueue_scripts', array($this, 'payhere_enqueue_admin_scripts'));
            add_action('wp_enqueue_scripts', array($this, 'payhere_enqueue_scripts'));

            add_action('woocommerce_subscription_cancelled_' . $this->id, array($this, 'unsubscribe_from_payhere_api'));


            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options')); //update for woocommerce >2.0
            } else {
                add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options')); // WC-1.6.6
            }
            add_action('woocommerce_receipt_payhere', array(&$this, 'receipt_page'));
        }

        function modify_gateway_icon_css($icon_html, $payment_gateway_id)
        {
            if ($payment_gateway_id != $this->id) {
                return $icon_html;
            }

            $new_css = 'class="ph-logo-style" src';
            $icon_html = preg_replace('/(src){1}/', $new_css, $icon_html);

            return $icon_html;
        }

        function get_payhere_live_url()
        {
            return 'https://www.payhere.lk/pay/checkout';
        }

        function get_payhere_sandbox_url()
        {
            return 'https://sandbox.payhere.lk/pay/checkout';
        }

        /**
         * Initiate Form Fields in the Admin Backend
         **/
        function init_form_fields()
        {

            $this->form_fields = array(
                // Activate the Gateway
                'enabled' => array(
                    'title' => __('Enable/Disable', 'woo_payhere'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayHere', 'woo_payhere'),
                    'default' => 'yes',
                    'description' => 'Show in the Payment List as a payment option'
                ),
                // Title as displayed on Frontend
                'title' => array(
                    'title' => __('Title', 'woo_payhere'),
                    'type' => 'text',
                    'default' => __('PayHere', 'woo_payhere'),
                    'description' => __('This controls the title which the user sees during checkout.', 'woo_payhere'),
                    'desc_tip' => true
                ),
                // Description as displayed on Frontend
                'description' => array(
                    'title' => __('Description:', 'woo_payhere'),
                    'type' => 'textarea',
                    'default' => __('Pay by Visa, MasterCard, AMEX, eZcash, mCash or Internet Banking via PayHere.', 'woo_payhere'),
                    'description' => __('This controls the description which the user sees during checkout.', 'woo_payhere'),
                    'desc_tip' => true
                ),
                // LIVE Key-ID
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'woo_payhere'),
                    'type' => 'text',
                    'description' => __('Your PayHere Merchant ID'),
                    'desc_tip' => true
                ),
                // LIVE Key-Secret
                'secret' => array(
                    'title' => __('Secret Key', 'woo_payhere'),
                    'type' => 'text',
                    'description' => __('Secret word you set in your PayHere Account'),
                    'desc_tip' => true
                ),// Business App ID
                'app_id' => array(
                    'title' => __('App ID', 'woo_payhere'),
                    'type' => 'text',
                    'description' => __('Your PayHere Business App ID <a target="_blank" href="https://support.payhere.lk/api-&-mobile-sdk/payhere-subscription#1-create-a-business-app">More Info</a>'),
                    'desc_tip' => false
                ),
                // Business App Secret
                'app_secret' => array(
                    'title' => __('App Secret', 'woo_payhere'),
                    'type' => 'text',
                    'description' => __('Your PayHere Business App Secret'),
                    'desc_tip' => true
                ),
                // Mode of Transaction
                'test_mode' => array(
                    'title' => __('Sandbox Mode', 'woo_payhere'),
                    'type' => 'checkbox',
                    'label' => __('Enable Sandbox Mode', 'woo_payhere'),
                    'default' => 'yes',
                    'description' => __('PayHere sandbox can be used to test payments', 'woo_payhere'),
                    'desc_tip' => true
                ),
                // Onsite checkout
                'onsite_checkout' => array(
                    'title' => __('Onsite checkout', 'woo_payhere'),
                    'type' => 'checkbox',
                    'label' => __('Enable On-site Checkout', 'woo_payhere'),
                    'default' => 'no',
                    'description' => __('Enable to let customers checkout with PayHere without leaving your site', 'woo_payhere'),
                    'desc_tip' => true
                ),
                // Page for Redirecting after Transaction
                'redirect_page' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this->payhere_get_pages('Select Page'),
                    'description' => __('Page to redirect the customer after payment', 'woo_payhere'),
                    'desc_tip' => true
                )
            );
        }

        public function payhere_enqueue_admin_scripts($hook)
        {
            $script_name = '/assets/js/payhere_admin.js';
            $script_dir_url = plugin_dir_url(__FILE__) . $script_name;
            $script_dir_path = plugin_dir_path(__FILE__) . $script_name;
            $mod_time = filemtime($script_dir_path);

            wp_enqueue_script('payhere-admin', $script_dir_url, ['jquery'], $mod_time);
        }

        public function payhere_enqueue_scripts($hook)
        {
            $style_name = 'assets/css/style.css';
            $style_dir_url = plugin_dir_url(__FILE__) . $style_name;
            $style_dir_path = plugin_dir_path(__FILE__) . $style_name;
            $mod_time = filemtime($style_dir_path);

            wp_enqueue_style('payhere', $style_dir_url, array(), $mod_time);
        }

        /**
         * Admin Panel Options
         * - Show info on Admin Backend
         **/
        public function admin_options()
        {
            echo '<h3>' . __('PayHere', 'woo_payhere') . '</h3>';
            echo '<p>' . __('WooCommerce Payment Plugin of PayHere Payment Gateway, The Digital Payment Service Provider of Sri Lanka') . '</p>';
            echo '<table class="form-table">';
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
            echo '</table>';
        }

        /**
         * Availability Check
         *
         * You can hook into this
         */
        public function is_available()
        {
            if (parent::is_available()) {
                $_avail = apply_filters('payhere_filter_is_available', true);
                return $_avail;
            } else {
                return false;
            }
        }

        /**
         *  There are no payment fields, but we want to show the description if set.
         **/
        function payment_fields()
        {
            if ($this->description) {
                echo wpautop(wptexturize($this->description));
            }
        }

        /**
         * Receipt Page
         **/
        function receipt_page($order)
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
        function generate_payhere_form($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);

            $redirect_url = $order->get_checkout_order_received_url();

            // Redirect URL : For WooCoomerce 2.0
            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                $notify_url = add_query_arg('wc-api', get_class($this), $redirect_url);
            }

            $productinfo = "Order $order_id";

            $txnid = $order_id . '_' . date("ymds");

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
                    ($order->get_billing_address_2() != "") ? ', ' . $order->billing_address_2 : ''
                    ),
                'address1' => $order->get_billing_address_1(),
                'address2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'country' => $order->get_billing_country(),

                'order_id' => $order_id,
                'items' => $productinfo,
                'currency' => get_woocommerce_currency(),
                'amount' => ($order->get_total()),

                "delivery_firstname" => $order->get_shipping_first_name(),
                "delivery_lastname" => $order->get_shipping_last_name(),
                "delivery_address" => $order->get_shipping_address_1() . (($order->get_shipping_address_2() != null) ? ', ' . $order->get_shipping_address_2() : ''),
                "delivery_zip" => $order->get_shipping_postcode(),
                "delivery_city" => $order->get_shipping_city(),
                "delivery_country" => $order->get_shipping_country(),

                'platform' => 'woocommerce'
            );

            $products = array();
            $i = 1;
            foreach ($order->get_items() as $item) {
                $products[] = $item["name"];

                $payhere_args['item_name_' . $i] = $item["name"];
                $payhere_args['item_number_' . $i] = $item["product_id"];
                $payhere_args['amount_' . $i] = $item["line_total"] / $item["qty"];
                $payhere_args['quantity_' . $i] = $item["qty"];
                $payhere_args['tax_' . $i] = $item["line_tax"];
                $i++;
            }

            $payhere_args['items'] = implode(', ', $products);
            $payhere_args['items'] = apply_filters('payhere_filter_items', $payhere_args['items'], $order_id);

            if (apply_filters('payhere_filter_hash_verification_required', true, $order_id, $effective_merchant_id)) {

                /* Frontend Hash */
                $frontend_hash = $this->generate_hash(
                    $effective_merchant_id,
                    $order_id,
                    $order->get_total(),
                    get_woocommerce_currency(),
                    $effective_merchant_secret
                );
                $payhere_args['hash'] = $frontend_hash;
            }

            $subscription_process_status = null;
            $subscription_err = null;
            $this->process_as_subscription_if_needed(
                $payhere_args, $subscription_process_status,
                $subscription_err,
                $order,
                $effective_merchant_secret);

            if ($subscription_process_status == self::SUB_PROCESS_STATUS_SUBSCRIPTION_ERROR) {
                $target_err_text = self::SUB_PROCESS_ERR_UNKNOWN;
                if (!empty($subscription_err)) {
                    $target_err_text = $subscription_err;
                }

                return sprintf(
                    '<ul class="woocommerce-error" role="alert"><li><b>Cannot Process Payment</b><br>%s</li></ul>',
                    $target_err_text);
            }

            if ($this->onsite_checkout_enabled) {
                $payment_obj = array();

                foreach ($payhere_args as $key => $value) {
                    $payment_obj[$key] = $value;
                }

                if ($effective_test_mode == 'yes') {
                    $payment_obj['sandbox'] = true;
                } else {
                    $payment_obj['sandbox'] = false;
                }

                return '<button type="button" class="button-alt" id="show_payhere_payment_onsite" onclick="payhere_showmodal()">' .
                    __('Pay via PayHere', 'woo_payhere') . '</button>
				<script type="text/javascript" src="https://www.payhere.lk/lib/payhere.js"></script>
				<script>

				payhere.onCompleted = function onCompleted(orderId) {
					window.location = "' . $payhere_args['return_url'] . '";
				};
			
				payhere.onError = function onError(error) {
					alert("An error occured while making the payment. Error: " + error);
				};

				function payhere_showmodal(){
					let payment = ' . json_encode($payment_obj) . ';
					payhere.startPayment(payment);
				}

				payhere_showmodal();
				</script>
				';
            } else {
                $payhere_args_array = array();
                foreach ($payhere_args as $key => $value) {
                    $payhere_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
                }

                $effective_url = "";
                if ($effective_test_mode == 'yes') {
                    $effective_url = $this->get_payhere_sandbox_url();
                } else {
                    $effective_url = $this->get_payhere_live_url();
                }

                return '<form action="' . $effective_url . '" method="post" id="payhere_payment_form">
  				' . implode('', $payhere_args_array) . '
				<input type="submit" class="button-alt" id="submit_payhere_payment_form" value="' . __('Pay via PayHere', 'woo_payhere') . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Cancel order &amp; restore cart', 'woo_payhere') . '</a>
					<script type="text/javascript">
					jQuery(function(){
					jQuery("body").block({
						message: "' . __('Thanks for your order! We are now redirecting you to PayHere Payment Gateway to make the payment.', 'woo_payhere') . '",
						overlayCSS: {
							background		: "#fff",
							opacity			: 0.8
						},
						css: {
							padding			: 20,
							textAlign		: "center",
							color			: "#333",
							border			: "1px solid #eee",
							backgroundColor	: "#fff",
							cursor			: "wait",
							lineHeight		: "32px"
						}
					});
					jQuery("#submit_payhere_payment_form").click();});
					</script>
				</form>';
            }

        }

        private function generate_hash($merchant_id, $order_id, $amount, $currency, $merchant_secret)
        {
            $pre_hash = '';
            $pre_hash .= $merchant_id;
            $pre_hash .= $order_id;
            $pre_hash .= sprintf("%.02f", round($amount, 2));
            $pre_hash .= $currency;
            $pre_hash .= strtoupper(md5($merchant_secret));

            return strtoupper(md5($pre_hash));
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {
            global $woocommerce;
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
                    $order->id,
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
        function check_payhere_response()
        {
            global $woocommerce;
            $is_subscription = !empty($_POST['subscription_id']);

            if (isset($_REQUEST['order_id']) && isset($_REQUEST['payment_id'])) {
                $order_id = $_REQUEST['order_id'];
                if ($order_id != '') {
                    try {
                        $order = new WC_Order($order_id);
                        $md5sig = $_REQUEST['md5sig'];
                        $status = $_REQUEST['status_code'];

                        $verified = true;
                        $verification_required = apply_filters('payhere_filter_verification_required', true, $_REQUEST['order_id'], $_REQUEST['merchant_id']);
                        if ($verification_required) {
                            $effective_merchant_secret = apply_filters('payhere_filter_merchant_secret', $this->secret, $_REQUEST['order_id'], $_REQUEST['merchant_id']);
                            if ($effective_merchant_secret) {
                                $hash = $_REQUEST['merchant_id'];
                                $hash .= $_REQUEST['order_id'];
                                $hash .= $_REQUEST['payhere_amount'];
                                $hash .= $_REQUEST['payhere_currency'];
                                $hash .= $_REQUEST['status_code'];
                                $hash .= strtoupper(md5($effective_merchant_secret));
                                $md5hash = strtoupper(md5($hash));
                                $md5sig = $_REQUEST['md5sig'];

                                if (($md5hash != $md5sig) || ($_REQUEST['payhere_amount'] != ($order->order_total) && !$is_subscription)) {
                                    $verified = false;
                                }
                            }
                        }
                        $trans_authorised = false;

                        if (($order->status !== 'completed' && !$is_subscription) || ($is_subscription)) {
                            if ($verified) {
                                $status = strtolower($status);

                                if ($status == "2") {
                                    $trans_authorised = true;
                                    $this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
                                    $this->msg['class'] = 'woocommerce-message';

                                    if ($order->status == 'processing') {
                                        $order->add_order_note('PayHere Payment ID: ' . $_REQUEST['payment_id']);
                                    } else {
                                        $order->payment_complete();
                                        $order->add_order_note('PayHere payment successful.<br/>PayHere Payment ID: ' . $_REQUEST['payment_id']);

                                        if ($is_subscription) {
                                            $order->add_order_note('PayHere Subscription ID: ' . $_REQUEST['subscription_id']);
                                            $order->update_meta_data('_payhere_subscription_id', $_REQUEST['subscription_id']);
                                            $order->save();
                                        }

                                        $woocommerce->cart->empty_csart();
                                    }

                                    if ($is_subscription) {
                                        WC_Subscriptions_Manager::process_subscription_payments_on_order($order);
                                    }
                                } else if ($status == "0") {
                                    $trans_authorised = true;
                                    $this->msg['message'] = "Thank you for shopping with us. Right now your payment status is pending. We will keep you posted regarding the status of your order through eMail";
                                    $this->msg['class'] = 'woocommerce-info';
                                    $order->add_order_note('PayHere payment status is pending<br/>PayHere Payment ID: ' . $_REQUEST['payment_id']);
                                    $order->update_status('on-hold');
                                    $woocommerce->cart->empty_cart();

                                    if ($is_subscription) {
                                        WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($order);
                                    }
                                } else {
                                    $this->msg['class'] = 'woocommerce-error';
                                    $this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                    $order->add_order_note('Transaction ERROR. Status Code: ' . $status);
                                }

                                if ($is_subscription) {
                                    $order->add_order_note(sprintf(
                                        'Subscription Message = %s, Subscription Status = %s, Next Recurring Date %s',
                                        $_REQUEST['message_type'],
                                        $_REQUEST['item_rec_status'],
                                        $_REQUEST['item_rec_date_next']
                                    ));
                                }
                            } else {
                                $this->msg['class'] = 'error';
                                $this->msg['message'] = "Security Error. Illegal access detected.";
                                $order->add_order_note('Checksum ERROR: ' . json_encode($_REQUEST));
                            }
                            if ($trans_authorised == false) {
                                $order->update_status('failed');
                            }
                        }
                    } catch (Exception $e) {
                        // $errorOccurred = true;
                        $msg = "Error";
                    }
                }

                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    if ($this->redirect_page == '' || $this->redirect_page == 0) {
                        $redirect_url = get_permalink(get_option('woocommerce_myaccount_page_id'));
                    } else {
                        $redirect_url = get_permalink($this->redirect_page);
                    }

                    wp_redirect($redirect_url);
                }

            }

        }

        /**
         * Get Page list from WordPress
         **/
        function payhere_get_pages($title = false, $indent = true)
        {
            $wp_pages = get_pages('sort_column=menu_order');
            $page_list = array();
            if ($title) $page_list[] = $title;
            foreach ($wp_pages as $page) {
                $prefix = '';
                // show indented child pages?
                if ($indent) {
                    $has_parent = $page->post_parent;
                    while ($has_parent) {
                        $prefix .= ' - ';
                        $next_page = get_post($has_parent);
                        $has_parent = $next_page->post_parent;
                    }
                }
                // add to page list array array
                $page_list[$page->ID] = $prefix . $page->post_title;
            }
            return $page_list;
        }

        /**
         * Modify the PayHere Arguments and notify whether
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

            if (wcs_order_contains_subscription($order)) {
                $process_status = self::SUB_PROCESS_STATUS_SUBSCRIPTION_ERROR;
            } else {
                $process_status = self::SUB_PROCESS_STATUS_NOT_SUBSCRIPTION;
                return;
            }

            $subscriptions = wcs_get_subscriptions_for_order($order);
            $supported_periods = array('week', 'year', 'month');

            if (count($subscriptions) > 1) {
                $process_status = self::SUB_PROCESS_ERR_MULT_SUBS;
                return;
            }

            // We only support one subscription per order.
            $subscription = $subscriptions[array_keys($subscriptions)[0]];

            $sub_initial_payment = $subscription->get_total_initial_payment();
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

            $contains_mixed_products = false;
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
            if ($sub_trial_length > 0) {
                $startup_fee -= $sub_price_per_period;
            }

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
            $payhere_args['amount'] = $amount;

            if (isset($payhere_args['hash'])) {
                $payhere_args['hash'] = $this->generate_hash(
                    $payhere_args['merchant_id'],
                    $order->get_id(),
                    $amount + $startup_fee,
                    $payhere_args['currency'],
                    $effective_merchant_secret
                );
            }

            $process_status = self::SUB_PROCESS_STATUS_SUBSCRIPTION_OK;

        }

        private function convert_timestamp_readable($ts)
        {
            $dt = new DateTime("@$ts");
            $format = 'Y-m-d H:i:s e';
            return $dt->format($format);
        }

        function unsubscribe_from_payhere_api($subscription)
        {

            $effective_merchant_id = apply_filters('payhere_filter_merchant_id', $this->merchant_id);
            $effective_app_id = apply_filters('payhere_filter_app_id', $this->app_id, $effective_merchant_id);
            $effective_app_secret = apply_filters('payhere_filter_app_secret', $this->app_secret, $effective_merchant_id);
            $effective_test_mode = apply_filters('payhere_filter_test_mode', $this->settings['test_mode'], $effective_merchant_id);

            $token = _payhere_getAuthorizationToken($effective_app_id, $effective_app_secret, $effective_test_mode);

            $url = 'https://www.payhere.lk/merchant/v1/subscription/cancel';
            if ($effective_test_mode == 'yes') {
                $url = 'https://sandbox.payhere.lk/merchant/v1/subscription/cancel';
            }

            $parent_order_id = $subscription->get_related_orders('ids', 'parent');
            $order = wc_get_order(($parent_order_id));

            $subscription_id = $order->get_meta('_payhere_subscription_id');

            if ($subscription_id) {

                $headers = array(
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json'
                );
                $fields = array('subscription_id' => $subscription_id);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, (json_encode($fields)));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                $head = curl_exec($ch);
                curl_close($ch);

                if ($head) {
                    $respond_data = json_decode($head);
                    write_log('subscription/cancel', array('respond' => $respond_data));
                    return true;
                }
                return false;
            } else {
                write_log('subscription/cancel', array('' => 'Subscription ID Not Found'));
            }

        }

    }

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_gateway_payhere_gateway($methods)
    {
        $methods[] = 'WC_Gateway_PayHere';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_payhere_gateway');

}

/**
 * 'Settings' link on plugin page
 **/
add_filter('plugin_action_links', 'payhere_add_action_plugin', 10, 5);
function payhere_add_action_plugin($actions, $plugin_file)
{
    static $plugin;

    if (!isset($plugin))
        $plugin = plugin_basename(__FILE__);
    if ($plugin == $plugin_file) {
        $settings = array('settings' => '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payhere">' . __('Settings') . '</a>');
        $actions = array_merge($settings, $actions);
    }

    return $actions;
}