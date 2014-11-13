<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

function mc_holiday_limit( $events, $holidays ) {
	foreach ( array_keys( $events ) as $key ) {
		if ( ! empty( $holidays[ $key ] ) ) {
			foreach ( $events[ $key ] as $k => $event ) {
				if ( $event->event_category != get_option( 'mc_skip_holidays_category' ) && $event->event_holiday == 1 ) {
					unset( $events[ $key ][ $k ] );
				}
			}
		}
	}

	return $events;
}

// Used to draw multiple events
function mc_set_date_array( $events ) {
	$event_array = array();
	if ( is_array( $events ) ) {
		foreach ( $events as $event ) {
			$date = date( 'Y-m-d', strtotime( $event->occur_begin ) );
			$end  = date( 'Y-m-d', strtotime( $event->occur_end ) );
			if ( $date != $end ) {
				$start = strtotime( $date );
				$end   = strtotime( $end );
				do {
					$date                   = date( 'Y-m-d', $start );
					$event_array[ $date ][] = $event;
					$start                  = strtotime( "+1 day", $start );
				} while ( $start <= $end );
			} else {
				$event_array[ $date ][] = $event;
			}
		}
	}

	return $event_array;
}

function my_calendar_draw_events( $events, $type, $process_date, $time, $template = '' ) {
	apply_filters( 'debug', "my_calendar( $type ) begin draw events" );

	if ( $type == 'mini' && ( get_option( 'mc_open_day_uri' ) == 'true' || get_option( 'mc_open_day_uri' ) == 'listanchor' || get_option( 'mc_open_day_uri' ) == 'calendaranchor' ) ) {
		return true;
	}
	// We need to sort arrays of objects by time
	if ( is_array( $events ) ) {
		$output_array = array();
		$begin        = $event_output = $end = '';
		if ( $type == "mini" && count( $events ) > 0 ) {
			$begin .= "<div id='date-$process_date' class='calendar-events'>";
		}
		foreach ( array_keys( $events ) as $key ) {
			$event          =& $events[ $key ];
			$output_array[] = my_calendar_draw_event( $event, $type, $process_date, $time, $template );
		}
		if ( is_array( $output_array ) ) {
			foreach ( array_keys( $output_array ) as $key ) {
				$value =& $output_array[ $key ];
				$event_output .= $value;
			}
		}
		if ( $event_output == '' ) {
			return '';
		}
		if ( $type == "mini" && count( $events ) > 0 ) {
			$end .= "</div>";
		}

		return $begin . $event_output . $end;
	}
	apply_filters( "debug", "my_calendar( $type ) end draw events" );

	return '';
}

function mc_get_template( $template ) {
	$templates = get_option( 'mc_templates' );
	$template  = $templates[ $template ];

	return $template;
}

function mc_time_html( $event, $type, $current_date ) {
	$id_start = date( 'Y-m-d', strtotime( $event->event_begin ) );
	$id_end   = date( 'Y-m-d', strtotime( $event->event_end ) );
	$tz       = mc_user_timezone();
	$cur_date = ( $type == 'list' ) ? '' : "<span class='mc-event-date'>$current_date</span>";

	$time = "<div class='time-block'>";
	$time .= "<p>$cur_date";
	if ( $event->event_time != "00:00:00" && $event->event_time != '' ) {
		$time .= "\n<span class='event-time dtstart'><time datetime='" . $id_start . 'T' . $event->event_time . "'>" . date_i18n( get_option( 'mc_time_format' ), strtotime( $event->event_time ) ) . '</time>';
		if ( $event->event_hide_end == 0 ) {
			if ( $event->event_endtime != '' && $event->event_endtime != $event->event_time ) {
				$time .= "<span class='time-separator'> &ndash; </span><time class='end-time dtend' datetime='" . $id_end . 'T' . $event->event_endtime . "'>" . date_i18n( get_option( 'mc_time_format' ), strtotime( $event->event_endtime ) ) . "</time>";
			}
		}
		if ( $tz != '' ) {
			$local_begin = date_i18n( get_option( 'mc_time_format' ), strtotime( $event->event_time . "+$tz hours" ) );
			$time .= "<hr /><small class='local-time'>" . sprintf( __( '(%s in your time zone)', 'my-calendar' ), $local_begin ) . "</small>";
		}
		$time .= "</span>\n";
	} else {
		$time .= "<span class='event-time'>";
		if ( get_option( 'mc_notime_text' ) == '' || get_option( 'mc_notime_text' ) == "N/A" ) {
			$time .= "<abbr title='" . __( 'Not Applicable', 'my-calendar' ) . "'>" . __( 'N/A', 'my-calendar' ) . "</abbr>\n";
		} else {
			$time .= get_option( 'mc_notime_text' );
		}
		$time .= "</span></p>";
	}
	$time .= apply_filters( 'mcs_end_time_block', '', $event );
	$time .= "
	</div>";

	return $time;
}

function mc_category_icon( $event, $html = 'html' ) {
	$url   = plugin_dir_url( __FILE__ );
	$image = '';
	if ( get_option( 'mc_hide_icons' ) != 'true' ) {
		if ( $event->category_icon != '' ) {
			$path = ( is_custom_icon() ) ? str_replace( 'my-calendar', 'my-calendar-custom', $url ) : plugins_url( 'images/icons', __FILE__ ) . '/';
			$hex  = ( strpos( $event->category_color, '#' ) !== 0 ) ? '#' : '';
			if ( $html == 'html' ) {
				$image = '<img src="' . $path . $event->category_icon . '" alt="' . __( 'Category', 'my-calendar' ) . ': ' . esc_attr( $event->category_name ) . '" class="category-icon" style="background:' . $hex . $event->category_color . '" />';
			} else {
				$image = $path . $event->category_icon;
			}
		}
	}

	return $image;
}

add_filter( 'the_title', 'mc_category_icon_title' );
function mc_category_icon_title( $title ) {
	if ( is_singular( 'mc-events' ) && in_the_loop() ) {
		$event_id = get_post_meta( get_the_ID(), '_mc_event_id', true );
		$event    = mc_get_event_core( $event_id );
		$icon     = mc_category_icon( $event );
		$title    = $icon . ' ' . $title;
	}

	return $title;
}

// Used to draw an event to the screen
function my_calendar_draw_event( $event, $type = "calendar", $process_date, $time, $template = '' ) {
	// if event is not approved, return without processing
	if ( get_option( 'mc_event_approve' ) == 'true' && (int) $event->event_approved !== 1 ) {
		return '';
	}
	// if event ends at midnight today (e.g., very first thing of the day), exit without re-drawing
	if ( $event->event_endtime == '00:00:00' && date( 'Y-m-d', strtotime( $event->occur_end ) ) == $process_date && date( 'Y-m-d', strtotime( $event->occur_begin ) ) != $process_date ) {
		return '';
	}
	if ( $event->category_private == 1 && ! is_user_logged_in() ) {
		return '';
	}
	// assign empty values to template sections
	$header      = $address = $more = $author = $list_title = $title = $output = $container = $short = $description = $link = $vcal = $gcal = '';
	$date_format = ( get_option( 'mc_date_format' ) != '' ) ? get_option( 'mc_date_format' ) : get_option( 'date_format' );
	$data        = mc_create_tags( $event );
	$templates   = get_option( 'mc_templates' );
	$details     = '';
	if ( mc_show_details( $time, $type ) ) {
		$details = apply_filters( 'mc_custom_template', false, $data, $event, $type, $process_date, $time, $template );
		if ( $details === false ) {
			if ( $template != '' && mc_file_exists( sanitize_file_name( $template ) ) ) {
				$template = @file_get_contents( mc_get_file( sanitize_file_name( $template ) ) );
				$details  = jd_draw_template( $data, $template );
			} else {
				switch ( $type ) {
					case 'mini':
						$template = $templates['mini'];
						if ( get_option( 'mc_use_mini_template' ) == 1 ) {
							$details = jd_draw_template( $data, $template );
						}
						break;
					case 'list':
						$template = $templates['list'];
						if ( get_option( 'mc_use_list_template' ) == 1 ) {
							$details = jd_draw_template( $data, $template );
						}
						break;
					case 'single':
						$template = $templates['details'];
						if ( get_option( 'mc_use_details_template' ) == 1 ) {
							$details = jd_draw_template( $data, $template );
						}
						break;
					case 'calendar':
					default:
						$template = $templates['grid'];
						if ( get_option( 'mc_use_grid_template' ) == 1 ) {
							$details = jd_draw_template( $data, $template );
						}
				}
			}
		}
	}
	$mc_display_author = get_option( 'mc_display_author' );
	$display_map       = get_option( 'mc_show_map' );
	$display_address   = get_option( 'mc_show_address' );
	$uid               = 'mc_' . $event->occur_id;
	$day_id            = date( 'd', strtotime( $process_date ) );

	$image = mc_category_icon( $event );
	$header .= "<div id='$uid-$day_id-$type' class='$type-event " . "mc_" . sanitize_title( $event->category_name ) . " vevent'>\n";

	$title_template = ( $templates['title'] == '' ) ? '{title}' : $templates['title'];
	$event_title    = jd_draw_template( $data, $title_template );
	$event_title    = ( $event_title == '' ) ? jd_draw_template( $data, '{title}' ) : $event_title; //prevent empty titles

	if ( strpos( $event_title, 'http' ) === false && $type != 'mini' && $type != 'list' ) {
		if ( get_option( 'mc_open_uri' ) == 'true' ) {
			$details_link = mc_get_details_link( $event );
			$wrap         = "<a href='$details_link'>";
			$balance      = "</a>";
		} else {
			$wrap    = "<a href='#$uid-$day_id-$type-details'>";
			$balance = "</a>";
		}
	} else {
		$wrap = $balance = '';
	}
	$current_date  = date_i18n( apply_filters( 'mc_date_format', $date_format, 'details' ), strtotime( $process_date ) );
	$group_class   = ( $event->event_span == 1 ) ? ' multidate group' . $event->event_group_id : '';
	$heading_level = apply_filters( 'mc_heading_level_table', 'h3', $type, $time, $template );
	$header .= ( $type != 'single' && $type != 'list' ) ? "<$heading_level class='event-title summary$group_class' id='$uid-$day_id-$type-title'>$wrap$image$event_title$balance</$heading_level>\n" : '';
	$event_title = ( $type == 'single' ) ? apply_filters( 'mc_single_event_title', $event_title, $event ) : $event_title;
	$title       = ( $type == 'single' && ! is_singular( 'mc-events' ) ) ? "<h2 class='event-title summary'>$image $event_title</h2>\n" : '';
	$title       = apply_filters( 'mc_event_title', $title, $event, $event_title, $image );
	$header .= $title;

	if ( mc_show_details( $time, $type ) ) {

		if ( $details === false ) {
			// put together address information as vcard
			if ( ( $display_address == 'true' || $display_map == 'true' ) ) {
				$address = mc_hcard( $event, $display_address, $display_map );
			}
			// end vcard
			$close = ( $type == 'calendar' || $type == 'mini' ) ? "<a href=\"#$uid-$day_id-$type\" class='mc-toggle mc-close close'><img src=\"" . plugin_dir_url( __FILE__ ) . "images/event-close.png\" alt='" . __( 'Close', 'my-calendar' ) . "' /></a>" : '';
			$time  = mc_time_html( $event, $type, $current_date );
			if ( $type == "list" ) {
				$heading_level = apply_filters( 'mc_heading_level_list', 'h3', $type, $time, $template );
				$list_title    = "<$heading_level class='event-title summary' id='$uid-$day_id-$type-title'>$image" . $event_title . "</$heading_level>\n";
			}
			if ( $mc_display_author == 'true' ) {
				if ( $event->event_author != 0 ) {
					$e      = get_userdata( $event->event_author );
					$author = '<p class="event-author">' . __( 'Posted by', 'my-calendar' ) . ' <span class="author-name">' . $e->display_name . "</span></p>\n";
				}
			}

			if ( ! isset( $_GET['mc_id'] ) ) {
				$details_label = mc_get_details_label( $event, $data );
				$details_link  = mc_get_details_link( $event );
				if ( _mc_is_url( $details_link ) ) {
					$more = "<p class='mc_details'><a href='$details_link'>$details_label</a></p>\n";
				} else {
					$more = '';
				}
			}
			// handle link expiration
			$event_link = mc_event_link( $event );

			if ( function_exists( 'mc_google_cal' ) && get_option( 'mc_show_gcal' ) == 'true' ) {
				$gcal_link = "<p class='gcal'>" . jd_draw_template( $data, '{gcal_link}' ) . "</p>";
				$gcal      = $gcal_link;
			}

			if ( function_exists( 'my_calendar_generate_vcal' ) && get_option( 'mc_show_event_vcal' ) == 'true' ) {
				$url       = add_query_arg( 'vcal', $uid, home_url() );
				$vcal_link = "<p class='ical'><a rel='nofollow' href='$url'>" . __( 'iCal', 'my-calendar' ) . "</a></p>\n";
				$vcal      = $vcal_link;
			}
			$default_size = apply_filters( 'mc_default_image_size', 'medium' );
			if ( is_numeric( $event->event_post ) && $event->event_post != 0 && ( isset( $data[ $default_size ] ) && $data[ $default_size ] != '' ) ) {
				$atts  = apply_filters( 'mc_post_thumbnail_atts', array( 'class' => 'mc-image' ) );
				$image = get_the_post_thumbnail( $event->event_post, $default_size, $atts );
			} else {
				$image = ( $event->event_image != '' ) ? "<img src='$event->event_image' alt='' class='mc-image' />" : '';
			}
			if ( get_option( 'mc_desc' ) == 'true' || $type == 'single' ) {
				$description = ( get_option( 'mc_process_shortcodes' ) == 'true' ) ? apply_filters( 'the_content', stripcslashes( $event->event_desc ) ) : wpautop( stripcslashes( $event->event_desc ), 1 );
				$description = "<div class='longdesc'>$description</div>";
			}
			if ( get_option( 'mc_short' ) == 'true' && $type != 'single' ) {
				$short = ( get_option( 'mc_process_shortcodes' ) == 'true' ) ? apply_filters( 'the_content', stripcslashes( $event->event_short ) ) : wpautop( stripcslashes( $event->event_short ), 1 );
				$short = "<div class='shortdesc'>$short</div>";
			}

			if ( get_option( 'mc_event_registration' ) == 'true' ) {
				switch ( $event->event_open ) {
					case '0':
						$status = get_option( 'mc_event_closed' );
						break;
					case '1':
						$status = get_option( 'mc_event_open' );
						break;
					case '2':
						$status = '';
						break;
					default:
						$status = '';
				}
			} else {
				$status = '';
			}

			$status = ( $status != '' ) ? "<p>$status</p>" : '';
			$status = apply_filters( 'mc_registration_state', $status, $event );
			$return = ( $type == 'single' ) ? "<p><a href='" . get_option( 'mc_uri' ) . "'>" . __( 'View full calendar', 'my-calendar' ) . "</a></p>" : '';

			if ( ! mc_show_details( $time, $type ) ) {
				$description = $short = $status = '';
			}

			if ( get_option( 'mc_gmap' ) == 'true' ) {
				$map = ( is_singular( 'mc-event' ) || $type == 'single' ) ? mc_generate_map( $event ) : '';
			} else {
				$map = '';
			}

			if ( $event_link != '' && get_option( 'mc_event_link' ) != 'false' ) {
				$is_external    = mc_external_link( $event_link );
				$external_class = ( $is_external ) ? "class='$type-link external'" : "class='$type-link'";
				$link_template  = ( isset( $templates['link'] ) ) ? $templates['link'] : '{title}';
				$link_text      = jd_draw_template( $data, $link_template );
				$link           = "<p><a href='$event_link' $external_class>" . $link_text . "</a></p>";
			}
			$details = "\n"
			           . $close
			           . $time
			           . $list_title
			           . $image
			           . "<div class='location'>"
			           . $map
			           . $address
			           . "</div>"
			           . $description
			           . $short
			           . $link
			           . $status
			           . $author
			           . "<div class='sharing'>"
			           . $vcal
			           . $gcal
			           . $more
			           . "</div>"
			           . $return;
		} else {
			// if a custom template is in use
			$toggle  = ( $type == 'calendar' || $type == 'mini' ) ? "<a href=\"#$uid-$day_id-$type\" class='mc-toggle mc-close close'><img src=\"" . plugin_dir_url( __FILE__ ) . "images/event-close.png\" alt='" . __( 'Close', 'my-calendar' ) . "' /></a>" : '';
			$details = $toggle . $details . "\n";
		}
		$container = "<div id='$uid-$day_id-$type-details' class='details' role='dialog' aria-labelledby='$uid-$day_id-$type-title'>\n";
		$container = apply_filters( 'mc_before_event', $container, $event, $type, $time );
		$details   = $header . $container . $details;
		$details .= apply_filters( 'mc_after_event', '', $event, $type, $time );
		$details .= $close; // second close button
		$details .= "</div><!--ends .details--></div>";
		$details = apply_filters( 'mc_event_content', $details, $event, $type, $time );
	} else {
		$details = $header . "</div>";
	}

	return $details;
}

function mc_show_details( $time, $type ) {
	return ( $type == 'calendar' && get_option( 'mc_open_uri' ) == 'true' && $time != 'day' ) ? false : true;
}

add_filter( 'mc_after_event', 'mc_edit_panel', 10, 4 );
function mc_edit_panel( $html, $event, $type, $time ) {
	// create edit links
	$edit = '';
	if ( mc_can_edit_event( $event->event_author ) && get_option( 'mc_remote' ) != 'true' ) {
		$mc_id     = $event->occur_id;
		$groupedit = ( $event->event_group_id != 0 ) ? " &bull; <a href='" . admin_url( "admin.php?page=my-calendar-groups&amp;mode=edit&amp;event_id=$event->event_id&amp;group_id=$event->event_group_id" ) . "' class='group'>" . __( 'Edit Group', 'my-calendar' ) . "</a>\n" : '';
		$recurs    = str_split( $event->event_recur, 1 );
		$recur     = $recurs[0];
		$referer   = urlencode( mc_get_current_url() );
		$edit      = "<div class='mc_edit_links'><p>";
		if ( $recur == 'S' ) {
			$edit .= "<a href='" . admin_url( "admin.php?page=my-calendar&amp;mode=edit&amp;event_id=$event->event_id&amp;ref=$referer" ) . "' class='edit'>" . __( 'Edit', 'my-calendar' ) . "</a> &bull; <a href='" . admin_url( "admin.php?page=my-calendar-manage&amp;mode=delete&amp;event_id=$event->event_id&amp;ref=$referer" ) . "' class='delete'>" . __( 'Delete', 'my-calendar' ) . "</a>$groupedit";
		} else {
			$edit .= "<a href='" . admin_url( "admin.php?page=my-calendar&amp;mode=edit&amp;event_id=$event->event_id&amp;date=$mc_id&amp;ref=$referer" ) . "' class='edit'>" . __( 'Edit This Date', 'my-calendar' ) . "</a> &bull; <a href='" . admin_url( "admin.php?page=my-calendar&amp;mode=edit&amp;event_id=$event->event_id&amp;ref=$referer" ) . "' class='edit'>" . __( 'Edit All', 'my-calendar' ) . "</a> &bull; <a href='" . admin_url( "admin.php?page=my-calendar-manage&amp;mode=delete&amp;event_id=$event->event_id&amp;date=$mc_id&amp;ref=$referer" ) . "' class='delete'>" . __( 'Delete This Date', 'my-calendar' ) . "</a> &bull; <a href='" . admin_url( "admin.php?page=my-calendar-manage&amp;mode=delete&amp;event_id=$event->event_id&amp;ref=$referer" ) . "' class='delete'>" . __( 'Delete All', 'my-calendar' ) . "</a>
			$groupedit";
		}
		$edit .= "</p></div>";
	}
	if ( ! mc_show_details( $time, $type ) ) {
		$edit = '';
	}

	return $html . $edit;
}

function mc_build_date_switcher( $type = 'calendar', $cid = 'all' ) {
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	$current_url   = mc_get_current_url();
	$date_switcher = "";
	$date_switcher .= '<div class="my-calendar-date-switcher">
            <form action="' . $current_url . '" method="get"><div>';
	$qsa = array();
	parse_str( $_SERVER['QUERY_STRING'], $qsa );
	if ( ! isset( $_GET['cid'] ) ) {
		$date_switcher .= '<input type="hidden" name="cid" value="' . $cid . '" />';
	}
	foreach ( $qsa as $name => $argument ) {
		$name     = esc_attr( strip_tags( $name ) );
		$argument = esc_attr( strip_tags( $argument ) );
		if ( $name != 'month' && $name != 'yr' && $name != 'dy' ) {
			$date_switcher .= '<input type="hidden" name="' . $name . '" value="' . $argument . '" />';
		}
	}
	// We build the months in the switcher
	$date_switcher .= '
            <label for="mc-' . $type . '-month">' . __( 'Month', 'my-calendar' ) . ':</label> <select id="mc-' . $type . '-month" name="month">' . "\n";
	for ( $i = 1; $i <= 12; $i ++ ) {
		$date_switcher .= "<option value='$i'" . mc_month_comparison( $i ) . '>' . date_i18n( 'F', mktime( 0, 0, 0, $i, 1 ) ) . '</option>' . "\n";
	}
	$date_switcher .= '</select>' . "\n" . '
            <label for="mc-' . $type . '-year">' . __( 'Year', 'my-calendar' ) . ':</label> <select id="mc-' . $type . '-year" name="yr">' . "\n";
	// query to identify oldest start date in the database
	$query  = "SELECT event_begin FROM " . MY_CALENDAR_TABLE . " WHERE event_approved = 1 AND event_flagged <> 1 ORDER BY event_begin ASC LIMIT 0 , 1";
	$year1  = date( 'Y', strtotime( $mcdb->get_var( $query ) ) );
	$diff1  = date( 'Y' ) - $year1;
	$past   = $diff1;
	$future = apply_filters( 'mc_jumpbox_future_years', 5, $cid );
	$fut    = 1;
	$f      = '';
	$p      = '';
	$offset = ( 60 * 60 * get_option( 'gmt_offset' ) );
	while ( $past > 0 ) {
		$p .= '<option value="';
		$p .= date( "Y", time() + ( $offset ) ) - $past;
		$p .= '"' . mc_year_comparison( date( "Y", time() + ( $offset ) ) - $past ) . '>';
		$p .= date( "Y", time() + ( $offset ) ) - $past . "</option>\n";
		$past = $past - 1;
	}
	while ( $fut < $future ) {
		$f .= '<option value="';
		$f .= date( "Y", time() + ( $offset ) ) + $fut;
		$f .= '"' . mc_year_comparison( date( "Y", time() + ( $offset ) ) + $fut ) . '>';
		$f .= date( "Y", time() + ( $offset ) ) + $fut . "</option>\n";
		$fut = $fut + 1;
	}
	$date_switcher .= $p;
	$date_switcher .= '<option value="' . date( "Y", time() + ( $offset ) ) . '"' . mc_year_comparison( date( "Y", time() + ( $offset ) ) ) . '>' . date( "Y", time() + ( $offset ) ) . "</option>\n";
	$date_switcher .= $f;
	$date_switcher .= '</select> <input type="submit" class="button" value="' . __( 'Go', 'my-calendar' ) . '" /></div>
	</form></div>';
	$date_switcher = apply_filters( 'mc_jumpbox', $date_switcher );

	return $date_switcher;
}

function my_calendar_print() {
	$url      = plugin_dir_url( __FILE__ );
	$time     = ( isset( $_GET['time'] ) ) ? $_GET['time'] : 'month';
	$category = ( isset( $_GET['mcat'] ) ) ? $_GET['mcat'] : ''; // these are sanitized elsewhere
	$ltype    = ( isset( $_GET['ltype'] ) ) ? $_GET['ltype'] : '';
	$lvalue   = ( isset( $_GET['lvalue'] ) ) ? $_GET['lvalue'] : '';
	header( 'Content-Type: ' . get_bloginfo( 'html_type' ) . '; charset=' . get_bloginfo( 'charset' ) );
	echo '<!DOCTYPE html>
<!--[if IE 7]>
<html id="ie7" dir="' . get_bloginfo( 'text_direction' ) . '" lang="' . get_bloginfo( 'language' ) . '">
<![endif]-->
<!--[if IE 8]>
<html id="ie8" dir="' . get_bloginfo( 'text_direction' ) . '" lang="' . get_bloginfo( 'language' ) . '">
<![endif]-->
<!--[if !(IE 6) | !(IE 7) | !(IE 8) ]><!-->
<html dir="' . get_bloginfo( 'text_direction' ) . '" lang="' . get_bloginfo( 'language' ) . '">
<!--<![endif]-->
<head>
<meta charset="' . get_bloginfo( 'charset' ) . '" />
<meta name="viewport" content="width=device-width" />
<title>' . get_bloginfo( 'name' ) . ' - ' . __( 'Calendar: Print View', 'my-calendar' ) . '</title>
<meta name="generator" content="My Calendar for WordPress" />
<meta name="robots" content="noindex,nofollow" />';
	if ( mc_file_exists( 'css/mc-print.css' ) ) {
		$stylesheet = mc_get_file( 'css/mc-print.css', 'url' );
	} else {
		$stylesheet = $url . "css/mc-print.css";
	}
	echo "
<!-- Copy mc-print.css to your theme directory if you wish to replace the default print styles -->
<link rel='stylesheet' href='$stylesheet' type='text/css' media='screen,print' />
</head>
<body>\n";
	echo my_calendar( 'print', 'calendar', $category, $time, $ltype, $lvalue, 'mc-print-view', '', '', null, null, '', '' );
	$return_url = ( get_option( 'mc_uri' ) != '' && ! is_numeric( get_option( 'mc_uri' ) ) ) ? get_option( 'mc_uri' ) : home_url();
	$add        = $_GET;
	unset( $add['cid'] );
	unset( $add['feed'] );
	$return_url = mc_build_url( $add, array( 'feed', 'cid' ), $return_url );
	echo "<p class='return'><a href='$return_url'>" . __( 'Return to site', 'my-calendar' ) . "</a></p>";
	echo '
</body>
</html>';
}

function mc_format_toggle( $format, $toggle ) {
	if ( $format != 'mini' && $toggle == 'yes' ) {
		$toggle = "<div class='mc-format'>";
		switch ( $format ) {
			case 'list':
				$url = mc_build_url( array( 'format' => 'calendar' ), array() );
				$toggle .= "<a href='$url'>" . __( 'View as Grid', 'my-calendar' ) . "</a>";
				break;
			default:
				$url = mc_build_url( array( 'format' => 'list' ), array() );
				$toggle .= "<a href='$url'>" . __( 'View as List', 'my-calendar' ) . "</a>";
				break;
		}
		$toggle .= "</div>";
	} else {
		$toggle = '';
	}

	return $toggle;
}

function mc_time_toggle( $format, $time, $toggle, $day, $month, $year ) {
	if ( $format != 'mini' && $toggle == 'yes' ) {
		$toggle      = "<div class='mc-time'>";
		$current_url = mc_get_current_url();
		switch ( $time ) {
			case 'week':
				$url = mc_build_url( array( 'time' => 'month' ), array( 'mc_id' ) );
				$toggle .= "<a href='$url'>" . __( 'Month', 'my-calendar' ) . "</a> ";
				$toggle .= "<span class='mc-active'>" . __( 'Week', 'my-calendar' ) . "</span>";
				$url = mc_build_url( array( 'time' => 'day', 'dy' => $day ), array( 'dy', 'mc_id' ) );
				$toggle .= " <a href='$url'>" . __( 'Day', 'my-calendar' ) . "</a>";
				break;
			case 'day':
				$url = mc_build_url( array( 'time' => 'month' ), array() );
				$toggle .= "<a href='$url'>" . __( 'Month', 'my-calendar' ) . "</a>";
				$url = mc_build_url( array(
						'time'  => 'week',
						'dy'    => $day,
						'month' => $month,
						'yr'    => $year
					), array( 'dy', 'month', 'mc_id' ) );
				$toggle .= " <a href='$url'>" . __( 'Week', 'my-calendar' ) . "</a> ";
				$toggle .= "<span class='mc-active'>" . __( 'Day', 'my-calendar' ) . "</span>";
				break;
			default:
				$toggle .= "<span class='mc-active'>" . __( 'Month', 'my-calendar' ) . "</span>";
				$url = mc_build_url( array( 'time' => 'week', 'dy' => $day, 'month' => $month ), array(
						'dy',
						'month',
						'mc_id'
					) );
				$toggle .= " <a href='$url'>" . __( 'Week', 'my-calendar' ) . "</a> ";
				$url = mc_build_url( array( 'time' => 'day' ), array() );
				$toggle .= "<a href='$url'>" . __( 'Day', 'my-calendar' ) . "</a>";
				break;
		}
		$toggle .= "</div>";
	} else {
		$toggle = '';
	}

	return $toggle;
}

function mc_date_array( $timestamp, $period ) {
	switch ( $period ) {
		case "month":
		case "month+1":
			if ( $period == 'month+1' ) {
				$timestamp = strtotime( '+1 month', $timestamp );
			}
			$first   = date( 'N', $timestamp );
			$n       = ( get_option( 'start_of_week' ) == 1 ) ? $first - 1 : $first;
			$from    = date( 'Y-m-d', strtotime( "-$n days", $timestamp ) );
			$endtime = mktime( 0, 0, 0, date( 'm', $timestamp ), date( 't', $timestamp ), date( 'Y', $timestamp ) );
			//	$endtime = strtotime("+$months months",$endtime); // this allows multiple months displayed. Will figure out splitting tables...
			$last = date( 'N', $endtime );
			$n    = ( get_option( 'start_of_week' ) == 1 ) ? 7 - $last : 6 - $last;
			if ( $n == '-1' && date( 'N', $endtime ) == '7' ) {
				$n = 6;
			}
			$to = date( 'Y-m-d', strtotime( "+$n days", $endtime ) );

			return array( 'from' => $from, 'to' => $to );
			break;
		case "week":
			// first day of the week is calculated prior to this function. Argument received is the first day of the week.
			$from = date( 'Y-m-d', $timestamp );
			$to   = date( 'Y-m-d', strtotime( "+6 days", $timestamp ) );

			return array( 'from' => $from, 'to' => $to );
			break;
		default:
			return false;
	}
}

// argument: array of event objects
function mc_events_class( $events, $date = false ) {
	$class = $events_class = '';
	if ( ! is_array( $events ) || ! count( $events ) ) {
		$events_class = "no-events";
	} else {
		foreach ( array_keys( $events ) as $key ) {
			$event =& $events[ $key ];
			if ( $event->event_endtime == '00:00:00' && date( 'Y-m-d', strtotime( $event->occur_end ) ) == $date && date( 'Y-m-d', strtotime( $event->occur_begin ) ) != $date ) {
				continue;
			}
			$author = ' author' . $event->event_author;
			if ( strpos( $class, $author ) === false ) {
				$class .= $author;
			}
			$cat = ' mcat_' . sanitize_title( $event->category_name );
			if ( strpos( $class, $cat ) === false ) {
				$class .= $cat;
			}
		}
		if ( $class ) {
			$events_class = "has-events$class";
		}
	}

	return $events_class;
}

function mc_list_title( $events ) {
	usort( $events, 'my_calendar_time_cmp' );
	$now   = $events[0];
	$count = count( $events ) - 1;
	if ( $count == 0 ) {
		$cstate = '';
	} else if ( $count == 1 ) {
		$cstate = sprintf( __( " and %d other event", 'my-calendar' ), $count );
	} else {
		$cstate = sprintf( __( " and %d other events", 'my-calendar' ), $count );
	}
	$title = stripcslashes( $now->event_title ) . $cstate;

	return $title;
}

function mc_search_results( $query ) {
	$before = apply_filters( 'mc_past_search_results', 0 );
	$after  = apply_filters( 'mc_future_search_results', 10 ); // return only future events, nearest 10
	if ( is_string( $query ) ) {
		$select_category = $limit_string = $select_author = $select_host = '';
		$search          = " MATCH(event_title,event_desc,event_short,event_label,event_city,event_postcode,event_registration) AGAINST ('$query' IN BOOLEAN MODE) AND ";
	} else {
		/*
		extract( $query );
		$select_category = ( $category != 'default' ) ? mc_select_category( $category ) : '';
		$limit_string    = mc_limit_string();
		$select_author   = ( $author != 'default' ) ? mc_select_author( $author ) : '';
		$select_host     = ( $host != 'default' ) ? mc_select_host( $host ) : '';
		$prefix          = ( $select_category . $limit_string . $select_author . $select_host != '' ) ? ' AND' : '';
		$search          = "$prefix MATCH(event_title,event_desc,event_short,event_label,event_city,event_postcode,event_registration) AGAINST ('$query' IN BOOLEAN MODE) AND ";
		*/
	}
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}

	$date = date( 'Y', current_time( 'timestamp' ) ) . '-' . date( 'm', current_time( 'timestamp' ) ) . '-' . date( 'd', current_time( 'timestamp' ) );
	// if a value is non-zero, I'll grab a handful of extra events so I can throw out holidays and others like that.
	if ( $before > 0 ) {
		$before  = $before + 5;
		$events1 = $mcdb->get_results( "SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end 
		FROM " . MY_CALENDAR_EVENTS_TABLE . " 
		JOIN " . MY_CALENDAR_TABLE . " 
		ON (event_id=occur_event_id) 
		JOIN " . MY_CALENDAR_CATEGORIES_TABLE . " 
		ON (event_category=category_id) WHERE $select_category $select_author $select_host $limit_string $search event_approved = 1 AND event_flagged <> 1 
		AND DATE(occur_begin) < '$date' ORDER BY occur_begin DESC LIMIT 0,$before" );
	} else {
		$events1 = array();
	}
	$events3 = $mcdb->get_results( "SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end 
		FROM " . MY_CALENDAR_EVENTS_TABLE . " 
		JOIN " . MY_CALENDAR_TABLE . " 
		ON (event_id=occur_event_id) 
		JOIN " . MY_CALENDAR_CATEGORIES_TABLE . " 
		ON (event_category=category_id) WHERE $select_category $select_author $select_host $limit_string $search event_approved = 1 AND event_flagged <> 1 
		AND DATE(occur_begin) = '$date'" );
	if ( $after > 0 ) {
		$after   = $after + 5;
		$events2 = $mcdb->get_results( "SELECT *, UNIX_TIMESTAMP(occur_begin) AS ts_occur_begin, UNIX_TIMESTAMP(occur_end) AS ts_occur_end 
		FROM " . MY_CALENDAR_EVENTS_TABLE . " 
		JOIN " . MY_CALENDAR_TABLE . " 
		ON (event_id=occur_event_id) 
		JOIN " . MY_CALENDAR_CATEGORIES_TABLE . " 
		ON (event_category=category_id) WHERE $select_category $select_author $select_host $limit_string $search event_approved = 1 AND event_flagged <> 1 
		AND DATE(occur_begin) > '$date' ORDER BY occur_begin ASC LIMIT 0,$after" );
	} else {
		$events2 = array();
	}
	$arr_events = array();
	if ( ! empty( $events1 ) || ! empty( $events2 ) || ! empty( $events3 ) ) {
		$arr_events = array_merge( $events1, $events3, $events2 );
	}
	if ( ! get_option( 'mc_skip_holidays_category' ) || get_option( 'mc_skip_holidays_category' ) == '' ) {
		$holidays = array();
	} else {
		$holidays      = mc_get_all_holidays( $before, $after, 'yes' );
		$holiday_array = mc_set_date_array( $holidays );
	}
	if ( is_array( $arr_events ) && ! empty( $arr_events ) ) {
		$no_events   = false;
		$event_array = mc_set_date_array( $arr_events );
		if ( is_array( $holidays ) && count( $holidays ) > 0 ) {
			$event_array = mc_holiday_limit( $event_array, $holiday_array ); // if there are holidays, rejigger.
		}
	}
	if ( ! empty( $event_array ) ) {
		$template = '<strong>{date}</strong> {title} {details}';
		$template = apply_filters( 'mc_search_template', $template );
		// no filters parameter prevents infinite looping on the_content filters.
		$output = mc_produce_upcoming_events( $event_array, $template, 'list', 'ASC', 0, $before, $after, 'yes', 'nofilters' );
	} else {
		$output = "<li class='no-results'>" . __( 'Sorry, your search produced no results.', 'my-calendar' ) . "</li>";
	}

	return "<ol class='mc-search-results'>$output</ol>";
}

add_filter( 'the_title', 'mc_search_results_title', 10, 2 );
function mc_search_results_title( $title, $id = false ) {
	if ( isset( $_GET['mcs'] ) && ( is_page( $id ) || is_single( $id ) ) && in_the_loop() ) {
		$query = esc_html( $_GET['mcs'] );
		$title = sprintf( __( 'Events Search for &ldquo;%s&rdquo;', 'my-calendar' ), $query );
	}

	return $title;
}

add_filter( 'the_content', 'mc_show_search_results' );
function mc_show_search_results( $content ) {
	// if this is the result of a search, show search output. 
	$ret   = false;
	$query = false;
	if ( isset( $_GET['mcs'] ) ) { // simple search
		$ret   = true;
		$query = $_GET['mcs'];
	} else if ( isset ( $_POST['mcs'] ) ) { // advanced search
		$ret   = true;
		$query = $_POST;
	}
	if ( $ret && $query ) {
		return mc_search_results( $query );
	} else {
		return $content;
	}
}

add_filter( 'the_content', 'mc_show_event_template', 100 );
function mc_show_event_template( $content ) {
	global $post;
	if ( $post->post_type == 'mc-events' ) {
		$content .= do_shortcode( get_post_meta( $post->ID, '_mc_event_shortcode', true ) ); // -> triggers an infinite loop when shortcode filters enabled in settings. If you're using this view, you have no reason to allow the_content filters.
	}

	return $content;
}

// Actually do the printing of the calendar
function my_calendar( $name, $format, $category, $time = 'month', $ltype = '', $lvalue = '', $id = 'jd-calendar', $template = '', $content = '', $author = null, $host = null, $above = '', $below = '' ) {
	check_my_calendar();
	apply_filters( "debug", "my_calendar( $name ) draw" );
	$mc_toporder    = array( 'nav', 'toggle', 'jump', 'print', 'timeframe' );
	$mc_bottomorder = array( 'key', 'feeds' );
	if ( $above != '' || $below != '' ) {
		$aboves = ( $above == 'none' ) ? array() : array_map( 'trim', explode( ',', $above ) );
		$belows = ( $below == 'none' ) ? array() : array_map( 'trim', explode( ',', $below ) );
	} else {
		$aboves = $mc_toporder;
		$belows = $mc_bottomorder;
	}
	$used = array_merge( $aboves, $belows );

	if ( isset( $_GET['format'] ) && in_array( $_GET['format'], array( 'list', 'mini' ) ) ) {
		$format = esc_attr( $_GET['format'] );
	} else {
		$format = esc_attr( $format );
	}

	if ( isset( $_GET['time'] ) && in_array( $_GET['time'], array(
				'day',
				'week',
				'month',
				'month+1'
			) ) && $format != 'mini'
	) {
		$time = esc_attr( $_GET['time'] );
	} else {
		$time = esc_attr( $time );
	}

	$offset           = ( 60 * 60 * get_option( 'gmt_offset' ) );
	$my_calendar_body = '';
	/* filter */
	if ( $time == 'day' ) {
		$format = 'list';
	}
	$args = array(
		'name'     => $name,
		'format'   => $format,
		'category' => $category,
		'above'    => $above,
		'below'    => $below,
		'time'     => $time,
		'ltype'    => $ltype,
		'lvalue'   => $lvalue,
		'author'   => $author,
		'id'       => $id
	);
	$my_calendar_body .= apply_filters( 'mc_before_calendar', '', $args );

	$main_class = ( $name != '' ) ? sanitize_title( $name ) : 'all';
	$cid        = ( isset( $_GET['cid'] ) ) ? esc_attr( strip_tags( $_GET['cid'] ) ) : $main_class;

	// mc body wrapper
	$mc_wrapper = "<div id=\"$id\" class=\"mc-main $format $time $main_class\" aria-live='assertive' aria-atomic='true'>";
	$mc_closer  = "</div>";

	if ( get_option( 'mc_convert' ) == 'true' ) {
		$format = ( mc_is_mobile() ) ? 'list' : $format;
	}
	$format      = apply_filters( 'mc_display_format', $format, $args );
	$date_format = ( get_option( 'mc_date_format' ) != '' ) ? get_option( 'mc_date_format' ) : get_option( 'date_format' );

	if ( isset( $_GET['mc_id'] ) && $format != 'mini' ) {
		// single event, main calendar only.
		$mc_id = (int) $_GET['mc_id'];
		$my_calendar_body .= mc_get_event( $mc_id, 'html' );
	} else {
		if ( $category == "" ) {
			$category = null;
		}
		// Deal with the week not starting on a monday
		$name_days = array(
			__( '<abbr title="Sunday">Sun</abbr>', 'my-calendar' ),
			__( '<abbr title="Monday">Mon</abbr>', 'my-calendar' ),
			__( '<abbr title="Tuesday">Tues</abbr>', 'my-calendar' ),
			__( '<abbr title="Wednesday">Wed</abbr>', 'my-calendar' ),
			__( '<abbr title="Thursday">Thur</abbr>', 'my-calendar' ),
			__( '<abbr title="Friday">Fri</abbr>', 'my-calendar' ),
			__( '<abbr title="Saturday">Sat</abbr>', 'my-calendar' )
		);
		$abbrevs   = array( 'sun', 'mon', 'tues', 'wed', 'thur', 'fri', 'sat' );
		if ( $format == "mini" ) {
			$name_days = array(
				__( '<abbr title="Sunday">S</abbr>', 'my-calendar' ),
				__( '<abbr title="Monday">M</abbr>', 'my-calendar' ),
				__( '<abbr title="Tuesday">T</abbr>', 'my-calendar' ),
				__( '<abbr title="Wednesday">W</abbr>', 'my-calendar' ),
				__( '<abbr title="Thursday">T</abbr>', 'my-calendar' ),
				__( '<abbr title="Friday">F</abbr>', 'my-calendar' ),
				__( '<abbr title="Saturday">S</abbr>', 'my-calendar' )
			);
		}
		$start_of_week = ( get_option( 'start_of_week' ) == 1 ) ? 1 : 7; // convert start of week to ISO 8601 (Monday/Sunday)
		$end_of_week   = ( $start_of_week == 1 ) ? 7 : 6;
		$start_of_week = ( get_option( 'mc_show_weekends' ) == 'true' ) ? $start_of_week : 1;
		//$start_of_week = ( $start_of_week==1||$start_of_week==0)?$start_of_week:0;
		if ( $start_of_week == '1' ) {
			$first       = array_shift( $name_days );
			$afirst      = array_shift( $abbrevs );
			$name_days[] = $first;
			$abbrevs[]   = $afirst;
		}
		// If we don't pass arguments we want a calendar that is relevant to today (current time period)
		$c_m = 0;
		if ( isset( $_GET['dy'] ) && $main_class == $cid && ( $time == 'day' || $time == 'week' ) ) { //
			$c_day = (int) $_GET['dy'];
		} else {
			if ( $time == 'week' ) {
				$dm    = first_day_of_week();
				$c_day = $dm[0];
				$c_m   = $dm[1];
			} else if ( $time == 'day' ) {
				$c_day = date( "d", time() + ( $offset ) );
			} else {
				$c_day = 1;
			}
		}
		if ( isset( $_GET['month'] ) && $main_class == $cid ) {
			$c_month = (int) $_GET['month'];
			if ( ! isset( $_GET['dy'] ) ) {
				$c_day = 1;
			}
		} else {
			$xnow    = date( 'Y-m-d', time() + ( $offset ) );
			$c_month = ( $c_m == 0 ) ? date( "m", time() + ( $offset ) ) : date( "m", strtotime( $xnow . ' -1 month' ) );
		}

		$is_start_of_week = ( date( 'N', current_time( 'timestamp' ) ) == get_option( 'start_of_week' ) ) ? true : false;
		if ( isset( $_GET['yr'] ) && $main_class == $cid ) {
			$c_year = (int) $_GET['yr'];
		} else {
			// weeks suck. seriously.
			if ( date( "Y", current_time( 'timestamp' ) ) == date( "Y", strtotime( date( 'Y-m-d', current_time( 'timestamp' ) ) . '- 6 days' ) ) || $is_start_of_week ) {
				$c_year = ( date( "Y", current_time( 'timestamp' ) ) );
			} else {
				$c_year = ( date( "Y", current_time( 'timestamp' ) ) ) - 1;
			}
		}
		// Years get funny if we exceed 3000, so we use this check
		if ( ! ( $c_year <= 3000 && $c_year >= 0 ) ) {
			// No valid year causes the calendar to default to today
			$c_year  = date( "Y", time() + ( $offset ) );
			$c_month = date( "m", time() + ( $offset ) );
			$c_day   = date( "d", time() + ( $offset ) );
		}
		if ( ! ( isset( $_GET['yr'] ) || isset( $_GET['month'] ) || isset( $_GET['dy'] ) ) ) {
			$c_year  = apply_filters( 'mc_filter_year', $c_year, $args );
			$c_month = apply_filters( 'mc_filter_month', $c_month, $args );
			$c_day   = apply_filters( 'mc_filter_day', $c_day, $args );
		}
		$c_day        = ( $c_day == 0 ) ? 1 : $c_day; // c_day can't equal 0.
		$current_date = mktime( 0, 0, 0, $c_month, $c_day, $c_year );
		$c_month      = str_pad( $c_month, 2, '0', STR_PAD_LEFT );
		//echo "<p>Debug:<br />Day: $c_day<br />Month: $c_month<br />Year: $c_year<br />Date: ".date('Y-m-d',$current_date)."</p>";
		$num = get_option( 'mc_show_months' ) - 1; // the value is total months to show; need additional months to show.

		if ( $format == "list" && $time != 'week' ) { // grid calendar can't show multiple months
			if ( $num > 0 && $time != 'day' && $time != 'week' ) {
				// grid calendar date calculation
				if ( $time == 'month+1' ) {
					$from = date( 'Y-m-d', strtotime( '+1 month', mktime( 0, 0, 0, $c_month, 1, $c_year ) ) );
					$next = strtotime( "+$num months", strtotime( '+1 month', mktime( 0, 0, 0, $c_month, 1, $c_year ) ) );
				} else {
					$from = date( 'Y-m-d', mktime( 0, 0, 0, $c_month, 1, $c_year ) );
					$next = strtotime( "+$num months", mktime( 0, 0, 0, $c_month, 1, $c_year ) );
				}
				$last = date( 't', $next );
				$to   = date( 'Y-m', $next ) . '-' . $last;
			} else {
				$from = date( 'Y-m-d', mktime( 0, 0, 0, $c_month, 1, $c_year ) );
				$to   = date( 'Y-m-d', mktime( 0, 0, 0, $c_month, date( 't', mktime( 0, 0, 0, $c_month, 1, $c_year ) ), $c_year ) );
			}
			$this_dates = array( 'from' => $from, 'to' => $to );
		} else {
			$this_dates = mc_date_array( $current_date, $time );
		}
		$from = $this_dates['from'];
		$to   = $this_dates['to'];
		//echo "<pre>$num $from, $to ($c_month,$c_day,$c_year)</pre>";
		apply_filters( "debug", "my_calendar( $name ) pre get events" );
		$event_array = my_calendar_events( $from, $to, $category, $ltype, $lvalue, 'calendar', $author, $host );
		$no_events   = ( empty( $event_array ) ) ? true : false;
		apply_filters( "debug", "my_calendar( $name ) post get events" );

		// define navigation element strings
		// These variables are used by reference {$value}
		$timeframe = $print = $toggle = $nav = $feeds = $jump = $mc_topnav = $mc_bottomnav = '';

		// setup print link
		$add      = array(
			'time'   => $time,
			'ltype'  => $ltype,
			'lvalue' => $lvalue,
			'mcat'   => $category,
			'yr'     => $c_year,
			'month'  => $c_month,
			'dy'     => $c_day,
			'cid'    => 'print'
		);
		$subtract = array();
		if ( $ltype == '' ) {
			$subtract[] = 'ltype';
			unset( $add['ltype'] );
		}
		if ( $lvalue == '' ) {
			$subtract[] = 'lvalue';
			unset( $add['lvalue'] );
		}
		if ( $category == 'all' ) {
			$subtract[] = 'mcat';
			unset( $add['mcat'] );
		}
		$mc_print_url = mc_build_url( $add, $subtract, mc_feed_base() . 'my-calendar-print' );
		$print        = "<div class='mc-print'><a href='$mc_print_url'>" . __( 'Print View', 'my-calendar' ) . "</a></div>";
		// set up format toggle
		$toggle = ( in_array( 'toggle', $used ) ) ? mc_format_toggle( $format, 'yes' ) : '';
		// set up time toggle
		if ( in_array( 'timeframe', $used ) ) {
			// if dy parameter not set, use today's date instead of first day of month.
			if ( isset( $_GET['dy'] ) ) {
				$weeks_day = first_day_of_week( $current_date );
			} else {
				$weeks_day = first_day_of_week( current_time( 'timestamp' ) );
			}
			$day = $weeks_day[0];
			if ( isset( $_GET['time'] ) && $_GET['time'] == 'day' ) {
				// don't adjust day if viewing day format
			} else {
				if ( $day > 20 ) {
					$day = date( 'j', strtotime( "$from + 1 week" ) );
				}
			}
			$timeframe = mc_time_toggle( $format, $time, 'yes', $day, $c_month, $c_year );
		}
		// set up category key
		$key = ( in_array( 'key', $used ) ) ? my_category_key( $category ) : '';
		// set up navigation links
		if ( in_array( 'nav', $used ) ) {
			$pLink         = my_calendar_prev_link( $c_year, $c_month, $c_day, $format, $time );
			$nLink         = my_calendar_next_link( $c_year, $c_month, $c_day, $format, $time );
			$prevLink      = mc_build_url( array(
					'yr'    => $pLink['yr'],
					'month' => $pLink['month'],
					'dy'    => $pLink['day'],
					'cid'   => $main_class
				), array() );
			$nextLink      = mc_build_url( array(
					'yr'    => $nLink['yr'],
					'month' => $nLink['month'],
					'dy'    => $nLink['day'],
					'cid'   => $main_class
				), array() );
			$previous_link = apply_filters( 'mc_previous_link', '		<li class="my-calendar-prev"><a href="' . $prevLink . '" rel="nofollow" data-rel="' . $id . '">' . $pLink['label'] . '</a></li>', $pLink );
			$next_link     = apply_filters( 'mc_next_link', '		<li class="my-calendar-next"><a href="' . $nextLink . '" rel="nofollow" data-rel="' . $id . '">' . $nLink['label'] . '</a></li>', $nLink );
			$nav           = '
				<div class="my-calendar-nav">
					<ul>
						' . $previous_link . '
						' . $next_link . '
					</ul>
				</div>';
		}
		// set up rss feeds
		if ( $format != 'mini' ) {
			$ical_m = ( isset( $_GET['month'] ) ) ? (int) $_GET['month'] : date( 'n' );
			$ical_y = ( isset( $_GET['yr'] ) ) ? (int) $_GET['yr'] : date( 'Y' );
			$feeds  = mc_rss_links( $ical_y, $ical_m, $nLink );
		}
		// set up date switcher
		if ( in_array( 'jump', $used ) ) {
			$jump = ( $time != 'week' && $time != 'day' ) ? mc_build_date_switcher( $format, $main_class ) : '';
		}
		// set up above-calendar order of fields
		if ( get_option( 'mc_topnav' ) != '' ) {
			$mc_toporder = explode( ',', get_option( 'mc_topnav' ) );
		}
		if ( $above != '' ) {
			$mc_toporder = explode( ',', $above );
		}
		foreach ( $mc_toporder as $value ) {
			if ( $value != 'none' ) {
				$value = trim( $value );
				$mc_topnav .= ${$value};
			}
		}
		if ( $mc_topnav != '' ) {
			$mc_topnav = '<div class="my-calendar-header">' . $mc_topnav . '</div>';
		}

		if ( get_option( 'mc_bottomnav' ) != '' ) {
			$mc_bottomorder = explode( ',', get_option( 'mc_bottomnav' ) );
		}
		if ( $below != '' ) {
			$mc_bottomorder = explode( ',', $below );
		}
		foreach ( $mc_bottomorder as $value ) {
			if ( $value != 'none' && $value != 'stop' ) {
				$value = trim( $value );
				$mc_bottomnav .= ${$value};
			}
		}
		if ( $mc_bottomnav != '' ) {
			$mc_bottomnav = "<div class='mc_bottomnav'>$mc_bottomnav</div>";
		}

		if ( $time == 'day' ) {
			apply_filters( "debug", "my_calendar( $name ) pre single-day parsing" );

			$my_calendar_body .= "<div class='mc-main $format $time'>" . $mc_topnav;
			// single day uses independent cycling.
			$dayclass = strtolower( date_i18n( 'D', mktime( 0, 0, 0, $c_month, $c_day, $c_year ) ) );
			$from     = $to = "$c_year-$c_month-$c_day";
			//echo "<p>Debug: $from, $to, $category, $ltype, $lvalue, $author</p>";
			$events = my_calendar_grab_events( $from, $to, $category, $ltype, $lvalue, 'calendar', $author, $host );
			if ( ! get_option( 'mc_skip_holidays_category' ) || get_option( 'mc_skip_holidays_category' ) == '' ) {
				$holidays = array();
			} else {
				$holidays = my_calendar_grab_events( $from, $to, get_option( 'mc_skip_holidays_category' ), $ltype, $lvalue, 'calendar', $author, $host, 'holidays' );
			}
			//echo "<pre>".print_r($events,1)."</pre>";
			$events_class = mc_events_class( $events, $from );
			$dateclass    = mc_dateclass( time() + $offset, mktime( 0, 0, 0, $c_month, $c_day, $c_year ) );
			$mc_events    = '';
			if ( is_array( $events ) && count( $events ) > 0 ) {
				if ( is_array( $holidays ) && count( $holidays ) > 0 ) {
					$mc_events .= my_calendar_draw_events( $holidays, $format, $from, $time, $template );
				} else {
					$mc_events .= my_calendar_draw_events( $events, $format, $from, $time, $template );
				}
			} else {
				$mc_events .= __( 'No events scheduled for today!', 'my-calendar' );
			}
			$heading_level = apply_filters( 'mc_heading_level', 'h3', $format, $time, $template );
			$my_calendar_body .= "
				<$heading_level class='mc-single'>" . date_i18n( apply_filters( 'mc_date_format', $date_format, 'grid' ), strtotime( "$c_year-$c_month-$c_day" ) ) . "</$heading_level>" . '
				<div id="mc-day" class="' . $dayclass . ' ' . $dateclass . ' ' . $events_class . '">' . "$mc_events\n</div>
			</div>";
			apply_filters( "debug", "my_calendar( $name ) post single-day parsing" );
		} else {
			apply_filters( "debug", "my_calendar( $name ) pre full parsing" );
			// if showing multiple months, figure out how far we're going.
			$num_months   = ( $time == 'week' ) ? 1 : get_option( 'mc_show_months' );
			$through_date = mktime( 0, 0, 0, $c_month + ( $num_months - 1 ), $c_day, $c_year );
			$month_format = ( get_option( 'mc_month_format' ) == '' ) ? 'F Y' : get_option( 'mc_month_format' );
			if ( $time == 'month+1' ) {
				$current_date_header = date_i18n( $month_format, strtotime( '+1 month', $current_date ) );
			} else {
				$current_date_header = date_i18n( $month_format, $current_date );
			}
			$current_month_header = ( date( 'Y', $current_date ) == date( 'Y', $through_date ) ) ? date_i18n( 'F', $current_date ) : date_i18n( 'F Y', $current_date );
			$through_month_header = date_i18n( $month_format, $through_date );
			$values               = array( 'date' => date( 'Y-m-d', $current_date ) );
			// Add the calendar table and heading
			$caption_text = ' ' . stripslashes( trim( get_option( 'mc_caption' ) ) ); // this option should be replaced JCD TODO
			$my_calendar_body .= $mc_topnav;
			if ( $format == "calendar" || $format == "mini" ) {
				$my_calendar_body .= "\n<table class=\"my-calendar-table\">\n";
				$week_template   = ( get_option( 'mc_week_caption' ) != '' ) ? get_option( 'mc_week_caption' ) : 'Week of {date format="M jS"}';
				$week_caption    = jd_draw_template( $values, stripslashes( $week_template ) );
				$caption_heading = ( $time != 'week' ) ? $current_date_header . $caption_text : $week_caption . $caption_text;
				$my_calendar_body .= "<caption class=\"my-calendar-$time\">" . $caption_heading . "</caption>\n";
			} else {
				// determine which header text to show depending on number of months displayed;
				if ( $time != 'week' && $time != 'day' ) {
					$list_heading = ( $num_months <= 1 ) ? __( 'Events in', 'my-calendar' ) . ' ' . $current_date_header . $caption_text . "\n" : $current_month_header . '&ndash;' . $through_month_header . $caption_text;
				} else {
					$list_heading = jd_draw_template( $values, stripslashes( get_option( 'mc_week_caption' ) ) );
				}
				$my_calendar_body .= "<h3 class=\"my-calendar-$time\">$list_heading</h3>\n";
			}
			// If not a valid time or layout format, skip.
			if ( in_array( $format, array( 'calendar', 'mini', 'list' ) ) && in_array( $time, array(
						'day',
						'week',
						'month',
						'month+1'
					) )
			) {
				// If in a calendar format, print the headings of the days of the week
				if ( $format == "list" ) {
					$list_id = ( $id == 'jd-calendar' ) ? 'calendar-list' : "list-$id";
					$my_calendar_body .= "<ul id='$list_id' class='mc-list'>";
				} else {
					$my_calendar_body .= "<thead>\n<tr>\n";
					for ( $i = 0; $i <= 6; $i ++ ) {
						if ( $start_of_week == 0 ) {
							$class = ( $i < 6 && $i > 0 ) ? 'day-heading' : 'weekend-heading';
						} else {
							$class = ( $i < 5 ) ? 'day-heading' : 'weekend-heading';
						}
						$dayclass = strtolower( strip_tags( $abbrevs[ $i ] ) );
						if ( ( $class == 'weekend-heading' && get_option( 'mc_show_weekends' ) == 'true' ) || $class != 'weekend-heading' ) {
							$my_calendar_body .= "<th scope='col' class='$class $dayclass'>" . $name_days[ $i ] . "</th>\n";
						}
					}
					$my_calendar_body .= "\n</tr>\n</thead>\n<tbody>";
				}
				$odd = 'odd';
				// get and display all the events
				$show_all = false; // show all dates in list format.
				if ( $no_events && $format == "list" && $show_all == false ) {
					// if there are no events in list format, just display that info.
					$no_events = ( $content == '' ) ? __( 'There are no events scheduled during this period.', 'my-calendar' ) : $content;
					$my_calendar_body .= "<li class='no-events'>$no_events</li>";
				} else {
					$start = strtotime( $from );
					$end   = strtotime( $to );
					do {
						$date       = date( 'Y-m-d', $start );
						$is_weekend = ( date( 'N', $start ) < 6 ) ? false : true;
						if ( get_option( 'mc_show_weekends' ) == 'true' || ( get_option( 'mc_show_weekends' ) != 'true' && ! $is_weekend ) ) {
							if ( date( 'N', $start ) == $start_of_week && $format != "list" ) {
								$my_calendar_body .= "<tr>";
							}
							// date-based classes
							$monthclass       = ( date( 'n', $start ) == $c_month || $time != 'month' ) ? '' : 'nextmonth';
							$dateclass        = mc_dateclass( time() + $offset, $start );
							$dayclass         = strtolower( date_i18n( 'D', $start ) );
							$week_format      = ( get_option( 'mc_week_format' ) == '' ) ? 'M j, \'y' : get_option( 'mc_week_format' );
							$week_date_format = date_i18n( $week_format, $start );
							$thisday_heading  = ( $time == 'week' ) ? "<small>$week_date_format</small>" : date( 'j', $start );
							$events           = ( isset( $event_array[ $date ] ) ) ? $event_array[ $date ] : array();
							$events_class     = mc_events_class( $events, $date );
							if ( get_option( 'mc_list_javascript' ) != 1 ) {
								$is_anchor       = "<a href='#'>";
								$is_close_anchor = "</a>";
							} else {
								$is_anchor = $is_close_anchor = "";
							}
							if ( ! empty( $events ) ) {
								$event_output = my_calendar_draw_events( $events, $format, $date, $time, $template );
								if ( $event_output === true ) {
									$event_output = ' ';
								}
								if ( $format == 'mini' && $event_output != '' ) {
									if ( get_option( 'mc_open_day_uri' ) == 'true' || get_option( 'mc_open_day_uri' ) == 'false' ) {
										// yes, this is weird. it's from some old settings...
										$target = array(
											'yr'    => date( 'Y', $start ),
											'month' => date( 'm', $start ),
											'dy'    => date( 'j', $start ),
											'time'  => 'day'
										);
										if ( $category != '' ) {
											$target['mcat'] = $category;
										}
										$day_url = mc_build_url( $target, array(
												'month',
												'dy',
												'yr',
												'ltype',
												'loc',
												'mcat',
												'cid',
												'mc_id'
											), apply_filters( 'mc_modify_day_uri', get_option( 'mc_uri' ) ) );
										$link    = ( get_option( 'mc_uri' ) != '' && ! is_numeric( get_option( 'mc_uri' ) ) ) ? $day_url : '#';
									} else {
										$atype    = str_replace( 'anchor', '', get_option( 'mc_open_day_uri' ) );
										$ad       = str_pad( date( 'j', $start ), 2, '0', STR_PAD_LEFT ); // need to match format in ID
										$am       = str_pad( $c_month, 2, '0', STR_PAD_LEFT );
										$date_url = mc_build_url( array(
												'yr'    => $c_year,
												'month' => $c_month,
												'dy'    => date( 'j', $start )
											), array(
												'month',
												'dy',
												'yr',
												'ltype',
												'loc',
												'mcat',
												'cid',
												'mc_id'
											), get_option( 'mc_mini_uri' ) );
										$link     = ( get_option( 'mc_mini_uri' ) != '' ) ? $date_url . '#' . $atype . '-' . $c_year . '-' . $am . '-' . $ad : '#';
									}
									$element = "a href='$link'";
									$close   = 'a';
									$trigger = 'trigger';
								} else {
									$element = 'span';
									$close   = 'span';
									$trigger = '';
								}
								// set up events
								if ( ( $is_weekend && get_option( 'mc_show_weekends' ) == 'true' ) || ! $is_weekend ) {
									$weekend_class = ( $is_weekend ) ? 'weekend' : '';
									if ( $format == "list" ) {
										if ( get_option( 'mc_show_list_info' ) == 'true' ) {
											$title = ' - ' . $is_anchor . mc_list_title( $events ) . $is_close_anchor;
										} else {
											$title = '';
										}
										//if ( $monthclass != 'nextmonth' ) { // only show current month in list view.
										if ( $event_output != '' ) {
											$my_calendar_body .= "
												<li id='$format-$date' class='mc-events $dayclass $dateclass $events_class $odd'>
													<strong class=\"event-date\">$is_anchor" . date_i18n( apply_filters( 'mc_date_format', $date_format, 'list' ), $start ) . "$is_close_anchor" . "$title</strong>" .
											                     $event_output . "
												</li>";
											$odd = ( $odd == 'odd' ) ? 'even' : 'odd';
										}
										//}
									} else {
										$my_calendar_body .= "
												<td id='$format-$date' class='$dayclass $dateclass $weekend_class $monthclass $events_class day-with-date'>" . "
													<$element class='mc-date $trigger'>$thisday_heading</$close>" .
										                     $event_output . "
												</td>\n";
									}
								}
							} else {
								// set up no events
								if ( $format != "list" ) {
									$weekend_class = ( $is_weekend ) ? 'weekend' : '';
									$my_calendar_body .= "
												<td class='no-events $dayclass $dateclass $weekend_class $monthclass day-with-date'>
													<span class='mc-date no-events'>$thisday_heading</span>
												</td>\n";
								} else {
									if ( $show_all == true ) {
										$my_calendar_body .= "
											<li id='$format-$date' class='no-events $dayclass $dateclass $events_class $odd'>
												<strong class=\"event-date\">$is_anchor" . date_i18n( $date_format, $start ) . "$is_close_anchor</strong></li>";
										$odd = ( $odd == 'odd' ) ? 'even' : 'odd';
									}
								}
							}

							if ( date( 'N', $start ) == $end_of_week && $format != "list" ) {
								$my_calendar_body .= "</tr>\n"; // end of 'is beginning of week'
							}
						}
						$start = strtotime( "+1 day", $start );

					} while ( $start <= $end );
				}
				$my_calendar_body .= ( $format == "list" ) ? "\n</ul>" : "\n</tbody>\n</table>";
			} else {
				if ( ! in_array( $format, array( 'list', 'calendar', 'mini' ) ) ) {
					$my_calendar_body .= "<p class='mc-error-format'>" . __( "Unrecognized calendar format. Please use one of 'list', 'calendar', or 'mini'.", 'my-calendar' ) . "</p>";
				}
				if ( ! in_array( $time, array( 'day', 'week', 'month', 'month+1' ) ) ) {
					$my_calendar_body .= "<p class='mc-error-time'>" . __( "Unrecognized calendar time period. Please use one of 'day', 'week', or 'month'.", 'my-calendar' ) . "</p>";
				}
			}
			$my_calendar_body .= $mc_bottomnav;
			apply_filters( "debug", "my_calendar( $name ) post full parsing" );
		}
	}
	// The actual printing is done by the shortcode function.
	$my_calendar_body .= apply_filters( 'mc_after_calendar', '', $args );
	apply_filters( "debug", "my_calendar( $name ) draw completed" );

	return $mc_wrapper . apply_filters( 'my_calendar_body', $my_calendar_body ) . $mc_closer;
}

function my_category_key( $category ) {
	global $wpdb;
	$url  = plugin_dir_url( __FILE__ );
	$dir  = plugin_dir_path( __FILE__ );
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	$key        = '';
	$cat_limit  = mc_select_category( $category, 'all', 'category' );
	$sql        = "SELECT * FROM " . MY_CALENDAR_CATEGORIES_TABLE . " $cat_limit ORDER BY category_name ASC";
	$categories = $mcdb->get_results( $sql );
	$key .= '<div class="category-key">
	<h3>' . __( 'Categories', 'my-calendar' ) . "</h3>\n<ul>\n";
	$subpath = ( is_custom_icon() ) ? 'my-calendar-custom/' : 'my-calendar/images/icons/';
	$path    = str_replace( basename( $dir ) . '/', '', $url ) . $subpath;
	foreach ( $categories as $cat ) {
		$hex   = ( strpos( $cat->category_color, '#' ) !== 0 ) ? '#' : '';
		$class = sanitize_title( $cat->category_name );
		if ( $cat->category_private == 1 ) {
			$class .= " private";
		}
		$url = add_query_arg( 'mcat', $cat->category_id, mc_get_current_url() );
		if ( $cat->category_icon != "" && get_option( 'mc_hide_icons' ) != 'true' ) {
			$key .= '<li class="cat_' . $class . '"><a href="' . $url . '"><span class="category-color-sample"><img src="' . $path . $cat->category_icon . '" alt="" style="background:' . $hex . $cat->category_color . ';" /></span>' . stripcslashes( $cat->category_name ) . "</a></li>\n";
		} else {
			$key .= '<li class="cat_' . $class . '"><a href="' . $url . '"><span class="category-color-sample no-icon" style="background:' . $hex . $cat->category_color . ';"> &nbsp; </span>' . stripcslashes( $cat->category_name ) . "</a></li>\n";
		}
	}
	if ( isset( $_GET['mcat'] ) ) {
		$key .= "<li><a href='" . mc_get_current_url() . "'>" . __( 'All Categories', 'my-calendar' ) . "</a></li>";
	}
	$key .= "</ul>\n</div>";
	$key = apply_filters( 'mc_category_key', $key, $categories );

	return $key;
}

function mc_rss_links( $y, $m, $next ) {
	global $wp_rewrite;
	$feed       = mc_feed_base() . 'my-calendar-rss';
	$end        = "&amp;nyr=$next[yr]&amp;nmonth=$next[month]";
	$ics_extend = ( $wp_rewrite->using_permalinks() ) ? "my-calendar-ics/?yr=$y&amp;month=$m" . $end : "my-calendar-ics&amp;yr=$y&amp;month=$m" . $end;
	$ics        = mc_feed_base() . $ics_extend;
	$rss        = "\n	<li class='rss'><a href='" . $feed . "'>" . __( 'Subscribe by <abbr title="Really Simple Syndication">RSS</abbr>', 'my-calendar' ) . "</a></li>";
	$ical       = "\n	<li class='ics'><a href='" . $ics . "'>" . __( 'Download as <abbr title="iCal Events Export">iCal</abbr>', 'my-calendar' ) . "</a></li>";
	$output     = "\n
<div class='mc-export'>
	<ul>$rss$ical</ul>
</div>\n";

	return $output;
}

function mc_feed_base() {
	global $wp_rewrite;
	$base = home_url();
	if ( $wp_rewrite->using_index_permalinks() ) {
		$append = "index.php/";
	} else {
		$append = '';
	}
	$base .= ( $wp_rewrite->using_permalinks() ) ? '/' . $append . 'feed/' : '?feed=';

	return $base;
}

// Configure the "Next" link in the calendar
function my_calendar_next_link( $cur_year, $cur_month, $cur_day, $format, $time = 'month' ) {
	$next_year   = $cur_year + 1;
	$next_events = ( get_option( 'mc_next_events' ) == '' ) ? __( "Next", 'my-calendar' ) : stripcslashes( get_option( 'mc_next_events' ) );
	$num_months  = get_option( 'mc_show_months' );
	if ( $num_months <= 1 || $format != "list" ) {
		if ( $cur_month == 12 ) {
			$nMonth = 1;
			$nYr    = $next_year;
		} else {
			$next_month = $cur_month + 1;
			$nMonth     = $next_month;
			$nYr        = $cur_year;
		}
	} else {
		$next_month = ( ( $cur_month + $num_months ) > 12 ) ? ( ( $cur_month + $num_months ) - 12 ) : ( $cur_month + $num_months );
		if ( $cur_month >= ( 13 - $num_months ) ) {
			$nMonth = $next_month;
			$nYr    = $next_year;
		} else {
			$nMonth = $next_month;
			$nYr    = $cur_year;
		}
	}
	$nDay = '';
	if ( $nYr != $cur_year ) {
		$format = 'F, Y';
	} else {
		$format = 'F';
	}
	$date = date_i18n( $format, mktime( 0, 0, 0, $nMonth, 1, $nYr ) );
	if ( $time == 'week' ) {
		$nextdate = strtotime( "$cur_year-$cur_month-$cur_day" . "+ 7 days" );
		$nDay     = date( 'd', $nextdate );
		$nYr      = date( 'Y', $nextdate );
		$nMonth   = date( 'm', $nextdate );
		if ( $nYr != $cur_year ) {
			$format = 'F j, Y';
		} else {
			$format = 'F j';
		}
		$date = __( 'Week of ', 'my-calendar' ) . date_i18n( $format, mktime( 0, 0, 0, $nMonth, $nDay, $nYr ) );
	}
	if ( $time == 'day' ) {
		$nextdate = strtotime( "$cur_year-$cur_month-$cur_day" . "+ 1 days" );
		$nDay     = date( 'd', $nextdate );
		$nYr      = date( 'Y', $nextdate );
		$nMonth   = date( 'm', $nextdate );
		if ( $nYr != $cur_year ) {
			$format = 'F j, Y';
		} else {
			$format = 'F j';
		}
		$date = date_i18n( $format, mktime( 0, 0, 0, $nMonth, $nDay, $nYr ) );
	}
	$next_events = str_replace( '{date}', $date, $next_events );
	$output      = array( 'month' => $nMonth, 'yr' => $nYr, 'day' => $nDay, 'label' => $next_events );

	return $output;
}

// Configure the "Previous" link in the calendar
function my_calendar_prev_link( $cur_year, $cur_month, $cur_day, $format, $time = 'month' ) {
	$last_year       = $cur_year - 1;
	$previous_events = ( get_option( 'mc_previous_events' ) == '' ) ? __( "Previous", 'my-calendar' ) : stripcslashes( get_option( 'mc_previous_events' ) );
	$num_months      = get_option( 'mc_show_months' );
	if ( $num_months <= 1 || $format != "list" ) {
		if ( $cur_month == 1 ) {
			$pMonth = 12;
			$pYr    = $last_year;
		} else {
			$next_month = $cur_month - 1;
			$pMonth     = $next_month;
			$pYr        = $cur_year;
		}
	} else {
		$next_month = ( $cur_month > $num_months ) ? ( $cur_month - $num_months ) : ( ( $cur_month - $num_months ) + 12 );
		if ( $cur_month <= $num_months ) {
			$pMonth = $next_month;
			$pYr    = $last_year;
		} else {
			$pMonth = $next_month;
			$pYr    = $cur_year;
		}
	}
	if ( $pYr != $cur_year ) {
		$format = 'F, Y';
	} else {
		$format = 'F';
	}
	$date = date_i18n( $format, mktime( 0, 0, 0, $pMonth, 1, $pYr ) );
	$pDay = '';
	if ( $time == 'week' ) {
		$prevdate = strtotime( "$cur_year-$cur_month-$cur_day" . "- 7 days" );
		$pDay     = date( 'd', $prevdate );
		$pYr      = date( 'Y', $prevdate );
		$pMonth   = date( 'm', $prevdate );
		if ( $pYr != $cur_year ) {
			$format = 'F j, Y';
		} else {
			$format = 'F j';
		}
		$date = __( 'Week of ', 'my-calendar' ) . date_i18n( $format, mktime( 0, 0, 0, $pMonth, $pDay, $pYr ) );
	}
	if ( $time == 'day' ) {
		$prevdate = strtotime( "$cur_year-$cur_month-$cur_day" . "- 1 days" );
		$pDay     = date( 'd', $prevdate );
		$pYr      = date( 'Y', $prevdate );
		$pMonth   = date( 'm', $prevdate );
		if ( $pYr != $cur_year ) {
			$format = 'F j, Y';
		} else {
			$format = 'F j';
		}
		$date = date_i18n( $format, mktime( 0, 0, 0, $pMonth, $pDay, $pYr ) );
	}
	$previous_events = str_replace( '{date}', $date, $previous_events );
	$output          = array( 'month' => $pMonth, 'yr' => $pYr, 'day' => $pDay, 'label' => $previous_events );

	return $output;
}

function mc_filters( $args ) {
	$fields      = explode( ',', $args );
	$return      = false;
	$current_url = mc_get_current_url();
	$form        = "
	<div id='mc_filters'>
		<form action='" . $current_url . "' method='get'>\n";
	$qsa         = array();
	parse_str( $_SERVER['QUERY_STRING'], $qsa );
	if ( ! isset( $_GET['cid'] ) ) {
		$form .= '<input type="hidden" name="cid" value="all" />';
	}
	foreach ( $qsa as $name => $argument ) {
		$name     = esc_attr( strip_tags( $name ) );
		$argument = esc_attr( strip_tags( $argument ) );
		if ( $name == 'access' || $name == 'mcat' || $name == 'ltype' || $name == 'lvalue' && in_array( $name, $args ) ) {
		} else {
			$form .= '		<input type="hidden" name="' . $name . '" value="' . $argument . '" />' . "\n";
		}
	}
	foreach ( $fields as $show ) {
		$show = trim( $show );
		switch ( $show ) {
			case 'categories':
				$form .= my_calendar_categories_list( 'form', 'public', 'group' );
				$return = true;
				break;
			case 'locations':
				$form .= my_calendar_locations_list( 'form', 'saved', 'name', 'group' );
				$return = true;
				break;
			case 'access':
				$form .= mc_access_list( 'form', 'group' );
				$return = true;
				break;
		}
	}
	$form .= "<p><input type='submit' value='" . esc_attr( __( 'Filter Events', 'my-calendar' ) ) . "' /></p>
	</form></div>";
	if ( $return ) {
		return $form;
	}

	return '';
}

function my_calendar_categories_list( $show = 'list', $context = 'public', $group = 'single' ) {
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	if ( isset( $_GET['mc_id'] ) ) {
		return '';
	}
	$output      = '';
	$current_url = mc_get_current_url();

	$name         = ( $context == 'public' ) ? 'mcat' : 'category';
	$admin_fields = ( $context == 'public' ) ? ' name="' . $name . '"' : ' multiple="multiple" size="5" name="' . $name . '[]"  ';
	$admin_label  = ( $context == 'public' ) ? '' : __( '(select to include)', 'my-calendar' );
	$form         = ( $group == 'single' ) ? "<form action='" . $current_url . "' method='get'>
				<div>" : '';
	if ( $group == 'single' ) {
		$qsa = array();
		parse_str( $_SERVER['QUERY_STRING'], $qsa );
		if ( ! isset( $_GET['cid'] ) ) {
			$form .= '<input type="hidden" name="cid" value="all" />';
		}
		foreach ( $qsa as $name => $argument ) {
			$name     = esc_attr( strip_tags( $name ) );
			$argument = esc_attr( strip_tags( $argument ) );
			if ( $name != 'mcat' ) {
				$form .= '		<input type="hidden" name="' . $name . '" value="' . $argument . '" />' . "\n";
			}
		}
	}
	$form .= ( $show == 'list' || $group == 'group' ) ? '' : '
		</div><p>';
	$public_form = ( $context == 'public' ) ? $form : '';

	$categories = $mcdb->get_results( "SELECT * FROM " . MY_CALENDAR_CATEGORIES_TABLE . " ORDER BY category_id ASC" );
	if ( ! empty( $categories ) && count( $categories ) >= 1 ) {
		$output = "<div id='mc_categories'>\n";
		$url    = mc_build_url( array( 'mcat' => 'all' ), array() );
		$output .= ( $show == 'list' ) ? "
		<ul>
			<li><a href='$url'>" . __( 'All Categories', 'my-calendar' ) . "</a></li>" : $public_form . '
			<label for="category">' . __( 'Categories', 'my-calendar' ) . ' ' . $admin_label . '</label>
			<select' . $admin_fields . ' id="category">
			<option value="all" selected="selected">' . __( 'All Categories', 'my-calendar' ) . '</option>' . "\n";

		foreach ( $categories as $category ) {
			$category_name = stripcslashes( $category->category_name );
			$mcat          = ( empty( $_GET['mcat'] ) ) ? '' : (int) $_GET['mcat'];
			if ( $show == 'list' ) {
				$this_url = mc_build_url( array( 'mcat' => $category->category_id ), array() );
				$selected = ( $category->category_id == $mcat ) ? ' class="selected"' : '';
				$output .= "			<li$selected><a rel='nofollow' href='$this_url'>$category_name</a></li>";
			} else {
				$selected = ( $category->category_id == $mcat ) ? ' selected="selected"' : '';
				$output .= "			<option$selected value='$category->category_id'>$category_name</option>\n";
			}
		}
		$output .= ( $show == 'list' ) ? '</ul>' : '</select>';
		if ( $context != 'admin' && $show != 'list' ) {
			if ( $group == 'single' ) {
				$output .= "<input type='submit' value=" . __( 'Submit', 'my-calendar' ) . " /></p></form>";
			}
		}
		$output .= "\n</div>";
	}
	$output = apply_filters( 'mc_category_selector', $output, $categories );

	return $output;
}

function mc_access_list( $show = 'list', $group = 'single' ) {
	if ( isset( $_GET['mc_id'] ) ) {
		return '';
	}
	$output      = '';
	$current_url = mc_get_current_url();
	$form        = ( $group == 'single' ) ? "<form action='" . $current_url . "' method='get'>
				<div>" : '';
	if ( $group == 'single' ) {
		$qsa = array();
		parse_str( $_SERVER['QUERY_STRING'], $qsa );
		if ( ! isset( $_GET['cid'] ) ) {
			$form .= '<input type="hidden" name="cid" value="all" />';
		}
		foreach ( $qsa as $name => $argument ) {
			$name     = esc_attr( strip_tags( $name ) );
			$argument = esc_attr( strip_tags( $argument ) );
			if ( $name != 'access' ) {
				$form .= '		<input type="hidden" name="' . $name . '" value="' . $argument . '" />' . "\n";
			}
		}
	}
	$form .= ( $show == 'list' || $group == 'group' ) ? '' : '</div><p>';

	$access_options = get_option( 'mc_event_access' );
	if ( ! empty( $access_options ) && count( $access_options ) >= 1 ) {
		$output       = "<div id='mc_access'>\n";
		$url          = mc_build_url( array( 'access' => 'all' ), array() );
		$not_selected = ( ! isset( $_GET['access'] ) ) ? 'selected="selected"' : '';
		$output .= ( $show == 'list' ) ? "
		<ul>
			<li><a href='$url'>" . __( 'Accessibility Services', 'my-calendar' ) . "</a></li>" : $form . '
		<label for="access">' . __( 'Accessibility Services', 'my-calendar' ) . '</label>
			<select name="access" id="access">
			<option value="all"' . $not_selected . '>' . __( 'No Limit', 'my-calendar' ) . '</option>' . "\n";

		foreach ( $access_options as $key => $access ) {
			$access_name = $access;
			$this_access = ( empty( $_GET['access'] ) ) ? '' : (int) $_GET['access'];
			if ( $show == 'list' ) {
				$this_url = mc_build_url( array( 'access' => $key ), array() );
				$selected = ( $key == $this_access ) ? ' class="selected"' : '';
				$output .= "			<li$selected><a rel='nofollow' href='$this_url'>$access_name</a></li>";
			} else {
				$selected = ( $this_access == $key ) ? ' selected="selected"' : '';
				$output .= "			<option$selected value='$key'>$access_name</option>\n";
			}
		}
		$output .= ( $show == 'list' ) ? '</ul>' : '</select>';
		$output .= ( $show != 'list' && $group == 'single' ) ? "<p><input type='submit' value=" . __( 'Limit by Access', 'my-calendar' ) . " /></p></form>" : '';
		$output .= "\n</div>";
	}
	$output = apply_filters( 'mc_access_selector', $output, $access_options );

	return $output;
}

// array $add == keys and values to add 
// array $subtract == keys to subtract
function mc_build_url( $add, $subtract, $root = '' ) {
	global $wp_rewrite;
	$home = '';
	if ( $root != '' ) {
		$home = $root;
	}
	if ( is_numeric( $root ) ) {
		$home = get_permalink( $root );
	}
	if ( $home == '' ) {
		if ( is_front_page() ) {
			$home = get_bloginfo( 'url' ) . '/';
		} else if ( is_home() ) {
			$page = get_option( 'page_for_posts' );
			$home = get_permalink( $page );
		} else if ( is_archive() ) {
			$home = ''; // an empty string seems to work best; leaving it open.
		} else {
			wp_reset_query(); // break out of any alternate loop that's been set up. If a theme uses query_posts to fetch pages, this will cause problems. But themes should *never* use query_posts to replace the loop, so screw that.
			$home = get_permalink();
		}
	}
	$variables = $_GET;
	$subtract  = array_merge( $subtract, array( 'from', 'to', 'my-calendar-api' ) );
	foreach ( $subtract as $value ) {
		unset( $variables[ $value ] );
	}
	foreach ( $add as $key => $value ) {
		$variables[ $key ] = $value;
	}
	unset( $variables['page_id'] );
	if ( $root == '' ) {
		// root is set to empty when I want to reference the current location
		$char = ( $wp_rewrite->using_permalinks() || is_front_page() || is_archive() ) ? '?' : '&amp;';
	} else {
		$char = ( $wp_rewrite->using_permalinks() ) ? '?' : '&amp;'; // this doesn't work -- may *never* need to be &. Consider	
	}

	return $home . $char . http_build_query( $variables, '', '&amp;' );
}

function my_calendar_show_locations( $datatype = 'name', $template = '' ) {
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	switch ( $datatype ) {
		case "name":
		case "location":
			$data = "location_label";
			break;
		case "city":
			$data = "location_city";
			break;
		case "state":
			$data = "location_state";
			break;
		case "zip":
			$data = "location_postcode";
			break;
		case "country":
			$data = "location_country";
			break;
		case "hcard":
			$data = "location_label";
			break;
		case "region":
			$data = "location_region";
			break;
		default:
			$data = "location_label";
	}
	$locations = $mcdb->get_results( "SELECT DISTINCT * FROM " . MY_CALENDAR_LOCATIONS_TABLE . " ORDER BY $data ASC" );
	if ( $locations ) {
		$output = "<ul class='mc-locations'>";
		foreach ( $locations as $key => $value ) {
			if ( $datatype != 'hcard' && $template == '' ) {
				$label = stripslashes( $value->{$data} );
				$url   = mc_maplink( $value, 'url', $source = 'location' );
				if ( $url ) {
					$output .= "<li>$url</li>";
				} else {
					$output .= "<li>$label</li>";
				}
			} else if ( $datatype == 'hcard' ) {
				$label = mc_hcard( $value, true, true, 'location' );
				$output .= "<li>$label</li>";
			} else if ( $template != '' ) {
				$values = array(
					'id'        => $value->location_id,
					'label'     => $value->location_label,
					'street'    => $value->location_street,
					'street2'   => $value->location_street2,
					'city'      => $value->location_city,
					'state'     => $value->location_state,
					'postcode'  => $value->location_postcode,
					'region'    => $value->location_region,
					'url'       => $value->location_url,
					'country'   => $value->location_country,
					'longitude' => $value->location_longitude,
					'latitude'  => $value->location_latitude,
					'zoom'      => $value->location_zoom,
					'phone'     => $value->location_phone
				);
				$label  = jd_draw_template( $values, $template );
				$output .= "<li>$label</li>";
			}
		}
		$output .= "</ul>";
		$output = apply_filters( 'mc_location_list', $output, $locations );

		return $output;
	}

	return '';
}

function my_calendar_searchform( $type, $url=false ) {
	$query = ( isset( $_GET['mcs'] ) ) ? esc_attr( $_GET['mcs'] ) : '';
	if ( $type == 'simple' ) {
		if ( !$url ) {
			$url = ( get_option( 'mc_uri' ) != '' ) ? get_option( 'mc_uri' ) : home_url();
		}
		return '
		<form role="search" method="get" id="mcsearchform" action="' . apply_filters( 'mc_search_page', $url ) . '" >
		<div><label class="screen-reader-text" for="mcs">' . __( 'Search Events', 'my-calendar' ) . '</label>
		<input type="text" value="' . stripslashes( $query ) . '" name="mcs" id="mcs" />
		<input type="submit" id="searchsubmit" value="' . __( 'Search Events', 'my-calendar' ) . '" />
		</div>
		</form>';
	}

	return '';
}

function my_calendar_locations_list( $show = 'list', $type = 'saved', $datatype = 'name', $group = 'single' ) {
	global $wpdb;
	$mcdb = $wpdb;
	if ( get_option( 'mc_remote' ) == 'true' && function_exists( 'mc_remote_db' ) ) {
		$mcdb = mc_remote_db();
	}
	$output = '';
	if ( isset( $_GET['mc_id'] ) ) {
		return '';
	}
	if ( $type == 'saved' ) {
		switch ( $datatype ) {
			case "name":
				$data = "location_label";
				break;
			case "city":
				$data = "location_city";
				break;
			case "state":
				$data = "location_state";
				break;
			case "zip":
				$data = "location_postcode";
				break;
			case "country":
				$data = "location_country";
				break;
			case "region":
				$data = "location_region";
				break;
			default:
				$data = "location_label";
		}
	} else {
		$data = $datatype;
	}
	$current_url = mc_get_current_url();
	if ( $type == 'saved' ) {
		$locations = $mcdb->get_results( "SELECT DISTINCT $data FROM " . MY_CALENDAR_LOCATIONS_TABLE . " ORDER BY $data ASC", ARRAY_A );
	} else {
		$data      = get_option( 'mc_user_settings' );
		$locations = $data['my_calendar_location_default']['values'];
		$datatype  = str_replace( 'event_', '', get_option( 'mc_location_type' ) );
		$datatype  = ( $datatype == 'label' ) ? 'name' : $datatype;
		$datatype  = ( $datatype == 'postcode' ) ? 'zip' : $datatype;
	}
	if ( count( $locations ) > 1 ) {
		if ( $show == 'list' ) {
			$url = mc_build_url( array( 'loc' => 'all', 'ltype' => 'all' ), array() );
			$output .= "<ul id='mc-locations-list'>
			<li><a href='$url'>" . __( 'Show all', 'my-calendar' ) . "</a></li>\n";
		} else {
			$ltype = ( ! isset( $_GET['ltype'] ) ) ? $datatype : $_GET['ltype'];
			$output .= "<div id='mc_locations'>";
			$output .= ( $group == 'single' ) ? "
		<form action='" . $current_url . "' method='get'>
		<div>" : '';
			$output .= "<input type='hidden' name='ltype' value='$ltype' />";
			if ( $group == 'single' ) {
				$qsa = array();
				parse_str( $_SERVER['QUERY_STRING'], $qsa );
				if ( ! isset( $_GET['cid'] ) ) {
					$output .= '<input type="hidden" name="cid" value="all" />';
				}
				foreach ( $qsa as $name => $argument ) {
					$name     = esc_attr( strip_tags( $name ) );
					$argument = esc_attr( strip_tags( $argument ) );
					if ( $name != 'loc' && $name != 'ltype' ) {
						$output .= "\n		" . '<input type="hidden" name="' . $name . '" value="' . $argument . '" />';
					}
				}
			}
			$output .= "
			<label for='mc-locations-list'>" . __( 'Location', 'my-calendar' ) . "</label>
			<select name='loc' id='mc-locations-list'>
			<option value='all'>" . __( 'Show all', 'my-calendar' ) . "</option>\n";
		}
		foreach ( $locations as $key => $location ) {
			if ( $type == 'saved' ) {
				foreach ( $location as $k => $value ) {
					$vt    = urlencode( trim( $value ) );
					$value = stripcslashes( $value );
					if ( $value == '' ) {
						continue;
					}
					if ( empty( $_GET['loc'] ) ) {
						$loc = '';
					} else {
						$loc = $_GET['loc'];
					}
					if ( $show == 'list' ) {
						$selected = ( $vt == $loc ) ? " class='selected'" : '';
						$this_url = mc_build_url( array( 'loc' => $vt, 'ltype' => $datatype ), array() );
						$output .= "			<li$selected><a rel='nofollow' href='$this_url'>$value</a></li>\n";
					} else {
						$selected = ( $vt == $loc ) ? " selected='selected'" : '';
						if ( $value != '' ) {
							$output .= "			<option value='$vt'$selected>$value</option>\n";
						}
					}
				}
			} else {
				$vk       = urlencode( trim( $key ) );
				$location = trim( $location );
				if ( $location == '' ) {
					continue;
				}
				if ( $show == 'list' ) {
					$selected = ( $vk == $_GET['loc'] ) ? " class='selected'" : '';
					$this_url = mc_build_url( array( 'loc' => $vk, 'ltype' => $datatype ), array() );
					$output .= "			<li$selected><a rel='nofollow' href='$this_url'>$location</a></li>\n";
				} else {
					$selected = ( $vk == $_GET['loc'] ) ? " selected='selected'" : '';
					$output .= "			<option value='$vk'$selected>$location</option>\n";
				}
			}
		}
		if ( $show == 'list' ) {
			$output .= "</ul>";
		} else {
			$output .= "</select>";
			$output .= ( $group == 'single' ) ? "<input type='submit' value=" . __( 'Submit', 'my-calendar' ) . " />
					</div>
				</form>" : '';
			$output .= "
			</div>";
		}
		$output = apply_filters( 'mc_location_selector', $output, $locations );

		return $output;
	} else {
		return '';
	}
}

function mc_user_timezone() {
	global $user_ID;
	$user_settings = get_option( 'mc_user_settings' );
	if ( empty( $user_settings['my_calendar_tz_default']['enabled'] ) ) {
		$enabled = 'off';
	} else {
		$enabled = $user_settings['my_calendar_tz_default']['enabled'];
	}
	if ( get_option( 'mc_user_settings_enabled' ) == 'true' && $enabled == 'on' ) {
		if ( is_user_logged_in() ) {
			get_currentuserinfo();
			$current_settings = get_user_meta( $user_ID, 'my_calendar_user_settings', true );
			$tz               = ( isset( $current_settings['my_calendar_tz_default'] ) ) ? $current_settings['my_calendar_tz_default'] : '';
		} else {
			$tz = '';
		}
	} else {
		$tz = 'none';
	}
	if ( $tz == get_option( 'gmt_offset' ) || $tz == 'none' || $tz == '' ) {
		$gtz = '';
	} else if ( $tz < get_option( 'gmt_offset' ) ) {
		$gtz = - ( abs( get_option( 'gmt_offset' ) - $tz ) );
	} else {
		$gtz = ( abs( get_option( 'gmt_offset' ) - $tz ) );
	}

	return $gtz;
}