<?php

// If uninstall is not called from WordPress, exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

global $wpdb;

$users_id = get_users( array(
  'fields' => 'ID'
) );

foreach( $users_id as $user_id ){
  delete_user_meta( $user_id, 'when_last_login' );
  delete_user_meta( $user_id, 'when_last_login_count' );
}

//Delete CPT's from databse if you uninstall When Last Login.
$sql = "DELETE FROM $wpdb->posts WHERE post_type='wll_records'";
$wpdb->query( $sql );

//Delete custom table if it exists
$delete_table = $wpdb->prefix . 'wll_login_attempts' ;
$sql = "DROP TABLE `$delete_table`";
$wpdb->query( $sql );

$sqlQuery = "DELETE FROM $wpdb->options WHERE option_name LIKE 'wll%'";
$wpdb->query($sqlQuery);
