<?php
class SubscriptionRestrictions
{

    public function restrict_user_actions($action_link, $subscription)
    {
        if ($subscription->get_payment_method() == 'payhere') {
            unset($action_link['resubscribe']);
            unset($action_link['reactivate']);
            unset($action_link['suspend']);
        }
        return $action_link;
    }

    public function payhere_user_has_capability($allcaps, $caps, $args)
    {

        if (isset($caps[0]) && $caps[0] == 'toggle_shop_subscription_auto_renewal') {
            $user_id = $args[1];
            $subscription = wcs_get_subscription($args[2]);

            if ($subscription && $user_id === $subscription->get_user_id()) {
                $allcaps['toggle_shop_subscription_auto_renewal'] = false;
            } else {
                unset($allcaps['toggle_shop_subscription_auto_renewal']);
            }
        }

        return $allcaps;
    }
}
