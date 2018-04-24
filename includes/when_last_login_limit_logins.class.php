<?php

class WLLLimitLoginAttempts{

	public function __construct(){

		add_action( 'wp_login_failed', array( $this, 'login_attempt_failed' ) );
		add_action( 'login_redirect', array( $this, 'login_attempt' ), 10, 3 );
		add_filter( 'login_errors', array( $this, 'login_attempt_message' ) );
		add_filter( 'login_message', array( $this, 'login_attempt_message_login' ) );
	}	

	private function get_login_attempts(){

		if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ){
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'wll_login_attempts';

		$login_timeframe = apply_filters( 'wll_login_limit_timeframe', 10 );

		$current_time = strtotime( current_time( 'mysql') . ' - '.$login_timeframe.' MINUTE' );
		
		$sql = "SELECT count(*) as attempts FROM `$table_name` WHERE `ip_address` = '$ip' AND `time_slot` > $current_time";

		$results = $wpdb->get_row( $sql );

		$login_attempts = intval( $results->attempts );

		return $login_attempts;

	}

	public function login_attempt( $redirect, $request, $user ){

		$login_attempts = $this::get_login_attempts();

		if( isset( $user ) ){
	
			if( $login_attempts >= 3 ){
				
				$redirect = wp_login_url();

				return $redirect;

			} else {

				return $redirect;

			}

		} else {

			if( $login_attempts >= 3 ){
				
				$redirect = wp_login_url();

				return $redirect;

			} else {

				return $redirect;

			}
		}
	}

	public function login_attempt_message( $message ){
		
		$login_attempts = $this->get_login_attempts();

		$login_timeframe = apply_filters( 'wll_login_limit_timeframe', 10 );

		$login_limit = apply_filters( 'wll_login_limit_value', 3 );

		if( $login_attempts >= $login_limit ){

			$message .= "<br/><p><strong>Login Attempt Blocked</strong> You have been blocked for $login_timeframe minutes. Please try again later.</p><br/>";

		}

		return $message;

	}

	public function login_attempt_message_login( $message ){

		$login_attempts = $this->get_login_attempts();

		$login_timeframe = apply_filters( 'wll_login_limit_timeframe', 10 );

		$login_limit = apply_filters( 'wll_login_limit_value', 3 );

		if( $login_attempts >= $login_limit ){

			$message .= "<div class='message'><p><strong>Login Attempt Blocked</strong> You have been blocked for $login_timeframe minutes. Please try again later.</p></div>";

		}

		return $message;

	}

	public function login_attempt_failed( $username ){

		add_filter( 'login_errors', function( $error ) {

			$login_timeframe = apply_filters( 'wll_login_limit_timeframe', 10 );

			$login_limit = apply_filters( 'wll_login_limit_value', 3 );

			$login_attempts = $this::get_login_attempts();
			
			if( $login_attempts < $login_limit ){
				$error .= "<br/><strong>Login Attempt Blocked</strong> After $login_limit incorrect attempts you will be blocked for $login_timeframe minutes.";
			}

			return $error;

		} );

		global $wpdb;

		$table_name = $wpdb->prefix . 'wll_login_attempts';

		if( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ){
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} else if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ){
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		$wpdb->insert( 
			$table_name, 
			array( 
				'username' => $username,
				'ip_address' => $ip,
				'time_slot' => current_time( 'timestamp' )
			), 
			array( 
				'%s', 
				'%s', 
				'%s'
			) 
		);

	}

}

new WLLLimitLoginAttempts();
