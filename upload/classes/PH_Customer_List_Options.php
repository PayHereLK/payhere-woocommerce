<?php

require_once 'PH_Customers_List.php';

class PH_Customer_List_Options
{

    // class instance
    static $instance;

    // customer WP_List_Table object
    public $customers_obj;

    // class constructor
    public function __construct()
    {
        add_filter('set-screen-option', [__CLASS__, 'set_screen'], 10, 3);
        add_action('admin_menu', [$this, 'plugin_menu'], 20);
    }


    public static function set_screen($status, $option, $value)
    {
        return $value;
    }

    public function plugin_menu()
    {

        $hook = add_submenu_page(
            'woocommerce',
            'Customer Cards on File',
            'Cards on File',
            'manage_options',
            'payhere_list_customer_list',
            [$this, 'plugin_settings_page']
        );

        add_action("load-$hook", [$this, 'screen_option']);

    }


    /**
     * Plugin settings page
     */
    public function plugin_settings_page()
    {
        ?>
        <div class="wrap">
            <h2>Cards on File </h2>

            <style>
                .disply-card {
                    background-image: url("<?php echo plugins_url('payhere-payment-gateway/assets/img/cards.png') ?>");
                    width: 47px;
                    height: 30px;
                    display: block;
                    background-size: cover;
                }

                .disply-card.visa-card {
                    background-position: left;
                }

                .disply-card.master-card {
                    background-position: right;
                }

                .filter-active {
                    color: #000;
                    text-decoration: underline;
                }
            </style>
            <div id="poststuff">
                <div id="post-body" class="metabox-holder">
                    <div id="post-body-content">
                        <div class="meta-box-sortables ui-sortable">
                            <form method="post"
                                  action="<?php echo admin_url('admin.php') . '?page=payhere_list_customer_list' ?>">
                                <?php
                                $this->customers_obj->prepare_items();
                                $this->customers_obj->views();
                                $this->customers_obj->search_box('Search', 'search-name');
                                $this->customers_obj->display(); ?>
                            </form>
                        </div>
                    </div>
                </div>
                <br class="clear">
            </div>
        </div>
        <?php
    }

    /**
     * Screen options
     */
    public function screen_option()
    {

        $option = 'per_page';
        $args = [
            'label' => 'Customers',
            'default' => 10,
            'option' => 'customers_per_page'
        ];

        add_screen_option($option, $args);

        $this->customers_obj = new PH_Customers_List();
    }


    /** Singleton instance */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}