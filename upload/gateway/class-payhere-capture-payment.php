<?php

class PayHereCapturePayment extends PayHereToken
{

    /**
     * Caputure payment for Authorized Payments
     * @param $token payhere auth token
     * @param $authorize_token  payment authorize token
     * @param $order_id woocommerce order id
     * @param $amount amount to capture
     */
    private function submit_capture_payment($_token, $_authorize_token, $_order_id, $_amount)
    {

        $token = sanitize_text_field($_token);
        $authorize_token = sanitize_text_field($_authorize_token);
        $order_id = sanitize_text_field($_order_id);
        $amount = sanitize_text_field($_amount);


        $this->gateway_util->_log('CAPTURE', array($token, $authorize_token, $order_id, $amount));
        $url = $this->get_payhere_capture_api_url();

        $this->gateway_util->_log('CAPTURE', $url);

        $fields = array(
            'deduction_details' => 'Capture Payment for Order : #' . $order_id,
            'amount' => $amount,
            'authorization_token' => $authorize_token,
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


    /**
     * @param WC_Order $order Order for capture payment
     * @param $token PayHere payment authorize token
     * @param $amount Amount to  capture
     * @return array Status array message with type and message
     */
    public function capture_payment_payhere(WC_Order $order, $_token, $_amount)
    {
        $json = [];
        $token = sanitize_text_field($_token);
        $amount = sanitize_text_field($_amount);

        $_auth_token_data = $this->getAuthorizationToken();
        $this->gateway_util->_log('authorization_token', $_auth_token_data);
        $auth_token_data = json_decode($_auth_token_data);

        if (isset($auth_token_data->access_token) && !empty($auth_token_data->access_token)) {

            $this->gateway_util->_log('INFO', "Trying to capture");
            $_capture_response = $this->submit_capture_payment($auth_token_data->access_token, $token, $order->get_id(), $amount);
            $this->gateway_util->_log('capture_response', $_capture_response);

            $capture_response = json_decode($_capture_response);

            if ($capture_response->status == '1') {

                if ($capture_response->data->status_code == '2') {
                    $order->set_status('processing');
                    $order->payment_complete(sanitize_text_field($capture_response->data->payment_id));
                    $order->add_meta_data('payhere_acpture_date', date("g:ia \o\n l jS F Y"));
                    $order->add_meta_data('payhere_acpture_amount', sanitize_text_field($capture_response->data->captured_amount));
                    $order->add_order_note(sanitize_text_field($capture_response->msg));
                    $order->add_order_note(sanitize_text_field($capture_response->data->status_message));
                    $order->save();

                    $json['type'] = 'OK';
                    $json['message'] = 'Payment Captured Successfully.';
                } else {
                    $json['type'] = 'ERR';
                    $json['message'] = 'Payment Un-Successful. Code : ' . esc_html($capture_response->data->status_code);
                }
            } else {
                $json['type'] = 'ERR';
                $_msg = isset($capture_response->msg) ? $capture_response->msg : $capture_response->error_description;
                $json['message'] = 'Can\'t make the payment. Payment Capture Request Failed.<br/>' . esc_html($_msg);
            }
        } else {
            $json['type'] = 'ERR';
            $json['message'] = 'Can\'t make the payment. Can\'t Generate the Authorization Tokens.';
        }

        return $json;
    }
}
