<div class='wrap'>
	<h2><?php _e('All Login Records', 'when-last-login' ); ?></h2>
	<p><?php _e('When Last Login Records - Version 2', 'when-last-login' ); ?></p>
	<form method='GET'>
		<input type='hidden' name='page' value='all-login-records' />
		<select name='wll_records_action'>
			<option value=''><?php _e('Select an Action', 'when-last-login'); ?></option>
			<option value='delete'><?php _e('Delete', 'when-last-login'); ?></option>
		</select><input type='submit' class='button' value='<?php _e('Submit', 'when-last-login'); ?>' />
		<br/>
		<br/>
		<table class='wp-list-table widefat fixed striped table-view-list pages'>
			<thead>
				<tr>
					<td style='width: 30px;'><input type='checkbox' style='margin: 0;' class='wll_records_select_all' /></td>
					<td><?php _e('Title', 'when-last-login'); ?></td>
					<td><?php _e('Author', 'when-last-login'); ?></td>
					<td><?php _e('Date', 'when-last-login'); ?></td>
					<td><?php _e('IP Address', 'when-last-login'); ?></td>
				</tr>
			</thead>
			<tbody>
				<?php

					$page = ( !empty( $_GET['wll_record_page'] ) ) ? intval( $_GET['wll_record_page'] ) : 1;

					$records = $this->get_records( $page );

					if( !empty( $records ) ){
						foreach( $records as $r ){
							$author = get_user_by( 'id', intval( $r->author ) );
							?>
							<tr>
								<td><input type='checkbox' name='wll_record[]' class='wll_record_checkboxes' value='<?php echo $r->id; ?>' /></td>
								<td><?php echo $r->title; ?><br/><a href='<?php echo admin_url( 'admin.php?page=all-login-records&action=delete_records&record='.$r->id ); ?>' onclick="return confirm('Are you sure you want to delete this record?')"><?php _e('Delete', 'when-last-login'); ?></a></td>
								<td><?php echo $author->display_name; ?></td>
								<td><?php echo $r->date; ?></td>
								<td><?php echo $r->ip; ?></td> 
							</tr>
							<?php
						}
					} else {
						?>
						<tr>
							<td colspan='4'><?php _e('No Records Found', 'when-last-login'); ?></td>
						</tr>
						<?php
					}
				?>
			</tbody>
			<tfooter>
				<tr>
					<td style='width: 30px;'><input type='checkbox' style='margin: 0;' class='wll_records_select_all' /></td>
					<td><?php _e('Title', 'when-last-login'); ?></td>
					<td><?php _e('Author', 'when-last-login'); ?></td>
					<td><?php _e('Date', 'when-last-login'); ?></td>
					<td><?php _e('IP Address', 'when-last-login'); ?></td>
				</tr>
			</tfooter>
		</table>
	</form>
	<br/>
	<div style='display: block; text-align: right;'><?php echo $this->build_pagination(); ?></div>
</div>