<?php

$page_content = "";

delete_transient( 'when_last_login_add_ons_page' );

$content = get_transient( 'when_last_login_add_ons_page' );

if ( false === $content || $content == '' ) {

    $url = 'https://yoohooplugins.com/api/add-ons/products.php';

    $add_ons_request = wp_remote_get( esc_url_raw( $url ), array( 'sslverify' => false ) );

    if ( ! is_wp_error( $add_ons_request ) ) {

        if ( isset( $add_ons_request['body'] ) && strlen( $add_ons_request['body'] ) > 0 ) {
            
            $content = wp_remote_retrieve_body( $add_ons_request );

            set_transient( 'when_last_login_add_ons_page', $content, 3600 );
        
        }

    } else {

        $page_content = '<div class="error"><p>' . __( 'An error occurred while retrieving the extensions list from the server. Please try again later.', 'when-last-login' ) . '</div>';
    
    }

}

if( $content != "" && false !== $content ){

	$content_array = json_decode( $content, TRUE );

	if( is_array( $content_array ) ){

		foreach( $content_array as $group ){

			if( !empty( $group ) && is_array( $group ) ){

				foreach( $group as $plugin_cat => $plugins ){

					if( $plugin_cat == 'when-last-login' && !empty( $plugins ) && is_array( $plugins ) ){

						foreach( $plugins as $plugin ){

							$page_content .= "<div class='wll_add_ons_single_container'>";

							$page_content .= "<div class='wll_add_ons_image'><img src='".$plugin['image']."' /></div>";

							$page_content .= "<div class='wll_add_ons_title'>".$plugin['name']."</div>";

							$page_content .= "<div class='wll_add_ons_description'>".$plugin['description']."</div>";

							$page_content .= "<div class='wll_add_ons_button'><a class='button' target='_BLANK' href='".$plugin['url']."'>".__('Get Add-on', 'when-last-login')."</a></div>";

							$page_content .= "</div>";

						}

					}

				}

			}

		}

	}

}

echo $page_content;


?>