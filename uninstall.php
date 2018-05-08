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
  delete_user_meta( $user_id, 'wll_consent_to_track' );
  delete_user_meta( $user_id, 'wll_consent_to_track_date' );
}

//Delete CPT's from databse if you uninstall When Last Login and Post Meta.
$sql = "DELETE p, pm FROM $wpdb->posts p INNER JOIN $wpdb->postmeta pm ON pm.post_id = p.ID WHERE p.post_type = 'wll_records'";
$wpdb->query( $sql );

//Delete custom table if it exists
$delete_table = $wpdb->prefix . 'wll_login_attempts' ;
$sql = "DROP TABLE IF EXISTS `$delete_table`";
$wpdb->query( $sql );

$sqlQuery = "DELETE FROM $wpdb->options WHERE option_name LIKE 'wll%'";
$wpdb->query($sqlQuery);
