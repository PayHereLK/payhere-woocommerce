<?php

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/screen.php');
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}
class PHCustomersList extends WP_List_Table
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
        $dbP = $wpdb->prefix;

        $parameters =  [];

        $sql = "SELECT meta_key,meta_value,{$dbP}wc_customer_lookup.* FROM {$dbP}wc_customer_lookup
        LEFT JOIN {$dbP}usermeta ON {$dbP}wc_customer_lookup.user_id = {$dbP}usermeta.user_id AND {$dbP}usermeta.meta_key = 'payhere_customer_data'";

        if (isset($_REQUEST['s']) && !empty($_REQUEST['s'])) {
            $search_text = sanitize_text_field($_REQUEST['s']);
            $sql .= " WHERE (first_name LIKE '%s' OR last_name LIKE '%s' )";
            $parameters[] = '%' . $wpdb->esc_like($search_text) . '%';
            $parameters[] = '%' . $wpdb->esc_like($search_text) . '%';
        }

        $sql .= " WHERE NOT ISNULL(meta_value) ";
        $sql .= " ORDER BY %s %s";

        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ($page_number - 1) * $per_page;


        $parameters[] = !empty($_REQUEST['orderby']) ? esc_sql(sanitize_text_field($_REQUEST['orderby'])) : ($dbP . 'usermeta.user_id');
        $parameters[] = !empty($_REQUEST['order']) ? esc_sql(sanitize_text_field($_REQUEST['order'])) : 'DESC';


        $prepared_sql = $wpdb->prepare(
            $sql,
            $parameters,
        );

        return $wpdb->get_results($prepared_sql, 'ARRAY_A');

    }


    /**
     * Delete a customer record.
     * @unused - not available in frontend. tb used in future.
     *
     * @param int $id customer ID
     */
    public static function delete_customer($id)
    {
        // Todo
    }


    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count()
    {
        global $wpdb;
        $dbP = $wpdb->prefix;

        $sql = "SELECT COUNT({$dbP}wc_customer_lookup.customer_id) FROM {$dbP}wc_customer_lookup
LEFT JOIN {$dbP}usermeta ON {$dbP}wc_customer_lookup.user_id = {$dbP}usermeta.user_id AND {$dbP}usermeta.meta_key = %s  WHERE NOT ISNULL(meta_value) ";

        $prepared_sql = $wpdb->prepare($sql, 'payhere_customer_data');
        return $wpdb->get_var($prepared_sql);
    }


    /** Text displayed when no customer data is available */
    public function no_items()
    {
        _e('No customers avaliable.', 'sp');
    }

    protected function get_views()
    {

        return array(
        );
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
                return isset($customer_data->saved_date) ? date('F j, Y, g:i a', $customer_data->saved_date) : '-';
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
        $title = '<strong>' . esc_html($item['username']) . '</strong>';
        return $title; // . $this->row_actions($actions);
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


        $per_page = $this->get_items_per_page('customers_per_page', 10);
        $current_page = $this->get_pagenum();
        $total_items = self::record_count();

        $this->set_pagination_args([
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page' => $per_page //WE have to determine how many items to show on a page
        ]);

        $this->items = self::get_customers($per_page, $current_page);
    }
}
