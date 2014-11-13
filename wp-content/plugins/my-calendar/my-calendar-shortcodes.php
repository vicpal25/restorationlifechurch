<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

function my_calendar_insert( $atts, $content = null ) {
	extract( shortcode_atts( array(
		'name'     => 'all',
		'format'   => 'calendar',
		'category' => 'all',
		'time'     => 'month',
		'ltype'    => '',
		'lvalue'   => '',
		'author'   => 'all',
		'host'     => 'all',
		'id'       => 'jd-calendar',
		'template' => '',
		'above'    => '',
		'below'    => ''
	), $atts, 'my_calendar' ) );
	if ( $format != 'mini' ) {
		if ( isset( $_GET['format'] ) ) {
			$format = esc_sql( $_GET['format'] );
		}
	}
	global $user_ID;
	if ( $author == 'current' ) {
		$author = apply_filters( 'mc_display_author', $user_ID, 'main' );
	}
	if ( $host == 'current' ) {
		$host = apply_filters( 'mc_display_host', $user_ID, 'main' );
	}

	return my_calendar( $name, $format, $category, $time, $ltype, $lvalue, $id, $template, $content, $author, $host, $above, $below );
}

function my_calendar_insert_upcoming( $atts ) {
	extract( shortcode_atts( array(
		'before'     => 'default',
		'after'      => 'default',
		'type'       => 'default',
		'category'   => 'default',
		'template'   => 'default',
		'fallback'   => '',
		'order'      => 'asc',
		'skip'       => '0',
		'show_today' => 'yes',
		'author'     => 'default',
		'host'       => 'default',
		'ltype'      => '',
		'lvalue'     => ''
	), $atts, 'my_calendar_upcoming' ) );
	global $user_ID;
	if ( $author == 'current' ) {
		$author = apply_filters( 'mc_display_author', $user_ID, 'upcoming' );
	}
	if ( $host == 'current' ) {
		$host = apply_filters( 'mc_display_host', $user_ID, 'upcoming' );
	}

	return my_calendar_upcoming_events( $before, $after, $type, $category, $template, $fallback, $order, $skip, $show_today, $author, $host, $ltype, $lvalue );
}

function my_calendar_insert_today( $atts ) {
	extract( shortcode_atts( array(
		'category' => 'default',
		'author'   => 'default',
		'host'     => 'default',
		'template' => 'default',
		'fallback' => ''
	), $atts, 'my_calendar_today' ) );
	global $user_ID;
	if ( $author == 'current' ) {
		$author = apply_filters( 'mc_display_author', $user_ID, 'today' );
	}
	if ( $host == 'current' ) {
		$host = apply_filters( 'mc_display_host', $user_ID, 'today' );
	}

	return my_calendar_todays_events( $category, $template, $fallback, $author, $host );
}

function my_calendar_locations( $atts ) {
	extract( shortcode_atts( array(
		'show'     => 'list',
		'type'     => 'saved',
		'datatype' => 'name'
	), $atts, 'my_calendar_locations' ) );

	return my_calendar_locations_list( $show, $type, $datatype );
}

function my_calendar_show_locations_list( $atts ) {
	extract( shortcode_atts( array(
		'datatype' => 'name',
		'template' => ''
	), $atts, 'my_calendar_locations_list' ) );

	return my_calendar_show_locations( $datatype, $template );
}

function my_calendar_categories( $atts ) {
	extract( shortcode_atts( array(
		'show' => 'list'
	), $atts, 'my_calendar_categories' ) );

	return my_calendar_categories_list( $show );
}

function my_calendar_access( $atts ) {
	extract( shortcode_atts( array(
		'show' => 'list'
	), $atts, 'my_calendar_access' ) );

	return mc_access_list( $show );
}

function my_calendar_filters( $atts ) {
	extract( shortcode_atts( array(
		'show' => 'categories,locations'
	), $atts, 'my_calendar_filters' ) );

	return mc_filters( $show );
}


function my_calendar_show_event( $atts ) {
	extract( shortcode_atts( array(
		'event'    => '',
		'template' => '<h3>{title}</h3>{description}',
		'list'     => '<li>{date}, {time}</li>',
		'before'   => '<ul>',
		'after'    => '</ul>'
	), $atts, 'my_calendar_event' ) );

	return mc_instance_list( $event, false, $template, $list, $before, $after );
}

function my_calendar_search( $atts ) {
	extract( shortcode_atts( array(
		'type' => 'simple',
		'url' => 'false'
	), $atts, 'my_calendar_search' ) );

	return my_calendar_searchform( $type, $url );
}