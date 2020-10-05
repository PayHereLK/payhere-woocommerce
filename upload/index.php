<?php
/*
Plugin Name: WooCommerce PayHere Payment Gateway
Plugin URI: https://www.payhere.lk
Description: PayHere Payment Gateway allows you to accept payment on your Woocommerce store via Visa, MasterCard, AMEX, eZcash, mCash & Internet banking services.
Version: 1.0.11
Author: PayHere (Private) Limited
Author URI: https://www.payhere.lk
*/

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'subscription_restrictions_actions.php';
include 'classes/PH_Customer_List_Options.php';
include 'my-account-actions.php';

add_action('plugins_loaded', 'woocommerce_gateway_payhere_init', 0);
define('payhere_IMG', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

add_action('wp_ajax_payhere_charge', 'payhere_ajax_charge_payment');
function payhere_ajax_charge_payment()
{

    $_wc_gtway = new WC_Gateway_PayHere();

    echo json_encode(array('data' => $_wc_gtway->charge_payment()));
    exit();
}

function woocommerce_gateway_payhere_init()
{
    include 'classes/WC_Gateway_PayHere.php';
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_payhere_gateway');
}

/**
 * Add the Gateway to WooCommerce
 **/
function woocommerce_add_gateway_payhere_gateway($methods)
{
    $methods[] = 'WC_Gateway_PayHere';
    return $methods;
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

//add_action('woocommerce_edit_account_form', 'woocommerce_account_edit_address', 999);

function woocommerce_account_edit_address()
{
    $customer_token = get_user_meta(get_current_user_id(), 'payhere_customer_token', true);
    if (!empty($customer_token)) {
        $_card_info = get_user_meta(get_current_user_id(), 'payhere_customer_data', true);
        $card_info = json_decode($_card_info);
        ob_start();
        include plugin_dir_path(__FILE__) . 'templates/remove-card.php';
        echo ob_get_clean();
    }
}

add_action('wp_ajax_payhere_remove_card', 'payhere_remove_saved_card');
function payhere_remove_saved_card()
{
    delete_user_meta(get_current_user_id(), 'payhere_customer_token');
    delete_user_meta(get_current_user_id(), 'payhere_customer_data');

    echo json_encode(array('type' => 'OK', 'message' => 'Saved card removed successfully.'));
    exit();
}

//Add Menu to Woocommerce admin menu list
add_action('plugins_loaded', function () {
    PH_Customer_List_Options::get_instance();
});


