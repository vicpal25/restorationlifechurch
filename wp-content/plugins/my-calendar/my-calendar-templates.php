<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

// draw array of information into a template with {$key} formatted tags
function jd_draw_template( $array, $template, $type = 'list' ) {
	$template = stripcslashes( $template );
	foreach ( $array as $key => $value ) {
		if ( is_object( $value ) && ! empty( $value ) ) {
			// null values return false...
		} else {
			if ( strpos( $template, "{" . $key ) !== false ) {
				if ( $type != 'list' ) {
					if ( $key == 'link' && $value == '' ) {
						$value = ( get_option( 'mc_uri' ) != '' && ! is_numeric( get_option( 'mc_uri' ) ) ) ? get_option( 'mc_uri' ) : home_url();
					}
					if ( $key != 'guid' ) {
						$value = htmlentities( $value );
					}
				}
				if ( strpos( $template, "{" . $key . " " ) !== false ) { // only do preg_match if appropriate
					preg_match_all( '/{' . $key . '\b(?>\s+(?:before="([^"]*)"|after="([^"]*)"|format="([^"]*)")|[^\s]+|\s+){0,2}}/', $template, $matches, PREG_PATTERN_ORDER );
					if ( $matches ) {
						$before = @$matches[1][0];
						$after  = @$matches[2][0];
						$format = @$matches[3][0];
						if ( $format != '' ) {
							$value = date_i18n( stripslashes( $format ), strtotime( stripslashes( $value ) ) );
						}
						$value    = ( $value == '' ) ? '' : $before . $value . $after;
						$search   = @$matches[0][0];
						$template = str_replace( $search, $value, $template );
					}
				} else { // don't do preg match (never required for RSS)
					$template = stripcslashes( str_replace( "{" . $key . "}", $value, $template ) );
				}
			} // end {$key check
			// secondary search for RSS output
			$rss_search = "{rss_$key}";
			if ( strpos( $template, $rss_search ) !== false ) {
				$value    = ent2ncr( $value ); // WP native function.
				$template = stripcslashes( str_replace( $rss_search, $value, $template ) );
			}
		}
	}

	return stripslashes( trim( $template ) );
}

// setup string version of address data
function mc_map_string( $event, $source = 'event' ) {
	if ( $source == 'event' ) {
		$map_string = $event->event_street . ' ' . $event->event_street2 . ' ' . $event->event_city . ' ' . $event->event_state . ' ' . $event->event_postcode . ' ' . $event->event_country;
	} else {
		$map_string = $event->location_street . ' ' . $event->location_street2 . ' ' . $event->location_city . ' ' . $event->location_state . ' ' . $event->location_postcode . ' ' . $event->location_country;
	}

	return $map_string;
}

// set up link to Google Maps
function mc_maplink( $event, $request = 'map', $source = 'event' ) {
	$map_string = mc_map_string( $event, $source );
	if ( $source == 'event' ) {
		if ( $request == 'gcal' ) {
			return $map_string;
		}
		$zoom       = ( $event->event_zoom != 0 ) ? $event->event_zoom : '15';
		$map_string = str_replace( " ", "+", $map_string );
		if ( $event->event_longitude != '0.000000' && $event->event_latitude != '0.000000' ) {
			$map_string = "$event->event_latitude,$event->event_longitude";
		}
	} else {
		$zoom       = ( $event->location_zoom != 0 ) ? $event->location_zoom : '15';
		$map_string = str_replace( " ", "+", $map_string );
		if ( $event->location_longitude != '0.000000' && $event->location_latitude != '0.000000' ) {
			$map_string = "$event->location_latitude,$event->location_longitude";
		}
	}
	if ( strlen( trim( $map_string ) ) > 6 ) {
		$map_url = "http://maps.google.com/maps?z=$zoom&amp;daddr=$map_string";
		if ( $request == 'url' || $source == 'location' ) {
			return $map_url;
		}
		$map_label = stripslashes( ( $event->event_label != "" ) ? $event->event_label : $event->event_title );
		$map       = "<a href=\"$map_url\" class='map-link external'>" . sprintf( __( 'Map<span> to %s</span>', 'my-calendar' ), $map_label ) . "</a>";
	} else {
		$map = "";
	}

	return $map;
}

// set up link to push events into Google Calendar.
function mc_google_cal( $dtstart, $dtend, $url, $title, $location, $description ) {
	$source = "https://www.google.com/calendar/render?action=TEMPLATE";
	$base   = "&dates=$dtstart/$dtend";
	$base .= "&sprop=website:" . $url;
	$base .= "&text=" . urlencode( $title );
	$base .= "&location=" . urlencode( $location );
	$base .= "&sprop=name:" . urlencode( get_bloginfo( 'name' ) );
	$base .= "&details=" . urlencode( stripcslashes( $description ) );
	$base .= "&sf=true&output=xml";

	return $source . $base;
}

// set up hCard formatted address.
function mc_hcard( $event, $address = 'true', $map = 'true', $source = 'event', $context = 'event' ) {
	$the_map = mc_maplink( $event, 'url', $source );
	$url     = ( $source == 'event' ) ? $event->event_url : $event->location_url;
	$label   = stripslashes( ( $source == 'event' ) ? $event->event_label : $event->location_label );
	$street  = stripslashes( ( $source == 'event' ) ? $event->event_street : $event->location_street );
	$street2 = stripslashes( ( $source == 'event' ) ? $event->event_street2 : $event->location_street2 );
	$city    = stripslashes( ( $source == 'event' ) ? $event->event_city : $event->location_city );
	$state   = stripslashes( ( $source == 'event' ) ? $event->event_state : $event->location_state );
	$zip     = stripslashes( ( $source == 'event' ) ? $event->event_postcode : $event->location_postcode );
	$country = stripslashes( ( $source == 'event' ) ? $event->event_country : $event->location_country );
	$phone   = stripslashes( ( $source == 'event' ) ? $event->event_phone : $event->location_phone );
	if ( ! $url && ! $label && ! $street && ! $street2 && ! $city && ! $state && ! $zip && ! $country && ! $phone ) {
		return '';
	}
	$link  = ( $url != '' ) ? "<a href='$url' class='location-link external'>$label</a>" : $label;
	$hcard = "<div class=\"address vcard\">";
	if ( $address == 'true' ) {
		$hcard .= "<div class=\"adr\">";
		$hcard .= ( $label != '' ) ? "<strong class=\"org\">" . $link . "</strong><br />" : '';
		$hcard .= ( $street . $street2 . $city . $state . $zip . $country . $phone == '' ) ? '' : "<div class='sub-address'>";
		$hcard .= ( $street != "" ) ? "<div class=\"street-address\">" . $street . "</div>" : '';
		$hcard .= ( $street2 != "" ) ? "<div class=\"street-address\">" . $street2 . "</div>" : '';
		$hcard .= ( $city . $state . $zip != '' ) ? "<div>" : '';
		$hcard .= ( $city != "" ) ? "<span class=\"locality\">" . $city . "</span><span class='sep'>, </span>" : '';
		$hcard .= ( $state != "" ) ? "<span class=\"region\">" . $state . "</span> " : '';
		$hcard .= ( $zip != "" ) ? " <span class=\"postal-code\">" . $zip . "</span>" : '';
		$hcard .= ( $city . $state . $zip != '' ) ? "</div>" : '';
		$hcard .= ( $country != "" ) ? "<div class=\"country-name\">" . $country . "</div>" : '';
		$hcard .= ( $phone != "" ) ? "<div class=\"tel\">" . $phone . "</div>" : '';
		$hcard .= ( $street . $street2 . $city . $state . $zip . $country . $phone == '' ) ? '' : "</div>";
		$hcard .= "</div>";
	}
	if ( $map == 'true' ) {
		$the_map = "<a href='$the_map' class='external'>" . __( 'Map', 'my-calendar' ) . "<span class='screen-reader-text'> $label</span></a>";
		$hcard .= ( $the_map != '' ) ? "<div class='url map'>$the_map</div>" : '';
	}
	$hcard .= "</div>";

	return apply_filters( 'mt_hcard', $hcard, $event, $address, $map, $source, $context );
}

// Produces the array of event details used for drawing templates
function mc_create_tags( $event, $context = 'filters' ) {
	$e                  = array();
	$e['post']          = $event->event_post;
	$date_format        = ( get_option( 'mc_date_format' ) != '' ) ? get_option( 'mc_date_format' ) : get_option( 'date_format' );
	$e                  = apply_filters( 'mc_insert_author_data', $e, $event );
	$e                  = apply_filters( 'mc_filter_image_data', $e, $event );
	$map                = mc_maplink( $event );
	$map_url            = mc_maplink( $event, 'url' );
	$sitelink_html      = "<div class='url link'><a href='$event->event_url' class='location-link external'>" . sprintf( __( 'Visit web site<span class="screen-reader-text">: %s</span>', 'my-calendar' ), $event->event_label ) . "</a></div>";
	$e['sitelink_html'] = $sitelink_html;
	$e['sitelink']      = $event->event_url;
	$e['access']        = mc_expand( get_post_meta( $event->event_post, '_mc_event_access', true ) );

	// date & time fields
	$dtstart           = mc_format_timestamp( strtotime( $event->occur_begin ) );
	$dtend             = mc_format_timestamp( strtotime( $event->occur_end ) );

	$real_end_date     = $event->occur_end;
	$e['date_utc']     = date_i18n( apply_filters( 'mc_date_format', $date_format, 'template_begin_ts' ), $event->ts_occur_begin );
	$e['date_end_utc'] = date_i18n( apply_filters( 'mc_date_format', $date_format, 'template_end_ts' ), $event->ts_occur_end );
	$e['time']         = ( date( 'H:i:s', strtotime( $event->occur_begin ) ) == '00:00:00' ) ? get_option( 'mc_notime_text' ) : date( get_option( 'mc_time_format' ), strtotime( $event->occur_begin ) );
	$e['time24']       = ( date( 'G:i', strtotime( $event->occur_begin ) ) == '00:00:00' ) ? get_option( 'mc_notime_text' ) : date( get_option( 'mc_time_format' ), strtotime( $event->occur_begin ) );
	$endtime           = date( 'H:i:s', strtotime( $event->occur_end ) );
	$e['endtime']      = ( $event->occur_end == $event->occur_begin || $event->event_hide_end == 1 ) ? '' : date_i18n( get_option( 'mc_time_format' ), strtotime( $endtime ) );
	$tz                = mc_user_timezone();
	$e['runtime']      = mc_runtime( $event->ts_occur_begin, $event->ts_occur_end, $event );
	if ( $tz != '' ) {
		$local_begin      = date_i18n( get_option( 'mc_time_format' ), strtotime( $event->occur_begin . "+$tz hours" ) );
		$local_end        = date_i18n( get_option( 'mc_time_format' ), strtotime( $event->occur_end . "+$tz hours" ) );
		$e['usertime']    = "$local_begin";
		$e['endusertime'] = ( $local_begin == $local_end ) ? '' : "$local_end";
	} else {
		$e['usertime']    = $e['time'];
		$e['endusertime'] = ( $e['time'] == $e['endtime'] ) ? '' : $e['endtime'];
	}
	$e['dtstart'] = date( 'Y-m-d\TH:i:s', strtotime( $event->occur_begin ) );// hcal formatted
	$e['dtend']   = date( 'Y-m-d\TH:i:s', strtotime( $event->occur_end ) );    //hcal formatted end
	$e['rssdate'] = date( 'D, d M Y H:i:s +0000', strtotime( $event->event_added ) );
	$date         = date_i18n( apply_filters( 'mc_date_format', $date_format, 'template_begin' ), strtotime( $event->occur_begin ) );
	$date_end     = date_i18n( apply_filters( 'mc_date_format', $date_format, 'template_end' ), strtotime( $real_end_date ) );
	$date_arr     = array( 'occur_begin' => $event->occur_begin, 'occur_end' => $event->occur_end );
	$date_obj     = (object) $date_arr;
	if ( $event->event_span == 1 ) {
		$dates = mc_event_date_span( $event->event_group_id, $event->event_span, array( 0 => $date_obj ) );
	} else {
		$dates = array();
	}
	$e['date']      = ( $event->event_span != 1 ) ? $date : mc_format_date_span( $dates, 'simple', $date );
	$e['enddate']   = $date_end;
	$e['daterange'] = ( $date == $date_end ) ? $date : "<span class='mc_db'>$date</span> <span>&ndash;</span> <span class='mc_de'>$date_end</span>";
	$e['timerange'] = ( ( $e['time'] == $e['endtime'] ) || $event->event_hide_end == 1 ) ? $e['time'] : "<span class='mc_tb'>" . $e['time'] . "</span> <span>&ndash;</span> <span class='mc_te'>" . $e['endtime'] . "</span>";
	$e['datespan']  = ( $event->event_span == 1 || ( $e['date'] != $e['enddate'] ) ) ? mc_format_date_span( $dates ) : $date;
	$e['multidate'] = mc_format_date_span( $dates, 'complex', "<span class='fallback-date'>$date</span><span class='separator'>,</span> <span class='fallback-time'>$e[time]</span>&ndash;<span class='fallback-endtime'>$e[endtime]</span>" );
	$e['began']     = $event->event_begin; // returns date of first occurrence of an event.
	$e['recurs']    = mc_event_recur_string( $event );
	$e['repeats']   = $event->event_repeats;

	// category fields
	$e['cat_id']    = $event->event_category;
	$e['category']  = stripslashes( $event->category_name );
	$e['icon']      = mc_category_icon( $event, 'img' );
	$e['icon_html'] = "<img src='$e[icon]' class='mc-category-icon' alt='" . __( 'Category', 'my-calendar' ) . ": " . esc_attr( $event->category_name ) . "' />";
	$e['color']     = $event->category_color;

	// special
	$e['skip_holiday'] = ( $event->event_holiday == 0 ) ? 'false' : 'true';
	$e['event_status'] = ( $event->event_approved == 1 ) ? __( 'Published', 'my-calendar' ) : __( 'Reserved', 'my-calendar' );

	// general text fields
	$e['title']                = stripslashes( $event->event_title );
	$e['description']          = ( get_option( 'mc_process_shortcodes' ) == 'true' && $context == 'filters' ) ? apply_filters( 'the_content', $event->event_desc ) : wpautop( stripslashes( $event->event_desc ) );
	$e['description_raw']      = stripslashes( $event->event_desc );
	$e['description_stripped'] = strip_tags( stripslashes( $event->event_desc ) );
	$e['shortdesc']            = ( get_option( 'mc_process_shortcodes' ) == 'true' && $context == 'filters' ) ? apply_filters( 'the_content', $event->event_short ) : wpautop( stripslashes( $event->event_short ) );
	$e['shortdesc_raw']        = stripslashes( $event->event_short );
	$e['shortdesc_stripped']   = strip_tags( stripslashes( $event->event_short ) );

	// registration fields
	$e['event_open']         = mc_event_open( $event );
	$e['event_tickets']      = $event->event_tickets;
	$e['event_registration'] = stripslashes( wp_kses_data( $event->event_registration ) );

	// links
	$templates    = get_option( 'mc_templates' );
	$e_template   = ( ! empty( $templates['label'] ) ) ? stripcslashes( $templates['label'] ) : __( 'Details about', 'my-calendar' ) . ' {title}';
	$tags         = array( "{title}", "{location}", "{color}", "{icon}", "{date}", "{time}" );
	$replacements = array(
		stripslashes( $event->event_title ),
		stripslashes( $event->event_label ),
		$event->category_color,
		$event->category_icon,
		$e['date'],
		$e['time']
	);
	$e_label      = str_replace( $tags, $replacements, $e_template );
	//$e_label = mc_get_details_label( $event, $e ); // recursive...hmmmm.
	$e_link    = mc_get_details_link( $event );
	$e['link'] = mc_event_link( $event );
	if ( $e['link'] ) {
		$e['link_image'] = str_replace( "alt=''", "alt='" . esc_attr( $e['title'] ) . "'", "<a href='" . $e['link'] . "'>" . $e['image'] . "</a>" );
		$e['link_title'] = "<a href='" . $event->event_link . "'>" . $e['title'] . "</a>";
	} else {
		$e['link_image'] = $e['image'];
		$e['link_title'] = $e['title'];
	}
	$e['details_link']  = ( get_option( 'mc_uri' ) != '' && ! is_numeric( get_option( 'mc_uri' ) ) ) ? $e_link : '';
	$e['details']       = ( get_option( 'mc_uri' ) != '' && ! is_numeric( get_option( 'mc_uri' ) ) ) ? "<a href='$e_link' class='mc-details'>$e_label</a>" : '';
	$e['linking']       = ( $e['link'] != '' ) ? $event->event_link : $e_link;
	$e['linking_title'] = ( $e['linking'] != '' ) ? "<a href='" . $e['linking'] . "'>" . $e['title'] . "</a>" : $e['title'];

	// location fields
	$strip_desc           = mc_newline_replace( strip_tags( $event->event_desc ) );
	$e['location']        = stripslashes( $event->event_label );
	$e['street']          = stripslashes( $event->event_street );
	$e['street2']         = stripslashes( $event->event_street2 );
	$e['phone']           = apply_filters( 'mc_phone_format', stripslashes( $event->event_phone ) );
	$e['phone2']          = apply_filters( 'mc_phone_format', stripslashes( $event->event_phone2 ) );
	$e['city']            = stripslashes( $event->event_city );
	$e['state']           = stripslashes( $event->event_state );
	$e['postcode']        = stripslashes( $event->event_postcode );
	$e['country']         = stripslashes( $event->event_country );
	$e['hcard']           = stripslashes( mc_hcard( $event ) );
	$e['link_map']        = $map;
	$e['map_url']         = $map_url;
	$e['map']             = mc_generate_map( $event );
	$url                  = ( get_option( 'mc_uri' ) != '' && ! is_numeric( get_option( 'mc_uri' ) ) ) ? $e_link : $event->event_url;
	$e['gcal']            = mc_google_cal( $dtstart, $dtend, $url, stripcslashes( $event->event_title ), mc_maplink( $event, 'gcal' ), $strip_desc );
	$e['gcal_link']       = "<a href='" . mc_google_cal( $dtstart, $dtend, $url, stripcslashes( $event->event_title ), mc_maplink( $event, 'gcal' ), $strip_desc ) . "'>" . sprintf( __( 'Send <span class="screen-reader-text">%1$s </span>to Google Calendar', 'my-calendar' ), stripcslashes( $event->event_title ) ) . "</a>";
	$e['location_access'] = mc_expand( unserialize( mc_location_data( 'location_access', $event->event_location ) ) );
	$e['location_source'] = $event->event_location;

	// IDs
	$e['dateid']     = $event->occur_id; // unique ID for this date of this event
	$e['id']         = $event->event_id;
	$e['group']      = $event->event_group_id;
	$e['event_span'] = $event->event_span;

	// RSS guid
	$e['region'] = $event->event_region;
	$e['guid']   = ( get_option( 'mc_uri' ) != '' && ! is_numeric( get_option( 'mc_uri' ) ) ) ? "<guid isPermaLink='true'>$e_link</guid>" : "<guid isPermalink='false'>$e_link</guid>";

	// iCAL
	$e['ical_location']    = $event->event_label . ' ' . $event->event_street . ' ' . $event->event_street2 . ' ' . $event->event_city . ' ' . $event->event_state . ' ' . $event->event_postcode;
	$e['ical_description'] = str_replace( "\r", "=0D=0A=", $event->event_desc );
	$e['ical_desc']        = $strip_desc;
	$e['ical_start']       = $dtstart;
	$e['ical_end']         = $dtend;
	$ical_link             = mc_build_url( array( 'vcal' => $event->occur_id ), array(
			'month',
			'dy',
			'yr',
			'ltype',
			'loc',
			'mcat',
			'format'
		), get_option( 'mc_uri' ) );
	$e['ical']             = $ical_link;
	$e['ical_html']        = "<a class='ical' rel='nofollow' href='$ical_link'>" . __( 'iCal', 'my-calendar' ) . "</a>";
	$e                     = apply_filters( 'mc_filter_shortcodes', $e, $event );

	return $e;
}

function mc_get_details_label( $event, $e ) {
	$templates  = get_option( 'mc_templates' );
	$e_template = ( ! empty( $templates['label'] ) ) ? stripcslashes( $templates['label'] ) : sprintf( __( 'Event Details %s', 'my-calendar' ), '<span class="screen-reader-text">about {title}</span> &raquo;' );
	$e_label    = wp_kses( jd_draw_template( $e, $e_template ), array(
			'span' => array( 'class' => array( 'screen-reader-text' ) ),
			'em',
			'strong'
		) );

	return $e_label;
}

function mc_format_timestamp( $os ) {
	$offset = ( 60 * 60 * get_option( 'gmt_offset' ) );
	$time   = ( get_option( 'mc_ical_utc' ) == 'true' ) ? date( "Ymd\THi00", ( mktime( date( 'H', $os ), date( 'i', $os ), date( 's', $os ), date( 'm', $os ), date( 'd', $os ), date( 'Y', $os ) ) - ( $offset ) ) ) . "Z" : date( "Ymd\THi00", ( mktime( date( 'H', $os ), date( 'i', $os ), date( 's', $os ), date( 'm', $os ), date( 'd', $os ), date( 'Y', $os ) ) ) );

	return $time;
}

function mc_runtime( $start, $end, $event ) {
	if ( $event->event_hide_end || $start == $end ) {
		return '';
	} else {
		return human_time_diff( $start, $end );
	}
}

function mc_event_link( $event ) {
	if ( $event->event_link_expires == 0 ) {
		$link = esc_url( $event->event_link );
	} else {
		if ( my_calendar_date_xcomp( $event->occur_end, date( 'Y-m-d', current_time( 'timestamp' ) ) ) ) {
			$link = '';
			do_action( 'mc_event_expired', $event );
		} else {
			$link = esc_url( $event->event_link );
		}
	}

	return $link;
}

function mc_event_open( $event ) {
	if ( $event->event_open == '1' ) {
		$event_open = get_option( 'mc_event_open' );
	} else if ( $event->event_open == '0' ) {
		$event_open = get_option( 'mc_event_closed' );
	} else {
		$event_open = '';
	}

	return apply_filters( 'mc_event_open_text', $event_open, $event );
}

function mc_generate_map( $event, $source = 'event' ) {
	$id            = rand();
	$zoom          = ( $event->event_zoom != 0 ) ? $event->event_zoom : '15';
	$category_icon = mc_category_icon( $event, 'img' );
	if ( ! $category_icon ) {
		$category_icon = "//maps.google.com/mapfiles/marker_green.png";
	}
	$address = addslashes( mc_map_string( $event, $source ) );
	if ( strlen( $address ) < 10 ) {
		return '';
	}
	$hcard  = mc_hcard( $event, true, false, 'event', 'map' );
	$hcard  = wp_kses( str_replace( array(
				'</div>',
				'<br />',
				'<br><br>'
			), '<br>', $hcard ), array( 'br' => array() ) );
	$html   = addslashes( apply_filters( 'mc_map_html', $hcard, $event ) );
	$width  = apply_filters( 'mc_map_height', '100%', $event );
	$height = apply_filters( 'mc_map_height', '300px', $event );
	$styles = " style='width: $width;height: $height'";
	$value  = "
<script type='text/javascript'>
	(function ($) { 'use strict';
		$(function () {
			$('#mc_gmap_$id').gmap3(
				{
					marker:{ 
						values:[{
							address: '$address',
							options: { icon: new google.maps.MarkerImage( '$category_icon', new google.maps.Size(32,32,'px','px') ) }, 
							data:'$html'
							}], 
						events:{
						  click: function( marker, event, context ){
							var map = $(this).gmap3('get'),
							  infowindow = $(this).gmap3( { get:{name:'infowindow'} } );
							if ( infowindow ){
							  infowindow.open(map, marker);
							  infowindow.setContent(context.data);
							} else {
							  $(this).gmap3({
								infowindow:{
								  anchor:marker, 
								  options:{content: context.data}
								}
							  });
							}
						  }
						}
					},
					map:{
						options:{
						  zoom: $zoom,
						  mapTypeControl: true,
						  mapTypeControlOptions: {
							style: google.maps.MapTypeControlStyle.DROPDOWN_MENU
						  },
						  navigationControl: true,
						  scrollwheel: true,
						  streetViewControl: false
						}
					}	
			});	
		}); 
	})(jQuery);
</script>
	<div id='mc_gmap_$id' class='mc-gmap-fupup'$styles></div>";

	return apply_filters( 'mc_gmap_html', $value, $event );
}

function mc_expand( $data ) {
	$output = '';
	if ( is_array( $data ) ) {
		if ( isset( $data['notes'] ) ) {
			unset( $data['notes'] );
		}
		foreach ( $data as $key => $value ) {
			$class = ( isset( $value ) ) ? sanitize_title( $value ) : '';
			$label = ( isset( $value ) ) ? $value : false;
			if ( ! $label ) {
				continue;
			}
			$output .= "<li class='$class'><span>$label</span></li>\n";
		}
		$output = "<ul class='mc-access'>" . $output . "</ul>";
	}

	return $output;
}

function mc_event_date_span( $group_id, $event_span, $dates = array() ) {
	global $wpdb;
	$mcdb = $wpdb;
	// cache as transient to save db queries.
	if ( get_transient( 'mc_event_date_span_' . $group_id . '_' . $event_span ) ) {
		return get_transient( 'mc_event_date_span_' . $group_id . '_' . $event_span );
	}
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	$group_id = (int) $group_id;
	if ( $group_id == 0 && $event_span != 1 ) {
		return $dates;
	} else {
		$sql   = "SELECT occur_begin, occur_end FROM " . my_calendar_event_table() . " WHERE occur_group_id = $group_id ORDER BY occur_begin ASC";
		$dates = $mcdb->get_results( $sql );
		set_transient( 'mc_event_date_span_' . $group_id . '_' . $event_span, $dates, HOUR_IN_SECONDS );

		return $dates;
	}
}

function mc_format_date_span( $dates, $display = 'simple', $default = '' ) {
	if ( ! $dates ) {
		return $default;
	}
	$count = count( $dates );
	$last  = $count - 1;
	if ( $display == 'simple' ) {
		$begin  = $dates[0]->occur_begin;
		$end    = $dates[ $last ]->occur_end;
		$begin  = date_i18n( apply_filters( 'mc_date_format', get_option( 'mc_date_format' ), 'date_span_begin' ), strtotime( $begin ) );
		$end    = date_i18n( apply_filters( 'mc_date_format', get_option( 'mc_date_format' ), 'date_span_end' ), strtotime( $end ) );
		$return = $begin . ' <span>&ndash;</span> ' . $end;
	} else {
		$return = "<ul class='multidate'>";
		foreach ( $dates as $date ) {
			$begin         = $date->occur_begin;
			$end           = $date->occur_end;
			$day_begin     = date( 'Y-m-d', strtotime( $begin ) );
			$day_end       = date( 'Y-m-d', strtotime( $end ) );
			$bformat       = "<span class='multidate-date'>" . date_i18n( get_option( 'mc_date_format' ), strtotime( $begin ) ) . '</span> <span class="multidate-time">' . date_i18n( get_option( 'mc_time_format' ), strtotime( $begin ) ) . "</span>";
			$endtimeformat = ( $date->occur_end == '00:00:00' ) ? '' : ' ' . get_option( 'mc_time_format' );
			$eformat       = ( $day_begin != $day_end ) ? get_option( 'mc_date_format' ) . $endtimeformat : $endtimeformat;
			$span          = ( $eformat != '' ) ? " <span>&ndash;</span> <span class='multidate-end'>" : '';
			$endspan       = ( $eformat != '' ) ? "</span>" : '';
			$return .= "<li>$bformat" . $span . date_i18n( $eformat, strtotime( $end ) ) . "$endspan</li>";
		}
		$return .= "</ul>";
	}

	return $return;
}

add_filter( 'mc_insert_author_data', 'mc_author_data', 10, 2 );
function mc_author_data( $e, $event ) {
	if ( $event->event_author != 0 ) {
		$author = get_userdata( $event->event_author );
		$host   = get_userdata( $event->event_host );
		if ( $author ) {
			$e['author']       = $author->display_name;
			$e['gravatar']     = get_avatar( $author->user_email );
			$e['author_email'] = $author->user_email;
			$e['author_id']    = $event->event_author;
		}
		if ( $host ) {
			$e['host']          = ( ! $host || $host->display_name == '' ) ? $author->display_name : $host->display_name;
			$e['host_id']       = $event->event_host;
			$e['host_email']    = ( ! $host || $host->user_email == '' ) ? $author->user_email : $host->user_email;
			$e['host_gravatar'] = ( ! $host || $host->user_email == '' ) ? $e['gravatar'] : get_avatar( $host->user_email );
		}
	} else {
		$e['author']        = 'Public Submitter';
		$e['host']          = 'Public Submitter';
		$e['host_email']    = '';
		$e['author_email']  = '';
		$e['gravatar']      = '';
		$e['host_gravatar'] = '';
		$e['author_id']     = false;
		$e['host_id']       = false;
	}

	return $e;
}

add_filter( 'mc_filter_shortcodes', 'mc_auto_excerpt', 10, 2 );
function mc_auto_excerpt( $e, $event ) {
	$description  = $e['description'];
	$shortdesc    = $e['shortdesc'];
	$e['excerpt'] = $shortdesc;
	if ( $description != '' ) { // if description is empty, this won't work, so skip it.
		$num_words    = apply_filters( 'mc_excerpt_length', 55 );
		$excerpt      = wp_trim_words( $description, $num_words );
		$e['excerpt'] = ( $shortdesc == '' ) ? $excerpt : $shortdesc;
	}

	return $e;
}

add_filter( 'mc_filter_image_data', 'mc_image_data', 10, 2 );
function mc_image_data( $e, $event ) {
	$atts      = apply_filters( 'mc_post_thumbnail_atts', array( 'class' => 'mc-image' ) );
	$e['full'] = get_the_post_thumbnail( $event->event_post );
	$sizes     = get_intermediate_image_sizes();
	foreach ( $sizes as $size ) {
		$e[ $size ] = get_the_post_thumbnail( $event->event_post, $size, $atts );
	}
	if ( is_numeric( $event->event_post ) && ( isset( $e['medium'] ) && $e['medium'] != '' ) ) {
		$e['image_url'] = strip_tags( $e['medium'] );
		$e['image']     = $e['medium'];
	} else {
		$e['image_url'] = ( $event->event_image != '' ) ? $event->event_image : '';
		$e['image']     = ( $event->event_image != '' ) ? "<img src='$event->event_image' alt='' class='mc-image' />" : '';
	}

	return $e;
}

function mc_event_recur_string( $event ) {
	$recurs      = str_split( $event->event_recur, 1 );
	$recur       = $recurs[0];
	$month_date  = date( 'dS', strtotime( $event->occur_begin ) );
	$day_name    = date_i18n( 'l', strtotime( $event->occur_begin ) );
	$week_number = mc_ordinal( week_of_month( date( 'j', strtotime( $event->occur_begin ) ) ) + 1 );
	switch ( $recur ) {
		case 'S':
			$event_recur = __( 'Does not recur', 'my-calendar' );
			break;
		case 'D':
			$event_recur = __( 'Daily', 'my-calendar' );
			break;
		case 'E':
			$event_recur = __( 'Daily, weekdays only', 'my-calendar' );
			break;
		case 'W':
			$event_recur = __( 'Weekly', 'my-calendar' );
			break;
		case 'B':
			$event_recur = __( 'Bi-weekly', 'my-calendar' );
			break;
		case 'M':
			$event_recur = sprintf( __( 'the %s of each month', 'my-calendar' ), $month_date );
			break;
		case 'U':
			$event_recur = sprintf( __( 'the %s %s of each month', 'my-calendar' ), $week_number, $day_name );
			break;
		case 'Y':
			$event_recur = __( 'Annually', 'my-calendar' );
			break;
		default:
			$event_recur = '';
	}

	return apply_filters( 'mc_event_recur_string', $event_recur, $event );
}