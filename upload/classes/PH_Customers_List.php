<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class PH_Customers_List extends WP_List_Table
{

    /** Class constructor */
    public function __construct()
    {

        parent::__construct([
            'singular' => __('Customer', 'sp'), //singular name of the listed records
            'plural' => __('Customers', 'sp'), //plural name of the listed records
            'ajax' => false //does this table support ajax?
        ]);

    }


    /**
     * Retrieve customers data from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_customers($per_page = 5, $page_number = 1)
    {

        global $wpdb;
        $sql = "SELECT meta_key,meta_value,{$wpdb->prefix}wc_customer_lookup.* FROM {$wpdb->prefix}wc_customer_lookup
LEFT JOIN {$wpdb->prefix}usermeta ON {$wpdb->prefix}wc_customer_lookup.user_id = {$wpdb->prefix}usermeta.user_id AND {$wpdb->prefix}usermeta.meta_key = 'payhere_customer_data'";

        $search_have = false;
        if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $search_have = true;
            $sql .= " WHERE (first_name LIKE '%" . esc_sql($_REQUEST['s']) . "%' OR last_name LIKE '%" . esc_sql($_REQUEST['s']) . "%' )";
        }
        if (isset($_REQUEST['ft']) && !empty($_REQUEST['ft'])) {
            if ($_REQUEST['ft'] !== 'all') {
                if (!$search_have) {
                    $sql .= " WHERE ";
                } else {
                    $sql .= " AND ";
                }
            }
            if ($_REQUEST['ft'] == 'tokenized') {
                $sql .= "  NOT ISNULL(meta_value) ";
            }
            if ($_REQUEST['ft'] == 'non-tokenized') {
                $sql .= " ISNULL(meta_value) ";
            }
        }

        if (!empty($_REQUEST['orderby'])) {
            $sql .= ' ORDER BY ' . esc_sql($_REQUEST['orderby']);
            $sql .= !empty($_REQUEST['order']) ? ' ' . esc_sql($_REQUEST['order']) : ' ASC';
        }


        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ($page_number - 1) * $per_page;


        $result = $wpdb->get_results($sql, 'ARRAY_A');
        return $result;
    }


    /**
     * Delete a customer record.
     * @unused - not available in frontend. tb used in future.
     *
     * @param int $id customer ID
     */
    public static function delete_customer($id)
    {
        /*
        global $wpdb;

        $wpdb->delete(
            "{$wpdb->prefix}customers",
            ['ID' => $id],
            ['%d']
        );*/
    }


    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count()
    {
        global $wpdb;

        $sql = "SELECT COUNT({$wpdb->prefix}wc_customer_lookup.customer_id) FROM {$wpdb->prefix}wc_customer_lookup
LEFT JOIN {$wpdb->prefix}usermeta ON {$wpdb->prefix}wc_customer_lookup.user_id = {$wpdb->prefix}usermeta.user_id AND {$wpdb->prefix}usermeta.meta_key = 'payhere_customer_data'";

        return $wpdb->get_var($sql);
    }


    /** Text displayed when no customer data is available */
    public function no_items()
    {
        _e('No customers avaliable.', 'sp');
    }

    protected function get_views()
    {
        $ft = isset($_REQUEST['ft']) ? $_REQUEST['ft'] : '';
        $s = isset($_REQUEST['s']) ? $_REQUEST['s'] : '';
        $status_links = array(
            "all" => sprintf('<a href="?page=%s&ft=%s&s=%s" class="%s">All</a>', esc_attr($_REQUEST['page']), 'all', $s, $ft == 'all' ? 'filter-active' : ''),
            "tokenized" => sprintf('<a href="?page=%s&ft=%s&s=%s" class="%s">Tokenized</a>', esc_attr($_REQUEST['page']), 'tokenized', $s, $ft == 'tokenized' ? 'filter-active' : ''),
            "non-tokenized" => sprintf('<a href="?page=%s&ft=%s&s=%s" class="%s">Non Tokenized</a>', esc_attr($_REQUEST['page']), 'non-tokenized', $s, $ft == 'non-tokenized' ? 'filter-active' : ''),
        );
        return $status_links;
    }

    /**
     * Render a column when no column specific method exist.
     *
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'first_name':
                $name = $item['first_name'] . ' ' . $item['last_name'];
                if (isset($item['user_id']) && !empty($item['user_id'])) {
                    return "<a href='" . site_url('wp-admin/user-edit.php?user_id=') . $item['user_id'] . "'>$name</a>";
                } else {
                    return $name;
                }
            case 'user_id':
            case 'email':
            case 'username':
                return $item[$column_name];
            case 'date_registered':
                return $item[$column_name] ? date("F j, Y, g:i a", strtotime($item[$column_name])) : '';
            case 'saved_date':
                $customer_data = json_decode($item['meta_value']);
                return isset($customer_data->saved_date) ? $customer_data->saved_date : '';
            case 'method':
                $customer_data = json_decode($item['meta_value']);
                if (isset($customer_data->method)) {
                    $class = $customer_data->method == 'VISA' || $customer_data->method == 'TEST' ? 'visa-card' : 'master-card';
                    $card_img = '<span class="disply-card ' . $class . '"></span>';

                    return $card_img;
                } else {
                    return '';
                }
            case 'card_no':
                $customer_data = json_decode($item['meta_value']);
                return isset($customer_data->card_no) ? substr($customer_data->card_no, 5) : '';
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }


    /**
     * Method for name column
     *
     * @param array $item an array of DB data
     *
     * @return string
     */
    function column_name($item)
    {
        $title = '<strong>' . $item['username'] . '</strong>';

//        $delete_nonce = wp_create_nonce('sp_delete_customer');


//        $actions = [
//            'delete' => sprintf('<a href="?page=%s&action=%s&customer=%s&_wpnonce=%s">Delete</a>', esc_attr($_REQUEST['page']), 'delete', absint($item['ID']), $delete_nonce)
//        ];

        return $title;// . $this->row_actions($actions);
    }


    /**
     *  Associative array of columns
     *
     * @return array
     */
    function get_columns()
    {
        $columns = [
            'user_id' => __('ID', 'woo_payhere'),
            'first_name' => __('Name', 'woo_payhere'),
            'username' => __('Username', 'woo_payhere'),
            'email' => __('E-mail', 'woo_payhere'),
            'date_registered' => __('Sign Up Date', 'woo_payhere'),
            'saved_date' => __('Card Saved Date', 'woo_payhere'),
            'method' => __('Method', 'woo_payhere'),
            'card_no' => __('Card', 'woo_payhere')
        ];

        return $columns;
    }


    /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'first_name' => array('first_name', false),
            'username' => array('username', false),
            'date_registered' => array('date_registered', false)
        );

        return $sortable_columns;
    }

    /**
     * Returns an associative array containing the bulk action
     * @disabled - customers cannot be deleted from our table
     *
     * @return array
     */
    public function get_bulk_actions()
    {
        $actions = [
//            'bulk-delete' => 'Delete'
        ];

        return $actions;
    }


    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items()
    {

        $this->_column_headers = $this->get_column_info();


        $per_page = $this->get_items_per_page('customers_per_page', 5);
        $current_page = $this->get_pagenum();
        $total_items = self::record_count();

        $this->set_pagination_args([
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page' => $per_page //WE have to determine how many items to show on a page
        ]);

        $this->items = self::get_customers($per_page, $current_page);
    }

}