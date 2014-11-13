<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

function mc_settings_field( $name, $label, $default = '', $note = '', $atts = array( 'size' => '30' ), $type = 'text' ) {
	$options = $attributes = '';
	if ( is_array( $atts ) && ! empty( $atts ) ) {
		foreach ( $atts as $key => $value ) {
			$attributes .= " $key='$value'";
		}
	}
	$value = ( get_option( $name ) != '' ) ? esc_attr( stripslashes( get_option( $name ) ) ) : $default;
	switch ( $type ) {
		case 'text':
		case 'url':
		case 'email':
			if ( $note ) {
				$note = sprintf( $note, "<code>$value</code>" );
				$note = "<span id='$name-note'>$note</span>";
				$aria = " aria-describedby='$name-note'";
			} else {
				$note = $aria = '';
			}
			echo "<label for='$name'>$label</label> <input type='$type' id='$name' name='$name' value='$value'$aria$attributes /> $note";
			break;
		case 'textarea':
			if ( $note ) {
				$note = sprintf( $note, "<code>$value</code>" );
				$note = "<span id='$name-note'>$note</span>";
				$aria = " aria-describedby='$name-note'";
			} else {
				$note = $aria = '';
			}
			echo "<label for='$name'>$label</label><br /><textarea id='$name' name='$name'$aria$attributes>$value</textarea>$note";
			break;
		case 'checkbox-single':
			$checked = mc_is_checked( $name, 'true', '', true );
			if ( $note ) {
				$note = sprintf( $note, "<code>$value</code>" );
			} else {
				$note = '';
			}
			echo "<input type='checkbox' id='$name' name='$name' value='on' $checked$attributes /> <label for='$name' class='checkbox-label'>$label $note</label>";
			break;
		case 'checkbox':
		case 'radio':
			if ( $note ) {
				$note = sprintf( $note, "<code>$value</code>" );
				$note = "<span id='$name-note'>$note</span>";
				$aria = " aria-describedby='$name-note'";
			} else {
				$note = $aria = '';
			}
			foreach ( $label as $k => $v ) {
				$checked = ( $k == $value ) ? ' checked="checked"' : '';
				$options .= "<li><input type='radio' id='$name-$k' value='$k' name='$name'$aria$attributes$checked /> <label for='$name-$k'>$v</label></li>";
			}
			echo "$options $note";
			break;
		case 'select':
			if ( $note ) {
				$note = sprintf( $note, "<code>$value</code>" );
				$note = "<span id='$name-note'>$note</span>";
				$aria = " aria-describedby='$name-note'";
			} else {
				$note = $aria = '';
			}
			if ( is_array( $default ) ) {
				foreach ( $default as $k => $v ) {
					$checked = ( $k == $value ) ? ' selected="selected"' : '';
					$options .= "<option value='$k'$checked>$v</option>";
				}
			}
			echo "
			<label for='$name'>$label</label> 
				<select id='$name' name='$name'$aria$attributes />
					$options
				</select>
			$note";
			break;
	}
}

// Display the admin configuration page
function my_calendar_import() {
	if ( get_option( 'ko_calendar_imported' ) != 'true' ) {
		global $wpdb;
		$mcdb = $wpdb;
		define( 'KO_CALENDAR_TABLE', $mcdb->prefix . 'calendar' );
		define( 'KO_CALENDAR_CATS', $mcdb->prefix . 'calendar_categories' );
		$events         = $mcdb->get_results( "SELECT * FROM " . KO_CALENDAR_TABLE, 'ARRAY_A' );
		$event_ids      = array();
		$events_results = false;
		foreach ( $events as $key ) {
			$endtime        = ( $key['event_time'] == '00:00:00' ) ? '00:00:00' : date( 'H:i:s', strtotime( "$key[event_time] +1 hour" ) );
			$data           = array(
				'event_title'    => $key['event_title'],
				'event_desc'     => $key['event_desc'],
				'event_begin'    => $key['event_begin'],
				'event_end'      => $key['event_end'],
				'event_time'     => $key['event_time'],
				'event_endtime'  => $endtime,
				'event_recur'    => $key['event_recur'],
				'event_repeats'  => $key['event_repeats'],
				'event_author'   => $key['event_author'],
				'event_category' => $key['event_category'],
				'event_hide_end' => 1,
				'event_link'     => ( isset( $key['event_link'] ) ) ? $key['event_link'] : ''
			);
			$format         = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s' );
			$update         = $mcdb->insert( my_calendar_table(), $data, $format );
			$events_results = ( $update ) ? true : false;
			$event_ids[]    = $mcdb->insert_id;
		}
		foreach ( $event_ids as $value ) { // propagate event instances.
			$sql   = "SELECT event_begin, event_time, event_end, event_endtime FROM " . my_calendar_table() . " WHERE event_id = $value";
			$event = $wpdb->get_results( $sql );
			$event = $event[0];
			$dates = array(
				'event_begin'   => $event->event_begin,
				'event_end'     => $event->event_end,
				'event_time'    => $event->event_time,
				'event_endtime' => $event->event_endtime
			);
			mc_increment_event( $value, $dates );
		}
		$cats         = $mcdb->get_results( "SELECT * FROM " . KO_CALENDAR_CATS, 'ARRAY_A' );
		$cats_results = false;
		foreach ( $cats as $key ) {
			$name         = esc_sql( $key['category_name'] );
			$color        = esc_sql( $key['category_colour'] );
			$id           = (int) $key['category_id'];
			$catsql       = "INSERT INTO " . my_calendar_categories_table() . " SET
				category_id='" . $id . "',
				category_name='" . $name . "',
				category_color='" . $color . "'
				ON DUPLICATE KEY UPDATE 
				category_name='" . $name . "',
				category_color='" . $color . "';
				";
			$cats_results = $mcdb->query( $catsql );
		}
		$message   = ( $cats_results !== false ) ? __( 'Categories imported successfully.', 'my-calendar' ) : __( 'Categories not imported.', 'my-calendar' );
		$e_message = ( $events_results !== false ) ? __( 'Events imported successfully.', 'my-calendar' ) : __( 'Events not imported.', 'my-calendar' );
		$return    = "<div id='message' class='updated fade'><ul><li>$message</li><li>$e_message</li></ul></div>";
		echo $return;
		if ( $cats_results !== false && $events_results !== false ) {
			update_option( 'ko_calendar_imported', 'true' );
		}
	}
}

function mc_drop_table( $table ) {
	global $wpdb;
	$sql = "DROP TABLE " . $table();
	$wpdb->query( $sql );
}

function edit_my_calendar_config() {
	global $wpdb;
	$mcdb = $wpdb;
	check_my_calendar();
	if ( ! empty( $_POST ) ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( "Security check failed" );
		}
		if ( isset( $_POST['remigrate'] ) ) {
			echo "<div class='updated fade'><ol>";
			echo "<li>" . __( 'Dropping occurrences database table', 'my-calendar' ) . "</li>";
			mc_drop_table( 'my_calendar_event_table' );
			sleep( 1 );
			echo "<li>" . __( 'Reinstalling occurrences database table.', 'my-calendar' ) . "</li>";
			mc_upgrade_db();
			sleep( 1 );
			echo "<li>" . __( 'Generating event occurrences.', 'my-calendar' ) . "</li>";
			mc_migrate_db();
			echo "<li>" . __( 'Event generation completed.', 'my-calendar' ) . "</li>";
			echo "</ol></div>";
		}
	}
	if ( isset( $_POST['mc_manage'] ) ) {
		// management
		$clear            = '';
		$mc_event_approve = ( ! empty( $_POST['mc_event_approve'] ) && $_POST['mc_event_approve'] == 'on' ) ? 'true' : 'false';
		$mc_api_enabled   = ( ! empty( $_POST['mc_api_enabled'] ) && $_POST['mc_api_enabled'] == 'on' ) ? 'true' : 'false';
		$mc_remote        = ( ! empty( $_POST['mc_remote'] ) && $_POST['mc_remote'] == 'on' ) ? 'true' : 'false';
		if ( isset( $_POST['mc_clear_cache'] ) && $_POST['mc_clear_cache'] == 'clear' ) {
			mc_delete_cache();
			$clear = __( 'My Calendar Cache cleared', 'my-calendar' );
		}
		update_option( 'mc_event_approve', $mc_event_approve );
		update_option( 'mc_api_enabled', $mc_api_enabled );
		update_option( 'mc_remote', $mc_remote );
		update_option( 'mc_default_sort', $_POST['mc_default_sort'] );
		if ( get_site_option( 'mc_multisite' ) == 2 ) {
			$mc_current_table = (int) $_POST['mc_current_table'];
			update_option( 'mc_current_table', $mc_current_table );
		}
		echo "<div class='updated'><p><strong>" . __( 'My Calendar Management Settings saved', 'my-calendar' ) . ". $clear</strong></p></div>";
	}
	if ( isset( $_POST['mc_permissions'] ) ) {
		$perms = $_POST['mc_caps'];
		$caps  = array(
			'mc_add_events'     => __( 'Add Events', 'my-calendar' ),
			'mc_approve_events' => __( 'Approve Events', 'my-calendar' ),
			'mc_manage_events'  => __( 'Manage Events', 'my-calendar' ),
			'mc_edit_cats'      => __( 'Edit Categories', 'my-calendar' ),
			'mc_edit_locations' => __( 'Edit Locations', 'my-calendar' ),
			'mc_edit_styles'    => __( 'Edit Styles', 'my-calendar' ),
			'mc_edit_behaviors' => __( 'Edit Behaviors', 'my-calendar' ),
			'mc_edit_templates' => __( 'Edit Templates', 'my-calendar' ),
			'mc_edit_settings'  => __( 'Edit Settings', 'my-calendar' ),
			'mc_view_help'      => __( 'View Help', 'my-calendar' )
		);
		foreach ( $perms as $key => $value ) {
			$role = get_role( $key );
			if ( is_object( $role ) ) {
				foreach ( $caps as $k => $v ) {
					if ( isset( $value[ $k ] ) ) {
						$role->add_cap( $k );
					} else {
						$role->remove_cap( $k );
					}
				}
			}
		}
		echo "<div class='updated'><p><strong>" . __( 'My Calendar Permissions Updated', 'my-calendar' ) . "</strong></p></div>";
	}
	// output
	if ( isset( $_POST['mc_show_months'] ) ) {
		$mc_open_day_uri = ( ! empty( $_POST['mc_open_day_uri'] ) ) ? $_POST['mc_open_day_uri'] : '';
		update_option( 'mc_uri', $_POST['mc_uri'] );
		update_option( 'mc_use_permalinks', ( ! empty( $_POST['mc_use_permalinks'] ) ) ? 'true' : 'false' );
		update_option( 'mc_open_uri', ( ! empty( $_POST['mc_open_uri'] ) && $_POST['mc_open_uri'] == 'on' && get_option( 'mc_uri' ) != '' ) ? 'true' : 'false' );
		update_option( 'mc_mini_uri', $_POST['mc_mini_uri'] );
		update_option( 'mc_open_day_uri', $mc_open_day_uri );
		update_option( 'mc_skip_holidays', ( ! empty( $_POST['mc_skip_holidays'] ) && $_POST['mc_skip_holidays'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_display_author', ( ! empty( $_POST['mc_display_author'] ) && $_POST['mc_display_author'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_show_event_vcal', ( ! empty( $_POST['mc_show_event_vcal'] ) && $_POST['mc_show_event_vcal'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_show_gcal', ( ! empty( $_POST['mc_show_gcal'] ) && $_POST['mc_show_gcal'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_show_list_info', ( ! empty( $_POST['mc_show_list_info'] ) && $_POST['mc_show_list_info'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_show_months', (int) $_POST['mc_show_months'] );
		// calculate sequence for navigation elements
		$top = $bottom = array();
		$nav = $_POST['mc_nav'];
		$set = 'top';
		foreach ( $nav as $n ) {
			if ( $n == 'calendar' ) {
				$set = 'bottom';
			} else {
				if ( $set == 'top' ) {
					$top[] = $n;
				} else {
					$bottom[] = $n;
				}
			}
			if ( $n == 'stop' ) {
				break;
			}
		}
		$top    = ( empty( $top ) ) ? 'none' : implode( ',', $top );
		$bottom = ( empty( $bottom ) ) ? 'none' : implode( ',', $bottom );
		update_option( 'mc_bottomnav', $bottom );
		update_option( 'mc_topnav', $top );
		update_option( 'mc_show_map', ( ! empty( $_POST['mc_show_map'] ) && $_POST['mc_show_map'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_gmap', ( ! empty( $_POST['mc_gmap'] ) && $_POST['mc_gmap'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_show_address', ( ! empty( $_POST['mc_show_address'] ) && $_POST['mc_show_address'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_hide_icons', ( ! empty( $_POST['mc_hide_icons'] ) && $_POST['mc_hide_icons'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_event_link_expires', ( ! empty( $_POST['mc_event_link_expires'] ) && $_POST['mc_event_link_expires'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_apply_color', $_POST['mc_apply_color'] );
		update_option( 'mc_event_registration', ( ! empty( $_POST['mc_event_registration'] ) && $_POST['mc_event_registration'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_inverse_color', ( ! empty( $_POST['mc_inverse_color'] ) && $_POST['mc_inverse_color'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_short', ( ! empty( $_POST['mc_short'] ) && $_POST['mc_short'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_desc', ( ! empty( $_POST['mc_desc'] ) && $_POST['mc_desc'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_process_shortcodes', ( ! empty( $_POST['mc_process_shortcodes'] ) && $_POST['mc_process_shortcodes'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_event_link', ( ! empty( $_POST['mc_event_link'] ) && $_POST['mc_event_link'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_show_weekends', ( ! empty( $_POST['mc_show_weekends'] ) && $_POST['mc_show_weekends'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_convert', ( ! empty( $_POST['mc_convert'] ) && $_POST['mc_convert'] == 'on' ) ? 'true' : 'false' );
		update_option( 'mc_no_fifth_week', ( ! empty( $_POST['mc_no_fifth_week'] ) && $_POST['mc_no_fifth_week'] == 'on' ) ? 'true' : 'false' );
		echo "<div class=\"updated\"><p><strong>" . __( 'Output Settings saved', 'my-calendar' ) . "</strong></p></div>";
	}
	// input
	if ( isset( $_POST['mc_dates'] ) ) {
		update_option( 'mc_date_format', stripslashes( $_POST['mc_date_format'] ) );
		update_option( 'mc_week_format', stripslashes( $_POST['mc_week_format'] ) );
		update_option( 'mc_time_format', stripslashes( $_POST['mc_time_format'] ) );
		update_option( 'mc_month_format', stripslashes( $_POST['mc_month_format'] ) );
		$mc_ical_utc = ( ! empty( $_POST['mc_ical_utc'] ) && $_POST['mc_ical_utc'] == 'on' ) ? 'true' : 'false';
		update_option( 'mc_ical_utc', $mc_ical_utc );
		echo "<div class=\"updated\"><p><strong>" . __( 'Date/Time Format Settings saved', 'my-calendar' ) . "</strong></p></div>";
	}
	if ( isset( $_POST['mc_input'] ) ) {
		$mc_input_options_administrators = ( ! empty( $_POST['mc_input_options_administrators'] ) && $_POST['mc_input_options_administrators'] == 'on' ) ? 'true' : 'false';
		$mc_input_options                = array(
			'event_short'             => ( ! empty( $_POST['mci_event_short'] ) && $_POST['mci_event_short'] ) ? 'on' : 'off',
			'event_desc'              => ( ! empty( $_POST['mci_event_desc'] ) && $_POST['mci_event_desc'] ) ? 'on' : 'off',
			'event_category'          => ( ! empty( $_POST['mci_event_category'] ) && $_POST['mci_event_category'] ) ? 'on' : 'off',
			'event_image'             => ( ! empty( $_POST['mci_event_image'] ) && $_POST['mci_event_image'] ) ? 'on' : 'off',
			'event_link'              => ( ! empty( $_POST['mci_event_link'] ) && $_POST['mci_event_link'] ) ? 'on' : 'off',
			'event_recurs'            => ( ! empty( $_POST['mci_event_recurs'] ) && $_POST['mci_event_recurs'] ) ? 'on' : 'off',
			'event_open'              => ( ! empty( $_POST['mci_event_open'] ) && $_POST['mci_event_open'] ) ? 'on' : 'off',
			'event_location'          => ( ! empty( $_POST['mci_event_location'] ) && $_POST['mci_event_location'] ) ? 'on' : 'off',
			'event_location_dropdown' => ( ! empty( $_POST['mci_event_location_dropdown'] ) && $_POST['mci_event_location_dropdown'] ) ? 'on' : 'off',
			'event_specials'          => ( ! empty( $_POST['mci_event_specials'] ) && $_POST['mci_event_specials'] ) ? 'on' : 'off',
			'event_access'            => ( ! empty( $_POST['mci_event_access'] ) && $_POST['mci_event_access'] ) ? 'on' : 'off'
		);
		update_option( 'mc_input_options', $mc_input_options );
		update_option( 'mc_input_options_administrators', $mc_input_options_administrators );
		echo "<div class=\"updated\"><p><strong>" . __( 'Input Settings saved', 'my-calendar' ) . ".</strong></p></div>";
	}
	if ( current_user_can( 'manage_network' ) ) {
		if ( isset( $_POST['mc_network'] ) ) {
			$mc_multisite = (int) $_POST['mc_multisite'];
			update_site_option( 'mc_multisite', $mc_multisite );
			$mc_multisite_show = (int) $_POST['mc_multisite_show'];
			update_site_option( 'mc_multisite_show', $mc_multisite_show );
			echo "<div class=\"updated\"><p><strong>" . __( 'Multisite settings saved', 'my-calendar' ) . ".</strong></p></div>";
		}
	}
	// custom text
	if ( isset( $_POST['mc_previous_events'] ) ) {
		$mc_title_template       = $_POST['mc_title_template'];
		$mc_details_label        = $_POST['mc_details_label'];
		$mc_link_label           = $_POST['mc_link_label'];
		$mc_event_title_template = $_POST['mc_event_title_template'];
		$mc_notime_text          = $_POST['mc_notime_text'];
		$mc_previous_events      = $_POST['mc_previous_events'];
		$mc_next_events          = $_POST['mc_next_events'];
		$mc_event_open           = $_POST['mc_event_open'];
		$mc_event_closed         = $_POST['mc_event_closed'];
		$mc_week_caption         = $_POST['mc_week_caption'];
		$mc_caption              = $_POST['mc_caption'];
		$templates               = get_option( 'mc_templates' );
		$templates['title']      = $mc_title_template;
		$templates['label']      = $mc_details_label;
		$templates['link']       = $mc_link_label;
		update_option( 'mc_templates', $templates );
		update_option( 'mc_event_title_template', $mc_event_title_template );
		update_option( 'mc_notime_text', $mc_notime_text );
		update_option( 'mc_week_caption', $mc_week_caption );
		update_option( 'mc_next_events', $mc_next_events );
		update_option( 'mc_previous_events', $mc_previous_events );
		update_option( 'mc_caption', $mc_caption );
		update_option( 'mc_event_open', $mc_event_open );
		update_option( 'mc_event_closed', $mc_event_closed );
		echo "<div class=\"updated\"><p><strong>" . __( 'Custom text settings saved', 'my-calendar' ) . ".</strong></p></div>";
	}
	// Mail function by Roland
	if ( isset( $_POST['mc_email'] ) ) {
		$mc_event_mail         = ( ! empty( $_POST['mc_event_mail'] ) && $_POST['mc_event_mail'] == 'on' ) ? 'true' : 'false';
		$mc_html_email         = ( ! empty( $_POST['mc_html_email'] ) && $_POST['mc_html_email'] == 'on' ) ? 'true' : 'false';
		$mc_event_mail_to      = $_POST['mc_event_mail_to'];
		$mc_event_mail_from    = $_POST['mc_event_mail_from'];
		$mc_event_mail_subject = $_POST['mc_event_mail_subject'];
		$mc_event_mail_message = $_POST['mc_event_mail_message'];
		$mc_event_mail_bcc     = $_POST['mc_event_mail_bcc'];
		update_option( 'mc_event_mail_to', $mc_event_mail_to );
		update_option( 'mc_event_mail_from', $mc_event_mail_from );
		update_option( 'mc_event_mail_subject', $mc_event_mail_subject );
		update_option( 'mc_event_mail_message', $mc_event_mail_message );
		update_option( 'mc_event_mail_bcc', $mc_event_mail_bcc );
		update_option( 'mc_event_mail', $mc_event_mail );
		update_option( 'mc_html_email', $mc_html_email );
		echo "<div class=\"updated\"><p><strong>" . __( 'Email notice settings saved', 'my-calendar' ) . ".</strong></p></div>";
	}
	// Custom User Settings

	apply_filters( 'mc_save_settings', '', $_POST );

	// pull templates for passing into functions.
	$templates         = get_option( 'mc_templates' );
	$mc_title_template = esc_attr( stripslashes( $templates['title'] ) );
	$mc_details_label  = esc_attr( stripslashes( $templates['label'] ) );
	$mc_link_label     = esc_attr( stripslashes( $templates['link'] ) );
	?>

	<div class="wrap jd-my-calendar mc-settings-page" id="mc_settings">
	<?php my_calendar_check_db(); ?>
	<div id="icon-options-general" class="icon32"><br/></div>
	<h2><?php _e( 'My Calendar Options', 'my-calendar' ); ?></h2>

	<div class="postbox-container jcd-wide">
	<div class="metabox-holder">
	<?php
	//update_option( 'ko_calendar_imported','false' ); // for testing importing.
	if ( isset( $_POST['import'] ) && $_POST['import'] == 'true' ) {
		$nonce = $_REQUEST['_wpnonce'];
		if ( ! wp_verify_nonce( $nonce, 'my-calendar-nonce' ) ) {
			die( "Security check failed" );
		}
		my_calendar_import();
	}
	if ( get_option( 'ko_calendar_imported' ) != 'true' ) {
		if ( function_exists( 'check_calendar' ) ) {
			?>
			<div class='import upgrade-db'>
				<p>
					<?php _e( 'My Calendar has identified that you have the Calendar plugin by Kieran O\'Shea installed. You can import those events and categories into the My Calendar database. Would you like to import these events?', 'my-calendar' ); ?>
				</p>

				<form method="post" action="<?php echo admin_url( "admin.php?page=my-calendar-config" ); ?>">
					<div><input type="hidden" name="_wpnonce"
					            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
					<div>
						<input type="hidden" name="import" value="true"/>
						<input type="submit" value="<?php _e( 'Import from Calendar', 'my-calendar' ); ?>"
						       name="import-calendar" class="button-primary"/>
					</div>
				</form>
			</div>
		<?php
		}
	}
	?>

	<div class="ui-sortable meta-box-sortables">
		<div class="postbox">
			<h3><?php _e( 'My Calendar Settings', 'my-calendar' ); ?></h3>

			<div class="inside">
				<ul class="mc-settings checkboxes">
					<li><a href="#my-calendar-manage"><?php _e( 'Management', 'my-calendar' ); ?></a></li>
					<li><a href="#my-calendar-text"><?php _e( 'Customizable Text', 'my-calendar' ); ?></a></li>
					<li><a href="#my-calendar-output"><?php _e( 'Output', 'my-calendar' ); ?></a></li>
					<li><a href="#my-calendar-time"><?php _e( 'Date/Time', 'my-calendar' ); ?></a></li>
					<li><a href="#my-calendar-input"><?php _e( 'Input', 'my-calendar' ); ?></a></li>
					<?php if ( current_user_can( 'manage_network' ) ) { ?>
						<li><a href="#my-calendar-multisite"><?php _e( 'Multi-site', 'my-calendar' ); ?></a></li>
					<?php } ?>
					<li><a href="#my-calendar-permissions"><?php _e( 'Permissions', 'my-calendar' ); ?></a></li>
					<li><a href="#my-calendar-email"><?php _e( 'Email Notifications', 'my-calendar' ); ?></a></li>
					<?php echo apply_filters( 'mc_settings_section_links', '' ); ?>
				</ul>
			</div>
		</div>
	</div>

	<div class="ui-sortable meta-box-sortables">
		<div class="postbox" id="my-calendar-manage">
			<h3><?php _e( 'My Calendar Management', 'my-calendar' ); ?></h3>

			<div class="inside">
				<?php if ( current_user_can( 'administrator' ) ) { ?>
					<form method="post" action="<?php echo admin_url( "admin.php?page=my-calendar-config" ); ?>">
						<div><input type="hidden" name="_wpnonce"
						            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
						<fieldset>
							<legend><?php _e( 'Management', 'my-calendar' ); ?></legend>
							<ul>
								<li><?php mc_settings_field( 'mc_remote', __( 'Get data (events, categories and locations) from a remote database.', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
								<?php if ( get_option( 'mc_remote' ) == 'true' ) { ?>
									<li><?php _e( 'Add this code to your theme\'s <code>functions.php</code> file:', 'my-calendar' ); ?>
										<pre>function mc_remote_db() {
	$mcdb = new wpdb('DB_USER','DB_PASSWORD','DB_NAME','DB_ADDRESS');
	return $mcdb;
}</pre>
										<?php _e( 'You will need to allow remote connections from this site to the site hosting your My Calendar events. Replace the above placeholders with the host-site information. The two sites must have the same WP table prefix. While this option is enabled, you may not enter or edit events through this installation.', 'my-calendar' ); ?>
									</li>
								<?php } ?>
								<li><?php mc_settings_field( 'mc_event_approve', __( 'Enable approval options.', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
								<li><?php mc_settings_field( 'mc_api_enabled', __( 'Enable external API.', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
								<?php
								if ( apply_filters( 'mc_caching_clear', false ) ) {
									?>
									<li><?php mc_settings_field( 'mc_clear_cache', __( 'Clear current cache. (Necessary if you edit shortcodes to change displayed categories, for example.)', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
								<?php } ?>
								<li><?php mc_settings_field( 'mc_default_sort', __( 'Default Sort order for Admin Events List', 'my-calendar' ), array(
											'1' => __( 'Event ID', 'my-calendar' ),
											'2' => __( 'Title', 'my-calendar' ),
											'3' => __( 'Description', 'my-calendar' ),
											'4' => __( 'Start Date', 'my-calendar' ),
											'5' => __( 'Author', 'my-calendar' ),
											'6' => __( 'Category', 'my-calendar' ),
											'7' => __( 'Location Name', 'my-calendar' )
										), '', array(), 'select' ); ?></li>
								<?php
								if ( get_site_option( 'mc_multisite' ) == 2 && MY_CALENDAR_TABLE != MY_CALENDAR_GLOBAL_TABLE ) {
									mc_settings_field( 'mc_current_table', array(
											'0' => __( 'Currently editing my local calendar', 'my-calendar' ),
											'1' => __( 'Currently editing the network calendar', 'my-calendar' )
										), '0', '', array(), 'radio' );
								} else {
									if ( get_option( 'mc_remote' ) != 'true' && current_user_can( 'manage_network' ) ) {
										?>
										<li><?php _e( 'You are currently working in the primary site for this network; your local calendar is also the global table.', 'my-calendar' ); ?></li><?php
									}
								} ?>
								<li><?php mc_settings_field( 'remigrate', __( 'Re-generate event occurrences table.', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							</ul>
						</fieldset>
						<p>
							<input type="submit" name="mc_manage" class="button-primary"
							       value="<?php _e( 'Save Management Settings', 'my-calendar' ); ?>"/>
						</p>
					</form>
				<?php } else { ?>
					<?php _e( 'My Calendar management settings are only available to administrators.', 'my-calendar' ); ?>
				<?php } ?>
			</div>
		</div>
	</div>

	<div class="ui-sortable meta-box-sortables">
		<div class="postbox" id="my-calendar-text">
			<h3><?php _e( 'Text Settings', 'my-calendar' ); ?></h3>

			<div class="inside">
				<form method="post" action="<?php echo admin_url( "admin.php?page=my-calendar-config" ); ?>">
					<div><input type="hidden" name="_wpnonce"
					            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
					<fieldset>
						<legend><?php _e( 'Customize Text Fields', 'my-calendar' ); ?></legend>
						<ul>
							<li><?php mc_settings_field( 'mc_notime_text', __( 'Label for all-day events', 'my-calendar' ), 'N/A' ); ?></li>
							<li><?php mc_settings_field( 'mc_previous_events', __( 'Previous events link', 'my-calendar' ), __( 'Previous', 'my-calendar' ), __( 'Use <code>{date}</code> to display date in navigation.', 'my-calendar' ) ); ?></li>
							<li><?php mc_settings_field( 'mc_next_events', __( 'Next events link', 'my-calendar' ), __( 'Next', 'my-calendar' ), __( 'Use <code>{date}</code> to display date in navigation.', 'my-calendar' ) ); ?></li>
							<li><?php mc_settings_field( 'mc_event_open', __( 'If events are open', 'my-calendar' ), __( 'Registration is open', 'my-calendar' ) ); ?></li>
							<li><?php mc_settings_field( 'mc_event_closed', __( 'If events are closed', 'my-calendar' ), __( 'Registration is closed', 'my-calendar' ) ); ?></li>
							<li><?php mc_settings_field( 'mc_week_caption', __( 'Week view caption:', 'my-calendar' ), '', __( 'Available tag: <code>{date format=""}</code>', 'my-calendar' ) ); ?></li>
							<li><?php mc_settings_field( 'mc_caption', __( 'Extended caption:', 'my-calendar' ), '', __( 'Follows month/year in list views.', 'my-calendar' ) ); ?></li>
							<li><?php mc_settings_field( 'mc_title_template', __( 'Event title template', 'my-calendar' ), $mc_title_template, "<a href='" . admin_url( "admin.php?page=my-calendar-help#templates" ) . "'>" . __( "Templating Help", 'my-calendar' ) . '</a>' ); ?></li>
							<li><?php mc_settings_field( 'mc_details_label', __( 'Event details link text', 'my-calendar' ), $mc_details_label, __( 'Tags: <code>{title}</code>, <code>{location}</code>, <code>{color}</code>, <code>{icon}</code>, <code>{date}</code>, <code>{time}</code>.', 'my-calendar' ) ); ?></li>
							<li><?php mc_settings_field( 'mc_link_label', __( 'Event URL link text', 'my-calendar' ), $mc_link_label, "<a href='" . admin_url( "admin.php?page=my-calendar-help#templates" ) . "'>" . __( "Templating Help", 'my-calendar' ) . '</a>' ); ?></li>
							<li><?php mc_settings_field( 'mc_event_title_template', __( 'Title element template', 'my-calendar' ), '{title} &raquo; {date}', __( 'Current: %s', 'my-calendar' ) ); ?></li>
						</ul>
					</fieldset>
					<p>
						<input type="submit" name="save" class="button-primary"
						       value="<?php _e( 'Save Custom Text Settings', 'my-calendar' ); ?>"/>
					</p>
				</form>
			</div>
		</div>
	</div>

	<div class="ui-sortable meta-box-sortables">
		<div class="postbox" id="my-calendar-output">
			<h3><?php _e( 'Output Settings', 'my-calendar' ); ?></h3>

			<div class="inside">
				<form method="post" action="<?php echo admin_url( "admin.php?page=my-calendar-config" ); ?>">
					<div><input type="hidden" name="_wpnonce"
					            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
					<fieldset>
						<legend><?php _e( 'Calendar Link Targets', 'my-calendar' ); ?></legend>
						<ul>
							<?php /* <li><?php mc_settings_field( 'mc_use_permalinks', __( 'Use Pretty Permalinks for Events','my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li> This just isn't ready; add in a point release. */ ?>
							<?php $guess = mc_guess_calendar(); ?>
							<li><?php mc_settings_field( 'mc_uri', __( 'Where is your main calendar page?', 'my-calendar' ), '', "<br /><small>" . __( 'Can be any Page or Post which includes the <code>[my_calendar]</code> shortcode.', 'my-calendar' ) . " $guess</small>", array( 'size' => '60' ), 'url' ); ?></li>
							<li><?php mc_settings_field( 'mc_mini_uri', __( 'Target <abbr title="Uniform resource locator">URL</abbr> for mini calendar date links:', 'my-calendar' ), '', "<br /><small>" . __( 'Can be any Page or Post which includes the <code>[my_calendar]</code> shortcode.', 'my-calendar' ) . "</small>", array( 'size' => '60' ), 'url' ); ?></li>
							<li><?php mc_settings_field( 'mc_open_uri', __( 'Open calendar links to event details URL', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<?php
							$disabled = ( ! get_option( 'mc_uri' ) && ! get_option( 'mc_mini_uri' ) ) ? array( 'disabled' => 'disabled' ) : array();
							?>
							<li><?php mc_settings_field( 'mc_open_day_uri', __( 'Mini calendar widget date links to:', 'my-calendar' ), array(
										'false'          => __( 'jQuery pop-up view', 'my-calendar' ),
										'true'           => __( 'daily view page (above)', 'my-calendar' ),
										'listanchor'     => __( 'in-page anchor on main calendar page (list)', 'my-calendar' ),
										'calendaranchor' => __( 'in-page anchor on main calendar page (grid)', 'my-calendar' )
									), '', $disabled, 'select' ); ?></li>
						</ul>
						<?php // End General Options // ?>
					</fieldset>

					<fieldset>
						<legend><?php _e( 'Set Default Navigation Element Order (can be overridden in shortcodes)', 'my-calendar' ); ?></legend>
						<?php
						$topnav       = explode( ',', get_option( 'mc_topnav' ) );
						$calendar     = array( 'calendar' );
						$botnav       = explode( ',', get_option( 'mc_bottomnav' ) );
						$order        = array_merge( $topnav, $calendar, $botnav );
						$nav_elements = array(
							'nav'       => '<div class="dashicons dashicons-arrow-left-alt2"></div> <div class="dashicons dashicons-arrow-right-alt2"></div> ' . __( 'Primary Previous/Next Buttons', 'my-calendar' ),
							'toggle'    => '<div class="dashicons dashicons-list-view"></div> <div class="dashicons dashicons-calendar"></div> ' . __( 'Switch between list and grid views', 'my-calendar' ),
							'jump'      => '<div class="dashicons dashicons-redo"></div> ' . __( 'Jump to any other month/year', 'my-calendar' ),
							'print'     => '<div class="dashicons dashicons-list-view"></div> ' . __( 'Link to printable view', 'my-calendar' ),
							'timeframe' => '<div class="dashicons dashicons-clock"></div> ' . __( 'Toggle between day, week, and month view', 'my-calendar' ),
							'calendar'  => '<div class="dashicons dashicons-calendar"></div> ' . __( 'The calendar', 'my-calendar' ),
							'key'       => '<div class="dashicons dashicons-admin-network"></div> ' . __( 'Categories', 'my-calendar' ),
							'feeds'     => '<div class="dashicons dashicons-rss"></div> ' . __( 'Links to RSS and iCal output', 'my-calendar' ),
							'stop'      => '<div class="dashicons dashicons-no"></div> ' . __( 'Elements below here will be hidden.' )
						);
						echo "<div id='mc-sortable-update' aria-live='assertive'></div>";
						echo "<ul id='mc-sortable'>";
						$inserted = array();
						foreach ( $order as $k ) {
							$k = trim( $k );
							$v = ( isset( $nav_elements[ $k ] ) ) ? $nav_elements[ $k ] : false;
							if ( $v !== false ) {
								$inserted[ $k ] = $v;
								echo "<li class='ui-state-default mc-$k'><button class='up'><i class='dashicons dashicons-arrow-up'></i><span class='screen-reader-text'>Up</span></button> <button class='down'><i class='dashicons dashicons-arrow-down'></i><span class='screen-reader-text'>Down</span></button> <code>$k</code> $v <input type='hidden' name='mc_nav[]' value='$k' /></li>";
							}
						}
						$missed = array_diff( $nav_elements, $inserted );
						foreach ( $missed as $k => $v ) {
							echo "<li class='ui-state-default mc-$k'><button class='up'><i class='dashicons dashicons-arrow-up'></i><span class='screen-reader-text'>Up</span></button> <button class='down'><i class='dashicons dashicons-arrow-down'></i><span class='screen-reader-text'>Down</span></button> <code>$k</code> $v <input type='hidden' name='mc_nav[]' value='$k' /></li>";
						}

						echo "</ul>";
						?>
					</fieldset>

					<fieldset>
						<legend><?php _e( 'Grid Layout Options', 'my-calendar' ); ?></legend>
						<ul>
							<li><?php mc_settings_field( 'mc_show_weekends', __( 'Show Weekends on Calendar', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_convert', __( 'Switch to list view on mobile devices', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
						</ul>
						<?php // End Grid Options // ?>
					</fieldset>

					<fieldset>
						<legend><?php _e( 'List Layout Options', 'my-calendar' ); ?></legend>
						<ul>
							<li><?php mc_settings_field( 'mc_show_months', __( 'How many months of events to show at a time:', 'my-calendar' ), '', '', array( 'size' => '3' ), 'text' ); ?></li>
							<li><?php mc_settings_field( 'mc_show_list_info', __( 'Show the first event\'s title and the number of events that day next to the date.', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
						</ul>
						<?php // End List Options // ?>
					</fieldset>

					<fieldset>
						<legend><?php _e( 'Event Details Pop-up', 'my-calendar' ); ?></legend>
						<p><?php _e( 'The checked items will be shown in your event details view. Does not apply if you are using a custom template', 'my-calendar' ); ?>
						<ul class="checkboxes">
							<li><?php mc_settings_field( 'mc_display_author', __( 'Author\'s name', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_show_event_vcal', __( 'Link to single event iCal download', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_show_gcal', __( 'Link to submit event to Google Calendar', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_hide_icons', __( 'Hide Category icons', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_show_map', __( 'Link to Google Map', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_gmap', __( 'Google Map (single event view only)', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_show_address', __( 'Event Address', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_short', __( 'Short description', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_desc', __( 'Full description', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_process_shortcodes', __( 'Process WordPress shortcodes in descriptions', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_event_link', __( 'External link', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_event_registration', __( 'Registration info', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
						</ul>
					</fieldset>
					<fieldset>
						<legend><?php _e( 'Event Category Display', 'my-calendar' ); ?></legend>
						<ul class='checkboxes'>
							<?php mc_settings_field( 'mc_apply_color', array(
									'default'    => __( 'No category colors with titles.', 'my-calendar' ),
									'font'       => __( 'Titles are in category colors.', 'my-calendar' ),
									'background' => __( 'Titles have category color as background.', 'my-calendar' )
								), 'default', '', array(), 'radio' ); ?>
							<li><?php mc_settings_field( 'mc_inverse_color', __( 'Optimize contrast for category colors.', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
						</ul>
						<?php // End Event Options // ?>
					</fieldset>
					<fieldset>
						<legend><?php _e( 'Event Scheduling Defaults', 'my-calendar' ); ?></legend>
						<ul>
							<li><?php mc_settings_field( 'mc_event_link_expires', __( 'Event links expire after event passes.', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_no_fifth_week', __( 'If a recurring event falls on a date that doesn\'t exist (like the 5th Wednesday in February), move it back one week.', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_skip_holidays', __( 'If an event coincides with an event in the designated "Holiday" category, do not show the event.', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
						</ul>
						<?php // End Scheduling Options // ?>
					</fieldset>
					<p><input type="submit" name="save" class="button-primary"
					          value="<?php _e( 'Save Output Settings', 'my-calendar' ); ?>"/></p>
				</form>
			</div>
		</div>
	</div>

	<div class="ui-sortable meta-box-sortables">
		<div class="postbox" id="my-calendar-time">
			<h3><?php _e( 'Calendar Time Formats', 'my-calendar' ); ?></h3>

			<div class="inside">
				<form method="post" action="<?php echo admin_url( "admin.php?page=my-calendar-config" ); ?>">
					<div><input type="hidden" name="_wpnonce"
					            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
					<fieldset>
						<legend><?php _e( 'Set default date/time formats', 'my-calendar' ); ?></legend>
						<div><input type='hidden' name='mc_dates' value='true'/></div>
						<ul>
							<?php
							$month_format = ( get_option( 'mc_month_format' ) == '' ) ? date_i18n( 'F Y' ) : date_i18n( get_option( 'mc_month_format' ) );
							$time_format  = ( get_option( 'mc_time_format' ) == '' ) ? date_i18n( get_option( 'time_format' ) ) : date_i18n( get_option( 'mc_time_format' ) );
							$week_format  = ( get_option( 'mc_week_format' ) == '' ) ? date_i18n( 'M j, \'y' ) : date_i18n( get_option( 'mc_week_format' ) );
							$date_format  = ( get_option( 'mc_date_format' ) == '' ) ? date_i18n( get_option( 'date_format' ) ) : date_i18n( get_option( 'mc_date_format' ) );
							?>
							<li><?php mc_settings_field( 'mc_month_format', __( 'Month format (calendar headings)', 'my-calendar' ), '', $month_format ); ?></li>
							<li><?php mc_settings_field( 'mc_time_format', __( 'Time format', 'my-calendar' ), '', $time_format ); ?></li>
							<li><?php mc_settings_field( 'mc_week_format', __( 'Date in grid mode, week view', 'my-calendar' ), '', $week_format ); ?></li>
							<li><?php mc_settings_field( 'mc_date_format', __( 'Date Format in other views', 'my-calendar' ), '', $date_format ); ?></li>
							<li>
								<?php _e( 'Date formats use syntax from the <a href="http://php.net/date">PHP <code>date()</code> function</a>. Save to update sample output.', 'my-calendar' ); ?>
							</li>
							<li><?php mc_settings_field( 'mc_ical_utc', __( 'iCal times are UTC', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
						</ul>
					</fieldset>
					<p>
						<input type="submit" name="save" class="button-primary"
						       value="<?php _e( 'Save Date/Time Settings', 'my-calendar' ); ?>"/>
					</p>
				</form>
			</div>
		</div>
	</div>


	<div class="ui-sortable meta-box-sortables">
		<div class="postbox" id="my-calendar-input">
			<h3><?php _e( 'Calendar Input Settings', 'my-calendar' ); ?></h3>

			<div class="inside">
				<form method="post" action="<?php echo admin_url( "admin.php?page=my-calendar-config" ); ?>">
					<div><input type="hidden" name="_wpnonce"
					            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
					<fieldset>
						<legend><?php _e( 'Select which input fields will be available when adding or editing events.', 'my-calendar' ); ?></legend>
						<div><input type='hidden' name='mc_input' value='true'/></div>
						<ul class="checkboxes">
							<?php
							$input_options = get_option( 'mc_input_options' );
							$input_labels  = array(
								'event_location_dropdown' => __( 'Event Location Dropdown Menu', 'my-calendar' ),
								'event_short'             => __( 'Event Short Description field', 'my-calendar' ),
								'event_desc'              => __( 'Event Description Field', 'my-calendar' ),
								'event_category'          => __( 'Event Category field', 'my-calendar' ),
								'event_image'             => __( 'Event Image field', 'my-calendar' ),
								'event_link'              => __( 'Event Link field', 'my-calendar' ),
								'event_recurs'            => __( 'Event Recurrence Options', 'my-calendar' ),
								'event_open'              => __( 'Event Registration options', 'my-calendar' ),
								'event_location'          => __( 'Event Location fields', 'my-calendar' ),
								'event_specials'          => __( 'Set Special Scheduling options', 'my-calendar' ),
								'event_access'            => __( "Event Accessibility", 'my-calendar' )
							);
							$output        = '';
							// if input options isn't an array, we'll assume that this plugin wasn't upgraded properly, and reset them to the default.
							if ( ! is_array( $input_options ) ) {
								update_option( 'mc_input_options', array(
										'event_short'             => 'on',
										'event_desc'              => 'on',
										'event_category'          => 'on',
										'event_image'             => 'on',
										'event_link'              => 'on',
										'event_recurs'            => 'on',
										'event_open'              => 'on',
										'event_location'          => 'off',
										'event_location_dropdown' => 'on',
										'event_specials'          => 'on',
										'event_access'            => 'on'
									) );
								$input_options = get_option( 'mc_input_options' );
							}
							foreach ( $input_options as $key => $value ) {
								$checked = ( $value == 'on' ) ? "checked='checked'" : '';
								if ( isset( $input_labels[ $key ] ) ) {
									$output .= "<li><input type='checkbox' id='mci_$key' name='mci_$key' $checked /> <label for='mci_$key'>$input_labels[$key]</label></li>";
								}
							}
							echo $output;
							?>
							<li><?php mc_settings_field( 'mc_input_options_administrators', __( 'Administrators see all input options', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
						</ul>
					</fieldset>
					<p>
						<input type="submit" name="save" class="button-primary"
						       value="<?php _e( 'Save Input Settings', 'my-calendar' ); ?>"/>
					</p>
				</form>
			</div>
		</div>
	</div>

	<?php if ( current_user_can( 'manage_network' ) ) { ?>
		<div class="ui-sortable meta-box-sortables">
			<div class="postbox" id="my-calendar-multisite">
				<h3><?php _e( 'Multisite Settings (Network Administrators only)', 'my-calendar' ); ?></h3>

				<div class="inside">
					<form method="post" action="<?php echo admin_url( "admin.php?page=my-calendar-config" ); ?>">
						<div><input type="hidden" name="_wpnonce"
						            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
						<div><input type='hidden' name='mc_network' value='true'/></div>
						<fieldset>
							<legend><?php _e( 'WP MultiSite configurations', 'my-calendar' ); ?></legend>
							<p><?php _e( 'The central calendar is the calendar associated with the primary site in your WordPress Multisite network.', 'my-calendar' ); ?></p>
							<ul>
								<li><input type="radio" value="0" id="ms0"
								           name="mc_multisite"<?php echo jd_option_selected( get_site_option( 'mc_multisite' ), '0' ); ?> />
									<label
										for="ms0"><?php _e( 'Site owners may only post to their local calendar', 'my-calendar' ); ?></label>
								</li>
								<li><input type="radio" value="1" id="ms1"
								           name="mc_multisite"<?php echo jd_option_selected( get_site_option( 'mc_multisite' ), '1' ); ?> />
									<label
										for="ms1"><?php _e( 'Site owners may only post to the central calendar', 'my-calendar' ); ?></label>
								</li>
								<li><input type="radio" value="2" id="ms2"
								           name="mc_multisite"<?php echo jd_option_selected( get_site_option( 'mc_multisite' ), 2 ); ?> />
									<label
										for="ms2"><?php _e( 'Site owners may manage either calendar', 'my-calendar' ); ?></label>
								</li>
							</ul>
							<p class="notice">
								<strong>*</strong> <?php _e( 'Changes only effect input permissions. Public-facing calendars will be unchanged.', 'my-calendar' ); ?>
							</p>
							<ul>
								<li><input type="radio" value="0" id="mss0"
								           name="mc_multisite_show"<?php echo jd_option_selected( get_site_option( 'mc_multisite_show' ), '0' ); ?> />
									<label
										for="mss0"><?php _e( 'Sub-site calendars show events from their local calendar.', 'my-calendar' ); ?></label>
								</li>
								<li><input type="radio" value="1" id="mss1"
								           name="mc_multisite_show"<?php echo jd_option_selected( get_site_option( 'mc_multisite_show' ), '1' ); ?> />
									<label
										for="mss1"><?php _e( 'Sub-site calendars show events from the central calendar.', 'my-calendar' ); ?></label>
								</li>
							</ul>
						</fieldset>
						<p>
							<input type="submit" name="save" class="button-primary"
							       value="<?php _e( 'Save Multisite Settings', 'my-calendar' ); ?>"/>
						</p>
					</form>
				</div>
			</div>
		</div>
	<?php } ?>

	<div class="ui-sortable meta-box-sortables">
		<div class="postbox" id="my-calendar-permissions">
			<h3><?php _e( 'My Calendar Permissions', 'my-calendar' ); ?></h3>

			<div class="inside mc-tabs">
				<?php if ( current_user_can( 'administrator' ) ) { ?>

					<form method="post" action="<?php echo admin_url( "admin.php?page=my-calendar-config" ); ?>">
						<div><input type="hidden" name="_wpnonce"
						            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
						<?php
						global $wp_roles;
						$roles     = $wp_roles->get_names();
						$caps      = array(
							'mc_add_events'     => __( 'Add Events', 'my-calendar' ),
							'mc_approve_events' => __( 'Approve Events', 'my-calendar' ),
							'mc_manage_events'  => __( 'Manage Events', 'my-calendar' ),
							'mc_edit_cats'      => __( 'Edit Categories', 'my-calendar' ),
							'mc_edit_locations' => __( 'Edit Locations', 'my-calendar' ),
							'mc_edit_styles'    => __( 'Edit Styles', 'my-calendar' ),
							'mc_edit_behaviors' => __( 'Edit Behaviors', 'my-calendar' ),
							'mc_edit_templates' => __( 'Edit Templates', 'my-calendar' ),
							'mc_edit_settings'  => __( 'Edit Settings', 'my-calendar' ),
							'mc_view_help'      => __( 'View Help', 'my-calendar' )
						);
						$role_tabs = $role_container = '';
						foreach ( $roles as $role => $rolename ) {
							if ( $role == 'administrator' ) {
								continue;
							}
							$role_tabs .= "<li><a href='#mc_$role'>$rolename</a></li>\n";
							$role_container .= "<div class='wptab mc_$role' id='mc_$role' aria-live='assertive'><fieldset id='mc_$role' class='roles'><legend>$rolename</legend>";
							$role_container .= "<input type='hidden' value='none' name='mc_caps[" . $role . "][none]' />
			<ul class='mc-settings checkboxes'>";
							foreach ( $caps as $cap => $name ) {
								$role_container .= mc_cap_checkbox( $role, $cap, $name );
							}
							$role_container .= "
			</ul></fieldset></div>\n";
						}
						echo "
		<ul class='tabs'>
			$role_tabs
		</ul>
		$role_container";

						?>
						<p>
							<input type="submit" name="mc_permissions" class="button-primary"
							       value="<?php _e( 'Save Permissions', 'my-calendar' ); ?>"/>
						</p>
					</form>
				<?php } else { ?>
					<?php _e( 'My Calendar permission settings are only available to administrators.', 'my-calendar' ); ?>
				<?php } ?>
			</div>
		</div>
	</div>

	<div class="ui-sortable meta-box-sortables">
		<div class="postbox" id="my-calendar-email">
			<h3><?php _e( 'Calendar Email Settings', 'my-calendar' ); ?></h3>

			<div class="inside">
				<form method="post" action="<?php echo admin_url( "admin.php?page=my-calendar-config" ); ?>">
					<div><input type="hidden" name="_wpnonce"
					            value="<?php echo wp_create_nonce( 'my-calendar-nonce' ); ?>"/></div>
					<fieldset>
						<legend><?php _e( 'Email Notifications', 'my-calendar' ); ?></legend>
						<div><input type='hidden' name='mc_email' value='true'/></div>
						<ul>
							<li><?php mc_settings_field( 'mc_event_mail', __( 'Send Email Notifications when new events are scheduled or reserved.', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
							<li><?php mc_settings_field( 'mc_event_mail_to', __( 'Notification messages are sent to:', 'my-calendar' ), get_bloginfo( 'admin_email' ) ); ?></li>
							<li><?php mc_settings_field( 'mc_event_mail_from', __( 'Notification messages are sent from:', 'my-calendar' ), get_bloginfo( 'admin_email' ) ); ?></li>
							<li><?php mc_settings_field( 'mc_event_mail_bcc', __( 'BCC on notifications (one per line):', 'my-calendar' ), '', '', array(
										'cols' => 60,
										'rows' => 6
									), 'textarea' ); ?></li>
							<li><?php mc_settings_field( 'mc_event_mail_subject', __( 'Email subject', 'my-calendar' ), get_bloginfo( 'name' ) . ': ' . __( 'New event added', 'my-calendar' ), '', array( 'size' => 60 ) ); ?></li>
							<li><?php mc_settings_field( 'mc_event_mail_message', __( 'Message Body', 'my-calendar' ), __( 'New Event:', 'my-calendar' ) . "\n{title}: {date}, {time} - {event_status}", "<br /><a href='" . admin_url( "admin.php?page=my-calendar-help#templates" ) . "'>" . __( "Templating Help", 'my-calendar' ) . '</a>', array(
										'cols' => 60,
										'rows' => 6
									), 'textarea' ); ?></li>
							<li><?php mc_settings_field( 'mc_html_email', __( 'Send HTML email', 'my-calendar' ), '', '', array(), 'checkbox-single' ); ?></li>
						</ul>
					</fieldset>
					<p>
						<input type="submit" name="save" class="button-primary"
						       value="<?php _e( 'Save Email Settings', 'my-calendar' ); ?>"/>
					</p>
				</form>
			</div>
		</div>
	</div>

	<?php echo apply_filters( 'mc_after_settings', '' ); ?>

	</div>
	</div>

	<?php mc_show_sidebar(); ?>

	</div>
<?php
}

function mc_check_caps( $role, $cap ) {
	$role = get_role( $role );
	if ( $role->has_cap( $cap ) ) {
		return " checked='checked'";
	}

	return '';
}

function mc_cap_checkbox( $role, $cap, $name ) {
	return "<li><input type='checkbox' id='mc_caps_{$role}_$cap' name='mc_caps[$role][$cap]' value='on'" . mc_check_caps( $role, $cap ) . " /> <label for='mc_caps_{$role}_$cap'>$name</label></li>";
}