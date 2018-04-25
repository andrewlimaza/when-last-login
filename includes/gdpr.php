<?php

/**
 * Some GDPR stuff
 */

/**
 * Add a checkbox to the user registration form for consent to track login / IP Address data.
 */
class When_Last_Login_GDPR{

	public function __construct() {
		add_action( 'register_form', array( $this, 'add_checkbox_to_registration' ) );
		add_action( 'user_register', array( $this, 'save_checkbox_registration_consent' ) );

		add_action( 'show_user_profile', array( $this, 'add_consent_to_user_profile' ) );
		add_action( 'personal_options_update', array( $this, 'update_consent_from_user_profile' ) );

	}

	public static function add_checkbox_to_registration(){
			?>
				<input type="checkbox" name="wll_consent_to_track" /><?php echo $settings['registration_consent_text']; ?><br/>
			<?php
	}

	public static function save_checkbox_registration_consent( $user_id ) {
		if ( isset( $_POST['wll_consent_to_track'] ) && ! empty( $_POST['wll_consent_to_track'] ) ) {
			$consent = '1';

			update_user_meta( $user_id, 'wll_consent_to_track', $consent );
			update_user_meta( $user_id, 'wll_consent_to_track_date', date( 'Y-m-d H:i:s' ) );
		}
	}

	public static function add_consent_to_user_profile( $user ){
		$settings = get_option( 'wll_settings' );

		//only show this if consent for GDPR is enabled.
		if ( 1 != $settings['gdpr_consent'] ) {
			return;
		}

		// $consent = intval( get_user_meta( $user->ID, 'wll_consent_to_track', true ) );
		$consent = intval( get_user_meta( $user->ID, 'wll_consent_to_track', true ) );
		$consent_date = get_user_meta( $user->ID, 'wll_consent_to_track_date', true );

		?>
			<table class="form-table">
				<tr>
					<th><?php _e( 'Login tracking', 'when-last-login' ); ?></th>
					<td>
						<input type="checkbox" name="wll_consent_to_track" <?php echo checked( 1,$consent ); ?> > <?php echo $settings['registration_consent_text' ]; ?>
						<small>(<?php _e( 'Date of consent', 'when-last-login' ); ?> - <?php echo $consent_date; ?>)</small>
					</td>
				</tr>


			</table>
		<?php
	}

	public static function update_consent_from_user_profile( $user_id ) {
		if( isset( $_POST['wll_consent_to_track'] ) && ! empty( $_POST['wll_consent_to_track'] ) ) {
			$consent = '1';
			update_user_meta( $user_id, 'wll_consent_to_track', $consent );
			update_user_meta( $user_id, 'wll_consent_to_track_date', date( 'Y-m-d H:i:s') );
		}else{
			delete_user_meta( $user_id, 'wll_consent_to_track' );
			delete_user_meta( $user_id, 'wll_consent_to_track_date' );
		}

	}
} // end of class.

$wll_gdpr = new When_Last_Login_GDPR();
