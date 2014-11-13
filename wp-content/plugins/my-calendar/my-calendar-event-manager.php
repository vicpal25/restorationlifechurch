<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

function mc_switch_sites() {
	if ( function_exists( 'is_multisite' ) && is_multisite() ) {
		if ( get_site_option( 'mc_multisite' ) == 2 && MY_CALENDAR_TABLE != MY_CALENDAR_GLOBAL_TABLE ) {
			if ( get_option( 'mc_current_table' ) == '1' ) {
				// can post to either, but is currently set to post to central table
				return true;
			}
		} else if ( get_site_option( 'mc_multisite' ) == 1 && MY_CALENDAR_TABLE != MY_CALENDAR_GLOBAL_TABLE ) {
			// can only post to central table
			return true;
		}
	}

	return false;
}

function mc_event_post( $action, $data, $event_id ) {
	// if the event save was successful.
	if ( $action == 'add' || $action == 'copy' ) {
		$post_id = mc_create_event_post( $data, $event_id );
	} else if ( $action == 'edit' ) {
		if ( isset( $_POST['event_post'] ) && ( $_POST['event_post'] == 0 || $_POST['event_post'] == '' ) ) {
			$post_id = mc_create_event_post( $data, $event_id );
		} else {
			$post_id = $_POST['event_post'];
		}
		$term              = mc_get_category_detail( $data['event_category'], 'category_term' );
		$privacy           = ( mc_get_category_detail( $data['event_category'], 'category_private' ) == 1 ) ? 'private' : 'publish';
		$title             = $data['event_title'];
		$template          = apply_filters( 'mc_post_template', 'details', $term );
		$data['shortcode'] = "[my_calendar_event event='$event_id' template='$template' list='']";
		$description       = $data['event_desc'];
		$excerpt           = $data['event_short'];
		$post_status       = $privacy;
		$auth              = $data['event_author'];
		$type              = 'mc-events';
		$my_post           = array(
			'ID'           => $post_id,
			'post_title'   => $title,
			'post_content' => $description,
			'post_status'  => $post_status,
			'post_author'  => $auth,
			'post_name'    => sanitize_title( $title ),
			'post_date'    => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
			'post_type'    => $type,
			'post_excerpt' => $excerpt
		);
		if ( mc_switch_sites() && defined( BLOG_ID_CURRENT_SITE ) ) {
			switch_to_blog( BLOG_ID_CURRENT_SITE );
		}
		$post_id = wp_update_post( $my_post );
		wp_set_object_terms( $post_id, (int) $term, 'mc-event-category' );
		if ( $data['event_image'] == '' ) {
			delete_post_thumbnail( $post_id );
		} else {
			$attachment_id = ( isset( $_POST['event_image_id'] ) && is_numeric( $_POST['event_image_id'] ) ) ? $_POST['event_image_id'] : false;
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}
		$access       = ( isset( $_POST['events_access'] ) ) ? $_POST['events_access'] : array();
		$access_terms = implode( ',', array_values( $access ) );
		mc_update_event( 'event_access', $access_terms, $event_id, '%s' );
		do_action( 'mc_update_event_post', $post_id, $_POST, $data, $event_id );
		if ( mc_switch_sites() ) {
			restore_current_blog();
		}
	}

	return $post_id;
}

// use this action to add any $_POST data; e.g., things not saved elsewhere.
add_action( 'mc_update_event_post', 'mc_add_post_meta_data', 10, 4 );
function mc_add_post_meta_data( $post_id, $post, $data, $event_id ) {
	// access features for the event
	update_post_meta( $post_id, '_mc_event_shortcode', $data['shortcode'] );
	update_post_meta( $post_id, '_mc_event_access', ( isset( $_POST['events_access'] ) ) ? $_POST['events_access'] : '' );
	update_post_meta( $post_id, '_mc_event_id', $event_id );
	update_post_meta( $post_id, '_mc_event_desc', $data['event_desc'] );
	update_post_meta( $post_id, '_mc_event_image', $data['event_image'] );
	$location_id = ( isset( $post['location_preset'] ) ) ? (int) $post['location_preset'] : 0;
	if ( $location_id ) { // only change location ID if dropdown set.
		update_post_meta( $post_id, '_mc_event_location', $location_id );
		mc_update_event( 'event_location', $location_id, $event_id );
	}
	update_post_meta( $post_id, '_mc_event_data', $data );
}

function mc_create_event_post( $data, $event_id ) {
	$term              = mc_get_category_detail( $data['event_category'], 'category_term' );
	$privacy           = ( mc_get_category_detail( $data['event_category'], 'category_private' ) == 1 ) ? 'private' : 'publish';
	$title             = $data['event_title'];
	$template          = apply_filters( 'mc_post_template', 'details', $term );
	$data['shortcode'] = "[my_calendar_event event='$event_id' template='$template' list='']";
	$description       = $data['event_desc'];
	$excerpt           = $data['event_short'];
	$location_id       = ( isset( $_POST['location_preset'] ) ) ? (int) $_POST['location_preset'] : 0;
	$post_status       = $privacy;
	$auth              = $data['event_author'];
	$type              = 'mc-events';
	$my_post           = array(
		'post_title'   => $title,
		'post_content' => $description,
		'post_status'  => $post_status,
		'post_author'  => $auth,
		'post_name'    => sanitize_title( $title ),
		'post_date'    => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
		'post_type'    => $type,
		'post_excerpt' => $excerpt
	);
	$post_id           = wp_insert_post( $my_post );
	wp_set_object_terms( $post_id, (int) $term, 'mc-event-category' );
	$attachment_id = ( isset( $_POST['event_image_id'] ) && is_numeric( $_POST['event_image_id'] ) ) ? $_POST['event_image_id'] : false;
	if ( $attachment_id ) {
		set_post_thumbnail( $post_id, $attachment_id );
	}
	mc_update_event( 'event_post', $post_id, $event_id );
	mc_update_event( 'event_location', $location_id, $event_id );
	do_action( 'mc_update_event_post', $post_id, $_POST, $data, $event_id );
	wp_publish_post( $post_id );

	return $post_id;
}

function mc_update_event( $field, $data, $event, $type = '%d' ) {
	global $wpdb;
	$field  = sanitize_key( $field );
	$type   = esc_sql( $type );
	$result = $wpdb->query( $wpdb->prepare( "UPDATE " . my_calendar_table() . " SET $field = $type WHERE event_id=$type", $data, $event ) );

	return $result;
}

function mc_update_category( $field, $data, $category ) {
	global $wpdb;
	$field  = sanitize_key( $field );
	$result = $wpdb->query( $wpdb->prepare( "UPDATE " . my_calendar_categories_table() . " SET $field = %d WHERE category_id=%d", $data, $category ) );

	return $result;
}

function mc_update_location( $field, $data, $location ) {
	global $wpdb;
	$field  = sanitize_key( $field );
	$result = $wpdb->query( $wpdb->prepare( "UPDATE " . my_calendar_locations_table() . " SET $field = %d WHERE location_id=%d", $data, $location ) );

	return $result;
}

/**
 * @param $event_id
 * @param $post_id
 */
function mc_event_delete_post( $event_id, $post_id ) {
	do_action( 'mc_deleted_post', $event_id, $post_id );
	wp_delete_post( $post_id, true );
}

function manage_my_calendar() {
	check_my_calendar();
	global $wpdb;
	$mcdb = $wpdb;
	if ( isset( $_GET['mode'] ) && $_GET['mode'] == 'delete' ) {
		$sql    = "SELECT event_title, event_author FROM " . my_calendar_table() . " WHERE event_id=" . (int) $_GET['event_id'];
		$result = $mcdb->get_results( $sql, ARRAY_A );
		if ( mc_can_edit_event( $result[0]['event_author'] ) ) {
			if ( isset( $_GET['date'] ) ) {
				$event_instance = (int) $_GET['date'];
				$sql            = "SELECT occur_begin FROM " . my_calendar_event_table() . " WHERE occur_id=" . $event_instance;
				$inst           = $mcdb->get_var( $sql );
				$instance_date  = '(' . date( 'Y-m-d', strtotime( $inst ) ) . ')';
			} else {
				$instance_date = '';
			} ?>
			<div class="error">
			<form action="<?php echo admin_url( 'admin.php?page=my-calendar' ); ?>" method="post">
				<p><strong><?php _e( 'Delete Event', 'my-calendar' ); ?>
						:</strong> <?php _e( 'Are you sure you want to delete this event?', 'my-calendar' ); ?>
					<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/>
					<input type="hidden" value="delete" name="event_action"/>
					<?php if ( ! empty( $_GET['date'] ) ) { ?>
						<input type="hidden" name="event_instance" value="<?php echo (int) $_GET['date']; ?>"/>
					<?php } ?>
					<?php if ( isset( $_GET['ref'] ) ) { ?>
						<input type="hidden" name="ref" value="<?php echo esc_url( $_GET['ref'] ); ?>"/>
					<?php } ?>

					<input type="hidden" name="event_id" value="<?php echo (int) $_GET['event_id']; ?>"/>
					<input type="submit" name="submit" class="button-secondary delete"
					       value="<?php _e( 'Delete', 'my-calendar' );
					       echo " &quot;" . stripslashes( $result[0]['event_title'] ) . "&quot;$instance_date"; ?>"/>
			</form>
			</div><?php
		} else {
			?>
			<div class="error">
			<p><strong><?php _e( 'You do not have permission to delete that event.', 'my-calendar' ); ?></strong></p>
			</div><?php
		}
	}

	// Approve and show an Event ...originally by Roland
	if ( isset( $_GET['mode'] ) && $_GET['mode'] == 'publish' ) {
		if ( current_user_can( 'mc_approve_events' ) ) {
			$sql = "UPDATE " . my_calendar_table() . " SET event_approved = 1 WHERE event_id=" . (int) $_GET['event_id'];
			$mcdb->get_results( $sql, ARRAY_A );
			mc_delete_cache();
		} else {
			?>
			<div class="error">
				<p><strong><?php _e( 'You do not have permission to approve that event.', 'my-calendar' ); ?></strong>
				</p>
			</div>
		<?php
		}
	}

	// Reject and hide an Event ...by Roland
	if ( isset( $_GET['mode'] ) && $_GET['mode'] == 'reject' ) {
		if ( current_user_can( 'mc_approve_events' ) ) {
			$sql = "UPDATE " . my_calendar_table() . " SET event_approved = 2 WHERE event_id=" . (int) $_GET['event_id'];
			$mcdb->get_results( $sql, ARRAY_A );
			mc_delete_cache();
		} else {
			?>
			<div class="error">
				<p><strong><?php _e( 'You do not have permission to reject that event.', 'my-calendar' ); ?></strong>
				</p>
			</div>
		<?php
		}
	}

	if ( ! empty( $_POST['mass_edit'] ) && isset( $_POST['mass_delete'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( "Security check failed" );
		}
		$events  = $_POST['mass_edit'];
		$i       = $total = 0;
		$deleted = $ids = array();
		foreach ( $events as $value ) {
			$value  = (int) $value;
			$ea     = "SELECT event_author FROM " . my_calendar_table() . " WHERE event_id = $value";
			$result = $mcdb->get_results( $ea, ARRAY_A );
			$total  = count( $events );
			if ( mc_can_edit_event( $result[0]['event_author'] ) ) {
				$delete_occurrences = "DELETE FROM " . my_calendar_event_table() . " WHERE occur_event_id = $value";
				$mcdb->query( $delete_occurrences );
				$ids[]     = (int) $value;
				$deleted[] = $value;
				$i ++;
			}
		}
		$statement = implode( ',', $ids );
		$sql       = 'DELETE FROM ' . my_calendar_table() . " WHERE event_id IN ($statement)";
		$result    = $mcdb->query( $sql );
		if ( $result !== 0 && $result !== false ) {
			mc_delete_cache();
			// argument: array of event IDs
			do_action( 'mc_mass_delete_events', $deleted );
			$message = "<div class='updated'><p>" . sprintf( __( '%1$d events deleted successfully out of %2$d selected', 'my-calendar' ), $i, $total ) . "</p></div>";
		} else {
			$message = "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong>" . __( 'Your events have not been deleted. Please investigate.', 'my-calendar' ) . "</p></div>";
		}
		echo $message;
	}

	if ( ! empty( $_POST['mass_edit'] ) && isset( $_POST['mass_approve'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( "Security check failed" );
		}
		$events   = $_POST['mass_edit'];
		$sql      = 'UPDATE ' . my_calendar_table() . ' SET event_approved = 1 WHERE event_id IN (';
		$i        = 0;
		$approved = array();
		foreach ( $events as $value ) {
			$value = (int) $value;
			$total = count( $events );
			if ( current_user_can( 'mc_approve_events' ) ) {
				$sql .= (int) $value . ',';
				$approved[] = $value;
				$i ++;
			}
		}
		$sql = substr( $sql, 0, - 1 );
		$sql .= ')';
		$result = $mcdb->query( $sql );
		if ( $result == 0 || $result == false ) {
			$message = "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong>" . __( 'Your events have not been approved. Please investigate.', 'my-calendar' ) . "</p></div>";
		} else {
			mc_delete_cache();
			// argument: array of event IDs
			do_action( 'mc_mass_approve_events', $approved );
			$message = "<div class='updated'><p>" . sprintf( __( '%1$d events approved successfully out of %2$d selected', 'my-calendar' ), $i, $total ) . "</p></div>";
		}
		echo $message;
	}

	if ( ! empty( $_POST['mass_edit'] ) && isset( $_POST['mass_archive'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( "Security check failed" );
		}
		$events   = $_POST['mass_edit'];
		$sql      = 'UPDATE ' . my_calendar_table() . ' SET event_status = 0 WHERE event_id IN (';
		$i        = $total = 0;
		$archived = array();
		foreach ( $events as $value ) {
			$total = count( $events );
			$sql .= (int) $value . ',';
			$archived[] = $value;
			$i ++;
		}
		$sql = substr( $sql, 0, - 1 );
		$sql .= ')';
		$result = $mcdb->query( $sql );
		if ( $result == 0 || $result == false ) {
			$message = "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong>" . __( 'Could not archive those events.', 'my-calendar' ) . "</p></div>";
		} else {
			mc_delete_cache();
			// argument: array of event IDs
			do_action( 'mc_mass_archive_events', $archived );
			$message = "<div class='updated'><p>" . sprintf( __( '%1$d events archived successfully out of %2$d selected.', 'my-calendar' ), $i, $total ) . "</p></div>";
		}
		echo $message;
	}
	?>
	<div class='wrap jd-my-calendar'>
		<div id="icon-edit" class="icon32"></div>
		<h2 class='mc-clear' id='mc-manage'><?php _e( 'Manage Events', 'my-calendar' ); ?></h2>

		<div class="postbox-container jcd-wide">
			<div class="metabox-holder">
				<div class="ui-sortable meta-box-sortables">
					<div class="postbox">
						<h3><?php _e( 'My Events', 'my-calendar' ); ?></h3>

						<div class="inside">
							<?php mc_list_events(); ?>
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php /* Todo 
	if ( isset( $_POST['mc-import-csv'] ) ) {
		
	}
	$add = array( 'Import Events'=>'<form action="'.admin_url('admin.php?page=my-calendar-manage').'" method="POST"><div><label for="mc-import-csv">'.__('Upload CSV File','my-calendar').'<input type="file" name="mc-import-csv" id="mc-import-csv" /><input type="submit" value="'.__('Import Events','my-calendar').'" /></div></form>'); */
		mc_show_sidebar(); ?>
	</div>
<?php
}

function edit_my_calendar() {
	global $current_user, $wpdb, $users_entries;
	$mcdb = $wpdb;

	if ( get_option( 'ko_calendar_imported' ) != 'true' ) {
		if ( function_exists( 'check_calendar' ) ) {
			echo "<div id='message'class='updated'>";
			echo "<p>";
			_e( 'My Calendar has identified that you have the Calendar plugin by Kieran O\'Shea installed. You can import those events and categories into the My Calendar database. Would you like to import these events?', 'my-calendar' );
			echo "</p>";
			?>
			<form method="post" action="<?php echo admin_url( 'admin.php?page=my-calendar-config' ); ?>">
				<div><input type="hidden" name="_wpnonce"
				            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/>
				</div>
				<div>
					<input type="hidden" name="import" value="true"/>
					<input type="submit" value="<?php _e( 'Import from Calendar', 'my-calendar' ); ?>"
					       name="import-calendar" class="button-primary"/>
				</div>
			</form>
			<?php
			echo "<p>";
			_e( 'Although it is possible that this import could fail to import your events correctly, it should not have any impact on your existing Calendar database. If you encounter any problems, <a href="http://www.joedolson.com/contact.php">please contact me</a>!', 'my-calendar' );
			echo "</p>";
			echo "</div>";
		}
	}

	$action   = ! empty( $_POST['event_action'] ) ? $_POST['event_action'] : '';
	$event_id = ! empty( $_POST['event_id'] ) ? $_POST['event_id'] : '';

	if ( isset( $_GET['mode'] ) ) {
		$action = $_GET['mode'];
		if ( $action == 'edit' || $action == 'copy' ) {
			$event_id = (int) $_GET['event_id'];
		}
	}

	if ( isset( $_POST['event_action'] ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( "Security check failed" );
		}
		global $mc_output;
		$count = 0;

		if ( isset( $_POST['event_begin'] ) && is_array( $_POST['event_begin'] ) ) {
			$count = count( $_POST['event_begin'] );
		} else {
			$response = my_calendar_save( $action, $mc_output, (int) $_POST['event_id'] );
			echo $response['message'];
		}
		for ( $i = 0; $i < $count; $i ++ ) {
			$mc_output = mc_check_data( $action, $_POST, $i );
			if ( $action == 'add' || $action == 'copy' ) {
				$response = my_calendar_save( $action, $mc_output );
			} else {
				$response = my_calendar_save( $action, $mc_output, (int) $_POST['event_id'] );
			}
			echo $response['message'];
		}
		if ( isset( $_POST['ref'] ) ) {
			$url = esc_url( urldecode( $_POST['ref'] ) );
			echo "<p class='return'><a href='$url'>" . __( 'Return to Calendar', 'my-calendar' ) . "</a></p>";
		}
	}

	?>

	<div class="wrap jd-my-calendar">
	<?php my_calendar_check_db();
	if ( get_site_option( 'mc_multisite' ) == 2 ) {
		if ( get_option( 'mc_current_table' ) == 0 ) {
			$message = __( 'Currently editing your local calendar', 'my-calendar' );
		} else {
			$message = __( 'Currently editing your central calendar', 'my-calendar' );
		}
		echo "<div class='message updated'><p>$message</p></div>";
	}
	if ( $action == 'edit' ) {
		?>
		<div id="icon-edit" class="icon32"></div>
		<h2><?php _e( 'Edit Event', 'my-calendar' ); ?></h2>
		<?php
		if ( empty( $event_id ) ) {
			echo "<div class='error'><p>" . __( "You must provide an event id in order to edit it", 'my-calendar' ) . "</p></div>";
		} else {
			mc_edit_event_form( 'edit', $event_id );
		}
	} else if ( $action == 'copy' ) {
		?>
		<div id="icon-edit" class="icon32"></div>
		<h2><?php _e( 'Copy Event', 'my-calendar' ); ?></h2>
		<?php
		if ( empty( $event_id ) ) {
			echo "<div class=\"error\"><p>" . __( "You must provide an event id in order to edit it", 'my-calendar' ) . "</p></div>";
		} else {
			mc_edit_event_form( 'copy', $event_id );
		}
	} else {
		?>
		<div id="icon-edit" class="icon32"></div>
		<h2><?php _e( 'Add Event', 'my-calendar' ); ?></h2><?php
		mc_edit_event_form();
	}
	mc_show_sidebar(); ?>
	</div><?php
}

function mc_tweet_approval( $prev, $new ) {
	if ( function_exists( 'jd_doTwitterAPIPost' ) && isset( $_POST['mc_twitter'] ) && trim( $_POST['mc_twitter'] ) != '' ) {
		if ( ( $prev == 0 || $prev == 2 ) && $new == 1 ) {
			jd_doTwitterAPIPost( stripslashes( $_POST['mc_twitter'] ) );
		}
	}
}

function my_calendar_save( $action, $output, $event_id = false ) {
	global $wpdb, $event_author;
	$mcdb    = $wpdb;
	$proceed = $output[0];
	$message = '';
	$formats = array(
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%s',
		'%d',
		'%d',
		'%d',
		'%d',
		'%d',
		'%d',
		'%d',
		'%d',
		'%d',
		'%d',
		'%d',
		'%d',
		'%d',
		'%d',
		'%d',
		'%f',
		'%f'
	);
	if ( ( $action == 'add' || $action == 'copy' ) && $proceed == true ) {
		$add      = $output[2]; // add format here
		$add      = apply_filters( 'mc_before_save_insert', $add );
		$result   = $mcdb->insert( my_calendar_table(), $add, $formats );
		$event_id = $mcdb->insert_id;
		mc_increment_event( $event_id );
		if ( ! $result ) {
			$message = "<div class='error notice'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong> " . __( 'I\'m sorry! I couldn\'t add that event to the database.', 'my-calendar' ) . "</p></div>";
		} else {
			// do an action using the $action and processed event data
			$data = $add;
			do_action( 'mc_save_event', $action, $data, $event_id, $result );
			// Call mail function
			if ( get_option( 'mc_event_mail' ) == 'true' ) {
				$event = mc_get_first_event( $event_id ); // insert_id is last occurrence inserted in the db
				my_calendar_send_email( $event );
			}
			if ( $add['event_approved'] == 0 ) {
				$message = "<div class='updated notice'><p>" . __( 'Event saved. An administrator will review and approve your event.', 'my-calendar' ) . "</p></div>";
			} else {
				if ( function_exists( 'jd_doTwitterAPIPost' ) && isset( $_POST['mc_twitter'] ) && trim( $_POST['mc_twitter'] ) != '' ) {
					jd_doTwitterAPIPost( stripslashes( $_POST['mc_twitter'] ) );
				}
				if ( get_option( 'mc_uri' ) != '' ) {
					$event_ids  = mc_get_occurrences( $event_id );
					$event_link = mc_build_url( array( 'mc_id' => $event_ids[0]->occur_id ), array( 'page' ), get_option( 'mc_uri' ) );
				} else {
					$event_link = false;
				}
				$message = "<div class='updated notice'><p>" . __( 'Event added. It will now show on the calendar.', 'my-calendar' );
				if ( $event_link !== false ) {
					$message .= sprintf( __( ' <a href="%s">View Event</a>', 'my-calendar' ), $event_link );
				}
				$message .= "</p></div>";
			}
			mc_delete_cache();
		}
	}
	if ( $action == 'edit' && $proceed == true ) {
		$result       = true;
		$url          = ( get_option( 'mc_uri' ) != '' && ! is_numeric( get_option( 'mc_uri' ) ) ) ? '' . sprintf( __( 'View <a href="%s">your calendar</a>.', 'my-calendar' ), get_option( 'mc_uri' ) ) : '';
		$event_author = (int) ( $_POST['event_author'] );
		if ( mc_can_edit_event( $event_author ) ) {
			$update       = $output[2];
			$update       = apply_filters( 'mc_before_save_update', $update, $event_id );
			$date_changed = (
				$update['event_begin'] != $_POST['prev_event_begin'] ||
				date( "H:i:00", strtotime( $update['event_time'] ) ) != $_POST['prev_event_time'] ||
				$update['event_end'] != $_POST['prev_event_end'] ||
				( date( "H:i:00", strtotime( $update['event_endtime'] ) ) != $_POST['prev_event_endtime'] && ( $_POST['prev_event_endtime'] != '' && date( "H:i:00", strtotime( $update['event_endtime'] ) ) != '00:00:00' ) ) )
				? true : false;
			if ( isset( $_POST['event_instance'] ) ) {
				$is_changed     = mc_compare( $update, $event_id );// compares the information sent to the information saved for a given event.
				$event_instance = (int) $_POST['event_instance'];
				if ( $is_changed ) {
					// if changed, create new event, match group id, update instance to reflect event connection, same group id.
					// if group ID == 0, need to add group ID to both records.
					if ( $update['event_group_id'] == 0 ) {
						$update['event_group_id'] = $event_id;
						mc_update_data( $event_id, 'event_group_id', $event_id );
					}
					$mcdb->insert(
						my_calendar_table(),
						$update,
						$formats
					);
					$new_event = $mcdb->insert_id; // need to get this variable into URL for form submit
					$result    = mc_update_instance( $event_instance, $new_event, $update );
					mc_delete_cache();
				} else {
					if ( $update['event_begin'][0] == $_POST['prev_event_begin'] && $update['event_end'][0] == $_POST['prev_event_end'] ) {
						// There were no changes at all.
					} else {
						$result = mc_update_instance( $event_instance, $event_id, $update );
						// Only dates were changed
						$message = "<div class='updated notice'><p>" . __( 'Date/time information for this event has been updated.', 'my-calendar' ) . "$url</p></div>";
						mc_delete_cache();
					}
				}
			} else {
				$result        = $mcdb->update(
					my_calendar_table(),
					$update,
					array( 'event_id' => $event_id ),
					$formats,
					'%d' );
				$recur_changed = ( $update['event_repeats'] != $_POST['prev_event_repeats'] || $update['event_recur'] != $_POST['prev_event_recur'] ) ? true : false;
				if ( $date_changed || $recur_changed ) {
					mc_delete_instances( $event_id );
					mc_increment_event( $event_id );
					mc_delete_cache();
				}
			}
			$data = $update;
			do_action( 'mc_save_event', $action, $data, $event_id, $result );
			if ( $result === false ) {
				$message = "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong>" . __( 'Your event was not updated.', 'my-calendar' ) . "$url</p></div>";
			} else {
				// do an action using the $action and processed event data
				do_action( 'mc_transition_event', (int) $_POST['prev_event_status'], (int) $_POST['event_approved'] );
				$message = "<div class='updated'><p>" . __( 'Event updated successfully', 'my-calendar' ) . ".$url</p></div>";
				mc_delete_cache();
			}
		} else {
			$message = "<div class='error'><p><strong>" . __( 'You do not have sufficient permissions to edit that event.', 'my-calendar' ) . "</strong></p></div>";
		}
	}

	if ( $action == 'delete' ) {
		// Deal with deleting an event from the database
		if ( empty( $event_id ) ) {
			$message = "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong>" . __( "You can't delete an event if you haven't submitted an event id", 'my-calendar' ) . "</p></div>";
		} else {
			$post_id = mc_get_data( 'event_post', $event_id );
			if ( empty( $_POST['event_instance'] ) ) {
				$sql                = "DELETE FROM " . my_calendar_table() . " WHERE event_id='" . (int) $event_id . "'";
				$delete_occurrences = "DELETE FROM " . my_calendar_event_table() . " WHERE occur_event_id = " . (int) $event_id;
				$mcdb->query( $delete_occurrences );
				$mcdb->query( $sql );
				$sql    = "SELECT event_id FROM " . my_calendar_table() . " WHERE event_id='" . (int) $event_id . "'";
				$result = $mcdb->get_results( $sql );
			} else {
				$delete = "DELETE FROM " . my_calendar_event_table() . " WHERE occur_id = " . (int) $_POST['event_instance'];
				$result = $mcdb->get_results( $delete );
			}
			if ( empty( $result ) || empty( $result[0]->event_id ) ) {
				mc_delete_cache();
				// do an action using the event_id
				do_action( 'mc_delete_event', $event_id, $post_id );
				$message = "<div class='updated'><p>" . __( 'Event deleted successfully', 'my-calendar' ) . "</p></div>";
			} else {
				$message = "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong>" . __( 'Despite issuing a request to delete, the event still remains in the database. Please investigate.', 'my-calendar' ) . "</p></div>";
			}
		}
	}
	$message = $message . "\n" . $output[3];

	return array( 'event_id' => $event_id, 'message' => $message );
}

function mc_form_data( $event_id = false ) {
	global $wpdb, $users_entries;
	$mcdb = $wpdb;
	if ( $event_id !== false ) {
		if ( intval( $event_id ) != $event_id ) {
			return "<div class=\"error\"><p>" . __( 'Sorry! That\'s an invalid event key.', 'my-calendar' ) . "</p></div>";
		} else {
			$data = $mcdb->get_results( "SELECT * FROM " . my_calendar_table() . " WHERE event_id='" . (int) $event_id . "'LIMIT 1" );
			if ( empty( $data ) ) {
				return "<div class=\"error\"><p>" . __( "Sorry! We couldn't find an event with that ID.", 'my-calendar' ) . "</p></div>";
			}
			$data = $data[0];
		}
		// Recover users entries if there was an error
		if ( ! empty( $users_entries ) ) {
			$data = $users_entries;
		}
	} else {
		// Deal with possibility that form was submitted but not saved due to error - recover user's entries here
		$data = $users_entries;
	}

	return $data;
}

// The event edit form for the manage events admin page
function mc_edit_event_form( $mode = 'add', $event_id = false ) {
	global $users_entries;
	if ( $event_id != false ) {
		$data = mc_form_data( $event_id );
	} else {
		$data = $users_entries;
	}
	if ( is_object( $data ) && $data->event_approved != 1 && $mode == 'edit' ) {
		$message = __( 'This event must be approved in order for it to appear on the calendar.', 'my-calendar' );
	} else {
		$message = "";
	}
	echo ( $message != '' ) ? "<div class='error'><p>$message</p></div>" : '';
	mc_form_fields( $data, $mode, $event_id );
}

function mc_get_instance_data( $instance_id ) {
	global $wpdb;
	$mcdb   = $wpdb;
	$result = $mcdb->get_row( "SELECT * FROM " . my_calendar_event_table() . " WHERE occur_id = $instance_id" );

	return $result;
}

function mc_show_edit_block( $field ) {
	$admin  = ( get_option( 'mc_input_options_administrators' ) == 'true' && current_user_can( 'manage_options' ) ) ? true : false;
	$input  = get_option( 'mc_input_options' );
	$user   = get_current_user_id();
	$screen = get_current_screen();
	$option = $screen->get_option( 'mc_show_on_page', 'option' );
	$show   = get_user_meta( $user, $option, true );
	if ( empty ( $show ) || $show < 1 ) {
		$show = $screen->get_option( 'mc_show_on_page', 'default' );
	}
	// if this doesn't exist in array, leave it on	
	if ( ! isset( $input[ $field ] ) || ! isset( $show[ $field ] ) ) {
		return true;
	}
	if ( $admin ) {
		if ( isset( $show[ $field ] ) && $show[ $field ] == 'on' ) {
			return true;
		} else {
			return false;
		}
	} else {
		if ( $input[ $field ] == 'off' || $input[ $field ] == '' ) {
			return false;
		} else if ( $show[ $field ] == 'off' ) {
			return false;
		} else {
			return true;
		}
	}
}

function mc_show_block( $field, $has_data, $data ) {
	$return     = $checked = $value = '';
	$show_block = mc_show_edit_block( $field );
	$pre        = '<div class="ui-sortable meta-box-sortables"><div class="postbox">';
	$post       = '</div></div>';
	switch ( $field ) {
		case 'event_desc' :
			if ( $show_block ) {
				// because wp_editor cannot return a value, event_desc fields cannot be filtered if its enabled.
				$value = ( $has_data ) ? stripslashes( $data->event_desc ) : '';
				echo '
				<div class="event_description">
				<label for="content">' . __( 'Event Description', 'my-calendar' ) . '</label><br />';
				if ( user_can_richedit() ) {
					wp_editor( $value, 'content', array( 'textarea_rows' => 10 ) );
				} else {
					echo '<textarea id="content" name="content" class="event_desc" rows="8" cols="80">' . stripslashes( esc_attr( $value ) ) . '</textarea>';
				}
				echo '</div>';
			}
			break;
		case 'event_short' :
			if ( $show_block ) {
				$value  = ( $has_data ) ? stripslashes( esc_attr( $data->event_short ) ) : '';
				$return = '
				<p>
					<label for="e_short">' . __( 'Short Description', 'my-calendar' ) . '</label><br /><textarea id="e_short" name="event_short" rows="2" cols="80">' . $value . '</textarea>
				</p>';
			}
			break;
		case 'event_image' :
			if ( $show_block ) {
				$value  = ( $has_data ) ? esc_attr( $data->event_image ) : '';
				$return = '
				<div class="mc-image-upload field-holder">
					<input type="hidden" name="event_image_id" value="" class="textfield" id="e_image_id" />
					<label for="e_image">' . __( "Add an image:", 'my-calendar' ) . '</label><br /><input type="text" name="event_image" id="e_image" size="60" value="' . $value . '" placeholder="http://yourdomain.com/image.jpg" /> <a href="#" class="button textfield-field">' . __( "Upload", 'my-calendar' ) . '</a>';
				if ( ! empty( $data->event_image ) ) {
					$return .= '<div class="event_image"><img src="' . esc_attr( $data->event_image ) . '" alt="" /></div>';
				} else {
					$return .= '<div class="event_image"></div>';
				}
				$return .= '</div>';
			} else {
				$return = '<input type="hidden" name="event_image" value="' . $value . '" />';
			}
			break;
		case 'event_category' :
			if ( $show_block ) {
				$return = '<p>
				<label for="e_category">' . __( 'Category', 'my-calendar' ) . '</label>
				<select id="e_category" name="event_category">' .
				          mc_category_select( $data ) . '
				</select>
				</p>';
			} else {
				$return = '<div><input type="hidden" name="event_category" value="' . mc_category_select( $data, false ) . '" /></div>';
			}
			break;
		case 'event_link' :
			if ( $show_block ) {
				$value = ( $has_data ) ? esc_url( $data->event_link ) : '';
				if ( $has_data && $data->event_link_expires == '1' ) {
					$checked = " checked=\"checked\"";
				} else if ( $has_data && $data->event_link_expires == '0' ) {
					$checked = "";
				} else if ( get_option( 'mc_event_link_expires' ) == 'true' ) {
					$checked = " checked=\"checked\"";
				}
				$return = '
					<p>
						<label for="e_link">' . __( 'URL', 'my-calendar' ) . '</label> <input type="text" id="e_link" name="event_link" size="40" value="' . $value . '" /> <input type="checkbox" value="1" id="e_link_expires" name="event_link_expires"' . $checked . ' /> <label for="e_link_expires">' . __( 'Link will expire after event', 'my-calendar' ) . '</label>
					</p>';
			}
			break;
		case 'event_recurs' :
			if ( is_object( $data ) ) {
				$event_recur = ( is_object( $data ) ) ? $data->event_recur : '';
				$recurs      = str_split( $event_recur, 1 );
				$recur       = $recurs[0];
				$every       = ( isset( $recurs[1] ) ) ? $recurs[1] : 1;
				if ( $every == 1 && $recur == 'B' ) {
					$every = 2;
				}
				$prev = '<input type="hidden" name="prev_event_repeats" value="' . $data->event_repeats . '" /><input type="hidden" name="prev_event_recur" value="' . $data->event_recur . '" />';
			} else {
				$recur = false;
				$every = 1;
				$prev  = '';
			}
			if ( $show_block && empty( $_GET['date'] ) ) {
				if ( is_object( $data ) && $data->event_repeats != null ) {
					$repeats = $data->event_repeats;
				} else {
					$repeats = 0;
				}
				$return = $pre . '
							<h3>' . __( 'Recurring', 'my-calendar' ) . '</h3>
								<div class="inside">' . $prev . '
									<fieldset>
									<legend>' . __( 'Recurring Events', 'my-calendar' ) . '</legend>
										<p>
											<label for="e_repeats">' . __( 'Repeats', 'my-calendar' ) . ' <input type="text" name="event_repeats" aria-labelledby="e_repeats_label" id="e_repeats" size="1" value="' . $repeats . '" /> <span id="e_repeats_label">' . __( 'times', 'my-calendar' ) . '</span>, </label>
											<label for="e_every">' . __( 'every', 'my-calendar' ) . '</label> <input type="number" name="event_every" id="e_every" size="1" min="1" max="9" maxlength="1" value="' . $every . '" /> 
											<label for="e_recur" class="screen-reader-text">' . __( 'Units', 'my-calendar' ) . '</label> 
											<select name="event_recur" id="e_recur">
												' . mc_recur_options( $recur ) . '
											</select><br />
											' . __( 'Your entry is the number of events after the first occurrence of the event: a recurrence of <em>2</em> means the event will happen three times.', 'my-calendar' ) . '
										</p>
									</fieldset>	
								</div>
							' . $post;
			} else {
				$return = '
				<div>' .
				          $prev . '		
					<input type="hidden" name="event_repeats" value="0" />
					<input type="hidden" name="event_recur" value="S" />
				</div>';
			}
			break;
		case 'event_access' :
			if ( $show_block ) {
				$label  = __( 'Event Access', 'my-calendar' );
				$return = $pre . '
						<h3>' . $label . '</h3>
							<div class="inside">		
								' . mc_event_accessibility( '', $data, $label ) .
				          apply_filters( 'mc_event_access_fields', '', $has_data, $data ) . '						
							</div>' . $post;
			}
			break;
		case 'event_open' :
			if ( $show_block ) {
				$return = $pre . '
				<h3>' . __( 'Event Registration Settings', 'my-calendar' ) . '</h3>
				<div class="inside">
					<fieldset>
					<legend>' . __( 'Event Registration', 'my-calendar' ) . '</legend>
					' . apply_filters( 'mc_event_registration', '', $has_data, $data, 'admin' ) . '		
					</fieldset>
				</div>
				' . $post;
			} else {
				$open         = ( $has_data ) ? $data->event_open : '2';
				$tickets      = ( $has_data ) ? esc_attr( esc_url( $data->event_tickets ) ) : '';
				$registration = ( $has_data ) ? esc_attr( $data->event_registration ) : '';
				$return       = '
				<div>
					<input type="hidden" name="event_open" value="' . $open . '" />
					<input type="hidden"  name="event_tickets" value="' . $tickets . '" />
					<input type="hidden" name="event_registration" value="' . $registration . '" />
				</div>';
			}
			break;
		case 'event_location' :
			if ( $show_block ) {
				$return = mc_locations_fields( $has_data, $data, 'event' );
			} else {
				if ( $has_data ) {
					$return = "
				<div>
					<input type='hidden' name='event_label' value='" . esc_attr( stripslashes( $data->event_label ) ) . "' />
					<input type='hidden' name='event_street' value='" . ( stripslashes( $data->event_street ) ) . "' />
					<input type='hidden' name='event_street2' value='" . ( stripslashes( $data->event_street2 ) ) . "' />
					<input type='hidden' name='event_phone' value='" . ( stripslashes( $data->event_phone ) ) . "' />
					<input type='hidden' name='event_phone2' value='" . ( stripslashes( $data->event_phone2 ) ) . "' />
					<input type='hidden' name='event_city' value='" . ( stripslashes( $data->event_city ) ) . "' />
					<input type='hidden' name='event_state' value='" . ( stripslashes( $data->event_state ) ) . "' />
					<input type='hidden' name='event_postcode' value='" . ( stripslashes( $data->event_postcode ) ) . "' />
					<input type='hidden' name='event_region' value='" . ( stripslashes( $data->event_region ) ) . "' />
					<input type='hidden' name='event_country' value='" . ( stripslashes( $data->event_country ) ) . "' />
					<input type='hidden' name='event_zoom' value='" . ( stripslashes( $data->event_zoom ) ) . "' />
					<input type='hidden' name='event_url' value='" . ( stripslashes( $data->event_url ) ) . "' />
					<input type='hidden' name='event_latitude' value='" . ( stripslashes( $data->event_latitude ) ) . "' />
					<input type='hidden' name='event_longitude' value='" . ( stripslashes( $data->event_longitude ) ) . "' />
				</div>";
				}
			}
			break;
		default:
			return;
	}
	echo apply_filters( 'mc_show_block', $return, $data, $field );
}

function mc_form_fields( $data, $mode, $event_id ) {
	global $wpdb, $user_ID;
	$mcdb     = $wpdb;
	$has_data = ( empty( $data ) ) ? false : true;
	if ( $data ) {
		if ( ! ( $data->event_recur == 'S' || $data->event_recur == 'S1' ) ) {
			$check = mc_increment_event( $data->event_id, array(), 'test' );
			if ( my_calendar_date_xcomp( $check['occur_begin'], $data->event_end . '' . $data->event_endtime ) ) {
				$warning = "<div class='updated'><p>" . __( 'This event ends after the next occurrence begins. Events must end <strong>before</strong> the next occurrence begins.', 'my-calendar' ) . "</p><p>" . sprintf( __( 'Event end date: <strong>%s %s</strong>. Next occurrence starts: <strong>%s</strong>', 'my-calendar' ), $data->event_end, $data->event_endtime, $check['occur_begin'] ) . "</p></div>";
				echo $warning;
			}
		}
	}
	$instance = ( isset( $_GET['date'] ) ) ? (int) $_GET['date'] : false;
	if ( $instance ) {
		$ins      = mc_get_instance_data( $instance );
		$event_id = $ins->occur_event_id;
		$data     = mc_get_event_core( $event_id );
	}
	?>

	<div class="postbox-container jcd-wide">
<div class="metabox-holder">
<?php if ( $mode == 'add' || $mode == 'copy' ) {
	$edit_args = '';
} else {
	$edit_args = "&amp;mode=$mode&amp;event_id=$event_id";
	if ( $instance ) {
		$edit_args .= "&amp;date=$instance";
	}
}
?>
<form id="my-calendar" method="post" action="<?php echo admin_url( 'admin.php?page=my-calendar' ) . $edit_args; ?>">
<div>
	<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/>
	<?php if ( isset( $_GET['ref'] ) ) { ?>
		<input type="hidden" name="ref" value="<?php echo esc_url( $_GET['ref'] ); ?>"/>
	<?php } ?>
	<input type="hidden" name="event_group_id" value="<?php if ( ! empty( $data->event_group_id ) && $mode != 'copy' ) {
		echo $data->event_group_id;
	} else {
		echo mc_group_id();
	} ?>"/>
	<input type="hidden" name="event_action" value="<?php echo $mode; ?>"/>
	<?php if ( ! empty( $_GET['date'] ) ) { ?>
		<input type="hidden" name="event_instance" value="<?php echo (int) $_GET['date']; ?>"/>
	<?php } ?>
	<input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>"/>
	<?php if ( $mode == 'edit' ) { ?>
		<input type='hidden' name='event_post' value="<?php echo $data->event_post; ?>"/>
	<?php } ?>
	<input type="hidden" name="event_author" value="<?php if ( $mode != 'edit' ) {
		echo $user_ID;
	} else {
		echo $data->event_author;
	} ?>"/>
	<input type="hidden" name="event_nonce_name" value="<?php echo wp_create_nonce( 'event_nonce' ); ?>"/>
</div>

<div class="ui-sortable meta-box-sortables">
	<div class="postbox">
		<?php
		if ( isset( $_GET['mode'] ) && $_GET['mode'] == 'edit' ) {
			$text   = __( 'Edit Event', 'my-calendar' );
			$delete = " &middot; <a href='" . admin_url( "admin.php?page=my-calendar-manage&amp;mode=delete&amp;event_id=$data->event_id" ) . "' class='delete'>" . __( 'Delete', 'my-calendar' ) . "</a>";
		} else {
			$text   = __( 'Add Event', 'my-calendar' );
			$delete = '';
		}
		$post_id = ( $has_data ) ? $data->event_post : false;
		if ( $has_data && ! $data->event_post ) {
			$array_data = (array) $data;
			$post_id    = mc_event_post( 'add', $array_data, $data->event_id );
		}
		if ( apply_filters( 'mc_use_permalinks', get_option( 'mc_use_permalinks' ) ) == 'true' ) {
			$post_link = ( $post_id ) ? get_edit_post_link( $post_id ) : false;
			$text_link = ( $post_link ) ? sprintf( " &middot; <a href='%s'>" . __( 'Edit Event Post', 'my-calendar' ) . "</a>", $post_link ) : '';
		} else {
			$text_link = '';
		}
		?>
		<h3><?php echo $text; ?> <span class="alignright"><a
					href="<?php echo admin_url( 'admin.php?page=my-calendar-manage' ); ?>"><?php _e( 'Manage events', 'my-calendar' ); ?></a><?php echo $delete;
				echo $text_link; ?></span>
		</h3>

		<div class="inside">
			<?php
			if ( ! empty( $_GET['date'] ) && $data->event_recur != 'S' ) {
				$event   = mc_get_event( $instance );
				$date    = date_i18n( get_option( 'mc_date_format' ), strtotime( $event->occur_begin ) );
				$message = __( "You are editing the <strong>$date</strong> instance of this event. Other instances of this event will not be changed.", 'my-calendar' );
				//echo "<div><input type='hidden' name='event_instance' value='$instance' /></div>";
				echo "<div class='message updated'><p>$message</p></div>";
			} else if ( isset( $_GET['date'] ) && empty( $_GET['date'] ) ) {
				echo "<div class='message updated'><p>" . __( 'There was an error acquiring information about this event instance. The ID for this event instance was not provided. <strong>You are editing this entire recurrence set.</strong>', 'my-calendar' ) . "</p></div>";
			}
			?>
			<fieldset>
				<legend><?php _e( 'Event Details', 'my-calendar' ); ?></legend>
				<p>
					<label for="e_title"><?php _e( 'Event Title', 'my-calendar' ); ?> <span
							class='required'><?php _e( '(required)', 'my-calendar' ); ?></span></label><br/><input
						type="text" id="e_title" name="event_title" size="50" maxlength="255"
						value="<?php if ( $has_data ) {
							echo apply_filters( 'mc_manage_event_title', stripslashes( esc_attr( $data->event_title ) ), $data );
						} ?>"/><?php
					if ( $mode == 'edit' ) {
						?>
						<input type='hidden' name='prev_event_status'
						       value='<?php echo $data->event_approved; ?>' /><?php
						if ( get_option( 'mc_event_approve' ) == 'true' ) {
							if ( current_user_can( 'mc_approve_events' ) ) { // Added by Roland P. 
								if ( $has_data && $data->event_approved == '1' ) {
									$checked = " checked=\"checked\"";
								} else if ( $has_data && $data->event_approved == '0' ) {
									$checked = "";
								} else if ( get_option( 'mc_event_approve' ) == 'true' ) {
									$checked = "checked=\"checked\"";
								} ?>
								<input type="checkbox" value="1" id="e_approved"
								       name="event_approved" <?php echo $checked; ?> /> <label
									for="e_approved"><?php _e( 'Approve', 'my-calendar' ); ?></label><?php
							} else { // case: editing, approval enabled, user cannot approve 
								?>
								<input type="hidden" value="0"
								       name="event_approved"/><?php _e( 'An administrator must approve your new event.', 'my-calendar' );
							}
						} else { // Case: editing, approval system is disabled - auto approve 
							?>
							<input type="hidden" value="1" name="event_approved"/><?php
						}
					} else { // case: adding new event (if use can, then 1, else 0) 
						if ( get_option( 'mc_event_approve' ) != 'true' ) {
							$dvalue = 1;
						} else if ( current_user_can( 'mc_approve_events' ) ) {
							$dvalue = 1;
						} else {
							$dvalue = 0;
						} ?>
						<input type="hidden" value="<?php echo $dvalue; ?>" name="event_approved" /><?php
					} ?>
				</p>
				<?php if ( is_object( $data ) && $data->event_flagged == 1 ) { ?>
					<div class="error">
						<p>
							<input type="checkbox" value="0" id="e_flagged"
							       name="event_flagged"<?php if ( $has_data && $data->event_flagged == '0' ) {
								echo " checked=\"checked\"";
							} else if ( $has_data && $data->event_flagged == '1' ) {
								echo "";
							} ?> /> <label
								for="e_flagged"><?php _e( 'This event is not spam', 'my-calendar' ); ?></label>
						</p>
					</div>
				<?php
				}
				if ( function_exists( 'jd_doTwitterAPIPost' ) && current_user_can( 'wpt_can_tweet' ) ) {
					if ( ! ( $mode == 'edit' && $data->event_approved == 1 ) ) {
						?>
						<p>
						<label
							for='mc_twitter'><?php _e( 'Post to Twitter (via WP to Twitter)', 'my-calendar' ); ?></label><br/>
						<textarea cols='70' rows='2' id='mc_twitter'
						          name='mc_twitter'><?php do_action( 'mc_twitter_text', $data ); ?></textarea>
						</p><?php
					}
				}
				mc_show_block( 'event_desc', $has_data, $data );
				mc_show_block( 'event_short', $has_data, $data );
				mc_show_block( 'event_image', $has_data, $data );
				?>
				<p>
					<label for="e_host"><?php _e( 'Host', 'my-calendar' ); ?></label>
					<select id="e_host" name="event_host">
						<?php
						// Grab all the categories and list them
						$users = my_calendar_getUsers();
						foreach ( $users as $u ) {
							$display_name = ( $u->display_name == '' ) ? $u->user_nicename : $u->display_name;
							if ( is_object( $data ) && $data->event_host == $u->ID ) {
								$selected = ' selected="selected"';
							} else if ( is_object( $u ) && $u->ID == $user_ID && empty( $data->event_host ) ) {
								$selected = ' selected="selected"';
							} else {
								$selected = '';
							}
							echo "<option value='$u->ID'$selected>$display_name</option>\n";
						}
						?>
					</select>
				</p>
				<?php
				mc_show_block( 'event_category', $has_data, $data );
				mc_show_block( 'event_link', $has_data, $data );
				echo apply_filters( 'mc_event_details', '', $has_data, $data, 'admin' );
				?>
			</fieldset>
		</div>
	</div>
</div>

<div class="ui-sortable meta-box-sortables">
	<div class="postbox">
		<h3><?php _e( 'Date and Time', 'my-calendar' ); ?></h3>

		<div class="inside">
			<?php if ( is_object( $data ) ) { // information for rewriting recurring data ?>
				<input type="hidden" name="prev_event_begin" value="<?php echo $data->event_begin; ?>"/>
				<input type="hidden" name="prev_event_time" value="<?php echo $data->event_time; ?>"/>
				<input type="hidden" name="prev_event_end" value="<?php echo $data->event_end; ?>"/>
				<input type="hidden" name="prev_event_endtime" value="<?php echo $data->event_endtime; ?>"/>
			<?php } ?>
			<fieldset>
				<legend><?php _e( 'Event Date and Time', 'my-calendar' ); ?></legend>
				<div id="e_schedule">
					<div id="event1" class="clonedInput" aria-live="assertive">
						<?php echo apply_filters( 'mc_datetime_inputs', '', $has_data, $data, 'admin' ); ?>
					</div>
					<?php if ( $mode != 'edit' ) { ?>
						<p id="event_span">
							<input type="checkbox" value="1" id="e_span"
							       name="event_span"<?php if ( $has_data && $data->event_span == '1' ) {
								echo " checked=\"checked\"";
							} else if ( $has_data && $data->event_span == '0' ) {
								echo "";
							} else if ( get_option( 'mc_event_span' ) == 'true' ) {
								echo " checked=\"checked\"";
							} ?> /> <label
								for="e_span"><?php _e( 'This is a multi-day event.', 'my-calendar' ); ?></label>
						</p>
						<p class="note">
							<em><?php _e( 'Enter start and end dates/times for each occurrence of the event.', 'my-calendar' ); ?></em>
						</p>
						<div>
							<input type="button" id="add_field"
							       value="<?php _e( 'Add another occurrence', 'my-calendar' ); ?>" class="button"/>
							<input type="button" id="del_field"
							       value="<?php _e( 'Remove last occurrence', 'my-calendar' ); ?>" class="button"/>
						</div>
					<?php } else { ?>
						<div id='mc-accordion'>
							<?php if ( $data->event_recur != 'S' ) { ?>
								<h4><?php _e( 'Scheduled dates for this event', 'my-calendar' ); ?></h4>
								<div>
									<?php _e( 'Editing a single date of an event changes only that date. Editing the root event changes all events in the series.', 'my-calendar' ); ?>
									<ul class="columns">
										<?php if ( isset( $_GET['date'] ) ) {
											$date = (int) $_GET['date'];
										} else {
											$date = false;
										} ?>
										<?php echo mc_instance_list( $data->event_id, $date ); ?>
									</ul>
								</div>
							<?php } ?>
							<?php if ( $data->event_group_id != 0 ) { ?>
								<?php
								$edit_group_url = admin_url( 'admin.php?page=my-calendar-groups&mode=edit&event_id=' . $data->event_id . '&group_id=' . $data->event_group_id );
								?>
								<h4><?php _e( 'Related Events:', 'my-calendar' ); ?> (<a
										href='<?php echo $edit_group_url; ?>'><?php _e( 'Edit group', 'my-calendar' ); ?></a>)
								</h4>
								<div>
									<ul class="columns">
										<?php mc_related_events( $data->event_group_id ); ?>
									</ul>
								</div>
							<?php } ?>
						</div>
					<?php } ?>
				</div>
			</fieldset>
		</div>
	</div>
</div>
<?php
mc_show_block( 'event_recurs', $has_data, $data );
mc_show_block( 'event_access', $has_data, $data );
mc_show_block( 'event_open', $has_data, $data );

if (mc_show_edit_block( 'event_location' ) || mc_show_edit_block( 'event_location_dropdown' )) {
?>

<div class="ui-sortable meta-box-sortables">
	<div class="postbox">
		<h3><?php _e( 'Event Location', 'my-calendar' ); ?></h3>

		<div class="inside location_form">
			<fieldset>
				<legend><?php _e( 'Event Location', 'my-calendar' ); ?></legend><?php
				}
				if ( mc_show_edit_block( 'event_location_dropdown' ) ) {
					$locs = $mcdb->get_results( "SELECT location_id,location_label FROM " . my_calendar_locations_table() . " ORDER BY location_label ASC" );
					if ( ! empty( $locs ) ) {
						?>
						<p>
						<label for="l_preset"><?php _e( 'Choose a preset location:', 'my-calendar' ); ?></label> <select
							name="location_preset" id="l_preset">
							<option value="none"> --</option><?php
							foreach ( $locs as $loc ) {
								echo "<option value=\"" . $loc->location_id . "\">" . stripslashes( $loc->location_label ) . "</option>";
							} ?>
						</select>
						</p><?php
					} else {
						?>
						<input type="hidden" name="location_preset" value="none"/>
						<p><a
							href="<?php echo admin_url( 'admin.php?page=my-calendar-locations' ); ?>"><?php _e( 'Add recurring locations for later use.', 'my-calendar' ); ?></a>
						</p><?php
					}
				} else {
					?>
					<input type="hidden" name="location_preset" value="none"/><?php
				}
				mc_show_block( 'event_location', $has_data, $data );
				if (mc_show_edit_block( 'event_location' ) || mc_show_edit_block( 'event_location_dropdown' )) {
				?>
			</fieldset>
		</div>
	</div>
</div><?php
}
if ( mc_show_edit_block( 'event_specials' ) ) {
	?>
	<div class="ui-sortable meta-box-sortables">
	<div class="postbox">
		<h3><?php _e( 'Special scheduling options', 'my-calendar' ); ?></h3>

		<div class="inside">
			<fieldset>
				<legend><?php _e( 'Special Options', 'my-calendar' ); ?></legend>
				<p>
					<label
						for="e_holiday"><?php _e( 'Cancel this event if it occurs on a date with an event in the Holidays category', 'my-calendar' ); ?></label>
					<input type="checkbox" value="true" id="e_holiday"
					       name="event_holiday"<?php if ( $has_data && $data->event_holiday == '1' ) {
						echo " checked=\"checked\"";
					} else if ( $has_data && $data->event_holiday == '0' ) {
						echo "";
					} else if ( get_option( 'mc_skip_holidays' ) == 'true' ) {
						echo " checked=\"checked\"";
					} ?> />
				</p>

				<p>
					<label
						for="e_fifth_week"><?php _e( 'If this event recurs, and falls on the 5th week of the month in a month with only four weeks, move it back one week.', 'my-calendar' ); ?></label>
					<input type="checkbox" value="true" id="e_fifth_week"
					       name="event_fifth_week"<?php if ( $has_data && $data->event_fifth_week == '1' ) {
						echo " checked=\"checked\"";
					} else if ( $has_data && $data->event_fifth_week == '0' ) {
						echo "";
					} else if ( get_option( 'mc_no_fifth_week' ) == 'true' ) {
						echo " checked=\"checked\"";
					} ?> />
				</p>
			</fieldset>
		</div>
	</div>
	</div><?php
} else {
	?>
	<div>
	<input type="hidden" name="event_holiday" value="true"<?php if ( get_option( 'mc_skip_holidays' ) == 'true' ) {
		echo " checked=\"checked\"";
	} ?> />
	<input type="hidden" name="event_fifth_week" value="true"<?php if ( get_option( 'mc_no_fifth_week' ) == 'true' ) {
		echo " checked=\"checked\"";
	} ?>/>
	</div><?php
} ?>
<p>
	<input type="submit" name="save" class="button-primary" value="<?php _e( 'Save Event', 'my-calendar' ); ?>"/>
</p>
</form>
</div>
	</div><?php
}

function mc_event_accessibility( $form, $data, $label ) {
	$note_value    = '';
	$events_access = array();
	$form .= "
		<fieldset>
			<legend>$label</legend>
			<ul class='accessibility-features checkboxes'>";
	$access = apply_filters( 'mc_event_accessibility', get_option( 'mc_event_access' ) );
	if ( ! empty( $data ) ) {
		$events_access = get_post_meta( $data->event_post, '_mc_event_access', true );
	}
	foreach ( $access as $k => $a ) {
		$id      = "events_access_$k";
		$label   = $a;
		$checked = '';
		if ( is_array( $events_access ) ) {
			$checked = ( in_array( $k, $events_access ) || in_array( $a, $events_access ) ) ? " checked='checked'" : '';
		}
		$item = sprintf( '<li><input type="checkbox" id="%1$s" name="events_access[]" value="%4$s" class="checkbox" %2$s /> <label for="%1$s">%3$s</label></li>', $id, $checked, $label, $a );
		$form .= $item;
	}
	if ( isset( $events_access['notes'] ) ) {
		$note_value = esc_attr( $events_access['notes'] );
	}
	$form .= '<li><label for="events_access_notes">' . __( 'Notes', 'my-calendar' ) . '</label> <input type="text" name="events_access[notes]" value="' . $note_value . '" /></li>';
	$form .= "</ul>
	</fieldset>";

	return $form;
}

// Used on the manage events admin page to display a list of events
function mc_list_events() {
	global $wpdb;
	$mcdb = $wpdb;
	if ( current_user_can( 'mc_approve_events' ) || current_user_can( 'mc_manage_events' ) || current_user_can( 'mc_add_events' ) ) {
		$sortby = ( isset( $_GET['sort'] ) ) ? (int) $_GET['sort'] : get_option( 'mc_default_sort' );

		if ( isset( $_GET['order'] ) ) {
			$sortdir = ( isset( $_GET['order'] ) && $_GET['order'] == 'ASC' ) ? 'ASC' : 'default';
		} else {
			$sortdir = 'default';
		}
		$sortbydirection = ( $sortdir == 'default' ) ? 'DESC' : $sortdir;
		if ( empty( $sortby ) ) {
			$sortbyvalue = 'event_begin';
		} else {
			switch ( $sortby ) {
				case 1:
					$sortbyvalue = 'event_ID';
					break;
				case 2:
					$sortbyvalue = 'event_title';
					break;
				case 3:
					$sortbyvalue = 'event_desc';
					break;
				case 4:
					$sortbyvalue = "event_begin $sortbydirection, event_time";
					break;
				case 5:
					$sortbyvalue = 'event_author';
					break;
				case 6:
					$sortbyvalue = 'event_category';
					break;
				case 7:
					$sortbyvalue = 'event_label';
					break;
				default:
					$sortbyvalue = "event_begin $sortbydirection, event_time";
			}
		}
		$sorting         = ( $sortbydirection == 'DESC' ) ? "&amp;order=ASC" : '';
		$allow_filters   = true;
		$status          = ( isset( $_GET['limit'] ) ) ? $_GET['limit'] : 'all';
		$restrict        = ( isset( $_GET['restrict'] ) ) ? $_GET['restrict'] : 'all';
		switch ( $status ) {
			case 'all':
				$limit = '';
				break;
			case 'reserved':
				$limit = 'WHERE event_approved <> 1';
				break;
			case 'published':
				$limit = 'WHERE event_approved = 1';
				break;
			default:
				$limit = '';
		}
		switch ( $restrict ) {
			case 'all':
				$filter = '';
				break;
			case 'where':
				$filter   = ( isset( $_GET['filter'] ) ) ? $_GET['filter'] : '';
				$restrict = "event_label";
				break;
			case 'author':
				$filter   = ( isset( $_GET['filter'] ) ) ? (int) $_GET['filter'] : '';
				$restrict = "event_author";
				break;
			case 'category':
				$filter   = ( isset( $_GET['filter'] ) ) ? (int) $_GET['filter'] : '';
				$restrict = "event_category";
				break;
			case 'flagged':
				$filter   = ( isset( $_GET['filter'] ) ) ? (int) $_GET['filter'] : '';
				$restrict = "event_flagged";
				break;
			default:
				$filter = '';
		}
		if ( ! current_user_can( 'mc_manage_events' ) && ! current_user_can( 'mc_approve_events' ) ) {
			$restrict      = 'event_author';
			$filter        = get_current_user_id();
			$allow_filters = false;
		}
		$filter = esc_sql( urldecode( $filter ) );
		if ( $restrict == "event_label" ) {
			$filter = "'$filter'";
		}
		if ( $limit == '' && $filter != '' ) {
			$limit = "WHERE $restrict = $filter";
		} else if ( $limit != '' && $filter != '' ) {
			$limit .= "AND $restrict = $filter";
		}
		if ( $filter == '' || ! $allow_filters ) {
			$filtered = "";
		} else {
			$filtered = "<span class='dashicons dashicons-no'></span><a href='" . admin_url( 'admin.php?page=my-calendar-manage' ) . "'>" . __( 'Clear filters', 'my-calendar' ) . "</a>";
		}
		$current        = empty( $_GET['paged'] ) ? 1 : intval( $_GET['paged'] );
		$user           = get_current_user_id();
		$screen         = get_current_screen();
		$option         = $screen->get_option( 'per_page', 'option' );
		$items_per_page = get_user_meta( $user, $option, true );
		if ( empty( $items_per_page ) || $items_per_page < 1 ) {
			$items_per_page = $screen->get_option( 'per_page', 'default' );
		}
		// default limits
		if ( $limit == '' ) {
			$limit .= ( $restrict != 'event_flagged' ) ? " WHERE event_flagged = 0" : '';
		} else {
			$limit .= ( $restrict != 'event_flagged' ) ? " AND event_flagged = 0" : '';
		}
		if ( isset( $_POST['mcs'] ) ) {
			$query = esc_sql( $_POST['mcs'] );
			$limit .= " AND MATCH(event_title,event_desc,event_short,event_label,event_city) AGAINST ('$query' IN BOOLEAN MODE) ";
		}
		$limit .= ( $restrict != 'archived' ) ? " AND event_status = 1" : ' AND event_status = 0';
		$events     = $mcdb->get_results( "SELECT SQL_CALC_FOUND_ROWS * FROM " . my_calendar_table() . " $limit ORDER BY $sortbyvalue $sortbydirection LIMIT " . ( ( $current - 1 ) * $items_per_page ) . ", " . $items_per_page );
		$found_rows = $wpdb->get_col( "SELECT FOUND_ROWS();" );
		$items      = $found_rows[0];
		if ( ( function_exists( 'akismet_http_post' ) || function_exists( 'bs_checker' ) ) && $allow_filters ) {
			?>
			<ul class="links">
			<li>
				<a <?php echo ( isset( $_GET['restrict'] ) && $_GET['restrict'] == 'flagged' ) ? 'class="active-link"' : ''; ?>
					href="<?php echo admin_url( 'admin.php?page=my-calendar-manage&amp;restrict=flagged&amp;filter=1' ); ?>"><?php _e( 'Spam', 'my-calendar' ); ?></a>
			</li>
			</ul><?php
		}
		?>
		<div class='mc-search'>
			<form action="<?php echo add_query_arg( $_GET, admin_url( 'admin.php' ) ); ?>" method="post">
				<div><input type="hidden" name="_wpnonce"
				            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/>
				</div>
				<div>
					<label for="mc_search" class='screen-reader-text'><?php _e( 'Search', 'my-calendar' ); ?></label>
					<input type='text' role='search' name='mcs' id='mc_search'
					       value='<?php if ( isset( $_POST['mcs'] ) ) {
						       esc_attr_e( $_POST['mcs'] );
					       } ?>'/> <input type='submit' value='<?php _e( 'Search Events', 'my-calendar' ); ?>'
					                      class='button-secondary'/>
				</div>
			</form>
		</div>
		<?php
		if ( get_option( 'mc_event_approve' ) == 'true' ) {
			?>
			<ul class="links">
			<li>
				<a <?php echo ( isset( $_GET['limit'] ) && $_GET['limit'] == 'published' ) ? 'class="active-link"' : ''; ?>
					href="<?php echo admin_url( 'admin.php?page=my-calendar-manage&amp;limit=published' ); ?>"><?php _e( 'Published', 'my-calendar' ); ?></a>
			</li>
			<li>
				<a <?php echo ( isset( $_GET['limit'] ) && $_GET['limit'] == 'reserved' ) ? 'class="active-link"' : ''; ?>
					href="<?php echo admin_url( 'admin.php?page=my-calendar-manage&amp;limit=reserved' ); ?>"><?php _e( 'Reserved', 'my-calendar' ); ?></a>
			</li>
			<li>
				<a <?php echo ( isset( $_GET['limit'] ) && $_GET['limit'] == 'all' || ! isset( $_GET['limit'] ) ) ? 'class="active-link"' : ''; ?>
					href="<?php echo admin_url( 'admin.php?page=my-calendar-manage&amp;limit=archived' ); ?>"><?php _e( 'Archived', 'my-calendar' ); ?></a>
			</li>
			<li>
				<a <?php echo ( isset( $_GET['limit'] ) && $_GET['limit'] == 'all' || ! isset( $_GET['limit'] ) ) ? 'class="active-link"' : ''; ?>
					href="<?php echo admin_url( 'admin.php?page=my-calendar-manage&amp;limit=all' ); ?>"><?php _e( 'All', 'my-calendar' ); ?></a>
			</li>
			</ul><?php
		}
		echo $filtered;
		$num_pages = ceil( $items / $items_per_page );
		if ( $num_pages > 1 ) {
			$page_links = paginate_links( array(
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'prev_text' => __( '&laquo; Previous<span class="screen-reader-text"> Events</span>', 'my-calendar' ),
				'next_text' => __( 'Next<span class="screen-reader-text"> Events</span> &raquo;', 'my-calendar' ),
				'total'     => $num_pages,
				'current'   => $current,
				'mid_size'  => 1
			) );
			printf( "<div class='tablenav'><div class='tablenav-pages'>%s</div></div>", $page_links );
		}
		if ( ! empty( $events ) ) {
			?>
			<form action="<?php echo add_query_arg( $_GET, admin_url( 'admin.php' ) ); ?>" method="post">
			<div><input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/>
			</div>
			<div class='mc-actions'>
				<input type="submit" class="button-secondary delete" name="mass_delete"
				       value="<?php _e( 'Delete events', 'my-calendar' ); ?>"/>
				<?php if ( current_user_can( 'mc_approve_events' ) ) { ?>
					<input type="submit" class="button-secondary mc-approve" name="mass_approve"
					       value="<?php _e( 'Approve events', 'my-calendar' ); ?>"/>
				<?php } ?>
				<?php if ( ! ( isset( $_GET['restrict'] ) && $_GET['restrict'] == 'archived' ) ) { ?>
					<input type="submit" class="button-secondary mc-archive" name="mass_archive"
					       value="<?php _e( 'Archive events', 'my-calendar' ); ?>"/>
				<?php } ?>
			</div>

			<table class="widefat wp-list-table" id="my-calendar-admin-table">
				<thead>
				<tr>
					<th scope="col" style="width: 50px;"><input type='checkbox' class='selectall' id='mass_edit'/>
						<label for='mass_edit'><b><?php __( 'Check/Uncheck all', 'my-calendar' ); ?></b></label> <a
							href="<?php echo admin_url( "admin.php?page=my-calendar-manage&amp;sort=1$sorting" ); ?>"><?php _e( 'ID', 'my-calendar' ) ?></a>
					</th>
					<th scope="col"><a
							href="<?php echo admin_url( "admin.php?page=my-calendar-manage&amp;sort=2$sorting" ); ?>"><?php _e( 'Title', 'my-calendar' ) ?></a>
					</th>
					<th scope="col"><a
							href="<?php echo admin_url( "admin.php?page=my-calendar-manage&amp;sort=7$sorting" ); ?>"><?php _e( 'Location', 'my-calendar' ) ?></a>
					</th>
					<th scope="col"><a
							href="<?php echo admin_url( "admin.php?page=my-calendar-manage&amp;sort=4$sorting" ); ?>"><?php _e( 'Date/Time', 'my-calendar' ) ?></a>
					</th>
					<th scope="col"><a
							href="<?php echo admin_url( "admin.php?page=my-calendar-manage&amp;sort=5$sorting" ); ?>"><?php _e( 'Author', 'my-calendar' ) ?></a>
					</th>
					<th scope="col"><a
							href="<?php echo admin_url( "admin.php?page=my-calendar-manage&amp;sort=6$sorting" ); ?>"><?php _e( 'Category', 'my-calendar' ) ?></a>
					</th>
				</tr>
				</thead>
				<?php
				$class      = '';
				$sql        = "SELECT * FROM " . my_calendar_categories_table();
				$categories = $mcdb->get_results( $sql );

				foreach ( array_keys( $events ) as $key ) {
					$event   =& $events[ $key ];
					$class   = ( $class == 'alternate' ) ? '' : 'alternate';
					$pending = ( $event->event_approved == 0 ) ? 'pending' : '';
					$author  = ( $event->event_author != 0 ) ? get_userdata( $event->event_author ) : 'Public Submitter';
					$title   = $event->event_title;

					if ( $event->event_flagged == 1 && ( isset( $_GET['restrict'] ) && $_GET['restrict'] == 'flagged' ) ) {
						$spam       = 'spam';
						$pending    = '';
						$spam_label = '<strong>' . __( 'Possible spam', 'my-calendar' ) . ':</strong> ';
					} else {
						$spam       = '';
						$spam_label = '';
					}
					if ( current_user_can( 'mc_manage_events' ) || current_user_can( 'mc_approve_events' ) || mc_can_edit_event( $event->event_author ) ) {
						?>
						<tr class="<?php echo "$class $spam $pending"; ?>">
							<th scope="row"><input type="checkbox" value="<?php echo $event->event_id; ?>"
							                       name="mass_edit[]"
							                       id="mc<?php echo $event->event_id; ?>" <?php echo ( $event->event_flagged == 1 ) ? 'checked="checked"' : ''; ?> />
								<label
									for="mc<?php echo $event->event_id; ?>"><?php echo $event->event_id; ?></label>
							</th>
							<td title="<?php echo esc_attr( substr( strip_tags( stripslashes( $event->event_desc ) ), 0, 240 ) ); ?>">
								<strong><?php if (mc_can_edit_event( $event->event_author )) { ?>
									<a href="<?php echo admin_url( "admin.php?page=my-calendar&amp;mode=edit&amp;event_id=$event->event_id" ); ?>"
									   class='edit'>
										<?php } ?>
										<?php echo $spam_label;
										echo stripslashes( $title ); ?>
									<?php if ( mc_can_edit_event( $event->event_author ) ) {
										echo "</a>";
									} ?></strong>

								<div class='row-actions' style="visibility:visible;">
									<a href="<?php echo admin_url( "admin.php?page=my-calendar&amp;mode=copy&amp;event_id=$event->event_id" ); ?>"
									   class='copy'><?php _e( 'Copy', 'my-calendar' ); ?></a> |
									<?php if ( mc_can_edit_event( $event->event_author ) ) { ?>
										<a href="<?php echo admin_url( "admin.php?page=my-calendar&amp;mode=edit&amp;event_id=$event->event_id" ); ?>"
										   class='edit'><?php _e( 'Edit', 'my-calendar' ); ?></a> <?php if ( mc_event_is_grouped( $event->event_group_id ) ) { ?>
											| <a
												href="<?php echo admin_url( "admin.php?page=my-calendar-groups&amp;mode=edit&amp;event_id=$event->event_id&amp;group_id=$event->event_group_id" ); ?>"
												class='edit group'><?php _e( 'Edit Group', 'my-calendar' ); ?></a>
										<?php } ?>
										| <a
											href="<?php echo admin_url( "admin.php?page=my-calendar-manage&amp;mode=delete&amp;event_id=$event->event_id" ); ?>"
											class="delete"><?php _e( 'Delete', 'my-calendar' ); ?></a>
									<?php
									} else {
										_e( "Not editable.", 'my-calendar' );
									} ?>
									<?php if ( get_option( 'mc_event_approve' ) == 'true' ) { ?>
										|
										<?php if ( current_user_can( 'mc_approve_events' ) ) { // Added by Roland P.?>
											<?php if ( $event->event_approved == '1' ) {
												$mo = 'reject';
												$te = __( 'Reject', 'my-calendar' );
											} else {
												$mo = 'publish';
												$te = __( 'Approve', 'my-calendar' );
											} ?>
											<a href="<?php echo admin_url( "admin.php?page=my-calendar-manage&amp;mode=$mo&amp;event_id=$event->event_id" ); ?>"
											   class='<?php echo $mo; ?>'><?php echo $te; ?></a>
										<?php
										} else {
											switch ( $event->event_approved ) {
												case 1:
													_e( 'Approved', 'my-calendar' );
													break;
												case 2:
													_e( 'Rejected', 'my-calendar' );
													break;
												default:
													_e( 'Awaiting Approval', 'my-calendar' );
											}
										}
									} ?>
								</div>
							</td>
							<td><?php if ( $event->event_label != '' ) { ?><a class='mc_filter'
							                                                  href='<?php $elabel = urlencode( $event->event_label );
							                                                  echo admin_url( "admin.php?page=my-calendar-manage&amp;filter=$elabel&amp;restrict=where" ); ?>'
							                                                  title="<?php _e( 'Filter by location', 'my-calendar' ); ?>">
									<span
										class="screen-reader-text"><?php _e( 'Show only: ', 'my-calendar' ); ?></span><?php echo stripslashes( $event->event_label ); ?>
									</a><?php } ?></td>
							<?php if ( $event->event_time != "00:00:00" ) {
								$eventTime = date_i18n( get_option( 'mc_time_format' ), strtotime( $event->event_time ) );
							} else {
								$eventTime = get_option( 'mc_notime_text' );
							} ?>
							<td><?php
								$date_format = ( get_option( 'mc_date_format' ) == '' ) ? get_option( 'date_format' ) : get_option( 'mc_date_format' );
								$begin       = date_i18n( $date_format, strtotime( $event->event_begin ) );
								echo "$begin, $eventTime"; ?>
								<div class="recurs">
									<strong><?php _e( 'Recurs', 'my-calendar' ); ?></strong>
									<?php
									$recurs = str_split( $event->event_recur, 1 );
									$recur  = $recurs[0];
									$every  = ( isset( $recurs[1] ) ) ? $recurs[1] : 1;

									// Interpret the DB values into something human readable
									switch ( $recur ) {
										case 'S':
											_e( 'Never', 'my-calendar' );
											break;
										case 'D':
											( $every == 1 ) ? _e( 'Daily', 'my-calendar' ) : printf( __( 'Every %d days', 'my-calendar' ), $every );
											break;
										case 'E':
											( $every == 1 ) ? _e( 'Weekdays', 'my-calendar' ) : printf( __( 'Every %d weekdays', 'my-calendar' ), $every );
											break;
										case 'W':
											( $every == 1 ) ? _e( 'Weekly', 'my-calendar' ) : printf( __( 'Every %d weeks', 'my-calendar' ), $every );
											break;
										case 'B':
											_e( 'Bi-Weekly', 'my-calendar' );
											break;
										case 'M':
											( $every == 1 ) ? _e( 'Monthly (by date)', 'my-calendar' ) : printf( __( 'Every %d months (by date)', 'my-calendar' ), $every );
											break;
										case 'U':
											_e( 'Monthly (by day)', 'my-calendar' );
											break;
										case 'Y':
											( $every == 1 ) ? _e( 'Yearly', 'my-calendar' ) : printf( __( 'Every %d years', 'my-calendar' ), $every );
											break;
									}
									$eternity = _mc_increment_values( $recur );
									if ( $recur == 'S' ) {
									} else if ( $event->event_repeats > 0 ) {
										printf( __( '&ndash; %d Times', 'my-calendar' ), $event->event_repeats );
									} else if ( $eternity ) {
										printf( __( '&ndash; %d Times', 'my-calendar' ), $eternity );
									}
									?>
								</div>
							</td>
							<td><a class='mc_filter' href="<?php $auth = ( is_object( $author ) ) ? $author->ID : 0;
								echo admin_url( "admin.php?page=my-calendar-manage&amp;filter=$auth&amp;restrict=author" ); ?>"
							       title="<?php _e( 'Filter by author', 'my-calendar' ); ?>"><span
										class="screen-reader-text"><?php _e( 'Show only: ', 'my-calendar' ); ?></span><?php echo( is_object( $author ) ? $author->display_name : $author ); ?>
								</a></td>
							<?php
							$this_category = $event->event_category;
							foreach ( $categories as $key => $value ) {
								if ( $value->category_id == $this_category ) {
									$this_cat = $categories[ $key ];
								}
							}
							?>
							<td>
								<div class="category-color"
								     style="background-color:<?php echo ( strpos( $this_cat->category_color, '#' ) !== 0 ) ? '#' : '';
								     echo $this_cat->category_color; ?>;"></div>
								<a class='mc_filter'
								   href='<?php echo admin_url( "admin.php?page=my-calendar-manage&amp;filter=$event->event_category&amp;restrict=category" ); ?>'
								   title="<?php _e( 'Filter by category', 'my-calendar' ); ?>"><span
										class="screen-reader-text"><?php _e( 'Show only: ', 'my-calendar' ); ?></span><?php echo stripslashes( $this_cat->category_name ); ?>
								</a></td>
							<?php unset( $this_cat ); ?>
						</tr>
					<?php
					}
				}
				?>
			</table>
			<p>
				<input type="submit" class="button-secondary delete" name="mass_delete"
				       value="<?php _e( 'Delete events', 'my-calendar' ); ?>"/>
				<?php if ( current_user_can( 'mc_approve_events' ) ) { ?>
					<input type="submit" class="button-secondary mc-approve" name="mass_approve"
					       value="<?php _e( 'Approve events', 'my-calendar' ); ?>"/>
				<?php } ?>
				<?php if ( ! ( isset( $_GET['restrict'] ) && $_GET['restrict'] == 'archived' ) ) { ?>
					<input type="submit" class="button-secondary mc-archive" name="mass_archive"
					       value="<?php _e( 'Archive events', 'my-calendar' ); ?>"/>
				<?php } ?>
			</p>

			<p>
				<?php if ( ! ( isset( $_GET['restrict'] ) && $_GET['restrict'] == 'archived' ) ) { ?>
					<a class='mc_filter'
					   href='<?php echo admin_url( "admin.php?page=my-calendar-manage&amp;restrict=archived" ); ?>'><?php _e( 'View Archived Events', 'my-calendar' ); ?></a>
				<?php } else { ?>
					<a class='mc_filter'
					   href='<?php echo admin_url( "admin.php?page=my-calendar-manage" ); ?>'><?php _e( 'Return to Manage Events', 'my-calendar' ); ?></a>
				<?php } ?>
			</p>
			</form>
		<?php
		} else {
			?>
			<p><?php _e( "There are no events in the database meeting your current criteria.", 'my-calendar' ) ?></p><?php
		}
	}
}

function mc_check_data( $action, $post, $i ) {
	$post = apply_filters( 'mc_pre_checkdata', $post, $action, $i );
	global $wpdb, $current_user, $users_entries;
	$mcdb   = $wpdb;
	$submit = array();
	$errors = '';
	$every  = $recur = $events_access = $begin = $end = $short = $time = $endtime = $event_label = $event_street = $event_street2 = $event_city = $event_state = $event_postcode = $event_region = $event_country = $event_url = $event_image = $event_phone = $event_phone2 = $event_access = $event_tickets = $event_registration = $event_author = $category = $expires = $event_zoom = $event_open = $event_group = $approved = $host = $event_fifth_week = $event_holiday = $event_group_id = $event_span = $event_hide_end = $event_longitude = $event_latitude = '';
	if ( get_magic_quotes_gpc() ) {
		$post = array_map( 'stripslashes_deep', $post );
	}
	if ( ! wp_verify_nonce( $post['event_nonce_name'], 'event_nonce' ) ) {
		return array();
	}

	if ( $action == 'add' || $action == 'edit' || $action == 'copy' ) {
		$title = ! empty( $post['event_title'] ) ? trim( $post['event_title'] ) : '';
		$desc  = ! empty( $post['content'] ) ? trim( $post['content'] ) : '';
		$short = ! empty( $post['event_short'] ) ? trim( $post['event_short'] ) : '';
		$recur = ! empty( $post['event_recur'] ) ? trim( $post['event_recur'] ) : '';
		$every = ! empty( $post['event_every'] ) ? (int) $post['event_every'] : 1;
		// if this is an all weekdays event, and it's been scheduled to start on a weekend, the math gets nasty. 
		// ...AND there's no reason to allow it, since weekday events will NEVER happen on the weekend.
		$begin = trim( $post['event_begin'][ $i ] );
		$end   = trim( $post['event_end'][ $i ] );
		if ( $recur == 'E' && ( date( 'w', strtotime( $begin ) ) == 0 || date( 'w', strtotime( $begin ) ) == 6 ) ) {
			if ( date( 'w', strtotime( $begin ) ) == 0 ) {
				$newbegin = my_calendar_add_date( $begin, 1 );
				if ( ! empty( $post['event_end'][ $i ] ) ) {
					$newend = my_calendar_add_date( $end, 1 );
				} else {
					$newend = $newbegin;
				}
			} else if ( date( 'w', strtotime( $begin ) ) == 6 ) {
				$newbegin = my_calendar_add_date( $begin, 2 );
				if ( ! empty( $post['event_end'][ $i ] ) ) {
					$newend = my_calendar_add_date( $end, 2 );
				} else {
					$newend = $newbegin;
				}
			}
			$begin = $newbegin;
			$end   = $newend;
		} else {
			$begin = ! empty( $post['event_begin'][ $i ] ) ? trim( $post['event_begin'][ $i ] ) : '';
			$end   = ! empty( $post['event_end'][ $i ] ) ? trim( $post['event_end'][ $i ] ) : $begin;
		}

		$begin   = date( 'Y-m-d', strtotime( $begin ) );// regardless of entry format, convert.
		$time    = ! empty( $post['event_time'][ $i ] ) ? trim( $post['event_time'][ $i ] ) : '';
		$endtime = ! empty( $post['event_endtime'][ $i ] ) ? trim( $post['event_endtime'][ $i ] ) : date( 'H:i:s', strtotime( $time . '+1 hour' ) );
		$endtime = ( $time == '' || $time == '00:00:00' ) ? '00:00:00' : $endtime; // set at midnight if all day.
		$endtime = ( $endtime == '' ) ? '00:00:00' : date( 'H:i:00', strtotime( $endtime ) );
		// if the end time is midnight but the date is empty, change to tomorrow.
		if ( $endtime == '00:00:00' && $action != 'edit' ) { // cascading problem if this happens on edits!
			$end = date( 'Y-m-d', strtotime( $end . ' +1 day' ) );
		}
		// prevent setting enddate to incorrect value on copy.
		if ( strtotime( $end ) < strtotime( $begin ) && $action == 'copy' ) {
			$end = date( 'Y-m-d', ( strtotime( $begin ) + ( strtotime( $post['prev_event_end'] ) - strtotime( $post['prev_event_begin'] ) ) ) );
		}
		if ( isset( $post['event_allday'] ) && (int) $post['event_allday'] !== 0 ) {
			$time = $endtime = '00:00:00';
		}
		$end                = date( 'Y-m-d', strtotime( $end ) ); // regardless of entry format, convert.			
		$repeats            = ( ! empty( $post['event_repeats'] ) || trim( $post['event_repeats'] ) == '' ) ? trim( $post['event_repeats'] ) : 0;
		$host               = ! empty( $post['event_host'] ) ? $post['event_host'] : $current_user->ID;
		$category           = ! empty( $post['event_category'] ) ? $post['event_category'] : '';
		$event_link         = ! empty( $post['event_link'] ) ? trim( $post['event_link'] ) : '';
		$expires            = ! empty( $post['event_link_expires'] ) ? $post['event_link_expires'] : '0';
		$approved           = ! empty( $post['event_approved'] ) ? $post['event_approved'] : '0';
		$location_preset    = ! empty( $post['location_preset'] ) ? $post['location_preset'] : '';
		$event_author       = ! empty( $post['event_author'] ) ? $post['event_author'] : $current_user->ID;
		$event_open         = ( isset( $post['event_open'] ) && $post['event_open'] !== 0 ) ? $post['event_open'] : '2';
		$event_tickets      = ( isset( $post['event_tickets'] ) ) ? trim( $post['event_tickets'] ) : '';
		$event_registration = ( isset( $post['event_registration'] ) ) ? trim( $post['event_registration'] ) : '';
		$event_group        = ! empty( $post['event_group'] ) ? 1 : 0;
		$event_image        = ( isset( $post['event_image'] ) ) ? esc_url_raw( $post['event_image'] ) : '';
		$event_fifth_week   = ! empty( $post['event_fifth_week'] ) ? 1 : 0;
		$event_holiday      = ! empty( $post['event_holiday'] ) ? 1 : 0;
		// get group id: if multiple events submitted, auto group OR if event being submitted is already part of a group; otherwise zero.
		$group_id_submitted = (int) $post['event_group_id'];
		$event_group_id     = ( ( is_array( $post['event_begin'] ) && count( $post['event_begin'] ) > 1 ) || mc_event_is_grouped( $group_id_submitted ) ) ? $group_id_submitted : 0;
		$event_span         = ( ! empty( $post['event_span'] ) && $event_group_id != 0 ) ? 1 : 0;
		$event_hide_end     = ( ! empty( $post['event_hide_end'] ) ) ? (int) $post['event_hide_end'] : 0;
		$event_hide_end     = ( $time == '' || $time == '00:00:00' ) ? 1 : $event_hide_end; // hide end time automatically on all day events
		// set location
		if ( $location_preset != 'none' ) {
			$sql             = "SELECT * FROM " . my_calendar_locations_table() . " WHERE location_id = $location_preset";
			$location        = $mcdb->get_row( $sql );
			$event_label     = $location->location_label;
			$event_street    = $location->location_street;
			$event_street2   = $location->location_street2;
			$event_city      = $location->location_city;
			$event_state     = $location->location_state;
			$event_postcode  = $location->location_postcode;
			$event_region    = $location->location_region;
			$event_country   = $location->location_country;
			$event_url       = $location->location_url;
			$event_longitude = $location->location_longitude;
			$event_latitude  = $location->location_latitude;
			$event_zoom      = $location->location_zoom;
			$event_phone     = $location->location_phone;
			$event_phone2    = $location->location_phone2;
			$event_access    = $location->location_access;
		} else {
			$event_label     = ! empty( $post['event_label'] ) ? $post['event_label'] : '';
			$event_street    = ! empty( $post['event_street'] ) ? $post['event_street'] : '';
			$event_street2   = ! empty( $post['event_street2'] ) ? $post['event_street2'] : '';
			$event_city      = ! empty( $post['event_city'] ) ? $post['event_city'] : '';
			$event_state     = ! empty( $post['event_state'] ) ? $post['event_state'] : '';
			$event_postcode  = ! empty( $post['event_postcode'] ) ? $post['event_postcode'] : '';
			$event_region    = ! empty( $post['event_region'] ) ? $post['event_region'] : '';
			$event_country   = ! empty( $post['event_country'] ) ? $post['event_country'] : '';
			$event_url       = ! empty( $post['event_url'] ) ? $post['event_url'] : '';
			$event_longitude = ! empty( $post['event_longitude'] ) ? $post['event_longitude'] : '';
			$event_latitude  = ! empty( $post['event_latitude'] ) ? $post['event_latitude'] : '';
			$event_zoom      = ! empty( $post['event_zoom'] ) ? $post['event_zoom'] : '';
			$event_phone     = ! empty( $post['event_phone'] ) ? $post['event_phone'] : '';
			$event_phone2    = ! empty( $post['event_phone2'] ) ? $post['event_phone2'] : '';
			$event_access    = ! empty( $post['event_access'] ) ? $post['event_access'] : '';
			$event_access    = ! empty( $post['event_access_hidden'] ) ? unserialize( $post['event_access_hidden'] ) : $event_access;
			if ( isset( $post['mc_copy_location'] ) && $post['mc_copy_location'] == 'on' && $i == 0 ) { // only the first event, if adding multiples.
				$add_loc     = array(
					'location_label'     => $event_label,
					'location_street'    => $event_street,
					'location_street2'   => $event_street2,
					'location_city'      => $event_city,
					'location_state'     => $event_state,
					'location_postcode'  => $event_postcode,
					'location_region'    => $event_region,
					'location_country'   => $event_country,
					'location_url'       => $event_url,
					'location_longitude' => $event_longitude,
					'location_latitude'  => $event_latitude,
					'location_zoom'      => $event_zoom,
					'location_phone'     => $event_phone,
					'location_phone2'    => $event_phone2,
					'location_access'    => ( is_array( $event_access ) ) ? serialize( $event_access ) : ''
				);
				$loc_formats = array(
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%f',
					'%f',
					'%d',
					'%s',
					'%s',
					'%s'
				);
				$mcdb->insert( my_calendar_locations_table(), $add_loc, $loc_formats );
			}
		}
		// Perform validation on the submitted dates - checks for valid years and months
		if ( mc_checkdate( $begin ) && mc_checkdate( $end ) ) {
			// Make sure dates are equal or end date is later than start date
			if ( strtotime( $end ) < strtotime( $begin ) ) {
				$errors .= "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong> " . __( 'Your event end date must be either after or the same as your event begin date', 'my-calendar' ) . "</p></div>";
			}
		} else {
			$errors .= "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong> " . __( 'Your date formatting is correct but one or more of your dates is invalid. Check for number of days in month and leap year related errors.', 'my-calendar' ) . "</p></div>";
		}

		// We check for a valid time, or an empty one
		$time            = ( $time == '' ) ? '00:00:00' : date( 'H:i:00', strtotime( $time ) );
		$time_format_one = '/^([0-1][0-9]):([0-5][0-9]):([0-5][0-9])$/';
		$time_format_two = '/^([2][0-3]):([0-5][0-9]):([0-5][0-9])$/';
		if ( preg_match( $time_format_one, $time ) || preg_match( $time_format_two, $time ) || $time == '' ) {
		} else {
			$errors .= "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong> " . __( 'The time field must either be blank or be entered in the format hh:mm am/pm', 'my-calendar' ) . "</p></div>";
		}
		// We check for a valid end time, or an empty one
		if ( preg_match( $time_format_one, $endtime ) || preg_match( $time_format_two, $endtime ) || $endtime == '' ) {
		} else {
			$errors .= "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong> " . __( 'The end time field must either be blank or be entered in the format hh:mm am/pm', 'my-calendar' ) . "</p></div>";
		}
		// We check to make sure the URL is acceptable (blank or starting with http://)
		if ( ! ( $event_link == '' || preg_match( '/^(http)(s?)(:)\/\//', $event_link ) ) ) {
			$event_link = "http://" . $event_link;
		}
	}
	// A title is required, and can't be more than 255 characters.
	$title_length = strlen( $title );
	if ( ! ( $title_length >= 1 && $title_length <= 255 ) ) {
		$title = __( 'Untitled Event', 'my-calendar' );
	}
	// Run checks on recurrence profile                                                                      
	if ( ( $repeats == 0 && $recur == 'S' ) || ( ( $repeats >= 0 ) && ( $recur == 'W' || $recur == 'B' || $recur == 'M' || $recur == 'U' || $recur == 'Y' || $recur == 'D' || $recur == 'E' ) ) ) {
		$recur = $recur . $every;
	} else {
		// if it's not valid, assign a default value.
		$repeats = 0;
		$recur   = 'S1';
	}
	if ( function_exists( 'mcs_submissions' ) && isset( $post['mcs_check_conflicts'] ) ) {
		$conflicts = mcs_check_conflicts( $begin, $time, $end, $endtime, $event_label );
		if ( $conflicts ) {
			$errors .= "<div class='error'><p><strong>" . __( 'Error', 'my-calendar' ) . ":</strong> " . __( 'That event conflicts with a previously scheduled event.', 'my-calendar' ) . "</p></div>";
		}
	}
	$spam = mc_spam( $event_link, $desc, $post );
	// the likelihood that something will be both flagged as spam and have a zero start time 
	// and yet be legitimate is crazy minimal. Just kill it.
	if ( $spam == 1 && $begin == '1970-01-01' ) {
		die;
	}
	if ( $errors == '' ) {
		$ok     = true;
		$submit = array(
			// strings
			'event_begin'        => $begin,
			'event_end'          => $end,
			'event_title'        => $title,
			'event_desc'         => force_balance_tags( $desc ),
			'event_short'        => force_balance_tags( $short ),
			'event_time'         => $time,
			'event_endtime'      => $endtime,
			'event_link'         => $event_link,
			'event_label'        => $event_label,
			'event_street'       => $event_street,
			'event_street2'      => $event_street2,
			'event_city'         => $event_city,
			'event_state'        => $event_state,
			'event_postcode'     => $event_postcode,
			'event_region'       => $event_region,
			'event_country'      => $event_country,
			'event_url'          => $event_url,
			'event_recur'        => $recur,
			'event_image'        => $event_image,
			'event_phone'        => $event_phone,
			'event_phone2'       => $event_phone2,
			'event_access'       => ( is_array( $event_access ) ) ? serialize( $event_access ) : '',
			'event_tickets'      => $event_tickets,
			'event_registration' => $event_registration,
			// integers
			'event_repeats'      => $repeats,
			'event_author'       => $event_author,
			'event_category'     => $category,
			'event_link_expires' => $expires,
			'event_zoom'         => $event_zoom,
			'event_open'         => $event_open,
			'event_group'        => $event_group,
			'event_approved'     => $approved,
			'event_host'         => $host,
			'event_flagged'      => $spam,
			'event_fifth_week'   => $event_fifth_week,
			'event_holiday'      => $event_holiday,
			'event_group_id'     => $event_group_id,
			'event_span'         => $event_span,
			'event_hide_end'     => $event_hide_end,
			// floats
			'event_longitude'    => $event_longitude,
			'event_latitude'     => $event_latitude
		);
	} else {
		$ok           = false;
		$event_access = ( is_array( $event_access ) ) ? serialize( $event_access ) : '';
		// The form is going to be rejected due to field validation issues, so we preserve the users entries here
		// all submitted data should be in this object, regardless of data destination.
		$users_entries->event_title        = $title;
		$users_entries->event_desc         = $desc;
		$users_entries->event_begin        = $begin;
		$users_entries->event_end          = $end;
		$users_entries->event_time         = $time;
		$users_entries->event_endtime      = $endtime;
		$users_entries->event_recur        = $recur;
		$users_entries->event_repeats      = $repeats;
		$users_entries->event_host         = $host;
		$users_entries->event_category     = $category;
		$users_entries->event_link         = $event_link;
		$users_entries->event_link_expires = $expires;
		$users_entries->event_label        = $event_label;
		$users_entries->event_street       = $event_street;
		$users_entries->event_street2      = $event_street2;
		$users_entries->event_city         = $event_city;
		$users_entries->event_state        = $event_state;
		$users_entries->event_postcode     = $event_postcode;
		$users_entries->event_country      = $event_country;
		$users_entries->event_region       = $event_region;
		$users_entries->event_url          = $event_url;
		$users_entries->event_longitude    = $event_longitude;
		$users_entries->event_latitude     = $event_latitude;
		$users_entries->event_zoom         = $event_zoom;
		$users_entries->event_phone        = $event_phone;
		$users_entries->event_phone2       = $event_phone2;
		$users_entries->event_author       = $event_author;
		$users_entries->event_open         = $event_open;
		$users_entries->event_short        = $short;
		$users_entries->event_group        = $event_group;
		$users_entries->event_approved     = $approved;
		$users_entries->event_image        = $event_image;
		$users_entries->event_fifth_week   = $event_fifth_week;
		$users_entries->event_holiday      = $event_holiday;
		$users_entries->event_flagged      = 0;
		$users_entries->event_group_id     = $event_group_id;
		$users_entries->event_span         = $event_span;
		$users_entries->event_hide_end     = $event_hide_end;
		$users_entries->event_access       = $event_access;
		$users_entries->events_access      = serialize( $events_access );
		$users_entries->event_tickets      = $event_tickets;
		$users_entries->event_registration = $event_registration;
	}
	$data = array( $ok, $users_entries, $submit, $errors );

	return $data;
}

function mcs_check_conflicts( $begin, $time, $end, $endtime, $event_label ) {
	global $wpdb;
	$select_location = ( $event_label != '' ) ? "event_label = '$event_label'AND" : '';
	$event_query     = "SELECT occur_id 
					FROM " . MY_CALENDAR_EVENTS_TABLE . "
					JOIN " . MY_CALENDAR_TABLE . "
					ON (event_id=occur_event_id) 
					WHERE $select_location
					( occur_begin BETWEEN '$begin $time'AND '$end $endtime'OR occur_end BETWEEN '$begin $time'AND '$end $endtime')";
	$results         = $wpdb->get_results( $event_query );

	return ( ! empty( $results ) ) ? $results : false;
}

/* Event editing utilities */
function mc_compare( $update, $id ) {
	$event         = mc_get_event_core( $id );
	$update_string = $event_string = '';
	//$comparison_test = array();
	foreach ( $update as $k => $v ) {
		// event_recur and event_repeats always set to single and 0; event_begin and event_end need to be checked elsewhere.
		if ( $k != 'event_recur' && $k != 'event_repeats' && $k != 'event_begin' && $k != 'event_end' ) {
			$update_string .= trim( $v );
			$event_string .= trim( $event->$k );
			$event->$k;
		}
	}
	$update_hash = md5( $update_string );
	$event_hash  = md5( $event_string );
	if ( $update_hash == $event_hash ) {
		return false;
	} else {
		return true;
	}
}

// args: instance ID, event ID, array containing updated dates.
function mc_update_instance( $event_instance, $event_id, $update = array() ) {
	global $wpdb;
	$mcdb = $wpdb;
	if ( ! empty( $update ) ) {
		$event   = mc_get_event( $event_instance );
		$formats = array( '%d', '%s', '%s', '%d' );
		$begin   = ( ! empty( $update ) ) ? $update['event_begin'] . ' ' . $update['event_time'] : $event->occur_begin;
		$end     = ( ! empty( $update ) ) ? $update['event_end'] . ' ' . $update['event_endtime'] : $event->occur_end;
		$data    = array(
			'occur_event_id' => $event_id,
			'occur_begin'    => $begin,
			'occur_end'      => $end,
			'occur_group_id' => $update['event_group_id']
		);
	} else {
		$formats  = array( '%d', '%d' );
		$group_id = mc_get_data( 'event_group_id', $event_id );
		$data     = array( 'occur_event_id' => $event_id, 'occur_group_id' => $group_id );
	}

	$result = $mcdb->update(
		my_calendar_event_table(),
		$data,
		array( 'occur_id' => $event_instance ),
		$formats,
		'%d' );

	return $result;
}

// arbitrary field update to event table
function mc_update_data( $event_id, $field, $value, $format = '%d' ) {
	global $wpdb;
	$data    = array( $field => $value );
	$format  = esc_sql( $format );
	$formats = ( $format );
	$result  = $wpdb->update(
		my_calendar_table(),
		$data,
		array( 'event_id' => $event_id ),
		$formats,
		'%d' );

	return $result;
}

/* returns next available group ID */
function mc_group_id() {
	global $wpdb;
	$mcdb   = $wpdb;
	$query  = "SELECT MAX(event_id) FROM " . my_calendar_table();
	$result = $mcdb->get_var( $query );
	$next   = $result + 1;

	return $next;
}

function mc_instance_list( $id, $occur = false, $template = '<h3>{title}</h3>{description}', $list = '<li>{date}, {time}</li>', $before = "<ul>", $after = "</ul>" ) {
	global $wpdb;
	$id      = (int) $id;
	$output  = '';
	$sql     = "SELECT * FROM " . my_calendar_event_table() . " WHERE occur_event_id=$id";
	$results = $wpdb->get_results( $sql );
	if ( is_array( $results ) && is_admin() ) {
		foreach ( $results as $result ) {
			if ( $result->occur_id == $occur ) {
				$form_control = '';
				$current      = "<em>" . __( 'Editing: ', 'my-calendar' ) . "</em>";
				$end          = '';
			} else {
				$form_control = "<input type='checkbox' name='delete_occurrences[]' id='delete_$result->occur_id' value='$result->occur_id' aria-labelledby='occur_label occur_date' /> <label id='occur_label' for='delete_$result->occur_id'>Delete</label> ";
				$current      = "<a href='" . admin_url( 'admin.php?page=my-calendar' ) . "&amp;mode=edit&amp;event_id=$id&amp;date=$result->occur_id'>";
				$end          = "</a>";
			}
			$begin = "<span id='occur_date'>" . date_i18n( get_option( 'mc_date_format' ), strtotime( $result->occur_begin ) ) . ', ' . date( get_option( 'mc_time_format' ), strtotime( $result->occur_begin ) ) . "</span>";
			$output .= "<li>$form_control$current$begin$end</li>";
		}
	} else {
		$details = '';
		foreach ( $results as $result ) {
			$event_id = $result->occur_id;
			$event    = mc_get_event( $event_id );
			$array    = mc_create_tags( $event );
			if ( in_array( $template, array( 'details', 'grid', 'list', 'mini' ) ) ) {
				if ( get_option( 'mc_use_' . $template . '_template' ) == 1 ) {
					$template = mc_get_template( $template );
				} else {
					$template = false;
					$details  = my_calendar_draw_event( $event, $type = "single", $event->event_begin, $event->event_time, '' );
				}
			}
			$item = ( $list != '' ) ? jd_draw_template( $array, $list ) : '';
			if ( $details == '' ) {
				$details = ( $template != '' ) ? jd_draw_template( $array, $template ) : '';
			}
			$output .= $item;
		}
		$output = $details . $before . $output . $after;
	}

	return $output;
}

function mc_event_is_grouped( $group_id ) {
	global $wpdb;
	$mcdb = $wpdb;
	if ( $group_id == 0 ) {
		return false;
	} else {
		$query = "SELECT count( event_group_id ) FROM " . my_calendar_table() . " WHERE event_group_id = $group_id";
		$value = $mcdb->get_var( $query );
		if ( $value > 1 ) {
			return true;
		} else {
			return false;
		}
	}
}

function mc_standard_datetime_input( $form, $has_data, $data, $instance, $context = 'admin' ) {
	if ( $has_data ) {
		$event_begin = esc_attr( $data->event_begin );
		$event_end   = esc_attr( $data->event_end );
		if ( isset( $_GET['date'] ) ) {
			$event       = mc_get_event( (int) $_GET['date'] );
			$event_begin = date( 'Y-m-d', strtotime( $event->occur_begin ) );
			$event_end   = date( 'Y-m-d', strtotime( $event->occur_end ) );
			if ( $event_begin == $event_end ) {
				$event_end = '';
			};
		}
		$starttime = ( $data->event_time == "00:00:00" && $data->event_endtime == "00:00:00" ) ? '' : date( "h:i A", strtotime( $data->event_time ) );
		$endtime   = ( $data->event_endtime == "00:00:00" && $data->event_time == "00:00:00" ) ? '' : date( "h:i A", strtotime( $data->event_endtime ) );
	} else {
		$event_begin = date( "Y-m-d" );
		$event_end   = $starttime = $endtime = '';
	}
	$allday = $hide = '';
	if ( $has_data && ( $data->event_time == '00:00:00' && $data->event_endtime == '00:00:00' ) ) {
		$allday = " checked=\"checked\"";
	}
	if ( $has_data && $data->event_hide_end == '1' ) {
		$hide = " checked=\"checked\"";
	}
	$scripting = '
<script type="text/javascript">
//<![CDATA[
jQuery(document).ready(function($) {
	$("#e_time").pickatime({
		interval: 15,
		format: "' . apply_filters( 'mc_time_format', 'h:i A' ) . '",
		editable: true
	});
	$("#e_endtime").pickatime({
		interval: 15,
		format: "' . apply_filters( 'mc_time_format', 'h:i A' ) . '",
		editable: true		
	});
});
//]]>
</script>';
	$form .= $scripting;
	$form .= '<p>
		<label for="e_begin" id="eblabel">' . __( 'Date (YYYY-MM-DD)', 'my-calendar' ) . '</label> <input type="text" id="e_begin" name="event_begin[]" size="10" value="" data-value="' . $event_begin . '" />
		<label for="e_time">' . __( 'From', 'my-calendar' ) . '</label> 
		<input type="text" id="e_time" name="event_time[]" size="8" value="' . $starttime . '" />	
		<label for="e_endtime">' . __( 'To', 'my-calendar' ) . '</label> 
		<input type="text" id="e_endtime" name="event_endtime[]" size="8" value="' . $endtime . '" />
	</p>
	<ul>
		<li><input type="checkbox" value="1" id="e_allday" name="event_allday"' . $allday . ' /> <label for="e_allday">' . __( 'All day event', 'my-calendar' ) . '</label> </li>
		<li><input type="checkbox" value="1" id="e_hide_end" name="event_hide_end"' . $hide . ' /> <label for="e_hide_end">' . __( 'Hide end time', 'my-calendar' ) . '</label></li>
	</ul>
	<p>
		<label for="e_end" id="eelabel"><em>' . __( 'End Date (YYYY-MM-DD, optional)', 'my-calendar' ) . '</em></label> <input type="text" name="event_end[]" id="e_end" size="10" value="" data-value="' . $event_end . '" /> 
	</p>';

	return $form;
}

function mc_standard_event_registration( $form, $has_data, $data, $context = 'admin' ) {
	if ( $has_data ) {
		$event_open   = jd_option_selected( $data->event_open, '1' );
		$not_open     = jd_option_selected( $data->event_open, '0' );
		$default      = jd_option_selected( $data->event_open, '2' );
		$group        = jd_option_selected( $data->event_group, '1' );
		$tickets      = esc_attr( $data->event_tickets );
		$registration = stripslashes( esc_attr( $data->event_registration ) );
	} else {
		$event_open = $not_open = $group = $tickets = $registration = '';
		$default    = 'checked="checked"';
	}
	if ( $context == 'admin' ) {
		$form .= "
			<p>
				<input type='radio' id='event_open' name='event_open' value='1' $event_open /> <label for='event_open'>" . __( 'Open', 'my-calendar' ) . "</label>
				<input type='radio' id='event_closed' name='event_open' value='0' $not_open /> <label for='event_closed'>" . __( 'Closed', 'my-calendar' ) . "</label>
				<input type='radio' id='event_none' name='event_open' value='2' $default /> <label for='event_none'>" . __( 'Does not apply', 'my-calendar' ) . "</label>
			</p>	
			<p>
				<input type='checkbox' name='event_group' id='event_group' $group /> <label for='event_group'>" . __( 'If this event recurs, it can only be registered for as a complete series.', 'my-calendar' ) . "</label>
			</p>";
	} else {
		$form .= '<input type="hidden" name="event_open" value="2" />';
	}
	$form .= "<p>
				<label for='event_tickets'>" . __( 'Tickets URL', 'my-calendar' ) . "</label> <input type='url' name='event_tickets' id='event_tickets' value='$tickets' />
			</p>
			<p>
				<label for='event_registration'>" . __( 'Registration Information', 'my-calendar' ) . "</label> <textarea name='event_registration'id='event_registration'cols='40'rows='4'/>$registration</textarea>
			</p>";

	return apply_filters( 'mc_event_registration_form', $form, $has_data, $data, 'admin' );
}