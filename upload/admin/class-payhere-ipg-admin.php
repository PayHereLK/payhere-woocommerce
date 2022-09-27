<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      2.0.0
 *
 * @package    PayHere
 * @subpackage PayHere/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    PayHere
 * @subpackage PayHere/admin
 * @author     Your Name <dilshan@payhere.lk>
 */
class PayHereAdmin
{

    /**
     * The ID of this plugin.
     *
     * @since    2.0.0
     * @access   private
     * @var      string $PayHere The ID of this plugin.
     */
    private $PayHere;

    private $AuthorizedbyPayHere = 'Authorized by PayHere';

    /**
     * The version of this plugin.
     *
     * @since    2.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $PayHere The name of this plugin.
     * @param string $version The version of this plugin.
     * @since    2.0.0
     */
    public function __construct($PayHere, $version)
    {

        $this->PayHere = $PayHere;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    2.0.0
     */
    public function enqueue_styles()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in PayHere_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The PayHere_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style($this->PayHere, plugin_dir_url(__FILE__) . 'css/payhere-ipg-admin.css', array(), $this->version, 'all');
    }

    public function add_action_links($actions, $plugin_file)
    {
        $plugin = plugin_basename(plugin_dir_path(__FILE__));
        if (explode('/', $plugin)[0] == explode('/', $plugin_file)[0]) {
            $settings = array('settings' => sprintf('<a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payhere">%s</a>', 'Settings'));
            $actions = array_merge($settings, $actions);
        }

        return $actions;
    }


    /**
     * @description  Load the PayHere Gateway class files
     */
    public function init_gateway_files()
    {
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            require_once plugin_dir_path(dirname(__FILE__)) . 'gateway/class-gateway-utilities.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'gateway/class-payhere-order-utilities.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'gateway/class-payhere-token.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'gateway/class-payhere-capture-payment.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'gateway/class-gateway-charge-payment.php';
            require_once plugin_dir_path(dirname(__FILE__)) . 'gateway/class-wc-gateway-payhere.php';
        }
    }

    /**
     * ajax charge PayHere payment
     * @return string
     */
    public function add_charge_ajax()
    {
        $gateWay = new WCGatewayPayHere();

        $gateWay->charge_payment();
    }
    /**
     * ajax capture authorized PayHere payment
     * @return string
     */
    public function add_capture_ajax()
    {
        $gateWay = new WCGatewayPayHere();
        $gateWay->capture_payment();
    }

    /**
     * @description  Load the PayHere Gateway to Woocommerce Gateway list.
     * @return Returns the list of Payment Gateways.
     */
    public function load_gateway($methods)
    {
        $methods[] = 'WCGatewayPayHere';
        return $methods;
    }

    public function add_customer_list_menu()
    {
        PHCustomerListOptions::get_instance();
    }

    /**
     * @description  Register Authorized By PayHere in Wordpress Post Status Registry
     */
    public function register_authorized_order_status()
    {
        register_post_status('wc-payhere-authorized', array(
            'label' => $this->AuthorizedbyPayHere,
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop($this->AuthorizedbyPayHere . ' (%s)', $this->AuthorizedbyPayHere . ' (%s)', 'payhere')
        ));
    }

    public function register_authorized_order_statuses($order_statuses)
    {
        $order_statuses['wc-phauthorized'] = array(
            'label' => $this->AuthorizedbyPayHere,
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop($this->AuthorizedbyPayHere . ' (%s)', $this->AuthorizedbyPayHere . ' (%s)', 'payhere')
        );
        return $order_statuses;
    }

    /**
     * @description  Add Authorized By PayHere to Woocommerce Order Status List
     * @return List of Order Status
     */
    public function add_authorized_to_order_statuses($order_statuses)
    {

        $new_order_statuses = array();
        foreach ($order_statuses as $key => $status) {
            $new_order_statuses[$key] = $status;
            if ('wc-processing' === $key) {
                $new_order_statuses['wc-phauthorized'] = $this->AuthorizedbyPayHere;
            }
        }

        return $new_order_statuses;
    }

    public function allow_authorize_status_edit($status, $order)
    {
        if (!$status && $order->get_status() == 'phauthorized') {
            return true;
        }
        return $status;
    }

    public function add_order_metabox_to_order()
    {
        global $post;
        if ($post && $post->post_type == 'shop_order') {
            $order = wc_get_order($post->ID);
            if ($order && $order->get_payment_method() == 'payhere') {
                add_meta_box(
                    'payhere',
                    '<span style="display: flex"><img style="margin-right: 5px" src="https://www.payhere.lk/images/favicon.png" height="20" />  <span>PayHere Payments</span></span> ',
                    array($this, 'payhere_order_auth_capture_content'),
                    'shop_order',
                    'normal',
                    'high'
                );
            }
        }
    }

    public function payhere_order_auth_capture_content()
    {
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/partials/order-auth-payment.php';
    }

    public function relocate_order_meta_box()
    {
        global $wp_meta_boxes;
        $post_type = 'shop_order';
        if (isset($wp_meta_boxes[$post_type]['normal']['high']['payhere'])) {

            $payhere_box = $wp_meta_boxes[$post_type]['normal']['high']['payhere'];
            unset($wp_meta_boxes[$post_type]['normal']['high']['payhere']);

            foreach ($wp_meta_boxes[$post_type]['normal']['high'] as $key => $data) {
                if ($key === 'woocommerce-order-data') {
                    $wp_meta_boxes[$post_type]['normal']['high']['payhere'] = $payhere_box;
                }
            }
        }
    }


    /**
     * Register the JavaScript for the admin area.
     *
     * @since    2.0.0
     */
    public function enqueue_scripts()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in PayHere_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The PayHere_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script($this->PayHere, plugin_dir_url(__FILE__) . 'js/payhere-ipg-admin.js', array('jquery'), $this->version, false);
    }
}
