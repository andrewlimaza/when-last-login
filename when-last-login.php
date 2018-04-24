<?php
/*
Plugin Name: When Last Login
Plugin URI: https://wordpress.org/plugins/when-last-login/
Description: Adds functionality to WordPress to show when a user last logged in.
Version: 0.9
Author: YooHoo Plugins
Author URI: https://yoohooplugins.com
Text Domain: when-last-login
Domain Path: /languages
  
  * 0.9
  * Enhancement: Multisite Support - Dashboard widget
  * Enhancement: Multisite Support - User activity is now visible in the network admin's 'Users' page
  * Bug Fix: Fixed an undefined variable when logging in
  * 
  * 0.8 07-06-2017
  * Enhancement: If enabled, user's IP address is available on the 'Users' profile page
  * Enhancement: If enabled, user's IP address is recorded on registration
  * Improvements to add-ons page
  * Enhancement: User IP address is now visible for each login record if enabled
  * 
  * 0.7 26-05-2017
  * New Feature: Settings page introduced
  * New Feature: Ability to record a user's IP address when logging in
  * Enhancement: Login Records moved under the 'When Last Login' menu item
  * New Hook Added: 'wll_settings_admin_menu_item'
  * New Hook Added: 'wll_logged_in_action'
  * 
  * 0.6 26-04-2017
  * Filter: 'when_last_login_show_records_table'. Accepts bool (default = true)
  * Filter: 'when_last_login_show_admin_widget'. Accepts bool (default = true)
  * Enhancement: Moved 'Login Records' under 'Users' link.
  *
  * 0.5 29-09-2016
  * Enhancement: Ability to see which users have logged in and at what times - Custom Post Type - @jarrydlong
  * Bug Fix: return default value for column data if no data is found - @seagyn
  * Enhancement: Improved code readability
  *
  * 0.4 29-08-2016
  * Enhancement: Implemented widgetto display top logged in users
  *
  * 0.3 22-07-2016
  * Enhancement: Implemented multi language support and a couple of language files.
  * Language Support: French, Spanish, German and Italian
  *
  * 0.2 - 15-07-2016
  * Bug Fixes: fixed missing 'static' on function 'sort_by_login_date'
  * Error Handling: Check if 'Paid Memberships Pro' is installed, if not return from the function
  *
  * 0.1 - 15-07-2016
  * Initial release
*/

class When_Last_Login {

    /** Refers to a single instance of this class. */
    private static $instance = null;

    /**
    * Initializes the plugin by setting localization, filters, and administration functions.
    */
    private function __construct() {        

        define( 'WHEN_LAST_LOGIN_BNAME', plugin_basename( __FILE__ ) );

        add_action( 'init', array( $this, 'init' ) );
        add_action( 'plugins_loaded', array( $this, 'text_domain' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_js_for_notice' ) );

        //Create the custom meta upon login
        add_action( 'wp_login', array( $this, 'last_login'), 10, 2 );
        add_action( 'user_register', array( $this, 'wll_user_register' ), 10, 1 );
        //Admin actions
        add_action( 'wp_dashboard_setup', array( $this, 'admin_dashboard_widget' ) );      
        add_action( 'admin_notices', array( $this, 'update_notice' ) );

        add_action( 'wp_ajax_save_update_notice', array( $this, 'save_update_notice' ) );
        add_action( 'wp_ajax_wll_hide_subscription_notice', array( $this, 'wll_save_subscription_notice' ) );
        add_action( 'wp_ajax_wll_subscribe_user_newsletter', array( $this, 'wll_subscribe_user_newsletter_callback' ) );


        //Setting up columns.
        add_filter( 'manage_users_columns', array( $this, 'column_header'), 10, 1 );
        add_action( 'manage_users_custom_column', array( $this, 'column_data'), 15, 3 );
        add_filter( 'manage_users_sortable_columns', array( $this, 'column_sortable' ) );
        add_action( 'pre_get_users', array( $this, 'sort_by_login_date') );

        //Integration for Paid Memberships Pro
        //TODO: Improve integration with Member List and Paid Memberships Pro
        add_action( 'pmpro_memberslist_extra_cols_header', array( $this, 'pmpro_memberlist_add_header' ) );
        add_action( 'pmpro_memberslist_extra_cols_body', array( $this, 'pmpro_memberlist_add_column_data' ) );
        add_action( 'init', array( $this, 'login_record_cp' ) );

        add_action( 'admin_menu', array( $this, 'wll_settings_page' ), 9 );
        add_action( 'admin_head', array( $this, 'wll_settings_page_head' ) );

        add_filter( 'manage_wll_records_posts_columns' , array( $this, 'wll_records_columns'), 10, 1 );
        add_action( 'manage_wll_records_posts_custom_column' , array( $this, 'wll_records_column_contents' ), 10, 2 );

        /**
         * Multisite support
         */
        add_action( 'wp_network_dashboard_setup', array( $this, 'admin_dashboard_widget' ) );
        add_filter( 'wpmu_users_columns', array( $this, 'column_header'), 10, 1 );
        add_action( 'wpmu_users_custom_column', array( $this, 'column_data'), 15, 3 );

        register_activation_hook( __FILE__, array( $this, 'wll_login_attempts_activation' ) );

    }

    /**
    * Creates or returns an instance of this class.
    *
    * @return  When_Last_Login A single instance of this class.
    */
    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self;
        }
        return self::$instance;
    } // end get_instance;

    /**
    * When Last plugin functions.
    */
    public static function init(){
    //init function
    
      if( floatval( get_option( 'wll_current_version' ) ) !== 0.9 ){
        delete_transient( 'when_last_login_add_ons_page' );
        update_option( 'wll_current_version', 0.9 );
      }

    }

    public function wll_login_attempts_activation(){

        $settings = get_option( 'WLLLimitLoginAttempts' );

        if( !$settings || $settings == "" ){

          $settings_array = array(
            'enabled' => 1,
            'attempts' => 3,
            'wait' => 60
          );

          update_option( 'WLLLimitLoginAttempts', $settings_array );

          global $wpdb;

          $table_name = $wpdb->prefix . 'wll_login_attempts';

          $wpdb_collate = $wpdb->collate;

          $sql =
          "CREATE TABLE {$table_name} (
            id int(11) unsigned NOT NULL auto_increment,
            username varchar(255) NULL, 
            ip_address varchar(255) NULL, 
            time_slot varchar(255) NULL,
            PRIMARY KEY  (id) 
          ) 
          COLLATE {$wpdb_collate}";

          require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
          dbDelta( $sql );

        }

      }


    public static function text_domain(){
      load_plugin_textdomain( 'when-last-login', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    public static function update_notice(){

      //clean up old notice option in database since we don't need this anymore. (Increment _1 or _2 etc.)
      if( get_option( 'wll_notice_hide') == '1' ){
        delete_option( 'wll_notice_hide' );
      }

      //this creates the dissmissible notice
      if( get_option( 'wll_notice_hide_1' ) !== '1'){
        ?>
        <div class="notice notice-success  wll-update-notice is-dismissible" >
        <p><?php printf( __( 'Thank you for using When Last Login. If you find this plugin useful please consider leaving a 5 star review %s. View the changelog %s', 'when-last-login' ), '<a href="https://wordpress.org/support/plugin/when-last-login/reviews/" target="_blank">here</a>', '<a href="https://whenlastlogin.com#updates" target="_blank">here</a>' ); ?></p>
        </div>
        <?php
      }

      if( get_option( 'wll_notice_hide_2' ) !== '1'){
        ?>
        <div class="notice notice-success  wll-update-notice-newsletter is-dismissible" >
        <h3><?php _e('When Last Login', 'when-last-login'); ?></h3>
        <p><?php _e( 'Sign up for our newsletter to get the latest product news and promotions, plus get 20% off of your next add-on purchase!', 'when-last-login' ); ?> <?php printf( __('Browse through our add-ons %s', 'when-last-login'), '<a href="'.admin_url( '?page=when-last-login-settings&tab=add-ons' ) .'">'.__('here', 'when-last-login').'</a>' ); ?></p>
        <p><input type='email' style='width: 250px;' name='wll_user_subscribe_to_newsletter' id='wll_user_subscribe_to_newsletter' value='<?php echo get_option('admin_email'); ?>' /><button class='button button-primary' id='wll_subscribe_user'><?php _e('Subscribe Me!', 'when-last-login'); ?></button></p>
        </div>
        <?php
      }
    }

    public static function save_update_notice(){
      //update the hide notice option
      update_option( 'wll_notice_hide_1', '1' );
    }

    public function wll_save_subscription_notice(){
      update_option( 'wll_notice_hide_2', '1' );
    }

    public function wll_subscribe_user_newsletter_callback(){

      if( isset( $_POST['action'] ) && $_POST['action'] == 'wll_subscribe_user_newsletter' ){

        if( isset( $_POST['email'] ) && $_POST['email'] != "" ){

          $request = wp_remote_post( 'https://yoohooplugins.com/api/mailing_list/subscribe.php', array( 'body' => array( 'action' => 'subscribe_newsletter', 'email' => $_POST['email'] ) ) );

          if( !is_wp_error( $request ) ){
            $request_body = wp_remote_retrieve_body( $request );

            if( $request_body == 'subscribed' ){
              echo '1';
              update_option( 'wll_notice_hide_2', '1' );
            }

          } else {

          }

        } else {

          _e('Please enter in an email address to subscribe to our mailing list and receive your 20% coupon', 'when-last-login');

        }

      }

      wp_die();

    }

    public static function load_js_for_notice(){
      if( get_option( 'wll_notice_hide_1' ) !== '1' || get_option( 'wll_notice_hide_2' ) !== '2' ){
        wp_enqueue_script( 'wll_notice_update', plugins_url( 'js/notice-update.js', __FILE__ ), array( 'jquery' ), '1.0', false );
      }
      if( isset( $_GET['page'] ) && $_GET['page'] == 'when-last-login-settings' ){
        wp_enqueue_style( 'wll_admin_settings_styles', plugins_url( '/css/admin.css', __FILE__ ) );
      }
    }

     public static function last_login( $user_login, $users ){

      global $show_login_records;

       //get/update user meta 'when_last_login' on login and add time() to it.
       update_user_meta( $users->ID, 'when_last_login', time() );

       //get and update user meta 'when_last_login_count' on login for # of login counts. Thanks to Jarryd Long (@jarrydlong) from Code Cabin (@code_cabin) for the assistance
       $wll_count = get_user_meta( $users->ID, 'when_last_login_count', true );

       if( $wll_count === false ){
         update_user_meta($users->ID, 'when_last_login_count', 1);
       } else {
         $wll_new_value = intval($wll_count);
         $wll_new_value = $wll_new_value + 1;

         update_user_meta($users->ID, 'when_last_login_count', $wll_new_value);
       }
       if( $show_login_records == true ){
       $args = array(
          'post_title'    => $users->data->display_name . __( ' has logged in at ', 'when-last-login' ) . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
          'post_status'   => 'publish',
          'post_author'   => $users->ID,
          'post_type'     => 'wll_records'
        );

        $post_id = wp_insert_post( $args );

        $wll_settings = get_option( 'wll_settings' );

        if( isset( $wll_settings['record_ip_address'] ) && $wll_settings['record_ip_address'] == 1 ){
          
          if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ){
            $ip = $_SERVER['HTTP_CLIENT_IP'];
          } else if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
          } else {
            $ip = $_SERVER['REMOTE_ADDR'];
          }

          update_post_meta( $post_id, 'wll_user_ip_address', $ip );
          update_user_meta( $users->ID, 'wll_user_ip_address', $ip );
        }

        do_action( 'wll_logged_in_action', array( 'login_count' => $wll_new_value, 'user' => $users ), $wll_settings );

      }

     }

     public function wll_user_register( $user_id ){

        $wll_settings = get_option( 'wll_settings' );

        if( isset( $wll_settings['record_ip_address'] ) && $wll_settings['record_ip_address'] == 1 ){
          
          if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ){
            $ip = $_SERVER['HTTP_CLIENT_IP'];
          } else if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
          } else {
            $ip = $_SERVER['REMOTE_ADDR'];
          }

          update_user_meta( $user_id, 'wll_user_ip_address', $ip );

        }

        do_action( 'wll_register_action', $user_id, $wll_settings );

     }

     public static function login_record_cp(){

      global $show_login_records;

      $show_login_records = apply_filters( 'when_last_login_show_records_table', true );

      if( $show_login_records != true ){
        return;
      }

       $labels = array(
         'name'               => __( 'Login Records', 'when-last-login' ),
         'singular_name'      => __( 'Login Record', 'when-last-login' ),
         'menu_name'          => __( 'Login Records', 'when-last-login' ),
         'name_admin_bar'     => __( 'Login Record', 'when-last-login' ),
         'add_new'            => __( 'Add New', 'when-last-login' ),
         'add_new_item'       => __( 'Add New Login Record', 'when-last-login' ),
         'new_item'           => __( 'New Login Record', 'when-last-login' ),
         'edit_item'          => __( 'Edit Login Record', 'when-last-login' ),
         'view_item'          => __( 'View Login Record', 'when-last-login' ),
         'all_items'          => __( 'All Login Records', 'when-last-login' ),
         'search_items'       => __( 'Search Login Records', 'when-last-login' ),
         'parent_item_colon'  => __( 'Parent Login Records:', 'when-last-login' ),
         'not_found'          => __( 'No login records found.', 'when-last-login' ),
         'not_found_in_trash' => __( 'No login records found in Trash.', 'when-last-login' )
       );

       $args = array(
         'labels'             => $labels,
         'description'        => __( 'Description.', 'when-last-login' ),
         'public'             => false,
         'publicly_queryable' => false,
         'show_ui'            => true,
         'show_in_menu'       => 'when-last-login-settings',
         'query_var'          => true,
         'rewrite'            => array( 'slug' => 'when-last-login-records' ),
         'capability_type'    => 'post',
         'has_archive'        => true,
         'hierarchical'       => false,
         'menu_position'      => null,
         'supports'           => array( 'title', 'author' ),
         'capabilities' => array(
           'create_posts' => false,
         ),
         'map_meta_cap' => true,
       );

       register_post_type( 'wll_records', $args );
     }

     /**
     * Setup admin backend to display custom meta box for login count for admins
     */
     public static function admin_dashboard_widget(){

      global $show_widget;

      $show_widget = apply_filters( 'when_last_login_show_admin_widget', true );
       //only show for administrators
       if( current_user_can( 'manage_options' ) && $show_widget ){
        wp_add_dashboard_widget( 'when_last_login_top_users', __( 'Top 3 Users', 'when-last-login' ), array( 'When_Last_Login', 'admin_dashboard_widget_display' ) );
       }
     }

    public static function admin_dashboard_widget_display(){

        global $show_widget, $show_login_records;

        if( $show_widget != true ){
            return;
        }

        if( is_network_admin() ){

            $sites = get_sites();
                        
            if( is_array( $sites ) ){
            
                foreach( $sites as $site ){
                
                    $blog_id = $site->blog_id;
                    $blog_details = get_blog_details( $blog_id );
                
                    ?><table width="100%" text-align="center" class='wp-list-table striped widefat'>          
                    <tr>
                        <th colspan='4' style='text-align: center;'><strong><?php echo $blog_details->blogname .' (<a href="'.$blog_details->siteurl.'" target="_BLANK">'.$blog_details->siteurl.')</a>'; ?></strong></th>
                    </tr>                      
                    <?php

                    $user_query = new WP_User_Query( array( 'meta_key' => 'when_last_login_count', 'meta_value' => 0, 'meta_compare' => '!=', 'order' => 'ASC', 'oderby' => 'meta_value', 'number' => 3, 'blog_id' => $blog_id ) );

                    $topusers = $user_query->get_results();

                    if( $topusers ){
                        ?>
                        <tr>
                            <th><strong>#</strong></th>
                            <th><strong><?php _e( 'Users', 'when-last-login' ); ?></strong></th>
                            <th><strong><?php _e( 'Login Count', 'when-last-login' ); ?></strong></th>
                            <th><strong><?php _e( 'Last Logged In', 'when-last-login' ); ?></strong></th>
                        </tr> 
                    <?php
                        
                        $count = 1;
                        
                        foreach($topusers as $wllusers){
                            echo '<tr><td>' . $count . '</td>';
                            echo '<td>' . $wllusers->display_name . '</td>';
                            echo '<td>' . get_user_meta( $wllusers->ID, 'when_last_login_count', true ) . '</td>';
                            echo '<td>' . date( 'Y-m-d H:i:s', get_user_meta( $wllusers->ID, 'when_last_login', true ) ) . '</td></tr>';
                            $count++;
                        }
                      
                    } else {

                        echo '<tr><td colspan="4">'.__('No data yet', 'when-last-login').'</td></tr>';
            
                    }

                    ?></table><br/><?php

                }
             
                ?>

                <a href="<?php echo admin_url( 'users.php?orderby=when_last_login&order=desc' ); ?>"><?php _e( 'View All Users', 'when-last-login' ); ?></a>

                <?php if( $show_login_records == true ){ ?>
                    <a style="float:right" href="<?php echo admin_url( 'edit.php?post_type=wll_records' ); ?>"><?php _e( 'View Login Records', 'when-last-login' ); } //end the if filter check here ?></a>
                <?php                
                    
            }
        
        } else {

            ?><table width="100%" text-align="center" class='wp-list-table striped widefat'>          

            <?php

            $user_query = new WP_User_Query( array( 'meta_key' => 'when_last_login_count', 'meta_value' => 0, 'meta_compare' => '!=', 'order' => 'ASC', 'oderby' => 'meta_value', 'number' => 3 ) );

            $topusers = $user_query->get_results();

            if( $topusers ){
                ?>
                <tr>
                    <th><strong>#</strong></th>
                    <th><strong><?php _e( 'Users', 'when-last-login' ); ?></strong></th>
                    <th><strong><?php _e( 'Login Count', 'when-last-login' ); ?></strong></th>
                    <th><strong><?php _e( 'Last Logged In', 'when-last-login' ); ?></strong></th>
                </tr> 
            <?php
                
                $count = 1;
                
                foreach($topusers as $wllusers){
                    echo '<tr><td>' . $count . '</td>';
                    echo '<td>' . $wllusers->display_name . '</td>';
                    echo '<td>' . get_user_meta( $wllusers->ID, 'when_last_login_count', true ) . '</td>';
                    echo '<td>' . date( 'Y-m-d H:i:s', get_user_meta( $wllusers->ID, 'when_last_login', true ) ) . '</td></tr>';
                    $count++;
                }
              
            } else {

                echo '<tr><td colspan="4">'.__('No data yet', 'when-last-login').'</td></tr>';
    
            }

            ?></table><br/><?php

        ?>

        <a href="<?php echo admin_url( 'users.php?orderby=when_last_login&order=desc' ); ?>"><?php _e( 'View All Users', 'when-last-login' ); ?></a>

        <?php if( $show_login_records == true ){ ?>
            <a style="float:right" href="<?php echo admin_url( 'edit.php?post_type=wll_records' ); ?>"><?php _e( 'View Login Records', 'when-last-login' ); } //end the if filter check here ?></a>
        <?php    

        }

    }

     /**
     * Setup Column and data for users page with sortable
     */
     public static function column_header( $column ){
       $column['when_last_login'] = __( 'Last Login', 'when-last-login' );
       $column['when_last_login_ip_address'] = __( 'Last Logged In IP Address', 'when-last-login' );

       return $column;
     }

     public static function column_data( $value, $column_name, $id ){
      if ($column_name == 'when_last_login'){

        $when_last_login_meta = get_the_author_meta( 'when_last_login', $id );

          if( ! empty( $when_last_login_meta ) ){
            return human_time_diff( $when_last_login_meta );
          }else{

            if(get_the_author_meta( 'when_last_login', $id ) === 0 ){
              return __( 'Never', 'when-last-login' );
            }else{
              update_user_meta( $id, 'when_last_login', 0 );
              return __( 'Never', 'when-last-login' );
            }

          }
        } else if( $column_name == 'when_last_login_ip_address' ){

          $when_last_login_ip_address = get_user_meta( $id, 'wll_user_ip_address', true );

          if ( $when_last_login_ip_address && $when_last_login_ip_address != "" ) {
            return "<a href='http://www.ip-adress.com/ip_tracer/".$when_last_login_ip_address."' target='_BLANK' title='".__( 'Lookup', 'when-last-login' )."'>".$when_last_login_ip_address."</a>";
          } else {
            return __( 'IP Address Not Recorded', 'when-last-login' );
          }


        }
      return $value;
     }

     public static function column_sortable( $columns ){
      $columns['when_last_login'] = 'when_last_login';
      return $columns;
     }

    public static function sort_by_login_date( $query ) {
      if ( 'when_last_login' == $query->get( 'orderby' ) ) {
        $query->set( 'orderby', 'meta_value_num' );
        $query->set( 'meta_key', 'when_last_login' );
      }
    }

     /*
     * Support for Paid Memberships Pro
     * TODO: use existing PMPro usermeta if installed
     */
     public static function pmpro_memberlist_add_header( $users ){
       if( !defined( 'PMPRO_VERSION' ) ){
         return;
       }
?>
      <th><?php _e( 'Last Login', 'when-last' );?></th>
<?php
     }

     public static function pmpro_memberlist_add_column_data( $users ){
       if( !defined( 'PMPRO_VERSION' ) ){
         return;
       }
?>
      <td>
<?php
      if( ! empty( $users->when_last_login ) ){
        echo human_time_diff( $users->when_last_login );
      }else{
        return _e( 'Never', 'when-last-login' );
      }
?>
      </td>
<?php
     }

    public function wll_settings_page(){

      add_menu_page( __('When Last Login', 'when-last-login'), __('When Last Login', 'when-last-login'), 'manage_options', 'when-last-login-settings', array( $this, 'wll_settings_callback' ) );
      add_submenu_page( 'when-last-login-settings', __('Settings', 'when-last-login'), __('Settings', 'when-last-login'), 'manage_options', 'when-last-login-settings', array( $this, 'wll_settings_callback' ) );
      add_submenu_page( 'when-last-login-settings', __('Extensions', 'when-last-login'), __('Extensions', 'when-last-login'), 'manage_options', 'admin.php?page=when-last-login-settings&tab=add-ons' );
      
      do_action( 'wll_settings_admin_menu_item' );

    }

    public function wll_settings_callback(){

      include plugin_dir_path( __FILE__ ).'/includes/settings.php';

    }

    public function wll_settings_page_head(){

      $wll_settings = array();

      if( isset( $_POST['wll_save_settings'] ) ){

        $wll_settings['user_access'] = isset( $_POST['wll_login_record_user_access'] ) ? $_POST['wll_login_record_user_access'] : "";
        $wll_settings['record_ip_address'] = isset( $_POST['wll_record_user_ip_address'] ) && $_POST['wll_record_user_ip_address'] == '1'  ? 1 : 0;

        $wll_settings = apply_filters( 'wll_settings_filter', $wll_settings );

        update_option( 'wll_settings', $wll_settings );

      }

    }

    public function wll_records_columns( $columns ){

      return array_merge( $columns, array( 'wll-ip-address' => __( 'IP Address', 'when-last-login' ) ) );

    }

    public function wll_records_column_contents( $column, $post_id ){

      switch ( $column ) {
        case 'wll-ip-address':
          $ip_address = get_post_meta( $post_id, 'wll_user_ip_address', true );
          if ( $ip_address && $ip_address != "" ) {
            echo "<a href='http://www.ip-adress.com/ip_tracer/".$ip_address."' target='_BLANK' title='".__( 'Lookup', 'when-last-login' )."'>".$ip_address."</a>";
          } else {
            _e( 'IP Address Not Recorded', 'when-last-login' );
          }
          break;

      }

    }

} // end class
When_Last_Login::get_instance();

// include plugin_dir_path( __FILE__ ).'/includes/when_last_login_limit_logins.class.php';