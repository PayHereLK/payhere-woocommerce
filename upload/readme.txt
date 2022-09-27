=== Plugin Name ===
Contributors: payhere
Donate link: https://www.payhere.lk
Tags: payhere, online, payments, sri lanka
Requires at least: 3.0.1
Tested up to: 6.0.2
WC tested up to: 6.9.2
Stable tag: 2.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

PayHere Payment Gateway Plugin for WooCommerce


== Description ==

PayHere is a Sri Lankan Payment Gateway Service that enables you to accept payments online from your customers via Visa, MasterCard, Amex, eZcash, mCash & Internet Banking services. You can install this plugin to list PayHere as a payment method in your WooCommerce store.


== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the WooCommerce->Settings->Checkout->PayHere screen to configure the plugin with your PayHere Merchant Account
4. Make sure you tick the Sandbox Mode checkbox if you want to test the plugin with your PayHere Sandbox account


== Changelog ==

= 1.0.0 =
Initial public release

= 1.0.1 =
Fixed PayHere logo not showing issue

= 1.0.2 =
Fixed redirecting issue

= 1.0.3 =
Fixed Return URL issue in latest WP versions

= 1.0.4 =
Added support for On-site checkout

= 1.0.5 =
Fixed issue with On-site checkout in live mode

= 1.0.6 =
Fixed various issues

= 1.0.7 =
Fixed various issues

= 1.0.8 =
Bug fixes
WooCommerce Subscription Support

= 1.0.9 =
Bug fixes

= 1.0.10 =
Added support for Tokenized Payments

= 1.0.11 =
Added support for delete tokenized card payments

= 1.0.12 =
Added option to show/hide save card in frontend.

= 1.0.13 =
Fixed not Empty cart issue.

= 1.0.14 =
Fixed not auto redirecting issue on checkout page.

= 1.0.15 =
Fixed if woocommerce not active error plugin breaks and cannot login to the site.

= 1.0.16 =
Fixes bug caused by WordPress optimization, where Payment starts before PayHere.js is loaded.

= 1.0.17 =
Added better support for orders with many items.

= 1.0.18 =
Added in-code order blacklisting capabilities.

= 1.0.19 =
Small bug fix for Tokenize payments.

= 1.0.20 =
Remove Auto Trigger authorize payment if Card is already tokenized.

= 2.0.0 =
Hold on card support with small bug fixes.

= 2.0.1 =
Small bug fix for invalid amount format.

= 2.0.2 =
Small bug fix and add notice for hold on card support.

= 2.0.3 =
Small bug fix for authorization payment activated by default.

= 2.0.4 =
Fix a bug for some themes giving an error when editing a Post or a Page.

= 2.0.5 =
Added support for daily recurring payments.

== Upgrade Notice ==

Please upgrade to 2.0.0 for the latest bug fixes and features.

== Frequently Asked Questions ==

= How to sign up for a PayHere Merchant Account? =

Go to PayHere website & apply for a Merchant Account.
https://www.payhere.lk

= How to enable On-site checkout? =

Go to WooCommerce Settings > Payments > PayHere > Manage and tick "Enable On-site checkout"

= Does the plugin support WooCommerce Subscriptions? =

Yes. Supported Subscription products can be checked out with the gateway.

= What are the limitations in support for WooCommerce Subscriptions? = 

1. Only one subscription can be checked out at one time
2. An order cannot contain normal and subscription products
3. Trial and billing periods must be equal
4. The trial length cannot be larger than one billing period
5. Synchronized subscriptions are not supported
6. Free trials cannot be processed

= How do customers use the Save Card Feature? =

You should be a PayHere PREMIUM plan subscriber or using a PayHere Sandbox account to use this feature. After enabling the feature as explained in the "How do I use Use PayHere Tokenized Cards?", customers will automatically see a "Save Card" tick mark when paying.

On their next visits, customers will be offered the option of paying with their saved card.

= Can customers remove their Saved Card? =

Yes. Customers should to navigate to My Account > Saved Cards. This menu will only appear for customers with saved cards.

Once in the menu, click "Remove Card".

= How do I integrate PayHere Tokenized Cards? =

1. You should be a PayHere PREMIUM plan subscriber or using a PayHere Sandbox account to use this feature
2. Login to your PayHere Merchant Account
3. Navigate to Settings > Business Apps > Create App
4. Generate an App ID and App Secret
5. Go to WooCommerce Settings > Payments > PayHere > Manage
6. Click "Enable Tokenizer"
7. Copy and paste the App ID and App Secret generated in Step 4
8. Click Save

= Can I view the list of customers with Pre-approved (saved) cards?

Yes. Navigate to WooCommerce > Cards on files

= The plugin gets de-activated after updating to version 2.0.* =

Go to WP-Admin > Plugins, and re-activated the PayHere plugin.

== Screenshots ==

1. PayHere configuration on WooCommerce admin area
2. PayHere subscription settings
3. PayHere Checkout page
4. PayHere Tokenized Customers list
5. PayHere Saved Cards Menu for Customers
6. Pay with saved card options
7. PayHere Hold on Card Capture