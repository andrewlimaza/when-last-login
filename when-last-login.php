<?php
/*
Plugin Name: When Last Login
Plugin URI: https://wordpress.org/plugins/when-last-login/
Description: See when a user logs into your WordPress site.
Version: 2.0
Author: Yoohoo Plugins
Author URI: https://yoohooplugins.com
Text Domain: when-last-login
Domain Path: /languages
*/

use geertw\IpAnonymizer\IpAnonymizer;

class When_Last_Login {

    /** Refers to a single instance of this class. */
    private static $instance = null;

    /**
    * Initializes the plugin by setting localization, filters, and administration functions.
    */
    private function __construct() {        

      define( 'WLL_BASENAME', plugin_basename( __FILE__ ) );
      define( 'WLL_DIR_PATH', plugin_dir_path( __FILE__ ) );
      define( 'WLL_PLUGIN', WP_PLUGIN_URL . '/when-last-login' );
      define( 'WLL_VERSION', '2.0' );

      $settings = get_option( 'wll_settings' );

      include WLL_DIR_PATH . '/includes/lib/IpAnonymizer.php';
      include WLL_DIR_PATH . '/includes/privacy-policy.php';

      add_action( 'admin_init', array( $this, 'admin_init' ) );
      add_action( 'plugins_loaded', array( $this, 'text_domain' ) );
      add_action( 'admin_enqueue_scripts', array( $this, 'load_js_for_notice' ) );

      //Create the custom meta upon login
      add_action( 'wp_login', array( $this, 'last_login'), 10, 2 );
      add_action( 'user_register', array( $this, 'wll_user_register' ), 10, 1 );

      //Admin actions
      add_action( 'wp_dashboard_setup', array( $this, 'admin_dashboard_widget' ) );      
      add_action( 'admin_notices', array( $this, 'update_notice' ) );

      add_action( 'wp_ajax_wll_hide_subscription_notice', array( $this, 'wll_hide_subscription_notice' ) );
      add_action( 'wp_ajax_wll_subscribe_user_newsletter', array( $this, 'wll_subscribe_user_newsletter_callback' ) );

      register_activation_hook( __FILE__, array( $this, 'activation' ) );

      //Setting up columns.
      add_filter( 'manage_users_columns', array( $this, 'column_header'), 10, 1 );
      add_action( 'manage_users_custom_column', array( $this, 'column_data'), 15, 3 );
      add_filter( 'manage_users_sortable_columns', array( $this, 'column_sortable' ) );
      add_action( 'pre_get_users', array( $this, 'sort_by_login_date') );

      //Integration for Paid Memberships Pro
      //TODO: Improve integration with Member List and Paid Memberships Pro
      add_action( 'pmpro_memberslist_extra_cols_header', array( $this, 'pmpro_memberlist_add_header' ) );
      add_action( 'pmpro_memberslist_extra_cols_body', array( $this, 'pmpro_memberlist_add_column_data' ) );      

      add_action( 'admin_menu', array( $this, 'wll_settings_page' ), 9 );
      add_action( 'admin_head', array( $this, 'wll_settings_page_head' ) );
      add_action( 'admin_init', array( $this, 'wll_automatically_remove_logs' ) );

      add_filter( 'plugin_row_meta', array( $this, 'wll_plugin_row_meta' ), 10, 2 );
      add_filter( 'plugin_action_links_' . WLL_BASENAME, array( $this, 'wll_plugin_action_links' ), 10, 2 );

      add_filter( 'manage_wll_records_posts_columns' , array( $this, 'wll_records_columns'), 10, 1 );
      add_action( 'manage_wll_records_posts_custom_column' , array( $this, 'wll_records_column_contents' ), 10, 2 );

      /**
      * Multisite support
      */
      add_action( 'wp_network_dashboard_setup', array( $this, 'admin_dashboard_widget' ) );
      add_filter( 'wpmu_users_columns', array( $this, 'column_header'), 10, 1 );
      add_action( 'wpmu_users_custom_column', array( $this, 'column_data'), 15, 3 );
    }

    /**
     * Acivatation Hook
     */
    public function activation(){

        $this->create_records_table();

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
    public static function admin_init(){
        //init function
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        do_action( 'wll_upgrade_check' );

        $current_version = floatval( get_option( 'wll_current_version' ) );

        // Clean up stuff for version 1.0
        if( $current_version < 1.0 || empty( $current_version ) ) {

            global $wpdb;

            $delete_table = $wpdb->prefix . 'wll_login_attempts' ;
            $sql = "DROP TABLE IF EXISTS `$delete_table`";
            $wpdb->query( $sql );

            delete_transient( 'when_last_login_add_ons_page' );

            // on upgrade remove the notice save.
            delete_option( 'wll_notice_hide' );
            delete_option( 'wll_notice_hide_1' );
            delete_option( 'wll_notice_hide_2' );

            // update version number to 1.0
            update_option( 'wll_current_version', 1.2 );
        }

        if( $current_version == 1.2 ){

            $this->create_records_table();
            $this->migrate_records();

            update_option( 'wll_current_version', 2.0 );
        }
    }

    function create_records_table(){

        global $wpdb;

        $records = $wpdb->prefix.'wll_login_records';

        $sql = "CREATE TABLE ".$records." ( `id` INT(11) NOT NULL AUTO_INCREMENT , `title` VARCHAR(255) NOT NULL , `author` INT(11) NOT NULL , `status` VARCHAR(255) NOT NULL , `date` VARCHAR(255) NOT NULL , `ip` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`)) ENGINE = MyISAM";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta( $sql );

    }

    function migrate_records(){

        $args = array(
            'post_type' => 'wll_records',
            'posts_per_page' => -1,
        );

        $the_query = new WP_Query( $args );

        if( $the_query->have_posts() ){
          while( $the_query->have_posts() ){
            $the_query->the_post();

            $recorded = $this->record_login( get_the_title(), get_the_author(), 'publish', get_the_date() );

            $stored_ip = get_post_meta( get_the_ID(), 'wll_user_ip_address', true );

            if( $stored_id !== "" ){
              $this->record_ip( $recorded, $stored_ip );
            }

          }
        }

    }

    function record_login( $title, $author, $status, $date ){

        global $wpdb;        

        $wpdb->insert(
            $wpdb->prefix.'wll_login_records',
            array(
                'title'     => $title,
                'author'    => $author,
                'status'    => $status,
                'date'      => $date
            ),
            array(
                '%s',
                '%d',
                '%s',
                '%s',
            )
        );

        return $wpdb->insert_id;

    }

    function record_ip( $id, $ip ){

        global $wpdb;

        return $wpdb->update( 
            $wpdb->prefix.'wll_login_records',
            array( 'ip' => $ip ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%s' )
        );

    }

    function get_records( $page = 1, $id = 0 ){

      global $wpdb;

      $table_name = $wpdb->prefix.'wll_login_records';

      $sql = "SELECT * FROM $table_name ";

      if( $page >= 1 && $id == 0 ){

        $offset = ( $page - 1 ) * 25;

        $sql .= "ORDER BY `id` DESC LIMIT ".$offset.",25";
        
        return $wpdb->get_results( $sql );
      }

      if( $id > 0 ){
        $sql .= "WHERE `id` = '".$id."'";

        return $wpdb->get_row( $sql );
      }

    }

    function count_records(){

      global $wpdb;

      $table_name = $wpdb->prefix.'wll_login_records';

      return $wpdb->get_var("SELECT count( * ) FROM $table_name" );

    }

    function build_pagination(){

      $records = $this->count_records();

      $pages = ceil( $records / 25 );

      echo $records.__(' Records', 'when-last-login' ).' | '.__('Page ', 'when-last-login');

      for( $i = 1; $i <= $pages; $i++ ){
        echo "<a href='".admin_url( 'admin.php?page=all-login-records&wll_record_page='.$i )."'>".$i."</a> ";
      }
      
    }

    function delete_record( $id ){

      global $wpdb;

      $deleted = $wpdb->delete( $wpdb->prefix.'wll_login_records', array( 'id' => $id ) );

    }

    public static function text_domain(){
      load_plugin_textdomain( 'when-last-login', false, dirname( 'WLL_BASE_NAME' ) . '/languages' );
    }

    public static function update_notice(){

      if( get_option( 'wll_notice_hide' ) != '1' && ( isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'when-last-login-settings' ) ){
        ?>
        <div class="notice notice-success  wll-update-notice-newsletter is-dismissible" >
        <h3><?php echo esc_html('Thank you for using When Last Login', 'when-last-login'); ?></h3>
        <p><?php  _e( sprintf( 'Please consider leaving an honest review for When Last Login by visiting %s', '<a href="'. esc_url( 'https://wordpress.org/support/plugin/when-last-login/reviews/#new-post' ) . '" target="_blank">this link</a>' ), 'when-last-login' ); ?></p>
        </div>
        <?php
      }
    }

    public function wll_hide_subscription_notice(){
      update_option( 'wll_notice_hide', '1' );
    }

    public function wll_subscribe_user_newsletter_callback(){

      if( isset( $_POST['action'] ) && sanitize_text_field( $_POST['action'] ) == 'wll_subscribe_user_newsletter' ){

        if( isset( $_POST['email'] ) && sanitize_text_field( $_POST['email'] ) != "" ){

          $request = wp_remote_post( 'https://yoohooplugins.com/api/mailing_list/subscribe.php', array( 'body' => array( 'action' => 'subscribe_newsletter', 'email' => $_POST['email'] ) ) );

          if( !is_wp_error( $request ) ){
            $request_body = wp_remote_retrieve_body( $request );

            if( $request_body == 'subscribed' ){
              echo '1';
              update_option( 'wll_notice_hide', '1' );
            }

          } else {

          }

        } else {

          esc_html_e( 'Please enter in an email address to subscribe to our mailing list and receive your coupon', 'when-last-login' );

        }

      }

      wp_die();

    }

    public static function load_js_for_notice(){
      if( get_option( 'wll_notice_hide' ) !== '1'){
        wp_enqueue_script( 'wll_notice_update', plugins_url( 'js/notice-update.js', __FILE__ ), array( 'jquery' ), '1.0', false );
      }
      if( isset( $_GET['page'] ) && sanitize_text_field( $_GET['page'] ) == 'when-last-login-settings' ){
        wp_enqueue_style( 'wll_admin_settings_styles', plugins_url( '/css/admin.css', __FILE__ ) );
      }
      if( isset( $_GET['page'] ) && $_GET['page'] == 'all-login-records' ){
        wp_enqueue_script( 'wll_admin_login_records_script', plugins_url( '/js/admin.js', __FILE__ ) );
      }
    }

     public static function last_login( $user_login, $users ){

      global $show_login_records;

       //get/update user meta 'when_last_login' on login and add time() to it.
       update_user_meta( $users->ID, 'when_last_login', time() );

       //get and update user meta 'when_last_login_count' on login for # of login counts. Thanks Jarryd Long (@jarrydlong).
       $wll_count = get_user_meta( $users->ID, 'when_last_login_count', true );

       if( $wll_count === false ){
         update_user_meta($users->ID, 'when_last_login_count', 1);
       } else {
         $wll_new_value = intval($wll_count);
         $wll_new_value = $wll_new_value + 1;

         update_user_meta($users->ID, 'when_last_login_count', $wll_new_value);
       }
       $settings = get_option( 'wll_settings' );

      $show = ( ! empty($settings['show_all_login_records']) && intval( $settings['show_all_login_records'] ) === 1);

      $show_login_records = apply_filters( 'when_last_login_show_records_table', $show );
     
       if( $show_login_records == true ){
       
        $post_id = $this->record_login( $users->data->display_name . __( ' has logged in at ', 'when-last-login' ) . date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ), $users->ID, 'publish', date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ) );
       
      }

        $wll_settings = get_option( 'wll_settings' );

        if( isset( $wll_settings['record_ip_address'] ) && intval( $wll_settings['record_ip_address'] ) == 1 ){

          // call function to anonymize here.
          $ip = When_Last_Login::wll_get_user_ip_address();

          if ( ! empty( $post_id ) ) {
            $this->record_ip( $post_id, $ip );
          }
          
            update_user_meta( $users->ID, 'wll_user_ip_address', $ip );
        }

        do_action( 'wll_logged_in_action', array( 'login_count' => $wll_new_value, 'user' => $users ), $wll_settings );

     }

     public function wll_user_register( $user_id ){

        $wll_settings = get_option( 'wll_settings' );

        if( isset( $wll_settings['record_ip_address'] ) && $wll_settings['record_ip_address'] == 1 ){
          
        $ip = When_Last_Login::wll_get_user_ip_address();
        update_user_meta( $user_id, 'wll_user_ip_address', $ip );

        }

        do_action( 'wll_register_action', $user_id, $wll_settings );

     }

     public function all_login_records_content(){

        include plugin_dir_path( __FILE__ ).'includes/settings/records.php';
       
     }

     /**
     * Setup admin backend to display custom meta box for login count for admins
     */
     public static function admin_dashboard_widget(){

      global $show_widget;

      $show_widget = apply_filters( 'when_last_login_show_admin_widget', true );
       //only show for administrators
       if( current_user_can( 'manage_options' ) && $show_widget ){
        wp_add_dashboard_widget( 'when_last_login_top_users', __( 'Most Active Users by Login', 'when-last-login' ), array( 'When_Last_Login', 'admin_dashboard_widget_display' ) );
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
                        <th colspan='4' style='text-align: center;'><strong><?php echo esc_html( $blog_details->blogname ) .' (<a href="'. esc_url( $blog_details->siteurl ).'" target="_BLANK">'. esc_html( $blog_details->siteurl ) . ')</a>'; ?></strong></th>
                    </tr>                      
                    <?php

                    $user_query = new WP_User_Query( array( 'meta_key' => 'when_last_login_count', 'meta_value' => 0, 'meta_compare' => '!=', 'order' => 'DESC', 'oderby' => 'meta_value', 'number' => apply_filters( 'wll_top_widget_user_count', 3 ), 'blog_id' => $blog_id, 'role__not_in' => 'Administrator' ) );

                    $topusers = $user_query->get_results();

                    if( $topusers ){
                        ?>
                        <tr>
                            <th><strong>#</strong></th>
                            <th><strong><?php esc_html_e( 'Users', 'when-last-login' ); ?></strong></th>
                            <th><strong><?php esc_html_e( 'Login Count', 'when-last-login' ); ?></strong></th>
                            <th><strong><?php esc_html_e( 'Last Logged In', 'when-last-login' ); ?></strong></th>
                        </tr> 
                    <?php
                        
                        $count = 1;
                        
                        foreach($topusers as $wllusers){
                            echo '<tr><td>' . intval( $count ) . '</td>';
                            echo '<td>' . esc_html( $wllusers->display_name ) . '</td>';
                            echo '<td>' . get_user_meta( $wllusers->ID, 'when_last_login_count', true ) . '</td>';
                            echo '<td>' . date_i18n( 'Y-m-d H:i:s', get_user_meta( $wllusers->ID, 'when_last_login', true ) ) . '</td></tr>';
                            $count++;
                        }
                      
                    } else {

                        echo '<tr><td colspan="4">'. __('No data captured yet', 'when-last-login').'</td></tr>';
            
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

            $user_query = new WP_User_Query( array( 'meta_key' => 'when_last_login_count', 'meta_value' => 0, 'meta_compare' => '!=', 'order' => 'DESC', 'oderby' => 'meta_value', 'number' => apply_filters( 'wll_top_widget_user_count', 3 ), 'role__not_in' => 'Administrator' ) );

            $topusers = $user_query->get_results();

            if( $topusers ){
                ?>
                <tr>
                    <th><strong>#</strong></th>
                    <th><strong><?php esc_html_e( 'Users', 'when-last-login' ); ?></strong></th>
                    <th><strong><?php esc_html_e( 'Login Count', 'when-last-login' ); ?></strong></th>
                    <th><strong><?php esc_html_e( 'Last Logged In', 'when-last-login' ); ?></strong></th>
                </tr> 
            <?php
                
                $count = 1;
                
                foreach($topusers as $wllusers){
                    echo '<tr><td>' . $count . '</td>';
                    echo '<td>' . $wllusers->display_name . '</td>';
                    echo '<td>' . get_user_meta( $wllusers->ID, 'when_last_login_count', true ) . '</td>';
                    echo '<td>' . date_i18n( 'Y-m-d H:i:s', get_user_meta( $wllusers->ID, 'when_last_login', true ) ) . '</td></tr>';
                    $count++;
                }
              
            } else {

                echo '<tr><td colspan="4">'. esc_html__( 'No data yet', 'when-last-login' ).'</td></tr>';
    
            }

            ?></table><br/><?php

        ?>

        <a href="<?php echo admin_url( 'users.php?orderby=when_last_login&order=desc' ); ?>"><?php esc_html_e( 'View All Users', 'when-last-login' ); ?></a>

        <?php if( $show_login_records == true ){ ?>
            <a style="float:right" href="<?php echo admin_url( 'edit.php?post_type=wll_records' ); ?>"><?php esc_html_e( 'View Login Records', 'when-last-login' ); } //end the if filter check here ?></a>
        <?php    
        }
        
        // Add link to upgrade to When Last User Statistics.
        if ( ! defined( 'WLL_STATS_VERSION' ) && apply_filters( 'wll_show_top_users_cta', true ) ) {
         ?>
          <br/><br/>
          <span class='wll-user-stats-cta description' style="margin:0 auto;text-align:center;display:block;font-style:italic"><?php _e( sprintf( 'Optional: Get advanced statistics with the %s.', '<a href="' . esc_url( 'https://yoohooplugins.com/plugins/when-last-login-user-statistics/?utm_source=plugin&utm_medium=WLL&utm_campaign=user_statistics') . '" target="_blank">' . __( 'User Statistics Add On', 'when-last-login' ). '</a>' ), 'when-last-login' ); ?></span>
         <?php
        }

    }

     /**
     * Setup Column and data for users page with sortable
     */
     public static function column_header( $column ){
      $settings = get_option( 'wll_settings' );

      $column['when_last_login'] = esc_html__( 'Last Login', 'when-last-login' );

      if ( ! empty( $settings['record_ip_address'] ) ) {
        $column['when_last_login_ip_address'] = esc_html__( 'IP Address', 'when-last-login' );
      }
      

       return $column;
     }

     public static function column_data( $value, $column_name, $id ){

      $settings = get_option( 'wll_settings' );

      if ( $column_name == 'when_last_login' ){

        $when_last_login_meta = get_the_author_meta( 'when_last_login', $id );

          if( ! empty( $when_last_login_meta ) ){
            return human_time_diff( $when_last_login_meta );
          } else {
            if( get_the_author_meta( 'when_last_login', $id ) === 0 ){
              return esc_html__( 'Never', 'when-last-login' );
            } else {
              update_user_meta( $id, 'when_last_login', 0 );
              return esc_html__( 'Never', 'when-last-login' );
            }
          }
        } else if( $column_name == 'when_last_login_ip_address' ){

          $when_last_login_ip_address = get_user_meta( $id, 'wll_user_ip_address', true );

          if ( $when_last_login_ip_address && $when_last_login_ip_address != "" && $settings['record_ip_address'] != "") {
            return "<a href='http://www.ip-adress.com/ip_tracer/". esc_attr( $when_last_login_ip_address ) ."' target='_BLANK' title='".__( 'Lookup', 'when-last-login' )."'>" . esc_html( $when_last_login_ip_address ) . "</a>";
          } else {
            return esc_html__( 'IP Address Not Recorded', 'when-last-login' );
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
      <th><?php esc_html_e( 'Last Login', 'when-last-login' );?></th>
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
        return esc_html_e( 'Never', 'when-last-login' );
      }
?>
      </td>
<?php
     }

    public function wll_settings_page(){
  
     global $show_login_records;

      $settings = get_option( 'wll_settings' );

      $show = ( ! empty($settings['show_all_login_records']) && intval( $settings['show_all_login_records'] ) === 1);

      $show_login_records = apply_filters( 'when_last_login_show_records_table', $show );

      if( $show_login_records ){
      add_menu_page( __( 'When Last Login', 'when-last-login' ), __( 'When Last Login', 'when-last-login' ), 'manage_options', 'when-last-login-settings', array( $this, 'wll_settings_callback' ) );
      add_submenu_page( 'when-last-login-settings', __('All Login Records', 'when-last-login'), __('All Login Records', 'when-last-login'), 'manage_options', 'all-login-records', array( $this, 'all_login_records_content' ) );
      add_submenu_page( 'when-last-login-settings', __('Add Ons', 'when-last-login'), __('Add Ons', 'when-last-login'), 'manage_options', 'admin.php?page=when-last-login-settings&tab=add-ons' );
    } else {
       add_submenu_page( 'options-general.php', __( 'When Last Login', 'when-last-login' ), __( 'When Last Login', 'when-last-login' ), 'manage_options', 'when-last-login-settings', array( $this, 'wll_settings_callback' ) );
    }
     
      
      do_action( 'wll_settings_admin_menu_item' );

    }

    public function wll_settings_callback(){

      include WLL_DIR_PATH . '/includes/settings.php';

    }

    public function wll_settings_page_head(){

      $wll_settings = array();

      if( isset( $_POST['wll_save_settings'] ) ){

        if( wp_verify_nonce( $_POST['_nonce'], 'wll_settings_nonce' ) ) {

          $wll_settings['user_access'] = isset( $_POST['wll_login_record_user_access'] ) ? sanitize_text_field( $_POST['wll_login_record_user_access'] ) : "";
          $wll_settings['record_ip_address'] = isset( $_POST['wll_record_user_ip_address'] ) && sanitize_text_field( $_POST['wll_record_user_ip_address'] ) == '1'  ? 1 : 0;
          $wll_settings['show_all_login_records'] = isset( $_POST['wll_all_login_records'] ) && sanitize_text_field( $_POST['wll_all_login_records'] ) == '1'  ? 1 : 0;

          $wll_settings = apply_filters( 'wll_settings_filter', $wll_settings );

          if ( update_option( 'wll_settings', $wll_settings ) ) {
            //show admin notice here.
            add_action( 'admin_notices', array( $this, 'wll_admin_notices' ) );
          }
        } else {
          die( 'nonce not valid' );
        }

      }

    }

    public function wll_admin_notices() {
    ?>
      <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e( 'Settings saved successfully.', 'when-last-login' ); ?></p>
      </div>
    <?php
    }

    public function wll_remove_records_notice__success() {
    ?>
      <div class="notice notice-success is-dismissible">
        <p><?php esc_html_e( 'Records have been removed successfully.', 'when-last-login' ); ?></p>
      </div>
    <?php
    }

    public function wll_remove_records_notice__warning() {
    ?>
      <div class="notice notice-warning is-dismissible">
        <p><?php esc_html_e( 'No old records to remove.', 'when-last-login' ); ?></p>
      </div>
    <?php
    }

    /**
     * Function to remove logs automatically older than 3 months.
     * @since 1.0.0
     */
    public function wll_automatically_remove_logs() {
        global $pagenow;

        if( !empty( $_GET['wll_records_action'] ) && $_GET['wll_records_action'] == 'delete' ){

          if( isset( $_GET['wll_record'] ) ){            
            foreach( $_GET['wll_record'] as $key => $val ){
              $this->delete_record( intval( $val ) );
            }
          }

        }

        if( !empty( $_GET['action'] ) && $_GET['action'] == 'delete_records' ){

          if( isset( $_GET['record'] ) ){
            $this->delete_record( intval( $_GET['record'] ) );
          }

        }

        // Bail if not on our settings page.
        if ( 'admin.php' == $pagenow && 'when-last-login-settings' != $_GET['page'] ) {
          return;
        }

        global $wpdb;

        if( !empty( $_GET['clearout'] ) && $_GET['clearout'] == 'version2' ){

            $login_records = $wpdb->prefix.'wll_login_records';

            $sql = "DELETE FROM ".$login_records;

            if ( isset( $_REQUEST['remove_all_wll_records'] ) ) {
                $nonce = $_REQUEST['wll_remove_all_records_nonce'];
                if ( wp_verify_nonce( $nonce, 'wll_remove_all_records_nonce' ) ) {
                    if ( $wpdb->query( $sql ) > 0 ) {
                        add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
                    } else {
                        add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
                    }
                } else {
                    die( 'nonce not valid.' );
                }
            }

            if ( isset( $_REQUEST['remove_wll_records'] ) ) {
                $nonce = $_REQUEST['wll_remove_records_nonce'];
                if ( wp_verify_nonce( $nonce, 'wll_remove_records_nonce' ) ) {
                    $date = apply_filters( 'wll_automatically_remove_logs_date', date( 'Y-m-d', strtotime( '-3 months' ) ) );
                    $sql .= " WHERE date <= '$date'";
                    if ( $wpdb->query( $sql ) > 0 ) {
                        add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
                    } else {
                        add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
                    }
                } else {
                    die( 'nonce not valid.' );
                } 
            }

            if ( isset( $_REQUEST['remove_wll_ip_addresses'] ) ) {
                $nonce = $_REQUEST['wll_remove_ip_nonce'];
                if ( wp_verify_nonce( $nonce, 'wll_remove_ip_nonce' ) ) {
                    $records = $this->get_records();
                    $login_records = $wpdb->prefix.'wll_login_records';
                    $failed = false;
                    if( !empty( $records ) ){
                        foreach( $records as $r ){
                            $updated = $wpdb->query( "UPDATE ".$login_records." SET `ip` = '' WHERE `id` = '".$r->id."'");
                            if( !$updated ){
                                $failed = true;
                            }
                        }
                    }
                    if ( $failed ) {
                        add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
                    } else {
                        add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
                    }
                } else {
                    die( 'nonce not valid.' );
                }
            }

        } else {

            $sql = "DELETE p, pm FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID WHERE p.post_type = 'wll_records'";

            if ( isset( $_REQUEST['remove_all_wll_records'] ) ) {

              $nonce = $_REQUEST['wll_remove_all_records_nonce'];
              if ( wp_verify_nonce( $nonce, 'wll_remove_all_records_nonce' ) ) {

                if ( $wpdb->query( $sql ) > 0 ) {
                  add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
                } else {
                  add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
                }
              } else {
                die( 'nonce not valid.' );
              }
            }

            if ( isset( $_REQUEST['remove_wll_records'] ) ) {

              $nonce = $_REQUEST['wll_remove_records_nonce'];
              if ( wp_verify_nonce( $nonce, 'wll_remove_records_nonce' ) ) {

                $date = apply_filters( 'wll_automatically_remove_logs_date', date( 'Y-m-d', strtotime( '-3 months' ) ) );

                $sql .= " AND p.post_date <= '$date'";

                if ( $wpdb->query( $sql ) > 0 ) {
                  add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
                } else {
                  add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
                }
              } else {
                die( 'nonce not valid.' );
              } 
            }

            if ( isset( $_REQUEST['remove_wll_ip_addresses'] ) ) {

              $nonce = $_REQUEST['wll_remove_ip_nonce'];
              if ( wp_verify_nonce( $nonce, 'wll_remove_ip_nonce' ) ) {

                $sql = "DELETE FROM $wpdb->usermeta WHERE meta_key = 'wll_user_ip_address'";

                if ( $wpdb->query( $sql ) > 0 ) {
                  add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__success' ) );
                } else {
                  add_action( 'admin_notices', array( $this, 'wll_remove_records_notice__warning' ) );
                }
              } else {
                die( 'nonce not valid.' );
              }
            }

        }
              
    }


    public function wll_records_columns( $columns ){

      return array_merge( $columns, array( 'wll-ip-address' => esc_html__( 'IP Address', 'when-last-login' ) ) );

    }

    public function wll_records_column_contents( $column, $post_id ){

      switch ( $column ) {
        case 'wll-ip-address':
          $ip_address = get_post_meta( $post_id, 'wll_user_ip_address', true );
          if ( ! empty( $ip_address ) && $ip_address != "" ) {
            echo "<a href='http://www.ip-adress.com/ip_tracer/". esc_attr( $ip_address ) ."' target='_BLANK' title='".__( 'Lookup', 'when-last-login' )."'>" . esc_html( $ip_address ) . "</a>";
          } else {
            esc_html_e( 'IP Address Not Recorded', 'when-last-login' );
          }
          break;

      }
    }

    public function wll_plugin_action_links( $links ) {
      $new_links = array(
        '<a href="' . admin_url('admin.php?page=when-last-login-settings') . '" title="' . esc_attr( __( 'View Settings', 'when-last-login' ) ) . '">' . esc_html__( 'Settings', 'when-last-login' ) . '</a>'
      );

      $new_links = apply_filters( 'wll_plugin_action_links', $new_links );

      return array_merge( $new_links, $links );
    }

    public function wll_plugin_row_meta( $links, $file ) {
      if ( strpos( $file, 'when-last-login.php' ) !== false ) {
        $new_links = array(
          '<a href="' . admin_url('admin.php?page=when-last-login-settings') . '" title="' . esc_attr( __( 'View Settings', 'when-last-login' ) ) . '">' . __( 'Settings', 'when-last-login' ) . '</a>',
          '<a href="' . esc_url( 'https://yoohooplugins.com/?s=when+last+login' ) . '" title="' . esc_attr__( 'View Documentation', 'when-last-login' ) . '">' . esc_html__( 'Docs', 'when-last-login' ) . '</a>',
          '<a href="' . esc_url( 'https://yoohooplugins.com/support/' ) . '" title="' . esc_attr__( 'Visit Customer Support Forum', 'when-last-login' ) . '">' . esc_html__( 'Support', 'when-last-login' ) . '</a>',
        );

        $new_links = apply_filters( 'wll_plugin_row_meta', $new_links );
        $links = array_merge( $links, $new_links );
      }
      return $links;
    }

    public static function wll_get_user_ip_address(){

      if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ){
        $ip = $_SERVER['HTTP_CLIENT_IP'];
      } else if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = $_SERVER['REMOTE_ADDR'];
      } else {
          $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
      }

      $ip = apply_filters( 'wll_user_ip_address', $ip );

      if ( apply_filters( 'wll_force_anon_ip', false ) ) {
        return $ip;
      } else {
        return IpAnonymizer::anonymizeIp( $ip );
      }
     
      // return anon IP if code reaches here.
      return IpAnonymizer::anonymizeIp( $ip );
    }

} // end class
When_Last_Login::get_instance();
