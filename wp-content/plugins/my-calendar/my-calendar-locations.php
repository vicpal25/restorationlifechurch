<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! empty( $_SERVER['SCRIPT_FILENAME'] ) && 'my-calendar-locations.php' == basename( $_SERVER['SCRIPT_FILENAME'] ) ) {
	die ( 'Please do not load this page directly. Thanks!' );
}

function mc_update_location_controls() {
	if ( isset( $_POST['mc_locations'] ) && $_POST['mc_locations'] == 'true' ) {
		$nonce = $_POST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			wp_die( "Invalid nonce" );
		}
		$locations            = $_POST['mc_location_controls'];
		$mc_location_controls = array();
		foreach ( $locations as $key => $value ) {
			$mc_location_controls[ $key ] = mc_csv_to_array( $value[0] );
		}
		update_option( 'mc_location_controls', $mc_location_controls );
		echo "<div class='notice updated'><p>" . __( 'Location Controls Updated', 'my-calendar' ) . "</p></div>";
	}
}

function mc_mass_delete_locations() {
	global $wpdb;
	$mcdb = $wpdb;
	// mass delete locations
	if ( ! empty( $_POST['mass_edit'] ) && isset( $_POST['mass_delete'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( "Security check failed" );
		}
		$locations = $_POST['mass_edit'];
		$i         = $total = 0;
		$deleted   = $ids = array();
		foreach ( $locations as $value ) {
			$total     = count( $locations );
			$ids[]     = (int) $value;
			$deleted[] = $value;
			$i ++;
		}
		$statement = implode( ',', $ids );
		$sql       = 'DELETE FROM ' . my_calendar_locations_table() . " WHERE location_id IN ($statement)";
		$result    = $mcdb->query( $sql );
		if ( $result !== 0 && $result !== false ) {
			mc_delete_cache();
			// argument: array of event IDs
			do_action( 'mc_mass_delete_locations', $deleted );
			$message = "<div class='updated'><p>" . sprintf( __( '%1$d locations deleted successfully out of %2$d selected', 'my-calendar' ), $i, $total ) . "</p></div>";
		} else {
			$message = "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong>" . __( 'Your locations have not been deleted. Please investigate.', 'my-calendar' ) . "</p></div>";
		}
		echo $message;
	}
}

function mc_insert_location( $add ) {
	global $wpdb;
	$mcdb    = $wpdb;
	$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%s', '%s' );
	$results = $mcdb->insert( my_calendar_locations_table(), $add, $formats );

	return $results;
}

function mc_modify_location( $update, $where ) {
	global $wpdb;
	$mcdb    = $wpdb;
	$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%s', '%s' );
	$results = $mcdb->update( my_calendar_locations_table(), $update, $where, $formats, '%d' );

	return $results;
}

function my_calendar_manage_locations() {
	global $wpdb;
	$mcdb = $wpdb;
	?>
	<div class="wrap jd-my-calendar">
	<?php my_calendar_check_db();
	// We do some checking to see what we're doing
	mc_update_location_controls();
	mc_mass_delete_locations();
	if ( ! empty( $_POST ) && ( ! isset( $_POST['mc_locations'] ) && ! isset( $_POST['mass_delete'] ) ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( "Security check failed" );
		}
	}
	if ( isset( $_POST['mode'] ) && $_POST['mode'] == 'add' ) {
		$add     = array(
			'location_label'     => $_POST['location_label'],
			'location_street'    => $_POST['location_street'],
			'location_street2'   => $_POST['location_street2'],
			'location_city'      => $_POST['location_city'],
			'location_state'     => $_POST['location_state'],
			'location_postcode'  => $_POST['location_postcode'],
			'location_region'    => $_POST['location_region'],
			'location_country'   => $_POST['location_country'],
			'location_url'       => $_POST['location_url'],
			'location_longitude' => $_POST['location_longitude'],
			'location_latitude'  => $_POST['location_latitude'],
			'location_zoom'      => $_POST['location_zoom'],
			'location_phone'     => $_POST['location_phone'],
			'location_phone2'    => $_POST['location_phone2'],
			'location_access'    => serialize( $_POST['location_access'] )
		);
		$results = mc_insert_location( $add );
		do_action( 'mc_save_location', $results, $add );
		if ( $results ) {
			echo "<div class=\"updated\"><p><strong>" . __( 'Location added successfully', 'my-calendar' ) . "</strong></p></div>";
		} else {
			echo "<div class=\"error\"><p><strong>" . __( 'Location could not be added to database', 'my-calendar' ) . "</strong></p></div>";
		}
	} else if ( isset( $_GET['location_id'] ) && $_GET['mode'] == 'delete' ) {
		$sql     = "DELETE FROM " . my_calendar_locations_table() . " WHERE location_id=" . (int) ( $_GET['location_id'] );
		$results = $mcdb->query( $sql );
		do_action( 'mc_delete_location', $results, (int) $_GET['location_id'] );
		if ( $results ) {
			echo "<div class=\"updated\"><p><strong>" . __( 'Location deleted successfully', 'my-calendar' ) . "</strong></p></div>";
		} else {
			echo "<div class=\"error\"><p><strong>" . __( 'Location could not be deleted', 'my-calendar' ) . "</strong></p></div>";
		}
	} else if ( isset( $_GET['mode'] ) && isset( $_GET['location_id'] ) && $_GET['mode'] == 'edit' && ! isset( $_POST['mode'] ) ) {
		$cur_loc = (int) $_GET['location_id'];
		mc_show_location_form( 'edit', $cur_loc );
	} else if ( isset( $_POST['location_id'] ) && isset( $_POST['location_label'] ) && $_POST['mode'] == 'edit' ) {
		$update  = array(
			'location_label'     => $_POST['location_label'],
			'location_street'    => $_POST['location_street'],
			'location_street2'   => $_POST['location_street2'],
			'location_city'      => $_POST['location_city'],
			'location_state'     => $_POST['location_state'],
			'location_postcode'  => $_POST['location_postcode'],
			'location_region'    => $_POST['location_region'],
			'location_country'   => $_POST['location_country'],
			'location_url'       => $_POST['location_url'],
			'location_longitude' => $_POST['location_longitude'],
			'location_latitude'  => $_POST['location_latitude'],
			'location_zoom'      => $_POST['location_zoom'],
			'location_phone'     => $_POST['location_phone'],
			'location_phone2'    => $_POST['location_phone2'],
			'location_access'    => serialize( $_POST['location_access'] )
		);
		$where   = array(
			'location_id' => (int) $_POST['location_id']
		);
		$results = mc_modify_location( $update, $where );
		if ( $results === false ) {
			echo "<div class=\"error\"><p><strong>" . __( 'Location could not be edited.', 'my-calendar' ) . "</strong></p></div>";
		} else if ( $results == 0 ) {
			echo "<div class=\"updated error\"><p><strong>" . __( 'Location was not changed.', 'my-calendar' ) . "</strong></p></div>";
		} else {
			echo "<div class=\"updated\"><p><strong>" . __( 'Location edited successfully', 'my-calendar' ) . "</strong></p></div>";
		}
		$cur_loc = (int) $_POST['location_id'];
		mc_show_location_form( 'edit', $cur_loc );

	}

	if ( isset( $_GET['mode'] ) && $_GET['mode'] != 'edit' || isset( $_POST['mode'] ) && $_POST['mode'] != 'edit' || ! isset( $_GET['mode'] ) && ! isset( $_POST['mode'] ) ) {
		mc_show_location_form( 'add' );
	}
}

function mc_show_location_form( $view = 'add', $curID = '' ) {
	global $wpdb;
	$mcdb    = $wpdb;
	$cur_loc = false;
	if ( $curID != '' ) {
		$sql     = "SELECT * FROM " . my_calendar_locations_table() . " WHERE location_id=$curID";
		$cur_loc = $mcdb->get_row( $sql );
	}
	$has_data = ( empty( $cur_loc ) ) ? false : true;
	if ( $view == 'add' ) {
		?>
		<h2><?php _e( 'Add New Location', 'my-calendar' ); ?></h2>
	<?php } else { ?>
		<h2><?php _e( 'Edit Location', 'my-calendar' ); ?></h2>
	<?php } ?>
	<div class="postbox-container jcd-wide">
		<div class="metabox-holder">

			<div class="ui-sortable meta-box-sortables">
				<div class="postbox">
					<h3><?php _e( 'Location Editor', 'my-calendar' ); ?></h3>

					<div class="inside location_form">
						<form id="my-calendar" method="post"
						      action="<?php echo admin_url( "admin.php?page=my-calendar-locations" ); ?>">
							<div><input type="hidden" name="_wpnonce"
							            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
							<?php if ( $view == 'add' ) { ?>
								<div>
									<input type="hidden" name="mode" value="add"/>
									<input type="hidden" name="location_id" value=""/>
								</div>
							<?php } else { ?>
								<div>
									<input type="hidden" name="mode" value="edit"/>
									<input type="hidden" name="location_id"
									       value="<?php echo $cur_loc->location_id ?>"/>
								</div>
							<?php
							}
							echo mc_locations_fields( $has_data, $cur_loc, 'location' );
							?>
							<p>
								<input type="submit" name="save" class="button-primary"
								       value="<?php if ( $view == 'edit' ) {
									       _e( 'Save Changes', 'my-calendar' );
								       } else {
									       _e( 'Add Location', 'my-calendar' );
								       } ?> &raquo;"/>
							</p>
						</form>
					</div>
				</div>
			</div>
			<?php if ( $view == 'edit' ) { ?>
				<p>
					<a href="<?php echo admin_url( "admin.php?page=my-calendar-locations" ); ?>"><?php _e( 'Add a New Location', 'my-calendar' ); ?> &raquo;</a>
				</p>
			<?php } ?>
			<div class="ui-sortable meta-box-sortables">
				<div class="postbox">
					<h3><?php _e( 'Manage Locations', 'my-calendar' ); ?></h3>

					<div class="inside">
						<?php mc_manage_locations(); ?>
					</div>
				</div>
			</div>
		</div>
		<?php mc_show_sidebar(); ?>
	</div>

<?php
}

function mc_controlled_field( $this_field ) {
	$this_field = trim( $this_field );
	$controls   = get_option( 'mc_location_controls' );
	if ( ! is_array( $controls ) || empty( $controls ) ) {
		return false;
	}
	$controlled = array_keys( $controls );
	if ( in_array( 'event_' . $this_field, $controlled ) && ! empty( $controls[ 'event_' . $this_field ] ) ) {
		return true;
	} else {
		return false;
	}
}

function mc_location_controller( $fieldname, $selected, $context = 'location' ) {
	$field    = ( $context == 'location' ) ? 'location_' . $fieldname : 'event_' . $fieldname;
	$selected = trim( $selected );
	$options  = get_option( 'mc_location_controls' );
	$regions  = $options[ 'event_' . $fieldname ];
	$form     = "<select name='$field' id='e_$fieldname'>";
	if ( $selected == '' || in_array( $selected, array_keys( $regions ) ) ) {
		$form .= "<option value='none'>No preference</option>\n";
	} else {
		$form .= "<option value='$selected'>$selected " . __( '(Not a controlled value)', 'my-calendar' ) . "</option>\n";
	}
	foreach ( $regions as $key => $value ) {
		$key       = trim( $key );
		$aselected = ( $selected == $key ) ? " selected='selected'" : '';
		$form .= "<option value='$key'$aselected>$value</option>\n";
	}
	$form .= "</select>";

	return $form;
}

function mc_manage_locations() {
	global $wpdb;
	$mcdb = $wpdb;

	// pull the locations from the database
	$items_per_page = 50;
	$current        = empty( $_GET['paged'] ) ? 1 : intval( $_GET['paged'] );
	$locations      = $mcdb->get_results( "SELECT SQL_CALC_FOUND_ROWS * FROM " . my_calendar_locations_table() . " ORDER BY location_label ASC LIMIT " . ( ( $current - 1 ) * $items_per_page ) . ", " . $items_per_page );
	$found_rows     = $wpdb->get_col( "SELECT FOUND_ROWS();" );
	$items          = $found_rows[0];

	$num_pages = ceil( $items / $items_per_page );
	if ( $num_pages > 1 ) {
		$page_links = paginate_links( array(
			'base'      => add_query_arg( 'paged', '%#%' ),
			'format'    => '',
			'prev_text' => __( '&laquo; Previous<span class="screen-reader-text"> Locations</span>', 'my-calendar' ),
			'next_text' => __( 'Next<span class="screen-reader-text"> Locations</span> &raquo;', 'my-calendar' ),
			'total'     => $num_pages,
			'current'   => $current,
			'mid_size'  => 1
		) );
		printf( "<div class='tablenav'><div class='tablenav-pages'>%s</div></div>", $page_links );
	}

	if ( ! empty( $locations ) ) {
		?>
	<form action="<?php echo add_query_arg( $_GET, admin_url( 'admin.php' ) ); ?>" method="post">
		<div><input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
		<div class='mc-actions'>
			<input type="submit" class="button-secondary delete" name="mass_delete"
			       value="<?php _e( 'Delete locations', 'my-calendar' ); ?>"/>
		</div>
		<table class="widefat page" id="my-calendar-admin-table">
			<thead>
			<tr>
				<th scope="col"><?php _e( 'ID', 'my-calendar' ) ?></th>
				<th scope="col"><?php _e( 'Location', 'my-calendar' ) ?></th>
				<th scope="col"><?php _e( 'Edit', 'my-calendar' ) ?></th>
				<th scope="col"><?php _e( 'Delete', 'my-calendar' ) ?></th>
			</tr>
			</thead>
			<?php
			$class = '';
			foreach ( $locations as $location ) {
				$class = ( $class == 'alternate' ) ? '' : 'alternate'; ?>
				<tr class="<?php echo $class; ?>">
					<th scope="row"><input type="checkbox" value="<?php echo $location->location_id; ?>"
					                       name="mass_edit[]" id="mc<?php echo $location->location_id; ?>"/> <label
							for="mc<?php echo $location->location_id; ?>"><?php echo $location->location_id; ?></label>
					</th>
					<td><?php echo mc_hcard( $location, 'true', 'false', 'location' ); ?></td>
					<td>
						<a href="<?php echo admin_url( "admin.php?page=my-calendar-locations&amp;mode=edit&amp;location_id=$location->location_id" ); ?>"
						   class='edit'><?php _e( 'Edit', 'my-calendar' ); ?></a></td>
					<td>
						<a href="<?php echo admin_url( "admin.php?page=my-calendar-locations&amp;mode=delete&amp;location_id=$location->location_id" ); ?>"
						   class="delete"
						   onclick="return confirm('<?php _e( 'Are you sure you want to delete this category?', 'my-calendar' ); ?>')"><?php _e( 'Delete', 'my-calendar' ); ?></a>
					</td>
				</tr>
			<?php } ?>
		</table>
		<p>
			<input type="submit" class="button-secondary delete" name="mass_delete"
			       value="<?php _e( 'Delete locations', 'my-calendar' ); ?>"/>
		</p>
		</form><?php
	} else {
		echo '<p>' . __( 'There are no locations in the database yet!', 'my-calendar' ) . '</p>';
	} ?>
	<p><em>
			<?php _e( 'Please note: editing or deleting locations stored for re-use will have no effect on any event previously scheduled at that location. The location database exists purely as a shorthand method to enter frequently used locations into event records.', 'my-calendar' ); ?>
		</em></p>

	<form method="post" action="<?php echo admin_url( "admin.php?page=my-calendar-locations" ); ?>">
		<div><input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
		<div><input type="hidden" name="mc_locations" value="true"/></div>
		<fieldset>
			<legend><?php _e( 'Control Input Options for Location Fields', 'my-calendar' ); ?></legend>
			<div id="mc-accordion">
				<?php
				// array of fields allowing input control.
				$location_fields      = array(
					'event_label',
					'event_city',
					'event_state',
					'event_country',
					'event_postcode',
					'event_region'
				);
				$mc_location_controls = get_option( 'mc_location_controls' );
				foreach ( $location_fields as $field ) {
					?>
					<h4><?php echo ucfirst( str_replace( 'event_', '', $field ) ); ?></h4>
					<div>
						<label
							for="loc_values_<?php echo $field; ?>"><?php printf( __( 'Location Controls for %s', 'my-calendar' ), ucfirst( str_replace( 'event_', '', $field ) ) ); ?>
							(<?php _e( 'Value, Label (one per line)', 'my-calendar' ); ?>)</label><br/>
						<?php
						$locations = '';
						if ( is_array( $mc_location_controls ) && isset( $mc_location_controls[ $field ] ) ) {
							foreach ( $mc_location_controls[ $field ] as $key => $value ) {
								$locations .= stripslashes( "$key,$value" ) . "\n";
							}
						}
						?>
						<textarea name="mc_location_controls[<?php echo $field; ?>][]"
						          id="loc_values_<?php echo $field; ?>" cols="80"
						          rows="6"><?php echo trim( $locations ); ?></textarea>
					</div>
				<?php } ?>
			</div>
			<p><input type='submit' class='button secondary'
			          value='<?php _e( 'Save Location Controls', 'my-calendar' ); ?>'/></p>
		</fieldset>
</div>
<?php
}

function mc_locations_fields( $has_data, $data, $context = 'location' ) {
	$return = '<div class="mc-locations">';
	if ( current_user_can( 'mc_edit_locations' ) && $context == 'event' ) {
		$return .= '<p><input type="checkbox" value="on" name="mc_copy_location" id="mc_copy_location" /> <label for="mc_copy_location">' . __( 'Copy this location into the locations table', 'my-calendar' ) . '</label></p>';
	}
	$return .= '
	<p class="checkbox">
	<label for="e_label">' . __( 'Name of Location (e.g. <em>Joe\'s Bar and Grill</em>)', 'my-calendar' ) . '</label>';
	$cur_label = ( ! empty( $data ) ) ? ( stripslashes( $data->{$context . '_label'} ) ) : '';
	if ( mc_controlled_field( 'label' ) ) {
		$return .= mc_location_controller( 'label', $cur_label, $context );
	} else {
		$return .= '<input type="text" id="e_label" name="' . $context . '_label" size="40" value="' . esc_attr( $cur_label ) . '" />';
	}
	$street_address  = ( $has_data ) ? esc_attr( stripslashes( $data->{$context . '_street'} ) ) : '';
	$street_address2 = ( $has_data ) ? esc_attr( stripslashes( $data->{$context . '_street2'} ) ) : '';
	$return .= '
	</p>
	<div class="locations-container">
	<div class="location-primary">
	<fieldset>
	<legend>' . __( 'Location Address', 'my-calendar' ) . '</legend>
	<p>
	<label for="e_street">' . __( 'Street Address', 'my-calendar' ) . '</label> <input type="text" id="e_street" name="' . $context . '_street" size="40" value="' . $street_address . '" />
	</p>
	<p>
	<label for="e_street2">' . __( 'Street Address (2)', 'my-calendar' ) . '</label> <input type="text" id="e_street2" name="' . $context . '_street2" size="40" value="' . $street_address2 . '" />
	</p>		
	<p>
	<label for="e_city">' . __( 'City', 'my-calendar' ) . '</label> ';
	$cur_city = ( ! empty( $data ) ) ? ( stripslashes( $data->{$context . '_city'} ) ) : '';
	if ( mc_controlled_field( 'city' ) ) {
		$return .= mc_location_controller( 'city', $cur_city, $context );
	} else {
		$return .= '<input type="text" id="e_city" name="' . $context . '_city" size="40" value="' . esc_attr( $cur_city ) . '" />';
	}
	$return .= "</p>
	<p>";
	$return .= '<label for="e_state">' . __( 'State/Province', 'my-calendar' ) . '</label> ';
	$cur_state = ( ! empty( $data ) ) ? ( stripslashes( $data->{$context . '_state'} ) ) : '';
	if ( mc_controlled_field( 'state' ) ) {
		$return .= mc_location_controller( 'state', $cur_state, $context );
	} else {
		$return .= '<input type="text" id="e_state" name="' . $context . '_state" size="10" value="' . esc_attr( $cur_state ) . '" />';
	}
	$return .= '</p>
	<p>
	<label for="e_postcode">' . __( 'Postal Code', 'my-calendar' ) . '</label> ';
	$cur_postcode = ( ! empty( $data ) ) ? ( stripslashes( $data->{$context . '_postcode'} ) ) : '';
	if ( mc_controlled_field( 'postcode' ) ) {
		$return .= mc_location_controller( 'postcode', $cur_postcode, $context );
	} else {
		$return .= '<input type="text" id="e_postcode" name="' . $context . '_postcode" size="40" value="' . esc_attr( $cur_postcode ) . '" />';
	}
	$return .= "</p>
	<p>";
	$return .= '<label for="e_region">' . __( 'Region', 'my-calendar' ) . '</label> ';
	$cur_region = ( ! empty( $data ) ) ? ( stripslashes( $data->{$context . '_region'} ) ) : '';
	if ( mc_controlled_field( 'region' ) ) {
		$return .= mc_location_controller( 'region', $cur_region, $context );
	} else {
		$return .= '<input type="text" id="e_region" name="' . $context . '_region" size="40" value="' . esc_attr( $cur_region ) . '" />';
	}
	$return .= '</p>
	<p>		
	<label for="e_country">' . __( 'Country', 'my-calendar' ) . '</label> ';
	$cur_country = ( $has_data ) ? ( stripslashes( $data->{$context . '_country'} ) ) : '';
	if ( mc_controlled_field( 'country' ) ) {
		$return .= mc_location_controller( 'country', $cur_country, $context );
	} else {
		$return .= '<input type="text" id="e_country" name="' . $context . '_country" size="10" value="' . esc_attr( $cur_country ) . '" />';
	}
	$zoom         = ( $has_data ) ? $data->{$context . '_zoom'} : '16';
	$event_phone  = ( $has_data ) ? esc_attr( stripslashes( $data->{$context . '_phone'} ) ) : '';
	$event_phone2 = ( $has_data ) ? esc_attr( stripslashes( $data->{$context . '_phone2'} ) ) : '';
	$event_url    = ( $has_data ) ? esc_attr( stripslashes( $data->{$context . '_url'} ) ) : '';
	$event_lat    = ( $has_data ) ? esc_attr( stripslashes( $data->{$context . '_latitude'} ) ) : '';
	$event_lon    = ( $has_data ) ? esc_attr( stripslashes( $data->{$context . '_longitude'} ) ) : '';
	$return .= '</p>
	<p>
	<label for="e_zoom">' . __( 'Initial Zoom', 'my-calendar' ) . '</label>
		<select name="' . $context . '_zoom" id="e_zoom">
			<option value="16"' . jd_option_selected( $zoom, '16', 'option' ) . '>' . __( 'Neighborhood', 'my-calendar' ) . '</option>
			<option value="14"' . jd_option_selected( $zoom, '14', 'option' ) . '>' . __( 'Small City', 'my-calendar' ) . '</option>
			<option value="12"' . jd_option_selected( $zoom, '12', 'option' ) . '>' . __( 'Large City', 'my-calendar' ) . '</option>
			<option value="10"' . jd_option_selected( $zoom, '10', 'option' ) . '>' . __( 'Greater Metro Area', 'my-calendar' ) . '</option>
			<option value="8"' . jd_option_selected( $zoom, '8', 'option' ) . '>' . __( 'State', 'my-calendar' ) . '</option>
			<option value="6"' . jd_option_selected( $zoom, '6', 'option' ) . '>' . __( 'Region', 'my-calendar' ) . '</option>
		</select>
	</p>
	</fieldset>
	<fieldset>
	<legend>' . __( 'GPS Coordinates (optional)', 'my-calendar' ) . '</legend>
	<p>
	' . __( 'If you supply GPS coordinates for your location, they will be used in place of any other address information to provide your map link.', 'my-calendar' ) . '
	</p>
	<p>
	<label for="e_latitude">' . __( 'Latitude', 'my-calendar' ) . '</label> <input type="text" id="e_latitude" name="' . $context . '_latitude" size="10" value="' . $event_lat . '" /> <label for="e_longitude">' . __( 'Longitude', 'my-calendar' ) . '</label> <input type="text" id="e_longitude" name="' . $context . '_longitude" size="10" value="' . $event_lon . '" />
	</p>			
	</fieldset>
	</div>
	<div class="location-secondary">
	<fieldset>
	<legend>' . __( 'Location Contact Information', 'my-calendar' ) . '</legend>
	<p>
	<label for="e_phone">' . __( 'Phone', 'my-calendar' ) . '</label> <input type="text" id="e_phone" name="' . $context . '_phone" size="32" value="' . $event_phone . '" />
	</p>
	<p>
	<label for="e_phone2">' . __( 'Secondary Phone', 'my-calendar' ) . '</label> <input type="text" id="e_phone2" name="' . $context . '_phone2" size="32" value="' . $event_phone2 . '" />
	</p>	
	<p>
	<label for="e_url">' . __( 'Location URL', 'my-calendar' ) . '</label> <input type="text" id="e_url" name="' . $context . '_url" size="40" value="' . $event_url . '" />
	</p>
	</fieldset>
	<fieldset>
	<legend>' . __( 'Location Accessibility', 'my-calendar' ) . '</legend>
	<ul class="accessibility-features checkboxes">';
	$access      = apply_filters( 'mc_venue_accessibility', get_option( 'mc_location_access' ) );
	$access_list = '';
	if ( $has_data ) {
		if ( $context == 'location' ) {
			$location_access = unserialize( $data->{$context . '_access'} );
		} else {
			$location_access = unserialize( mc_location_data( 'location_access', $data->event_location ) );
		}
	} else {
		$location_access = array();
	}
	foreach ( $access as $k => $a ) {
		$id      = "loc_access_$k";
		$label   = $a;
		$checked = '';
		if ( is_array( $location_access ) ) {
			$checked = ( in_array( $a, $location_access ) || in_array( $k, $location_access ) ) ? " checked='checked'" : '';
		}
		$item = sprintf( '<li><input type="checkbox" id="%1$s" name="' . $context . '_access[]" value="%4$s" class="checkbox" %2$s /> <label for="%1$s">%3$s</label></li>', $id, $checked, $label, $a );
		$access_list .= $item;
	}
	$return .= $access_list;
	$return .= '</ul>
	</fieldset></div>
	</div>
	</div>';

	return $return;
}

// get a specific field with an location ID
function mc_location_data( $field, $id ) {
	if ( $id ) {
		global $wpdb;
		$mcdb = $wpdb;
		if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
			$mcdb = mc_remote_db();
		}
		$field  = esc_sql( $field );
		$sql    = $wpdb->prepare( "SELECT $field FROM " . my_calendar_locations_table() . " WHERE location_id = %d", $id );
		$result = $mcdb->get_var( $sql );

		return $result;
	}
}