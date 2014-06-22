<?php

class GCE_Event {
	
	private $feed;
	
	function __construct( GCE_Feed $feed, $id, $title, $description, $location, $start_time, $end_time, $link ) {
		
		$this->feed = $feed;
		$this->id = $id;
		$this->title = $title;
		$this->description = $description;
		$this->location = $location;
		$this->start_time = $start_time;
		$this->end_time = $end_time;
		$this->link = $link;
		

		//Calculate which day type this event is (SWD = single whole day, SPD = single part day, MWD = multiple whole day, MPD = multiple part day)
		if ( ( $start_time + 86400 ) <= $end_time ) {
			if ( ( $start_time + 86400 ) == $end_time ) {
				$this->day_type = 'SWD';
			} else {
				if ( ( '12:00 am' == date( 'g:i a', $start_time ) ) && ( '12:00 am' == date( 'g:i a', $end_time ) ) ) {
					$this->day_type = 'MWD';
				} else {
					$this->day_type = 'MPD';
				}
			}
		} else {
			$this->day_type = 'SPD';
		}
	}
	
	//Returns an array of days (as UNIX timestamps) that this events spans
	function get_days() {
		//Round start date to nearest day
		$start_time = mktime( 0, 0, 0, date( 'm', $this->start_time ), date( 'd', $this->start_time ) , date( 'Y', $this->start_time ) );

		$days = array();

		//If multiple day events should be handled, and this event is a multi-day event, add multiple day event to required days
		if ( $this->feed->multiple_day_events && ( 'MPD' == $this->day_type || 'MWD' == $this->day_type ) ) {
			$on_next_day = true;
			$next_day = $start_time;

			while ( $on_next_day ) {
				//If the end time of the event is after 00:00 on the next day (therefore, not doesn't end on this day)
				if ( $this->end_time > $next_day ) {
					//If $next_day is within the event retrieval date range (specified by retrieve events from / until settings)
					if ( $next_day >= $this->feed->start && $next_day < $this->feed->end ) {
						$days[] = $next_day;
					}
				} else {
					$on_next_day = false;
				}
				$next_day += 86400;
			}
		} else {
			//Add event into array of events for that day
			$days[] = $start_time;
		}

		return $days;
	}

	//Returns the markup for this event, so that it can be used in the construction of a grid / list
	function get_event_markup( $display_type, $num_in_day, $num ) {
		//Set the display type (either tooltip or list)
		$this->type = $display_type;

		//Set which number event this is in day (first in day etc)
		$this->num_in_day = $num_in_day;

		//Set the position of this event in array of events currently being processed
		$this->pos = $num;

		$this->time_now = current_time( 'timestamp' );

		return $this->use_old_display_options();
	}
	
	//Return the event markup using the old display options
	function use_old_display_options() {
		$display_options = array(
					'display_start'         => 'time',
					'display_end'           => 'time-date',
					'display_location'      => '',
					'display_desc'          => '',
					'display_link'          => 1,
					'display_start_text'    => 'Start:',
					'display_end_text'      => 'End:',
					'display_location_text' => '',
					'display_desc_text'     => '',
					'display_desc_limit'    => '',
					'display_link_text'     => 'Click here for event',
					'display_link_target'   => '',
					'display_separator'     => ', '
				);

		$markup = '<p class="gce-' . $this->type . '-event">' . esc_html( $this->title )  . '</p>';

		$start_end = array();

		//If start date / time should be displayed, set up array of start date and time
		if ( 'none' != $display_options['display_start'] ) {
			$sd = $this->start_time;
			$start_end['start'] = array(
				'time' => date_i18n( $this->feed->time_format, $sd ),
				'date' => date_i18n( $this->feed->date_format, $sd )
			);
		}

		//If end date / time should be displayed, set up array of end date and time
		if ( 'none' != $display_options['display_end'] ) {
			$ed = $this->end_time;
			$start_end['end'] = array(
				'time' => date_i18n( $this->feed->time_format, $ed ),
				'date' => date_i18n( $this->feed->date_format, $ed )
			);
		}

		//Add the correct start / end, date / time information to $markup
		foreach ( $start_end as $start_or_end => $info ) {
			$markup .= '<p class="gce-' . $this->type . '-' . $start_or_end . '"><span>' . esc_html( $display_options['display_' . $start_or_end . '_text'] ) . '</span> ';

			switch ( $display_options['display_' . $start_or_end] ) {
				case 'time': $markup .= esc_html( $info['time'] );
					break;
				case 'date': $markup .= esc_html( $info['date'] );
					break;
				case 'time-date': $markup .= esc_html( $info['time'] . $display_options['display_separator'] . $info['date'] );
					break;
				case 'date-time': $markup .= esc_html( $info['date'] . $display_options['display_separator'] . $info['time'] );
			}

			$markup .= '</p>';
			
			//$markup .= '<pre>Startend: ' . print_r( $start_end, true ) . '</pre>';
		}

		//If location should be displayed (and is not empty) add to $markup
		if ( isset( $display_options['display_location'] ) ) {
			$event_location = $this->location;
			if ( '' != $event_location )
				$markup .= '<p class="gce-' . $this->type . '-loc"><span>' . esc_html( $display_options['display_location_text'] ) . '</span> ' . esc_html( $event_location ) . '</p>';
		}

		//If description should be displayed (and is not empty) add to $markup
		if ( isset($display_options['display_desc'] ) ) {
			$event_desc = $this->description;

			if ( '' != $event_desc ) {
				//Limit number of words of description to display, if required
				if ( '' != $display_options['display_desc_limit'] ) {
					preg_match( '/([\S]+\s*){0,' . $display_options['display_desc_limit'] . '}/', $this->description, $event_desc );
					$event_desc = trim( $event_desc[0] );
				}

				$markup .= '<p class="gce-' . $this->type . '-desc"><span>' . $display_options['display_desc_text'] . '</span> ' . make_clickable( nl2br( esc_html( $event_desc ) ) ) . '</p>';
			}
		}

		//If link should be displayed add to $markup
		if ( isset($display_options['display_link'] ) )
			$markup .= '<p class="gce-' . $this->type . '-link"><a href="' . esc_url( $this->link ) . '&amp;ctz=' . esc_html( $this->feed->timezone_offset ) . '"' . ( ( isset( $display_options['display_link_target'] ) ) ? ' target="_blank"' : '' ) . '>' . esc_html( $display_options['display_link_text'] ) . '</a></p>';

		return $markup;
	}

	//Returns the difference between two times in human-readable format. Based on a patch for human_time_diff posted in the WordPress trac (http://core.trac.wordpress.org/ticket/9272) by Viper007Bond 
	function gce_human_time_diff( $from, $to = '', $limit = 1 ) {
		$units = array(
			31556926 => array( __( '%s year', GCE_TEXT_DOMAIN ),  __( '%s years', GCE_TEXT_DOMAIN ) ),
			2629744  => array( __( '%s month', GCE_TEXT_DOMAIN ), __( '%s months', GCE_TEXT_DOMAIN ) ),
			604800   => array( __( '%s week', GCE_TEXT_DOMAIN ),  __( '%s weeks', GCE_TEXT_DOMAIN ) ),
			86400    => array( __( '%s day', GCE_TEXT_DOMAIN ),   __( '%s days', GCE_TEXT_DOMAIN ) ),
			3600     => array( __( '%s hour', GCE_TEXT_DOMAIN ),  __( '%s hours', GCE_TEXT_DOMAIN ) ),
			60       => array( __( '%s min', GCE_TEXT_DOMAIN ),   __( '%s mins', GCE_TEXT_DOMAIN ) ),
		);

		if ( empty( $to ) )
			$to = time(); 

		$from = (int) $from;
		$to   = (int) $to;
		$diff = (int) abs( $to - $from );

		$items = 0;
		$output = array();

		foreach ( $units as $unitsec => $unitnames ) {
			if ( $items >= $limit )
				break; 

			if ( $diff < $unitsec )
				continue; 

			$numthisunits = floor( $diff / $unitsec ); 
			$diff = $diff - ( $numthisunits * $unitsec ); 
			$items++; 

			if ( $numthisunits > 0 )
				$output[] = sprintf( _n( $unitnames[0], $unitnames[1], $numthisunits ), $numthisunits ); 
		} 

		$seperator = _x( ', ', 'human_time_diff' ); 

		if ( ! empty( $output ) ) {
			return implode( $seperator, $output ); 
		} else {
			$smallest = array_pop( $units ); 
			return sprintf( $smallest[0], 1 ); 
		} 
	}
	
}