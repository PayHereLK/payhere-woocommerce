<?php
function payhere_add_saved_card_endpoint()
{
    add_rewrite_endpoint('saved-cards', EP_ROOT | EP_PAGES);
}

add_action('init', 'payhere_add_saved_card_endpoint');

function payhere_saved_cards_query_vars($vars)
{
    $vars[] = 'saved-cards';
    return $vars;
}

add_filter('query_vars', 'payhere_saved_cards_query_vars', 0);

function payhere_add_saved_card_link_my_account($items)
{
    $customer_token = get_user_meta(get_current_user_id(), 'payhere_customer_token', true);
    if (!empty($customer_token)) {
        $items['saved-cards'] = 'Saved Cards';
    }
    return $items;
}

add_filter('woocommerce_account_menu_items', 'payhere_add_saved_card_link_my_account');


function payhere_saved_card_content()
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

add_action('woocommerce_account_saved-cards_endpoint', 'payhere_saved_card_content');