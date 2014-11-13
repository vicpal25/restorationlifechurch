<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

function my_calendar_ical() {
	$p  = ( isset( $_GET['span'] ) ) ? 'year' : false;
	$y  = ( isset( $_GET['yr'] ) ) ? $_GET['yr'] : date( 'Y' );
	$m  = ( isset( $_GET['month'] ) ) ? $_GET['month'] : date( 'n' );
	$ny = ( isset( $_GET['nyr'] ) ) ? $_GET['nyr'] : $y;
	$nm = ( isset( $_GET['nmonth'] ) ) ? $_GET['nmonth'] : $m;

	if ( $p ) {
		$from = "$y-1-1";
		$to   = "$y-12-31";
	} else {
		$d    = date( 't', mktime( 0, 0, 0, $m, 1, $y ) );
		$from = "$y-$m-1";
		$to   = "$ny-$nm-$d";
	}

	$from = apply_filters( 'mc_ical_download_from', $from, $p );
	$to   = apply_filters( 'mc_ical_download_to', $to, $p );
	$atts = array(
		'category' => null,
		'ltype'    => '',
		'lvalue'   => '',
		'source'   => 'calendar',
		'author'   => null,
		'host'     => null
	);
	$atts = apply_filters( 'mc_ical_attributes', $atts );
	extract( $atts );

	global $mc_version;
// establish template
	$template = "BEGIN:VEVENT
UID:{dateid}-{id}
LOCATION:{ical_location}
SUMMARY:{title}
DTSTAMP:{ical_start}
ORGANIZER;CN={host}:MAILTO:{host_email}
DTSTART:{ical_start}
DTEND:{ical_end}
URL;VALUE=URI:{link}
DESCRIPTION:{ical_desc}
CATEGORIES:{category}
END:VEVENT";
// add ICAL headers
	$output = 'BEGIN:VCALENDAR
VERSION:2.0
METHOD:PUBLISH
PRODID:-//Accessible Web Design//My Calendar//http://www.joedolson.com//v' . $mc_version . '//EN';
	// to do : add support for other arguments
	$events = my_calendar_grab_events( $from, $to );
	if ( is_array( $events ) && ! empty( $events ) ) {
		foreach ( array_keys( $events ) as $key ) {
			$event =& $events[ $key ];
			if ( is_object( $event ) ) {
				if ( ! ( $event->category_private == 1 && ! is_user_logged_in() ) ) {
					$array = mc_create_tags( $event );
					$output .= "\n" . jd_draw_template( $array, $template, 'ical' );
				}
			}
		}
	}
	$output .= "\nEND:VCALENDAR";
	$output = html_entity_decode( preg_replace( "~(?<!\r)\n~", "\r\n", $output ) );
	if ( ! ( isset( $_GET['sync'] ) && $_GET['sync'] == 'true' ) ) {
		header( "Content-Type: text/calendar; charset=" . get_bloginfo( 'charset' ) );
		header( "Pragma: no-cache" );
		header( "Expires: 0" );
		header( "Content-Disposition: inline; filename=my-calendar.ics" );
	}
	echo $output;
}