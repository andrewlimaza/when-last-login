<?php
/*
Plugin Name: When Last Login
Plugin URI: https://wordpress.org/plugins/when-last-login/
Description: Adds functionality to your WordPress install to show when a user last logged in.
Version: 0.3
Author: Arctek Technologies (Pty) Ltd
Author URI: http://www.whenlastlogin.com
Text Domain: when-last-login
Domain Path: /languages

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
      define('WHEN_LAST_LOGIN_BNAME', plugin_basename(__FILE__));

      add_action( 'init', array( 'When_Last_Login', 'init' ));
      add_action( 'plugins_loaded', array( 'When_Last_Login', 'text_domain' ));

      //Create the custom meta upon login
      add_action( 'wp_login', array( 'When_Last_Login', 'last_login'), 10, 2);

      //Setting up columns.
      add_filter( 'manage_users_columns', array( 'When_Last_Login', 'column_header'), 10, 1);
      add_action( 'manage_users_custom_column', array( 'When_Last_Login', 'column_data'), 15, 3);
      add_filter( 'manage_users_sortable_columns', array( 'When_Last_Login', 'column_sortable' ));
      add_action( 'pre_get_users', array('When_Last_Login', 'sort_by_login_date'));

      //Integration for Paid Memberships Pro
      add_action('pmpro_memberslist_extra_cols_header', array( 'When_Last_Login', 'pmpro_memberlist_add_header' ));
      add_action('pmpro_memberslist_extra_cols_body', array('When_Last_Login', 'pmpro_memberlist_add_column_data' ));

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

     public static function last_login( $user_login, $users ){
       //get/update user meta 'when_last_login' on login and add time() to it.
       update_user_meta( $users->ID, 'when_last_login', time() );
     }

     /*
     * Setup Column and data for users page with sortable
     */
     public static function column_header( $column ){
       $column['when_last_login'] = __( 'Last Login', 'when-last-login' );
       return $column;
     }

     public static function column_data( $value, $column_name, $id ){
      if ($column_name == 'when_last_login'){
        $when_last_login_meta = get_the_author_meta('when_last_login', $id);

        if(!empty($when_last_login_meta)){

          return human_time_diff($when_last_login_meta);

        }else{

          if(get_the_author_meta('when_last_login', $id) === 0){
            return __( 'Never', 'when-last-login' );
          }else{
            update_user_meta( $id, 'when_last_login', 0);
            return __( 'Never', 'when-last-login' );
          }

        }
      }
     }

     public static function column_sortable( $columns ){
      $columns['when_last_login'] = 'when_last_login';
      return $columns;
     }

    public static function sort_by_login_date( $query ) {
      if ( 'when_last_login' == $query->get( 'orderby' ) ) {
        $query->set( 'orderby', 'meta_value_num' );
        $query->set( 'meta_key', 'when_last_login');
      }
    }

     /*
     * Support for Paid Memberships Pro
     */
     public static function pmpro_memberlist_add_header( $users ){
       if( !defined('PMPRO_VERSION') ){
         return;
       }
?>
      <th><?php _e( 'Last Login', 'when-last' );?></th>
<?php
     }

     public static function pmpro_memberlist_add_column_data( $users ){
       if( !defined('PMPRO_VERSION') ){
         return;
       }
?>
      <td>
<?php
      if(!empty( $users->when_last_login )){
        echo human_time_diff( $users->when_last_login );
      }else{
        return _e('Never', 'when-last-login');
      }
?>
      </td>
<?php
     }

} // end class
When_Last_Login::get_instance();
