<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

function mc_generate() {
	if ( isset( $_POST['generator'] ) ) {
		$string = '';
		switch ( $_POST['shortcode'] ) {
			case 'main':
				$shortcode = 'my_calendar';
				break;
			case 'upcoming':
				$shortcode = 'my_calendar_upcoming';
				break;
			case 'today':
				$shortcode = 'my_calendar_today';
				break;
			default:
				$shortcode = 'my_calendar';
		}
		foreach ( $_POST as $key => $value ) {
			if ( $key != 'generator' && $key != 'shortcode' ) {
				if ( is_array( $value ) ) {
					if ( in_array( 'all', $value ) ) {
						unset( $value[0] );
					}
					$v = implode( ',', $value );
				} else {
					$v = $value;
				}
				if ( $v != '' ) {
					$string .= " $key=&quot;$v&quot;";
				}
			}
		}
		$output = $shortcode . $string;
		$output = apply_filters( 'mc_shortcode_generator', $output, $_POST );
		$return = "<div class='updated'><textarea readonly='readonly'>[$output]</textarea></div>";
		echo $return;
	}
}

function mc_generator( $type ) {
	?>
<form action="<?php echo admin_url( 'admin.php?page=my-calendar-help' ); ?>" method="POST" id="my-calendar-generate">
	<fieldset>
		<legend><strong><?php echo ucfirst( $type ); ?></strong>: <?php _e( 'Shortcode Attributes', 'my-calendar' ); ?>
		</legend>
		<div id="mc-generator" class="generator">
			<input type='hidden' name='shortcode' value='<?php echo $type; ?>'/>
			<?php // Common Elements to all Shortcodes ?>
			<p><?php echo my_calendar_categories_list( 'select', 'admin' ); ?></p>

			<p>
				<label for="ltype"><?php _e( 'Location filter type:', 'my-calendar' ); ?></label>
				<select name="ltype" id="ltype">
					<option value="" selected="selected"><?php _e( 'All locations', 'my-calendar' ); ?></option>
					<option value='event_label'><?php _e( 'Location Name', 'my-calendar' ); ?></option>
					<option value='event_city'><?php _e( 'City', 'my-calendar' ); ?></option>
					<option value='event_state'><?php _e( 'State', 'my-calendar' ); ?></option>
					<option value='event_postcode'><?php _e( 'Postal Code', 'my-calendar' ); ?></option>
					<option value='event_country'><?php _e( 'Country', 'my-calendar' ); ?></option>
					<option value='event_region'><?php _e( 'Region', 'my-calendar' ); ?></option>
				</select>
			</p>
			<p>
				<label for="lvalue" id='lval'><?php _e( 'Location filter value:', 'my-calendar' ); ?></label>
				<input type="text" name="lvalue" id="lvalue" aria-labelledby='lval location-info'/>
			</p>

			<p id='location-info'>
				<?php _e( '<strong>Note:</strong> If you provide a location filter value, it must be an exact match for that information as saved with your events. (e.g. "Saint Paul" is not equivalent to "saint paul" or "St. Paul")', 'my-calendar' ); ?>
			</p>
			<?php
			// Grab users and list them
			$users   = my_calendar_getUsers();
			$options = '';
			foreach ( $users as $u ) {
				$options = '<option value="' . $u->ID . '">' . $u->display_name . "</option>\n";
			} ?>
			<p>
				<label for="author"><?php _e( 'Limit by Author', 'my-calendar' ); ?></label>
				<select name="author[]" id="author" multiple="multiple">
					<option value="all"><?php _e( 'All authors', 'my-calendar' ); ?></option>
					<option value="current"><?php _e( 'Currently logged-in user', 'my-calendar' ); ?></option>
					<?php echo $options; ?>
				</select>
			</p>
			<p>
				<label for="host"><?php _e( 'Limit by Host', 'my-calendar' ); ?></label>
				<select name="host[]" id="host" multiple="multiple">
					<option value="all"><?php _e( 'All hosts', 'my-calendar' ); ?></option>
					<option value="current"><?php _e( 'Currently logged-in user', 'my-calendar' ); ?></option>
					<?php echo $options; ?>
				</select>
			</p>
			<?php
			// Main shortcode only
			if ( $type == 'main' ) {
				?>
				<p>
					<label for="format"><?php _e( 'Format', 'my-calendar' ); ?></label>
					<select name="format" id="format">
						<option value="calendar" selected="selected"><?php _e( 'Grid', 'my-calendar' ); ?></option>
						<option value="list"><?php _e( 'List', 'my-calendar' ); ?></option>
					</select>
				</p>
				<p>
					<label for="time"><?php _e( 'Time Segment', 'my-calendar' ); ?></label>
					<select name="time" id="time">
						<option value="month" selected="selected"><?php _e( 'Month', 'my-calendar' ); ?></option>
						<option value="month+1"><?php _e( 'Next Month', 'my-calendar' ); ?></option>
						<option value="week"><?php _e( 'Week', 'my-calendar' ); ?></option>
						<option value="day"><?php _e( 'Day', 'my-calendar' ); ?></option>
					</select>
				</p>
				<p id='navigation-info'>
					<?php _e( "For navigational fields above and below the calendar: the defaults specified in your settings will be used if the attribute is left blank. Use <code>none</code> to hide all navigation elements.", 'my-calendar' ); ?>
				</p>
				<p>
					<label for="above" id='labove'><?php _e( 'Navigation above calendar', 'my-calendar' ); ?></label>
					<input type="text" name="above" id="above" value="nav,toggle,jump,print,timeframe"
					       aria-labelledby='labove navigation-info'/><br/>
				</p>
				<p>
					<label for="below" id='lbelow'><?php _e( 'Navigation below calendar', 'my-calendar' ); ?></label>
					<input type="text" name="below" id="below" value="key,feeds"
					       aria-labelledby='lbelow navigation-info'/><br/>
				</p>
			<?php
			}
			if ( $type == 'upcoming' || $type == 'today' ) {
				// Upcoming Events & Today's Events shortcodes
				?>
				<p>
					<label for="fallback"><?php _e( 'Fallback Text', 'my-calendar' ); ?></label>
					<input type="text" name="fallback" id="fallback" value=""/>
				</p>
				<p>
					<label for="template"><?php _e( 'Template', 'my-calendar' ); ?></label>
					<textarea cols="40" rows="4" name="template"
					          id="template"><?php echo htmlentities( "<strong>{date}</strong>, {time}: {link_title}" ); ?></textarea>
				</p>
			<?php
			}
			if ( $type == 'upcoming' ) {
				// Upcoming events only
				?>
				<p>
					<label for="before"><?php _e( 'Events/Days Before Current Day', 'my-calendar' ); ?></label>
					<input type="number" name="before" id="before" value=""/>
				</p>
				<p>
					<label for="after"><?php _e( 'Events/Days After Current Day', 'my-calendar' ); ?></label>
					<input type="number" name="after" id="after" value=""/>
				</p>
				<p>
					<label for="skip"><?php _e( 'Events/Days to Skip', 'my-calendar' ); ?></label>
					<input type="number" name="skip" id="skip" value=""/>
				</p>
				<p>
					<label for="show_today"><?php _e( 'Fallback', 'my-calendar' ); ?></label>
					<input type="checkbox" name="show_today" id="show_today" value="yes"/>
				</p>
				<p>
					<label for="type"><?php _e( 'Type of Upcoming Events List', 'my-calendar' ); ?></label>
					<select name="type" id="type">
						<option value="events" selected="selected"><?php _e( 'Events', 'my-calendar' ); ?></option>
						<option value="month"><?php _e( 'Current Month', 'my-calendar' ); ?></option>
						<option value="month+1"><?php _e( 'Next Month', 'my-calendar' ); ?></option>
						<option value="year"><?php _e( 'Current Year', 'my-calendar' ); ?></option>
						<option value="days"><?php _e( 'Days', 'my-calendar' ); ?></option>
					</select>
				</p>
				<p>
					<label for="order"><?php _e( 'Event Order', 'my-calendar' ); ?></label>
					<select name="order" id="order">
						<option value="asc" selected="selected"><?php _e( 'Ascending', 'my-calendar' ); ?></option>
						<option value="desc"><?php _e( 'Descending', 'my-calendar' ); ?></option>
					</select>
				</p>
			<?php } ?>
		</div>
	</fieldset>
	<p>
		<input type="submit" class="button-primary" name="generator"
		       value="<?php _e( 'Generate Shortcode', 'my-calendar' ); ?>"/>
	</p>
	</form><?php
}