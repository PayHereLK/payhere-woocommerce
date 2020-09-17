<?php

function restrict_user_actions($action_link, $subscription)
{
    if ($subscription->get_payment_method() == 'payhere') {
        unset($action_link['resubscribe']);
        unset($action_link['reactivate']);
        unset($action_link['suspend']);
    }
    return $action_link;
}

add_filter('wcs_view_subscription_actions', 'restrict_user_actions', 10, 2);

function payhere_user_has_capability($allcaps, $caps, $args){
    if ( isset( $caps[0] ) ) {
        switch ( $caps[0] ) {
            case 'toggle_shop_subscription_auto_renewal' :
                $user_id      = $args[1];
                $subscription = wcs_get_subscription( $args[2] );

                if ( $subscription && $user_id === $subscription->get_user_id() ) {
                    $allcaps['toggle_shop_subscription_auto_renewal'] = false;
                } else {
                    unset( $allcaps['toggle_shop_subscription_auto_renewal'] );
                }
                break;
        }
    }
    return $allcaps;
}
add_filter( 'user_has_cap', 'payhere_user_has_capability', 25, 3 );


function _payhere_getAuthorizationToken($app_id, $app_secret, $test_mode)
{
    if ($test_mode == 'yes') {
        $url = 'https://sandbox.payhere.lk/merchant/v1/oauth/token';
    } else {
        $url = 'https://www.payhere.lk/merchant/v1/oauth/token';
    }

    if (!empty($app_id) && !empty($app_secret)) {
        $bs64 = base64_encode($app_id . ':' . $app_secret);
        $headers = array(
            'Authorization: Basic ' . $bs64,
            'Content-Type: application/x-www-form-urlencoded'
        );
        $fields = array(
            'grant_type' => 'client_credentials',
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $head = curl_exec($ch);
        curl_close($ch);

        if ($head) {
            $auth_token_data = json_decode($head);
            write_log('tokenize', array('app_id' => $app_id, 'app_secret' => $app_secret, 'respond' => $auth_token_data));
            if (isset($auth_token_data->access_token) && !empty($auth_token_data->access_token)) {
                return $auth_token_data->access_token;
            }
        }
    }

    return FALSE;
}

function write_log($section, $content)
{
    $uploads = wp_upload_dir(null, false);
    $logs_dir = $uploads['basedir'] . '/payhere-logs';

    if (!is_dir($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }

    $__content = '';
    if (is_array($content) || is_object($content)) {
        $__content = json_encode(print_r($content, true));
    }
    $_content = "[" . date('Y-m-d H:i:s') . " -- $section -- $__content]";
    file_put_contents($logs_dir . '/log_' . date("j.n.Y") . '.log', [$_content . '' . PHP_EOL], FILE_APPEND);

}