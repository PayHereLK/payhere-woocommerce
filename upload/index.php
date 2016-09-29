<?php
/*
Plugin Name: WooCommerce PayHere Payment Gateway
Plugin URI: https://www.payhere.lk
Description: PayHere Payment Gateway allows you to accept payment on your Woocommerce store via Visa, MasterCard, AMEX, eZcash, mCash & Internet banking services.
Version: 1.0.0
Author: PayHere (Private) Limited
Author URI: https://www.payhere.lk
*/

add_action('plugins_loaded', 'woocommerce_gateway_payhere_init', 0);
define('payhere_IMG', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_gateway_payhere_init() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Gateway class
 	 */
	class WC_Gateway_PayHere extends WC_Payment_Gateway {

	     /**
         * Make __construct()
         **/	
		public function __construct(){
			
			$this->id 					= 'payhere'; // ID for WC to associate the gateway values
            $this -> icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__ )) . '/assets/img/logo_.png';
			$this->method_title 		= 'PayHere'; // Gateway Title as seen in Admin Dashboad
			$this->method_description	= 'The Digital Payment Service Provider of Sri Lanka'; // Gateway Description as seen in Admin Dashboad
			$this->has_fields 			= false; // Inform WC if any fileds have to be displayed to the visitor in Frontend 
			
			$this->init_form_fields();	// defines your settings to WC
			$this->init_settings();		// loads the Gateway settings into variables for WC
						
            $this->liveurl 			= 'https://www.payhere.lk/pay/checkout';
			// Special settigns if gateway is on Test Mode
			$test_title			= '';	
			$test_description	= '';
			if ( $this->settings['test_mode'] == 'yes' ) {
				$test_title 		= '';
				$test_description 	= '<br/><br/>(Sandbox Mode is Active. You will not be charged.)<br/>';	
                $this->liveurl 			= 'https://sandbox.payhere.lk/pay/checkout';
			} //END--test_mode=yes

			$this->title 			= $this->settings['title'].$test_title; // Title as displayed on Frontend
			$this->description 		= $this->settings['description'].$test_description; // Description as displayed on Frontend
			if ( $this->settings['show_logo'] != "no" ) { // Check if Show-Logo has been allowed
				$this->icon 		= payhere_IMG . 'logo_' . $this->settings['show_logo'] . '.png';
			}
            $this->merchant_id 		= $this->settings['merchant_id'];
            $this->secret 		    = $this->settings['secret'];
			$this->redirect_page	= $this->settings['redirect_page']; // Define the Redirect Page.
			$this->service_provider	= $this->settings['service_provider']; // The Service options for PayHere.
			
            $this->msg['message']	= '';
            $this->msg['class'] 	= '';
			
			add_action('init', array(&$this, 'check_payhere_response'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_payhere_response')); //update for woocommerce >2.0

            if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); //update for woocommerce >2.0
                 } else {
                    add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) ); // WC-1.6.6
                }
            add_action('woocommerce_receipt_payhere', array(&$this, 'receipt_page'));	
		} //END-__construct
		
        /**
         * Initiate Form Fields in the Admin Backend
         **/
		function init_form_fields(){

			$this->form_fields = array(
				// Activate the Gateway
				'enabled' => array(
					'title' 			=> __('Enable/Disable', 'woo_payhere'),
					'type' 			=> 'checkbox',
					'label' 			=> __('Enable PayHere', 'woo_payhere'),
					'default' 		=> 'yes',
					'description' 	=> 'Show in the Payment List as a payment option'
				),
				// Title as displayed on Frontend
      			'title' => array(
					'title' 			=> __('Title', 'woo_payhere'),
					'type'			=> 'text',
					'default' 		=> __('PayHere', 'woo_payhere'),
					'description' 	=> __('This controls the title which the user sees during checkout.', 'woo_payhere'),
					'desc_tip' 		=> true
				),
				// Description as displayed on Frontend
      			'description' => array(
					'title' 			=> __('Description:', 'woo_payhere'),
					'type' 			=> 'textarea',
					'default' 		=> __('Pay by Visa, MasterCard, AMEX, eZcash, mCash or Internet Banking via PayHere.', 'woo_payhere'),
					'description' 	=> __('This controls the description which the user sees during checkout.', 'woo_payhere'),
					'desc_tip' 		=> true
				),
				// LIVE Key-ID
      			'merchant_id' => array(
					'title' 		=> __('Merchant ID', 'woo_payhere'),
					'type' 			=> 'text',
					'description' 	=> __('Your PayHere Merchant ID'),
					'desc_tip' 		=> true
				),
  				// LIVE Key-Secret
    			'secret' => array(
					'title' 			=> __('Secret', 'woo_payhere'),
					'type' 			=> 'text',
					'description' 	=> __('Secret word you set in your PayHere Account'),
					'desc_tip' 		=> true
                ),
  				// Mode of Transaction
      			'test_mode' => array(
                    'title'         => __('Sandbox Mode', 'woo_payhere'),
                    'type'          => 'checkbox',
                    'label'         => __('Enable Sandbox Mode', 'woo_payhere'),
                    'default'       => 'yes',
                    'description'   => __('PayHere sandbox can be used to test payments', 'woo_payhere'),
                    'desc_tip' 		=> true
                ),
  				// Page for Redirecting after Transaction
      			'redirect_page' => array(
					'title' 			=> __('Return Page'),
					'type' 			=> 'select',
					'options' 		=> $this->payhere_get_pages('Select Page'),
					'description' 	=> __('Page to redirect the customer after payment', 'woo_payhere'),
					'desc_tip' 		=> true
                )
			);

		} //END-init_form_fields
		
        /**
         * Admin Panel Options
         * - Show info on Admin Backend
         **/
		public function admin_options(){
			echo '<h3>'.__('PayHere', 'woo_payhere').'</h3>';
			echo '<p>'.__('WooCommerce Payment Plugin of PayHere Payment Gateway, The Digital Payment Service Provider of Sri Lanka').'</p>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			echo '</table>';
		} //END-admin_options

        /**
         *  There are no payment fields, but we want to show the description if set.
         **/
		function payment_fields(){
			if( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		} //END-payment_fields
		
        /**
         * Receipt Page
         **/
		function receipt_page($order){
			echo '<p><strong>' . __('Thank you for your order.', 'woo_payhere').'</strong><br/>' . __('The payment page will open soon.', 'woo_payhere').'</p>';
			echo $this->generate_payhere_form($order);
		} //END-receipt_page
    
        /**
         * Generate button link
         **/
		function generate_payhere_form($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );

			// Redirect URL
			if ( $this->redirect_page == '' || $this->redirect_page == 0 ) {
				$redirect_url = get_site_url() . "/";
			} else {
				$redirect_url = get_permalink( $this->redirect_page );
			}
			// Redirect URL : For WooCoomerce 2.0
			if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				$notify_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
			}

            $productinfo = "Order $order_id";

			$txnid = $order_id.'_'.date("ymds");
			
			$payhere_args = array(
				'merchant_id' => $this->merchant_id,
                'return_url' => $redirect_url,
                'cancel_url' => $redirect_url,
                'notify_url' => $notify_url,

                'first_name' => $order -> billing_first_name,
                'last_name' => $order -> billing_last_name,
                'email' => $order -> billing_email,
                'phone' => $order -> billing_phone,
                'address' => $order->billing_address_1 . (($order->billing_address_2 != "") ? ', ' . $order->billing_address_2 : ''),
                'address1' => $order -> billing_address_1,
                'address2' => $order -> billing_address_2,
                'city' => $order -> billing_city,
                'state' => $order -> billing_state,
                'country' => $order -> billing_country,
                'zip' => $order -> billing_zip,

                'order_id' => $order_id,
                'items' => $productinfo,
                'currency' => get_woocommerce_currency(),
                'amount' => ($order -> order_total),

                "delivery_firstname" => $order->shipping_first_name,
                "delivery_lastname" => $order->shipping_last_name,
                "delivery_address" => $order->shipping_address_1 . (($order->shipping_address_2 != null) ? ', '.$order->shipping_address_2 : ''),
                "delivery_zip" => $order->shipping_postcode,
                "delivery_city" => $order->shipping_city,
                "delivery_country" => $order->shipping_country,

                'platform' => 'woocommerce'
			);
            
            $products = array();
            $i=1;
            foreach ($order->get_items() as $item) {
                $products[] = $item["name"];
                
                $payhere_args['item_name_'.$i] = $item["name"];
                $payhere_args['item_number_'.$i] = $item["product_id"];
                $payhere_args['amount_'.$i] = $item["line_total"]/$item["qty"];
                $payhere_args['quantity_'.$i] = $item["qty"];
                $payhere_args['tax_'.$i] = $item["line_tax"];
                $i++;
            }
            
            $payhere_args['items'] = implode( ', ', $products);
            
            
			$payhere_args_array = array();
			foreach($payhere_args as $key => $value){
				$payhere_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
			}
			
			return '	<form action="'.$this->liveurl.'" method="post" id="payhere_payment_form">
  				' . implode('', $payhere_args_array) . '
				<input type="submit" class="button-alt" id="submit_payhere_payment_form" value="'.__('Pay via PayHere', 'woo_payhere').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'woo_payhere').'</a>
					<script type="text/javascript">
					jQuery(function(){
					jQuery("body").block({
						message: "'.__('Thanks for your order! We\'re now redirecting you to PayHere Payment Gateway to make the payment.', 'woo_payhere').'",
						overlayCSS: {
							background		: "#fff",
							opacity			: 0.8
						},
						css: {
							padding			: 20,
							textAlign		: "center",
							color			: "#333",
							border			: "3px solid #eee",
							backgroundColor	: "#fff",
							cursor			: "wait",
							lineHeight		: "32px"
						}
					});
					jQuery("#submit_payhere_payment_form").click();});
					</script>
				</form>';		
		
		} //END-generate_payhere_form

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
			global $woocommerce;
            $order = new WC_Order($order_id);
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) { // For WC 2.1.0
			  	$checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
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
		} //END-process_payment

        /**
         * Check for valid gateway server callback
         **/
        function check_payhere_response(){
           global $woocommerce;

			if( isset($_REQUEST['order_id']) && isset($_REQUEST['payment_id']) ){
				$order_id = $_REQUEST['order_id'];
				if($order_id != ''){
					try{
						$order = new WC_Order( $order_id );
						$md5sig = $_REQUEST['md5sig'];
						$status = $_REQUEST['status_code'];
                        
                        $verified = true;
                        if ($this->secret) {
                            $hash  = $_REQUEST['merchant_id'];
                            $hash .= $_REQUEST['order_id'];
                            $hash .= $_REQUEST['payhere_amount'];
                            $hash .= $_REQUEST['payhere_currency'];
                            $hash .= $_REQUEST['status_code'];
                            $hash .= strtoupper(md5($this->secret));
                            $md5hash = strtoupper(md5($hash));
                            $md5sig = $_REQUEST['md5sig'];

                            if (($md5hash != $md5sig) || (strtolower($_REQUEST['merchant_id']) != strtolower($this->merchant_id))
                                || $_REQUEST['payhere_amount'] != ($order -> order_total)
                                || $_REQUEST['payhere_currency'] != get_woocommerce_currency() ) {
                                $verified = false;
                            }
                        } 
						
                        $trans_authorised = false;
						
						if( $order->status !=='completed' ){
							if($verified){
								$status = strtolower($status);
								if($status=="2"){
									$trans_authorised = true;
									$this->msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful.";
									$this->msg['class'] = 'woocommerce-message';
									if($order->status == 'processing'){
										$order->add_order_note('PayHere Payment ID: '.$_REQUEST['payment_id']);
									}else{
										$order->payment_complete();
										$order->add_order_note('PayHere payment successful.<br/>PayHere Payment ID: '.$_REQUEST['payment_id']);
										$woocommerce->cart->empty_cart();
									}
								}else if($status=="0"){
									$trans_authorised = true;
									$this->msg['message'] = "Thank you for shopping with us. Right now your payment status is pending. We will keep you posted regarding the status of your order through eMail";
									$this->msg['class'] = 'woocommerce-info';
									$order->add_order_note('PayHere payment status is pending<br/>PayHere Payment ID: '.$_REQUEST['payment_id']);
									$order->update_status('on-hold');
									$woocommerce -> cart -> empty_cart();
								}else{
									$this->msg['class'] = 'woocommerce-error';
									$this->msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
									$order->add_order_note('Transaction ERROR. Status Code: '. $status);
								}
							}else{
								$this->msg['class'] = 'error';
								$this->msg['message'] = "Security Error. Illegal access detected.";
								$order->add_order_note('Checksum ERROR: '.json_encode($_REQUEST));
							}
							if($trans_authorised==false){
								$order->update_status('failed');
							}
//							//removed for WooCommerce 2.0
//							//add_action('the_content', array(&$this, 'payherepaisa_showMessage'));
						}
					}catch(Exception $e){
                        // $errorOccurred = true;
                        $msg = "Error";
					}
				}

				if ( $this->redirect_page == '' || $this->redirect_page == 0 ) {
					//$redirect_url = $order->get_checkout_payment_url( true );
					$redirect_url = get_permalink( get_option('woocommerce_myaccount_page_id') );
				} else {
					$redirect_url = get_permalink( $this->redirect_page );
				}
				
				wp_redirect( $redirect_url );
                exit;
	
			}

        } //END-check_payhere_response

        /**
         * Get Page list from WordPress
         **/
		function payhere_get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
                	$has_parent = $page->post_parent;
                	while($has_parent) {
                    	$prefix .=  ' - ';
                    	$next_page = get_post($has_parent);
                    	$has_parent = $next_page->post_parent;
                	}
            	}
            	// add to page list array array
            	$page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
		} //END-payhere_get_pages

	} //END-class
	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_gateway_payhere_gateway($methods) {
		$methods[] = 'WC_Gateway_PayHere';
		return $methods;
	}//END-wc_add_gateway
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_payhere_gateway' );
	
} //END-init

/**
* 'Settings' link on plugin page
**/
add_filter( 'plugin_action_links', 'payhere_add_action_plugin', 10, 5 );
function payhere_add_action_plugin( $actions, $plugin_file ) {
	static $plugin;

	if (!isset($plugin))
		$plugin = plugin_basename(__FILE__);
	if ($plugin == $plugin_file) {

			$settings = array('settings' => '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payhere">' . __('Settings') . '</a>');
		
    			$actions = array_merge($settings, $actions);
			
		}
		
		return $actions;
}//END-settings_add_action_link