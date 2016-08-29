<?php

// If uninstall is not called from WordPress, exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

$users_id = get_users( array(
  'fields' => 'ID'
) );

foreach( $users_id as $user_id ){
  delete_user_meta( $user_id, 'when_last_login' );
  delete_user_meta( $user_id, 'when_last_login_count' );
}
