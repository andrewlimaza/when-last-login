<?php
/*
Plugin Name: When Last Login
Plugin URI: https://wordpress.org/plugins/when-last-login/
Description: Adds functionality to WordPress to show when a user last logged in.
Version: 0.6
Author: Andrew Lima
Author URI: https://www.whenlastlogin.com
Text Domain: when-last-login
Domain Path: /languages

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

      //Admin actions
      add_action( 'wp_dashboard_setup', array( $this, 'admin_dashboard_widget' ) );
      add_action( 'admin_notices', array( $this, 'update_notice' ) );
      add_action( 'wp_ajax_save_update_notice', array( $this, 'save_update_notice' ) );

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
      } // end constructor

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
   }

    public static function save_update_notice(){
      //update the hide notice option
      update_option( 'wll_notice_hide_1', '1' );
    }

    public static function load_js_for_notice(){
      if( get_option( 'wll_notice_hide_1' ) !== '1'){
        wp_enqueue_script( 'wll_notice_update', plugins_url( 'js/notice-update.js', __FILE__ ), array( 'jquery' ), '1.0', false );
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

        wp_insert_post( $args );
      }

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
         'show_in_menu'       => 'users.php',
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

      $user_query = new WP_User_Query( array( 'meta_key' => 'when_last_login_count', 'meta_value' => 0, 'meta_compare' => '!=', 'order' => 'ASC', 'oderby' => 'meta_value', 'number' => 3 ) );

      $topusers = $user_query->get_results();

       if( $topusers ){
?>
         <table width="100%" text-align="center">
           <tr>
             <td><b><span style="font-size:18px;"><?php _e( 'Users', 'when-last-login' ); ?></span></b></td>
             <td><b><span style="font-size:18px;"><?php _e( 'Login Count', 'when-last-login' ); ?></span><b></td>
           </tr>
<?php
         foreach($topusers as $wllusers){
            echo '<tr><td>' . $wllusers->display_name . '</td>';
            echo '<td>' . get_user_meta( $wllusers->ID, 'when_last_login_count', true ) . '</td></tr>';
        }
?>
      </table>
      <br/>
      <a href="<?php echo admin_url( 'users.php?orderby=when_last_login&order=desc' ); ?>"><?php _e( 'View All Users', 'when-last-login' ); ?></a>

      <?php if( $show_login_records == true ){ ?>
      <a style="float:right" href="<?php echo admin_url( 'edit.php?post_type=wll_records' ); ?>"><?php _e( 'View Login Records', 'when-last-login' ); } //end the if filter check here ?></a>
<?php
      }else{
       echo 'No data yet';
     }
}

     /**
     * Setup Column and data for users page with sortable
     */
     public static function column_header( $column ){
       $column['when_last_login'] = __( 'Last Login', 'when-last-login' );
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

} // end class
When_Last_Login::get_instance();
