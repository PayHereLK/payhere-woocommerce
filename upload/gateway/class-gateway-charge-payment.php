<?php

class ChargePayment extends PayHereToken
{

    private function submitCharge($token, $customer_token, $order_id, $amount)
    {

        $url = $this->get_payhere_chargin_api_url();

        $fields = array(
            "type"              => "PAYMENT",
            'order_id'          => 'WC_' . $order_id,
            'items'             => 'Woocommerce  Order :' . $order_id,
            'currency'          => get_woocommerce_currency(),
            'amount'            => $amount,
            'customer_token'    => $customer_token,
            'custom_1'          => '',
            'custom_2'          => '',
            'itemList'          => [],
        );

        $args = array(
            'body'        => json_encode($fields),
            'timeout'     => '10',
            'redirection' => '1',
            'httpversion' => '2.0',
            'blocking'    => true,
            'headers'     => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ),
            'cookies'     => array(),
            'data_format' => 'body',
        );

        $this->gateway_util->_log('chargin_ARGS', $args);

        $res = wp_remote_post($url, $args);

        if ($res instanceof WP_Error) {
            return false;
        }

        return $res['body'];
    }


    public function charge_payment(WC_Order $order, $token)
    {

        $json = [];

        $_auth_token_data = $this->getAuthorizationToken();
        $auth_token_data = json_decode($_auth_token_data);
        $this->gateway_util->_log('authorization_token', $_auth_token_data);


        if (isset($auth_token_data->access_token) && !empty($auth_token_data->access_token)) {
            $this->gateway_util->_log('ORDER', $order->get_id());
            $_charge_response = $this->submitCharge(
                $auth_token_data->access_token,
                $token,
                $order->get_id(),
                $order->get_total()
            );


            $this->gateway_util->_log('charge_response', $_charge_response);
            $charge_response = json_decode($_charge_response);
            if ($charge_response->status == '1') {

                if ($charge_response->data->status_code == '2') {
                    $order->payment_complete();
                    $order->add_order_note($charge_response->msg);
                    $order->add_order_note('PayHere payment successful.<br/>PayHere Payment ID: ' . $charge_response->data->payment_id);

                    $json['type'] = 'OK';
                    $json['message'] = 'Payment Charged Successfully.';
                } else {
                    $json['type'] = 'ERR';
                    $json['message'] = 'Payment Un-Successful. Code : ' . $charge_response->data->status_code;
                }
            } else {
                $json['type'] = 'ERR';
                $json['message'] = 'Can\'t make the payment. Payment Charge Request Failed.<br/>' . $charge_response->msg;
            }
        } else {
            $json['type'] = 'ERR';
            $json['message'] = 'Can\'t make the payment. Can\'t Generate the Authorization Tokens.';
        }

        return $json;
    }
}
