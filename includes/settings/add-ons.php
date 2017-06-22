<?php

$content = get_transient( 'when_last_login_add_ons_page' );

if ( false === $content || $content == '' ) {

    $url = 'https://yoohooplugins.com/api/add-ons-when-last-login/v1/products.php';

    $add_ons_request = wp_remote_get( esc_url_raw( $url ), array( 'sslverify' => false ) );

    if ( ! is_wp_error( $add_ons_request ) ) {

        if ( isset( $add_ons_request['body'] ) && strlen( $add_ons_request['body'] ) > 0 ) {
            
            $content = wp_remote_retrieve_body( $add_ons_request );

            set_transient( 'when_last_login_add_ons_page', $content, 3600 );
        
        }

    } else {

        $content = '<div class="error"><p>' . __( 'An error occurred while retrieving the extensions list from the server. Please try again later.', 'when-last-login' ) . '</div>';
    
    }

}

echo $content;

