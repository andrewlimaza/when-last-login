<?php $settings = get_option( 'wll_settings' ); ?>
<!-- <tr>
	<th><?php _e('Preferred user role to access login records', 'when-last-login'); ?></th>
	<td>
		<select name='wll_login_record_user_access'>
			<?php 

				if( $settings == 'all' ){
					$selected = 'all';
				} else {
					if( isset( $settings['user_access'] ) ){
						$selected = $settings['user_access'];	
					} else {
						$selected = '';
					}
				}
			?>
			<option value='all' <?php selected( 'all', $selected ); ?>><?php _e('All User Roles', 'when-last-login-slack-notifications'); ?></option>
			<?php wp_dropdown_roles( $selected ); ?>
		</select>
	</td>
</tr> -->
<?php if( isset( $settings['record_ip_address'] ) && $settings['record_ip_address'] == 1 ){ $checked = 1; } else { $checked = 0; } ?>
<tr>
	<th><?php _e('Record user\'s IP address when logging in', 'when-last-login'); ?></th>
	<td><input type='checkbox' value='1' name='wll_record_user_ip_address' <?php checked( 1, $checked ); ?>/></td>
</tr>

<tr>
    <th><input type="submit" name="wll_save_settings"  class="button-primary" value="<?php _e('Save Settings', 'when-last-login'); ?>" /></th>
    <td></td>
</tr>