<?php

class PayHereToken
{

    public $app_id;
    public $app_secret;
    public $is_sandbox;
    public $gateway_util;

    public function __construct($app_id, $app_secret, $is_sandbox)
    {
        $this->app_id = $app_id;
        $this->app_secret = $app_secret;
        $this->is_sandbox = $is_sandbox;
        $this->gateway_util = new GatewayUtilities();
    }

    /**
     * return PayHere access token URL
     * @param $is_test_mode
     * @return string
     */
    public function get_payhere_access_token_url()
    {
        if ($this->is_sandbox == 'yes') {
            return 'https://sandbox.payhere.lk/merchant/v1/oauth/token';
        }
        return 'https://www.payhere.lk/merchant/v1/oauth/token';
    }

    /**
     * return PayHere chargin API URL
     * @param $is_test_mode
     * @return string
     */
    public function get_payhere_chargin_api_url()
    {

        if ($this->is_sandbox == 'yes') {
            return 'https://sandbox.payhere.lk/merchant/v1/payment/charge';
        }
        return 'https://www.payhere.lk/merchant/v1/payment/charge';
    }
    /**
     * return PayHere capture API URL
     * @param $is_test_mode
     * @return string
     */
    public function get_payhere_capture_api_url()
    {

        if ($this->is_sandbox == 'yes') {
            return 'https://sandbox.payhere.lk/merchant/v1/payment/capture';
        }
        return 'https://www.payhere.lk/merchant/v1/payment/capture';
    }

    public function get_auth_header_token()
    {
        $bs64 = base64_encode($this->app_id . ':' . $this->app_secret);
        return array(
            'Authorization: Basic ' . $bs64,
            'Content-Type: application/x-www-form-urlencoded'
        );
    }


    /**
     * @param $app_id PayHere Business APP ID
     * @param $app_secret PayHere Business APP Secret
     * @param false $sandbox Is Sandbox Mode
     */
    public function getAuthorizationToken()
    {

        $url = $this->get_payhere_access_token_url();

        $headers = $this->get_auth_header_token();

        $fields = array('grant_type' => 'client_credentials');


        $args = array(
            'body'        => $fields,
            'timeout'     => '7',
            'redirection' => '1',
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => $headers,
            'cookies'     => array(),
        );

        $res = wp_remote_post($url, $args);

        if ($res instanceof WP_Error) {
            return false;
        }

        return $res;
    }
}
