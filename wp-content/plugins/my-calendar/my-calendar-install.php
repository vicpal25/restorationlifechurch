<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

// define global variables;
global $initial_db, $initial_occur_db, $initial_loc_db, $initial_cat_db, $default_template, $wpdb, $grid_template, $list_template, $rss_template, $mini_template, $single_template, $defaults;

$defaults = array(
	'upcoming' => array(
		'type'     => 'event',
		'before'   => 3,
		'after'    => 3,
		'template' => $default_template,
		'category' => '',
		'text'     => '',
		'title'    => 'Upcoming Events'
	),
	'today'    => array(
		'template' => $default_template,
		'category' => '',
		'title'    => 'Today\'s Events',
		'text'     => ''
	)
);

$grid_template = addslashes( '<span class="event-time dtstart" title="{dtstart}">{time}<span class="time-separator"> - </span>{endtime before="<span class=\'end-time dtend\' title=\'{dtend}\'>" after="</span>"}</span>

<div class="sub-details">
{hcard}
{details before="<p class=\'mc_details\'>" after="</p>"}
<p><a href="{linking}" class="event-link external">{title}</a></p></div>' );

$list_template = addslashes( '<span class="event-time dtstart" title="{dtstart}">{time}<span class="time-separator"> - </span>{endtime before="<span class=\'end-time dtend\' title=\'{dtend}\'>" after="</span>"}</span>

<div class="sub-details">
{hcard}
{details before="<p class=\'mc_details\'>" after="</p>"}
<p><a href="{linking}" class="event-link external">{title}</a></p></div>' );

$mini_template = addslashes( '<span class="event-time dtstart" title="{dtstart}">{time}<span class="time-separator"> - </span>{endtime before="<span class=\'end-time dtend\' title=\'{dtend}\'>" after="</span>"}</span>

<div class="sub-details">
{excerpt before="<div class=\'excerpt\'>" after="</div>"}
{hcard}
<p><a href="{linking}" class="event-link external">{title}</a></p></div>' );

$single_template = addslashes( '<span class="event-time dtstart" title="{dtstart}">{time}<span class="time-separator"> - </span><span class="end-time dtend" title="{dtend}">{endtime}</span></span>

<div class="sub-details">
{hcard}
<div class="mc-description">{image}{description}</div>
<p>{ical_html} &bull; {gcal_link}</p>
{map}
<p><a href="{linking}" class="event-link external">{title}</a></p></div>' );

$rss_template = addslashes( "\n<item>
    <title>{rss_title}: {date}, {time}</title>
    <link>{link}</link>
	<pubDate>{rssdate}</pubDate>
	<dc:creator>{author}</dc:creator>  	
    <description><![CDATA[{rss_description}]]></description>
	<content:encoded><![CDATA[<div class='vevent'>
    <h1 class='summary'>{rss_title}</h1>
    <div class='description'>{rss_description}</div>
    <p class='dtstart' title='{ical_start}'>Begins: {time} on {date}</p>
    <p class='dtend' title='{ical_end}'>Ends: {endtime} on {enddate}</p>	
	<p>Recurrence: {recurs}</p>
	<p>Repetition: {repeats} times</p>
    <div class='location'>{rss_hcard}</div>
	{link_title}
    </div>]]></content:encoded>
	<dc:format xmlns:dc='http://purl.org/dc/elements/1.1/'>text/html</dc:format>
	<dc:source xmlns:dc='http://purl.org/dc/elements/1.1/'>" . home_url() . "</dc:source>
	{guid}
  </item>\n" );

$default_template = '<strong>{timerange}, {date}</strong> &#8211; {linking_title}';
$charset_collate  = '';
if ( ! empty( $wpdb->charset ) ) {
	$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
}

if ( ! empty( $wpdb->collate ) && $charset_collate != '' ) {
	$charset_collate .= " COLLATE $wpdb->collate";
}

$event_fifth_week = ( get_option( 'mc_no_fifth_week' ) == 'true' ) ? 1 : 0;

$initial_db = "CREATE TABLE " . my_calendar_table() . " ( 
 event_id INT(11) NOT NULL AUTO_INCREMENT,
 event_begin DATE NOT NULL,
 event_end DATE NOT NULL,
 event_title VARCHAR(255) NOT NULL,
 event_desc TEXT NOT NULL,
 event_short TEXT NOT NULL,
 event_open INT(3) DEFAULT '2',
 event_registration TEXT NOT NULL,
 event_tickets VARCHAR(255) NOT NULL,
 event_time TIME,
 event_endtime TIME,
 event_recur CHAR(2),
 event_repeats INT(3),
 event_status INT(1) NOT NULL DEFAULT '1',  
 event_author BIGINT(20) UNSIGNED,
 event_host BIGINT(20) UNSIGNED, 
 event_category BIGINT(20) UNSIGNED NOT NULL DEFAULT '1',
 event_link TEXT,
 event_post BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
 event_link_expires TINYINT(1) NOT NULL,
 event_location BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
 event_label VARCHAR(60) NOT NULL,
 event_street VARCHAR(60) NOT NULL,
 event_street2 VARCHAR(60) NOT NULL,
 event_city VARCHAR(60) NOT NULL,
 event_state VARCHAR(60) NOT NULL,
 event_postcode VARCHAR(10) NOT NULL,
 event_region VARCHAR(255) NOT NULL,
 event_country VARCHAR(60) NOT NULL,
 event_url TEXT,
 event_longitude FLOAT(10,6) NOT NULL DEFAULT '0',
 event_latitude FLOAT(10,6) NOT NULL DEFAULT '0',
 event_zoom INT(2) NOT NULL DEFAULT '14',
 event_phone VARCHAR(32) NOT NULL,
 event_phone2 VARCHAR(32) NOT NULL, 
 event_access TEXT,
 event_group INT(1) NOT NULL DEFAULT '0',
 event_group_id INT(11) NOT NULL DEFAULT '0',
 event_span INT(1) NOT NULL DEFAULT '0',
 event_approved INT(1) NOT NULL DEFAULT '1',
 event_flagged INT(1) NOT NULL DEFAULT '0',
 event_hide_end INT(1) NOT NULL DEFAULT '0',
 event_holiday INT(1) NOT NULL DEFAULT '0',
 event_fifth_week INT(1) NOT NULL DEFAULT '$event_fifth_week',
 event_image TEXT,
 event_added TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY  (event_id),
 KEY event_recur (event_recur)
 ) $charset_collate;";

$initial_occur_db = "CREATE TABLE " . my_calendar_event_table() . " ( 
 occur_id INT(11) NOT NULL AUTO_INCREMENT,
 occur_event_id INT(11) NOT NULL,
 occur_begin DATETIME NOT NULL,
 occur_end DATETIME NOT NULL,
 occur_group_id INT(11) NOT NULL DEFAULT '0',
 PRIMARY KEY  (occur_id),
 KEY occur_event_id (occur_event_id)
 ) $charset_collate;";

$initial_cat_db = "CREATE TABLE " . my_calendar_categories_table() . " ( 
 category_id INT(11) NOT NULL AUTO_INCREMENT, 
 category_name VARCHAR(255) NOT NULL, 
 category_color VARCHAR(7) NOT NULL, 
 category_icon VARCHAR(128) NOT NULL,
 category_private INT(1) NOT NULL DEFAULT '0',
 category_term INT(11) NOT NULL DEFAULT '0',
 PRIMARY KEY  (category_id) 
 ) $charset_collate;";

$initial_loc_db = "CREATE TABLE " . my_calendar_locations_table() . " ( 
 location_id INT(11) NOT NULL AUTO_INCREMENT, 
 location_label VARCHAR(60) NOT NULL,
 location_street VARCHAR(60) NOT NULL,
 location_street2 VARCHAR(60) NOT NULL,
 location_city VARCHAR(60) NOT NULL,
 location_state VARCHAR(60) NOT NULL,
 location_postcode VARCHAR(10) NOT NULL,
 location_region VARCHAR(255) NOT NULL,
 location_url TEXT,
 location_country VARCHAR(60) NOT NULL,
 location_longitude FLOAT(10,6) NOT NULL DEFAULT '0',
 location_latitude FLOAT(10,6) NOT NULL DEFAULT '0',
 location_zoom INT(2) NOT NULL DEFAULT '14',
 location_phone VARCHAR(32) NOT NULL,
 location_phone2 VARCHAR(32) NOT NULL,
 location_access TEXT,
 PRIMARY KEY  (location_id) 
 ) $charset_collate;";

function mc_default_settings() {
	global $initial_db, $initial_occur_db, $initial_loc_db, $initial_cat_db, $grid_template, $rss_template, $list_template, $mini_template, $single_template, $mc_version, $defaults;

	add_option( 'mc_location_access', array(
		'1'  => __( 'Accessible Entrance', 'my-calendar' ),
		'2'  => __( 'Accessible Parking Designated', 'my-calendar' ),
		'3'  => __( 'Accessible Restrooms', 'my-calendar' ),
		'4'  => __( 'Accessible Seating', 'my-calendar' ),
		'5'  => __( 'Accessible Transportation Available', 'my-calendar' ),
		'6'  => __( 'Wheelchair Accessible', 'my-calendar' ),
		'7'  => __( 'Courtesy Wheelchairs', 'my-calendar' ),
		'8'  => __( 'Bariatric Seating Available', 'my-calendar' ),
		'9'  => __( 'Elevator to all public areas', 'my-calendar' ),
		'10' => __( 'Braille Signage', 'my-calendar' ),
		'11' => __( 'Fragrance-Free Policy', 'my-calendar' ),
		'12' => __( 'Other', 'my-calendar' )
	) );
	add_option( 'mc_event_access', array(
		'1'  => __( 'Audio Description', 'my-calendar' ),
		'2'  => __( 'ASL Interpretation', 'my-calendar' ),
		'3'  => __( 'ASL Interpretation with voicing', 'my-calendar' ),
		'4'  => __( 'Deaf-Blind ASL', 'my-calendar' ),
		'5'  => __( 'Real-time Captioning', 'my-calendar' ),
		'6'  => __( 'Scripted Captioning', 'my-calendar' ),
		'7'  => __( 'Assisted Listening Devices', 'my-calendar' ),
		'8'  => __( 'Tactile/Touch Tour', 'my-calendar' ),
		'9'  => __( 'Braille Playbill', 'my-calendar' ),
		'10' => __( 'Large Print Playbill', 'my-calendar' ),
		'11' => __( 'Sensory Friendly', 'my-calendar' ),
		'12' => __( 'Other', 'my-calendar' )
	) );
	add_option( 'mc_display_author', 'false' );
	add_option( 'mc_version', $mc_version );
	add_option( 'mc_use_styles', 'false' );
	add_option( 'mc_show_months', 1 );
	add_option( 'mc_show_map', 'true' );
	add_option( 'mc_show_address', 'false' );
	add_option( 'mc_calendar_javascript', 0 );
	add_option( 'mc_list_javascript', 0 );
	add_option( 'mc_mini_javascript', 0 );
	add_option( 'mc_ajax_javascript', 0 );
	add_option( 'mc_notime_text', 'N/A' );
	add_option( 'mc_hide_icons', 'false' );
	add_option( 'mc_event_link_expires', 'no' );
	add_option( 'mc_apply_color', 'background' );
	add_option( 'mc_inverse_color', 'true' );
	add_option( 'mc_input_options', array( 'event_short'             => 'off',
	                                       'event_desc'              => 'on',
	                                       'event_category'          => 'on',
	                                       'event_image'             => 'on',
	                                       'event_link'              => 'on',
	                                       'event_recurs'            => 'on',
	                                       'event_open'              => 'off',
	                                       'event_location'          => 'off',
	                                       'event_location_dropdown' => 'on',
	                                       'event_specials'          => 'on',
	                                       'event_access'            => 'on'
		) );
	add_option( 'mc_input_options_administrators', 'false' );
	add_site_option( 'mc_multisite', '0' );
	add_option( 'mc_event_mail', 'false' );
	add_option( 'mc_desc', 'true' );
	add_option( 'mc_process_shortcodes', 'false' );
	add_option( 'mc_short', 'false' );
	add_option( 'mc_event_approve', 'false' );
	add_option( 'mc_event_approve_perms', 'manage_options' );
	add_option( 'mc_no_fifth_week', 'true' );
	add_option( 'mc_week_format', "M j, 'y" );
	add_option( 'mc_location_type', 'event_state' );
	add_option( 'mc_user_settings_enabled', false );
	add_option( 'mc_user_location_type', 'state' );
	add_option( 'mc_date_format', get_option( 'date_format' ) );
	add_option( 'mc_templates', array(
		'title'   => '{title}',
		'link'    => '{title}',
		'grid'    => $grid_template,
		'list'    => $list_template,
		'mini'    => $mini_template,
		'rss'     => $rss_template,
		'details' => $single_template,
		'label'   => addslashes( 'More<span class="screen-reader-text"> about {title}</span>' )
	) );
	add_option( 'mc_skip_holidays', 'false' );
	add_option( 'mc_css_file', 'twentyfourteen.css' );
	add_option( 'mc_time_format', get_option( 'time_format' ) );
	add_option( 'mc_widget_defaults', $defaults );
	add_option( 'mc_show_weekends', 'true' );
	add_option( 'mc_convert', 'true' );
	add_option( 'mc_show_event_vcal', 'false' );
	add_option( 'mc_week_caption', "The week's events" );
	add_option( 'mc_multisite_show', 0 );
	add_option( 'mc_event_link', 'true' );
	mc_add_roles();
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $initial_db );
	dbDelta( $initial_occur_db );
	dbDelta( $initial_cat_db );
	dbDelta( $initial_loc_db );

}

function mc_migrate_db() {
	global $wpdb;
	// this function migrates the DB from version 1.10.x to version 2.0.
	$tables = $wpdb->get_results( "show tables;" );
	foreach ( $tables as $table ) {
		foreach ( $table as $value ) {
			if ( $value == my_calendar_event_table() ) {
				$count  = $wpdb->get_var( 'SELECT count(1) from ' . my_calendar_event_table() );
				$count2 = $wpdb->get_var( 'SELECT count(1) from ' . my_calendar_table() );
				if ( $count2 > 0 && $count > 0 ) {
					return;
				}
				if ( $count2 == 0 && $count == 0 ) {
					return; // no events, migration unnecessary
				}
				break 2;
			}
		}
	}
	// 2) create new occurrences database, if necessary
	//dbDelta($initial_occur_db);
	// 3) migrate events
	$sql    = "SELECT event_id, event_begin, event_time, event_end, event_endtime FROM " . my_calendar_table();
	$events = $wpdb->get_results( $sql );
	foreach ( $events as $event ) {
		// assign endtimes to all events
		if ( $event->event_endtime == '00:00:00' && $event->event_time != '00:00:00' ) {
			$event->event_endtime = date( 'H:i:s', strtotime( "$event->event_time +1 hour" ) );
			mc_flag_event( $event->event_id, $event->event_endtime );
		}
		$dates = array( 'event_begin'   => $event->event_begin,
		                'event_end'     => $event->event_end,
		                'event_time'    => $event->event_time,
		                'event_endtime' => $event->event_endtime
		);
		mc_increment_event( $event->event_id, $dates );
	}
}

function mc_flag_event( $id, $time ) {
	global $wpdb;
	$data    = array( 'event_hide_end' => 1, 'event_endtime' => $time );
	$formats = array( '%d', '%s' );
	$result  = $wpdb->update(
		my_calendar_table(),
		$data,
		array( 'event_id' => $id ),
		$formats,
		'%d' );

	return;
}

function mc_upgrade_db() {
	global $mc_version, $initial_db, $initial_occur_db, $initial_loc_db, $initial_cat_db;
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $initial_db );
	dbDelta( $initial_occur_db );
	dbDelta( $initial_cat_db );
	dbDelta( $initial_loc_db );
	update_option( 'mc_db_version', $mc_version );
}

function mc_check_location_table( $event, $locations ) {
	$location = array(
		'location_label'     => $event['event_label'],
		'location_street'    => $event['event_street'],
		'location_street2'   => $event['event_street2'],
		'location_city'      => $event['event_city'],
		'location_state'     => $event['event_state'],
		'location_postcode'  => $event['event_postcode'],
		'location_region'    => $event['event_region'],
		'location_url'       => $event['event_url'],
		'location_country'   => $event['event_country'],
		'location_longitude' => $event['event_longitude'],
		'location_latitude'  => $event['event_latitude'],
		'location_zoom'      => $event['event_zoom'],
		'location_phone'     => $event['event_phone'],
		'location_phone2'    => $event['event_phone2'],
		'location_access'    => $event['event_access']
	);

	foreach ( $locations as $id => $loc ) {
		// compare locations - if there are differences, return as not existing
		$diff = array_diff( $location, $loc );
		if ( empty( $diff ) ) {
			return $id;
		}
	}

	return false;
}

function mc_transition_db() {
	// copy to post types. Don't do this if referencing remote sites.
	if ( get_option( 'mc_remote' ) != 'true' ) {
		global $wpdb;
		$results   = $wpdb->get_results( 'SELECT * FROM ' . my_calendar_locations_table(), ARRAY_A );
		$locations = array();
		foreach ( $results as $result ) {
			$location_id = $result['location_id'];
			unset( $result['location_id'] );
			$hash                      = md5( serialize( $result ) );
			$locations[ $location_id ] = $result;
		}
		$results = $wpdb->get_results( 'SELECT * FROM ' . my_calendar_categories_table() );
		foreach ( $results as $category ) {
			$term = wp_insert_term( $category->category_name, 'mc-event-category' );
			if ( ! is_wp_error( $term ) ) {
				$term_id = $term['term_id'];
				mc_update_category( 'category_term', $term_id, $category->category_id );
			} else {
				$term_id = $term->error_data['term_exists'];
				mc_update_category( 'category_term', $term_id, $category->category_id );
			}
		}
		$results = $wpdb->get_results( 'SELECT * FROM ' . my_calendar_table(), ARRAY_A );
		foreach ( $results as $event ) {
			$post_id = mc_create_event_post( $event, $event['event_id'] );
			mc_update_event( 'event_post', $post_id, $event['event_id'] );
			// false if not found, id if found.
			$location = mc_check_location_table( $event, $locations );
			if ( $location ) {
				mc_update_event( 'event_location', $location, $event['event_id'] );
			} else {
				if ( $event['event_label'] == '' &&
				     $event['event_street'] == '' &&
				     $event['event_url'] == '' &&
				     $event['event_city'] == '' &&
				     $event['event_state'] == '' &&
				     $event['event_country'] == ''
				) {
					// don't insert the row if location does not have basic data.
				} else {
					$add = array(
						'location_label'     => $event['event_label'],
						'location_street'    => $event['event_street'],
						'location_street2'   => $event['event_street2'],
						'location_city'      => $event['event_city'],
						'location_state'     => $event['event_state'],
						'location_postcode'  => $event['event_postcode'],
						'location_region'    => $event['event_region'],
						'location_country'   => $event['event_country'],
						'location_url'       => $event['event_url'],
						'location_longitude' => $event['event_longitude'],
						'location_latitude'  => $event['event_latitude'],
						'location_zoom'      => $event['event_zoom'],
						'location_phone'     => $event['event_phone'],
						'location_access'    => '' // no events in this transition will have access data.
					);
					mc_insert_location( $add );
				}
				// could add delete routine to allow user to select what location to use for events using a given location.
			}
		}
	}
}

function my_calendar_copyr( $source, $dest ) {
	// Sanity check
	if ( ! file_exists( $source ) ) {
		return false;
	}
	// Check for symlinks
	if ( is_link( $source ) ) {
		return symlink( readlink( $source ), $dest );
	}
	// Simple copy for a file
	if ( is_file( $source ) ) {
		return @copy( $source, $dest );
	}
	// Make destination directory
	if ( ! is_dir( $dest ) ) {
		@mkdir( $dest );
	}
	// Loop through the folder
	$dir = dir( $source );
	while ( false !== $entry = $dir->read() ) {
		// Skip pointers
		if ( $entry == '.' || $entry == '..' ) {
			continue;
		}
		// Deep copy directories
		my_calendar_copyr( "$source/$entry", "$dest/$entry" );
	}
	// Clean up
	$dir->close();

	return true;
}

function my_calendar_rmdirr( $dirname ) {
	// Sanity check
	if ( ! file_exists( $dirname ) ) {
		return false;
	}
	// Simple delete for a file
	if ( is_file( $dirname ) ) {
		return unlink( $dirname );
	}
	// Loop through the folder
	$dir = dir( $dirname );
	while ( false !== $entry = $dir->read() ) {
		// Skip pointers
		if ( $entry == '.' || $entry == '..' ) {
			continue;
		}
		// Recurse
		my_calendar_rmdirr( "$dirname/$entry" );
	}
	// Clean up
	$dir->close();

	return @rmdir( $dirname );
}

function my_calendar_backup( $process, $plugin ) {
	if ( isset( $plugin['plugin'] ) && $plugin['plugin'] == 'my-calendar/my-calendar.php' ) {
		$to   = dirname( __FILE__ ) . "/../styles_backup/";
		$from = dirname( __FILE__ ) . "/styles/";
		my_calendar_copyr( $from, $to );

		$to   = dirname( __FILE__ ) . "/../icons_backup/";
		$from = dirname( __FILE__ ) . "/images/icons/";
		my_calendar_copyr( $from, $to );
	}
}

function my_calendar_recover( $process, $plugin ) {
	if ( isset( $plugin['plugin'] ) && $plugin['plugin'] == 'my-calendar/my-calendar.php' ) {
		$from = dirname( __FILE__ ) . "/../styles_backup/";
		$to   = dirname( __FILE__ ) . "/styles/";
		my_calendar_copyr( $from, $to );
		if ( is_dir( $from ) ) {
			my_calendar_rmdirr( $from );
		}
		$from = dirname( __FILE__ ) . "/../icons_backup/";
		$to   = dirname( __FILE__ ) . "/images/icons/";
		my_calendar_copyr( $from, $to );
		if ( is_dir( $from ) ) {
			my_calendar_rmdirr( $from );
		}
	}
}

add_filter( 'upgrader_pre_install', 'my_calendar_backup', 10, 2 );
add_filter( 'upgrader_post_install', 'my_calendar_recover', 10, 2 );