<?php
/*
Plugin Name: My Calendar
Plugin URI: http://www.joedolson.com/my-calendar/
Description: Accessible WordPress event calendar plugin. Show events from multiple calendars on pages, in posts, or in widgets.
Author: Joseph C Dolson
Author URI: http://www.joedolson.com
Text Domain: my-calendar
Domain Path: lang
Version: 2.3.20
*/
/*  Copyright 2009-2014  Joe Dolson (email : joe@joedolson.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

apply_filters( "debug", "MC Started" );

global $mc_version, $wpdb;
$mc_version = '2.3.20';

// Define the tables used in My Calendar
if ( is_multisite() && get_site_option( 'mc_multisite_show' ) == 1 ) {
	define( 'MY_CALENDAR_TABLE', $wpdb->base_prefix . 'my_calendar' );
	define( 'MY_CALENDAR_EVENTS_TABLE', $wpdb->base_prefix . 'my_calendar_events' );
	define( 'MY_CALENDAR_CATEGORIES_TABLE', $wpdb->base_prefix . 'my_calendar_categories' );
	define( 'MY_CALENDAR_LOCATIONS_TABLE', $wpdb->base_prefix . 'my_calendar_locations' );
} else {
	define( 'MY_CALENDAR_TABLE', $wpdb->prefix . 'my_calendar' );
	define( 'MY_CALENDAR_EVENTS_TABLE', $wpdb->prefix . 'my_calendar_events' );
	define( 'MY_CALENDAR_CATEGORIES_TABLE', $wpdb->prefix . 'my_calendar_categories' );
	define( 'MY_CALENDAR_LOCATIONS_TABLE', $wpdb->prefix . 'my_calendar_locations' );
}

if ( is_multisite() ) {
	// Define the tables used in My Calendar
	define( 'MY_CALENDAR_GLOBAL_TABLE', $wpdb->base_prefix . 'my_calendar' );
	define( 'MY_CALENDAR_GLOBAL_EVENT_TABLE', $wpdb->base_prefix . 'my_calendar_events' );
	define( 'MY_CALENDAR_GLOBAL_CATEGORIES_TABLE', $wpdb->base_prefix . 'my_calendar_categories' );
	define( 'MY_CALENDAR_GLOBAL_LOCATIONS_TABLE', $wpdb->base_prefix . 'my_calendar_locations' );
}

register_activation_hook( __FILE__, 'mc_plugin_activated' );
register_deactivation_hook( __FILE__, 'mc_plugin_deactivated' );
function mc_plugin_activated() {
	flush_rewrite_rules();
	if ( my_calendar_exists() ) {
		mc_upgrade_db();
	}
	check_my_calendar();
}

function mc_plugin_deactivated() {
	flush_rewrite_rules();
}

include( dirname( __FILE__ ) . '/includes/date-utilities.php' );
include( dirname( __FILE__ ) . '/my-calendar-core.php' );
include( dirname( __FILE__ ) . '/my-calendar-install.php' );
include( dirname( __FILE__ ) . '/my-calendar-settings.php' );
include( dirname( __FILE__ ) . '/my-calendar-categories.php' );
include( dirname( __FILE__ ) . '/my-calendar-locations.php' );
include( dirname( __FILE__ ) . '/my-calendar-help.php' );
include( dirname( __FILE__ ) . '/my-calendar-event-manager.php' );
include( dirname( __FILE__ ) . '/my-calendar-styles.php' );
include( dirname( __FILE__ ) . '/my-calendar-behaviors.php' );
include( dirname( __FILE__ ) . '/my-calendar-events.php' );
include( dirname( __FILE__ ) . '/my-calendar-widgets.php' );
include( dirname( __FILE__ ) . '/my-calendar-upgrade-db.php' );
include( dirname( __FILE__ ) . '/my-calendar-output.php' );
include( dirname( __FILE__ ) . '/my-calendar-templates.php' );
include( dirname( __FILE__ ) . '/my-calendar-ical.php' );
include( dirname( __FILE__ ) . '/my-calendar-limits.php' );
include( dirname( __FILE__ ) . '/my-calendar-shortcodes.php' );
include( dirname( __FILE__ ) . '/my-calendar-templating.php' );
include( dirname( __FILE__ ) . '/my-calendar-group-manager.php' );
include( dirname( __FILE__ ) . '/my-calendar-api.php' );
include( dirname( __FILE__ ) . '/my-calendar-generator.php' );

// Enable internationalisation
load_plugin_textdomain( 'my-calendar', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );

if ( version_compare( get_bloginfo( 'version' ), '3.0', '<' ) && is_ssl() ) {
	$wp_content_url = str_replace( 'http://', 'https://', get_option( 'siteurl' ) );
} else {
	$wp_content_url = get_option( 'siteurl' );
}

// Add actions
add_action( 'admin_menu', 'my_calendar_menu' );
add_action( 'wp_head', 'my_calendar_wp_head' );
add_action( 'delete_user', 'mc_deal_with_deleted_user' );
add_action( 'widgets_init', create_function( '', 'return register_widget("my_calendar_today_widget");' ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("my_calendar_upcoming_widget");' ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("my_calendar_mini_widget");' ) );
add_action( 'widgets_init', create_function( '', 'return register_widget("my_calendar_simple_search");' ) );
add_action( 'init', 'my_calendar_add_feed' );
add_action( 'admin_menu', 'my_calendar_add_javascript' );
add_action( 'wp_footer', 'mc_footer_js' );
add_action( 'wp_head', 'my_calendar_fouc' );
add_action( 'init', 'mc_export_vcal', 200 );
// Add filters 
add_filter( 'widget_text', 'do_shortcode', 9 );
add_filter( 'plugin_action_links', 'mc_plugin_action', - 10, 2 );
add_filter( 'wp_title', 'mc_event_filter', 10, 3 );

function mc_event_filter( $title, $sep = ' | ', $seplocation = 'right' ) {
	if ( isset( $_GET['mc_id'] ) ) {
		$id        = (int) $_GET['mc_id'];
		$event     = mc_get_event( $id );
		$array     = mc_create_tags( $event );
		$left_sep  = ( $seplocation != 'right' ? ' ' . $sep . ' ' : '' );
		$right_sep = ( $seplocation != 'right' ? '' : ' ' . $sep . ' ' );
		$template  = ( get_option( 'mc_event_title_template' ) != '' ) ? stripslashes( get_option( 'mc_event_title_template' ) ) : "$left_sep {title} $sep {date} $right_sep ";

		return strip_tags( jd_draw_template( $array, $template ) );
	} else {
		return $title;
	}
}

// back compat
function jd_show_support_box() {
	mc_show_sidebar();
}

// produce admin support box
function mc_show_sidebar( $show = '', $add = false, $remove = false ) {
	if ( current_user_can( 'mc_view_help' ) ) {
		?>
		<div class="postbox-container jcd-narrow">
		<div class="metabox-holder">
		<?php if ( ! $remove ) { ?>
			<?php if ( ! function_exists( 'mcs_submit_exists' ) ) { ?>
				<div class="ui-sortable meta-box-sortables">
					<div class="postbox support">
						<h3><strong><?php _e( 'My Calendar: Submissions', 'my-calendar' ); ?></strong></h3>

						<div class="inside resources">
							<p class="mcsbuy"><?php _e( "Buy the <a href='http://www.joedolson.com/my-calendar/submissions/' rel='external'>My Calendar Submissions add-on</a> &mdash; let your audience build your calendar.", 'my-calendar' ); ?></p>

							<p class="mc-button"><a href="http://www.joedolson.com/my-calendar/submissions/" rel="external"><?php _e( 'Learn more!', 'my-calendar' ); ?></a>
							</p>
						</div>
					</div>
				</div>
			<?php } ?>
			<div class="ui-sortable meta-box-sortables">
				<div class="postbox support">
					<h3><strong><?php _e( 'Support This Plug-in', 'my-calendar' ); ?></strong></h3>

					<div class="inside resources">
						<p>
							<a href="https://twitter.com/intent/follow?screen_name=joedolson"
							   class="twitter-follow-button" data-size="small" data-related="joedolson">Follow
								@joedolson</a>
							<script>!function (d, s, id) {
									var js, fjs = d.getElementsByTagName(s)[0];
									if (!d.getElementById(id)) {
										js = d.createElement(s);
										js.id = id;
										js.src = "https://platform.twitter.com/widgets.js";
										fjs.parentNode.insertBefore(js, fjs);
									}
								}(document, "script", "twitter-wjs");</script>
						</p>
						<p class="mcbuy"><?php _e( 'Help me help you:', 'my-calendar' ); ?> <a
								href="http://www.joedolson.com/my-calendar/users-guide/"
								rel="external"><?php _e( "Buy the My Calendar User's Guide", 'my-calendar' ); ?></a>
						</p>

						<p><?php _e( '<strong>Make a donation today!</strong> Every donation helps - donate $5, $20, or $100 and keep this plug-in running!', 'my-calendar' ); ?></p>

						<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
							<p class="mcd">
								<input type="hidden" name="cmd" value="_s-xclick"/>
								<input type="hidden" name="hosted_button_id" value="UZBQUG2LKKMRW"/>
								<input type="image"
								       src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif"
								       name="submit" alt="<?php _e( 'Make a Donation', 'my-calendar' ); ?>"/>
								<img alt=""
								     src="https://www.paypalobjects.com/WEBSCR-640-20110429-1/en_US/i/scr/pixel.gif"
								     width="1" height="1"/>
							</p>
						</form>
					</div>
				</div>
			</div>
		<?php } ?>
		<div class="ui-sortable meta-box-sortables">
			<div class="postbox">
				<h3><?php _e( 'Get Help', 'my-calendar' ); ?></h3>

				<div class="inside">
					<ul>
						<li><strong><a
									href="<?php echo admin_url( "admin.php?page=my-calendar-help" ); ?>#get-started"><?php _e( "Getting Started", 'my-calendar' ); ?>
							</strong></a></li>
						<li><strong><a
									href="<?php echo admin_url( "admin.php?page=my-calendar-help" ); ?>#mc-generator"><?php _e( "Shortcode Generator", 'my-calendar' ); ?>
							</strong></a></li>
						<li>
							<a href="<?php echo admin_url( "admin.php?page=my-calendar-help" ); ?>#get-support"><?php _e( "Get Support", 'my-calendar' ); ?></a>
						</li>
						<li>
							<div class="dashicons dashicons-editor-help"></div>
							<a href="<?php echo admin_url( "admin.php?page=my-calendar-help" ); ?>"><?php _e( "My Calendar Help", 'my-calendar' ); ?></a>
						</li>
						<li>
							<div class="dashicons dashicons-yes"></div>
							<a href="http://profiles.wordpress.org/users/joedolson/"><?php _e( 'Check out my other plug-ins', 'my-calendar' ); ?></a>
						</li>
						<li>
							<div class="dashicons dashicons-star-filled"></div>
							<a href="http://wordpress.org/support/view/plugin-reviews/my-calendar"><?php _e( 'Rate this plug-in 5 stars!', 'my-calendar' ); ?></a>
						</li>
						<li>
							<div class="dashicons dashicons-translation"></div>
							<a href="http://translate.joedolson.com/projects/my-calendar"><?php _e( 'Help translate this plug-in!', 'my-calendar' ); ?></a>
						</li>
					</ul>
				</div>
			</div>
		</div>
		<?php if ( is_array( $add ) ) {
			foreach ( $add as $key => $value ) {
				?>
				<div class="ui-sortable meta-box-sortables">
					<div class="postbox">
						<h3><?php echo $key; ?></h3>

						<div class='<?php echo sanitize_title( $key ); ?> inside'>
							<?php echo $value; ?>
						</div>
					</div>
				</div>
			<?php
			}
		} ?>
		<?php if ( $show == 'templates' ) { ?>
			<div class="ui-sortable meta-box-sortables">
				<div class="postbox">
					<h3><?php _e( 'Event Template Tags', 'my-calendar' ); ?></h3>

					<div class='mc_template_tags inside'>
						<dl>
							<dt><code>{title}</code></dt>
							<dd><?php _e( 'Title of the event.', 'my-calendar' ); ?></dd>

							<dt><code>{link_title}</code></dt>
							<dd><?php _e( 'Title of the event as a link if a URL is present, or the title alone if not.', 'my-calendar' ); ?></dd>

							<dt><code>{time}</code></dt>
							<dd><?php _e( 'Start time for the event.', 'my-calendar' ); ?></dd>

							<dt><code>{date}</code></dt>
							<dd><?php _e( 'Date on which the event begins.', 'my-calendar' ); ?></dd>

							<dt><code>{daterange}</code></dt>
							<dd><?php _e( 'Beginning date to end date; excludes end date if same as beginning.', 'my-calendar' ); ?></dd>

							<dt><code>{multidate}</code></dt>
							<dd><?php _e( 'Multi-day events: an unordered list of dates/times. Otherwise, beginning date/time.', 'my-calendar' ); ?></dd>

							<dt><code>{author}</code></dt>
							<dd><?php _e( 'Author who posted the event.', 'my-calendar' ); ?></dd>

							<dt><code>{host}</code></dt>
							<dd><?php _e( 'Name of the assigned host for the event.', 'my-calendar' ); ?></dd>

							<dt><code>{shortdesc}</code></dt>
							<dd><?php _e( 'Short event description.', 'my-calendar' ); ?></dd>

							<dt><code>{description}</code></dt>
							<dd><?php _e( 'Description of the event.', 'my-calendar' ); ?></dd>

							<dt><code>{image}</code></dt>
							<dd><?php _e( 'Image associated with the event.', 'my-calendar' ); ?></dd>

							<dt><code>{link}</code></dt>
							<dd><?php _e( 'URL provided for the event.', 'my-calendar' ); ?></dd>

							<dt><code>{details}</code></dt>
							<dd><?php _e( 'Link to an auto-generated page containing information about the event.', 'my-calendar' ); ?>

							<dt><code>{event_open}</code></dt>
							<dd><?php _e( 'Whether event is currently open for registration.', 'my-calendar' ); ?></dd>

							<dt><code>{event_status}</code></dt>
							<dd><?php _e( 'Current status of event: either "Published" or "Reserved."', 'my-calendar' ); ?></dd>
						</dl>

						<h4><?php _e( 'Location Template Tags', 'my-calendar' ); ?></h4>
						<dl>
							<dt><code>{location}</code></dt>
							<dd><?php _e( 'Name of the location of the event.', 'my-calendar' ); ?></dd>

							<dt><code>{street}</code></dt>
							<dd><?php _e( 'First line of the site address.', 'my-calendar' ); ?></dd>

							<dt><code>{street2}</code></dt>
							<dd><?php _e( 'Second line of the site address.', 'my-calendar' ); ?></dd>

							<dt><code>{city}</code></dt>
							<dd><?php _e( 'City', 'my-calendar' ); ?></dd>

							<dt><code>{state}</code></dt>
							<dd><?php _e( 'State', 'my-calendar' ); ?></dd>

							<dt><code>{postcode}</code></dt>
							<dd><?php _e( 'Postal Code', 'my-calendar' ); ?></dd>

							<dt><code>{region}</code></dt>
							<dd><?php _e( 'Custom region.', 'my-calendar' ); ?></dd>

							<dt><code>{country}</code></dt>
							<dd><?php _e( 'Country for the event location.', 'my-calendar' ); ?></dd>

							<dt><code>{sitelink}</code></dt>
							<dd><?php _e( 'Output the URL for the location.', 'my-calendar' ); ?></dd>

							<dt><code>{hcard}</code></dt>
							<dd><?php _e( 'Event address in <a href="http://microformats.org/wiki/hcard">hcard</a> format.', 'my-calendar' ); ?></dd>

							<dt><code>{link_map}</code></dt>
							<dd><?php _e( 'Link to Google Map to the event, if address information is available.', 'my-calendar' ); ?></dd>
						</dl>
						<h4><?php _e( 'Category Template Tags', 'my-calendar' ); ?></h4>

						<dl>
							<dt><code>{category}</code></dt>
							<dd><?php _e( 'Name of the category of the event.', 'my-calendar' ); ?></dd>

							<dt><code>{icon}</code></dt>
							<dd><?php _e( 'URL for the event\'s category icon.', 'my-calendar' ); ?></dd>

							<dt><code>{color}</code></dt>
							<dd><?php _e( 'Hex code for the event\'s category color.', 'my-calendar' ); ?></dd>

							<dt><code>{cat_id}</code></dt>
							<dd><?php _e( 'ID of the category of the event.', 'my-calendar' ); ?></dd>
						</dl>
						<p>
							<a href="<?php echo admin_url( 'admin.php?page=my-calendar-help#templates' ); ?>"><?php _e( 'All Template Tags &raquo;', 'my-calendar' ); ?></a>
						</p>
					</div>
				</div>
			</div>
		<?php } ?>
		</div>
		</div>
	<?php
	}
}

// Function to deal with adding the calendar menus
function my_calendar_menu() {
	$icon_path = plugins_url( '/my-calendar/images' );
	if ( function_exists( 'add_object_page' ) ) {
		if ( get_option( 'mc_remote' ) != 'true' ) {
			add_object_page( __( 'My Calendar', 'my-calendar' ), __( 'My Calendar', 'my-calendar' ), 'mc_add_events', apply_filters( 'mc_modify_default', 'my-calendar' ), apply_filters( 'mc_modify_default_cb', 'edit_my_calendar' ), $icon_path . '/icon.png' );
		} else {
			add_object_page( __( 'My Calendar', 'my-calendar' ), __( 'My Calendar', 'my-calendar' ), 'mc_edit_settings', 'my-calendar', 'edit_my_calendar_config', $icon_path . '/icon.png' );
		}
	} else {
		if ( function_exists( 'add_menu_page' ) ) {
			if ( get_option( 'mc_remote' ) != 'true' ) {
				add_menu_page( __( 'My Calendar', 'my-calendar' ), __( 'My Calendar', 'my-calendar' ), 'mc_add_events', apply_filters( 'mc_modify_default', 'my-calendar' ), apply_filters( 'mc_modify_default_cb', 'edit_my_calendar' ), $icon_path . '/icon.png' );
			} else {
				add_menu_page( __( 'My Calendar', 'my-calendar' ), __( 'My Calendar', 'my-calendar' ), 'mc_edit_settings', 'my-calendar', 'edit_my_calendar_config', $icon_path . '/icon.png' );
			}
		}
	}
	if ( function_exists( 'add_submenu_page' ) ) {
		add_action( "admin_head", 'my_calendar_write_js' );
		add_action( "admin_head", 'my_calendar_add_styles' );
		if ( get_option( 'mc_remote' ) == 'true' ) {
		} else { // if we're accessing a remote page, remove these pages.
			$edit = add_submenu_page( apply_filters( 'mc_locate_events_page', 'my-calendar' ), __( 'Add New Event', 'my-calendar' ), __( 'Add New Event', 'my-calendar' ), 'mc_add_events', 'my-calendar', 'edit_my_calendar' );
			add_action( "load-$edit", 'mc_event_editing' );
			$manage = add_submenu_page( 'my-calendar', __( 'Manage Events', 'my-calendar' ), __( 'Manage Events', 'my-calendar' ), 'mc_add_events', 'my-calendar-manage', 'manage_my_calendar' );
			add_action( "load-$manage", 'mc_add_screen_option' );
			add_submenu_page( 'my-calendar', __( 'Event Categories', 'my-calendar' ), __( 'Manage Categories', 'my-calendar' ), 'mc_edit_cats', 'my-calendar-categories', 'my_calendar_manage_categories' );
			add_submenu_page( 'my-calendar', __( 'Event Locations', 'my-calendar' ), __( 'Manage Locations', 'my-calendar' ), 'mc_edit_locations', 'my-calendar-locations', 'my_calendar_manage_locations' );
			$groups = add_submenu_page( 'my-calendar', __( 'Event Groups', 'my-calendar' ), __( 'Manage Event Groups', 'my-calendar' ), 'mc_manage_events', 'my-calendar-groups', 'edit_my_calendar_groups' );
			add_action( "load-$groups", 'mc_add_screen_option' );
		}
		add_submenu_page( 'my-calendar', __( 'Style Editor', 'my-calendar' ), __( 'Style Editor', 'my-calendar' ), 'mc_edit_styles', 'my-calendar-styles', 'edit_my_calendar_styles' );
		add_submenu_page( 'my-calendar', __( 'Script Manager', 'my-calendar' ), __( 'Script Manager', 'my-calendar' ), 'mc_edit_behaviors', 'my-calendar-behaviors', 'edit_my_calendar_behaviors' );
		add_submenu_page( 'my-calendar', __( 'Template Editor', 'my-calendar' ), __( 'Template Editor', 'my-calendar' ), 'mc_edit_templates', 'my-calendar-templates', 'edit_mc_templates' );
		add_submenu_page( 'my-calendar', __( 'Settings', 'my-calendar' ), __( 'Settings', 'my-calendar' ), 'mc_edit_settings', 'my-calendar-config', 'edit_my_calendar_config' );
		add_submenu_page( 'my-calendar', __( 'My Calendar Help', 'my-calendar' ), __( 'Help', 'my-calendar' ), 'mc_view_help', 'my-calendar-help', 'my_calendar_help' );
	}
	if ( function_exists( 'mcs_submissions' ) ) {
		$permission = apply_filters( 'mcs_submission_permissions', 'manage_options' );
		add_action( "admin_head", 'my_calendar_sub_js' );
		add_action( "admin_head", 'my_calendar_sub_styles' );
		add_submenu_page( 'my-calendar', __( 'Event Submissions', 'my-calendar' ), __( 'Event Submissions', 'my-calendar' ), $permission, 'my-calendar-submissions', 'mcs_settings' );
		add_submenu_page( 'my-calendar', __( 'Payments', 'my-calendar' ), __( 'Payments', 'my-calendar' ), $permission, 'my-calendar-payments', 'mcs_sales_page' );
	}
}

function mc_event_editing() {
	$option = 'mc_show_on_page';
	$args   = array(
		'label'   => 'Show these fields',
		'default' => get_option( 'mc_input_options' ),
		'option'  => 'mc_show_on_page'
	);
	add_screen_option( $option, $args );
}

add_filter( 'screen_settings', 'mc_show_event_editing', 10, 2 );
function mc_show_event_editing( $status, $args ) {
	$return = $status;
	if ( $args->base == 'toplevel_page_my-calendar' ) {
		$input_options = get_user_meta( get_current_user_id(), 'mc_show_on_page', true );
		$settings_options = get_option( 'mc_input_options' );
		if ( ! is_array( $input_options ) ) {
			$input_options = $settings_options;
		}
		$input_labels = array(
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
			'event_access'            => __( 'Event Accessibility' )
		);
		$output       = '';
		foreach ( $input_options as $key => $value ) {
			$checked = ( $value == 'on' ) ? "checked='checked'" : '';
			$allowed = ( isset( $settings_options[ $key ] ) && $settings_options[ $key ] == 'on' ) ? true : false;
			if ( ! ( current_user_can( 'manage_options' ) && get_option( 'mc_input_options_administrators' ) == 'true' ) && ! $allowed ) {
				// don't display options if this user can't use them.
				$output .= "<input type='hidden' name='mc_show_on_page[$key]' value='off' />";
			} else {
				if ( isset( $input_labels[ $key ] ) ) {
					// don't show if label doesn't exist. That means I removed the option.
					$output .= "<label for='mci_$key'><input type='checkbox' id='mci_$key' name='mc_show_on_page[$key]' value='on' $checked /> $input_labels[$key]</label>";
				}
			}
		}
		$button = get_submit_button( __( 'Apply' ), 'button', 'screen-options-apply', false );
		$return .= "
	<fieldset>
	<legend>" . __( 'Event editing fields to show', 'my-calendar' ) . "</legend>
	<div class='metabox-prefs'>
		<div><input type='hidden' name='wp_screen_options[option]' value='mc_show_on_page' /></div>
		<div><input type='hidden' name='wp_screen_options[value]' value='yes' /></div>
		$output
	</div>
	</fieldset>
	<br class='clear'>
	$button";
	}

	return $return;
}

add_filter( 'set-screen-option', 'mc_set_event_editing', 11, 3 );
function mc_set_event_editing( $status, $option, $value ) {
	if ( 'mc_show_on_page' == $option ) {
		$orig  = get_option( 'mc_input_options' );
		$value = array();
		foreach ( $orig as $k => $v ) {
			if ( isset( $_POST['mc_show_on_page'][ $k ] ) ) {
				$value[ $k ] = 'on';
			} else {
				$value[ $k ] = 'off';
			}
		}
	}

	return $value;
}

function mc_add_screen_option() {
	$items_per_page = ( get_option( 'mc_num_per_page' ) ) ? get_option( 'mc_num_per_page' ) : 50;
	$option         = 'per_page';
	$args           = array(
		'label'   => 'Events',
		'default' => $items_per_page,
		'option'  => 'mc_num_per_page'
	);
	add_screen_option( $option, $args );
}

add_filter( 'set-screen-option', 'mc_set_screen_option', 10, 3 );
function mc_set_screen_option( $status, $option, $value ) {
	return $value;
}

// add shortcode interpreters
add_shortcode( 'my_calendar', 'my_calendar_insert' );
add_shortcode( 'my_calendar_upcoming', 'my_calendar_insert_upcoming' );
add_shortcode( 'my_calendar_today', 'my_calendar_insert_today' );
add_shortcode( 'my_calendar_locations', 'my_calendar_locations' );
add_shortcode( 'my_calendar_categories', 'my_calendar_categories' );
add_shortcode( 'my_calendar_access', 'my_calendar_access' );
add_shortcode( 'mc_filters', 'my_calendar_filters' );
add_shortcode( 'my_calendar_show_locations', 'my_calendar_show_locations_list' );
add_shortcode( 'my_calendar_event', 'my_calendar_show_event' );
add_shortcode( 'my_calendar_search', 'my_calendar_search' );

apply_filters( "debug", "MC Loaded" );