<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

// EVENTS
/**
 * AJAX Controller - Fetch events in order to display them
 * @version 1.9.0
 */
function bookacti_controller_fetch_template_events() {
	$template_id = intval( $_POST[ 'template_id' ] );

	// Check nonce and capabilities
	$is_nonce_valid	= check_ajax_referer( 'bookacti_fetch_template_events', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'fetch_template_events' ); }
	
	$is_allowed = current_user_can( 'bookacti_read_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed || ! $template_id ) { bookacti_send_json_not_allowed( 'fetch_template_events' ); }

	$event_id	= intval( $_POST[ 'event_id' ] );
	$interval = ! empty( $_POST[ 'interval' ] ) ? bookacti_sanitize_events_interval( $_POST[ 'interval' ] ) : array();

	$events_args = array( 'templates' => array( $template_id ), 'events' => $event_id ? array( $event_id ) : array(), 'interval' => $interval, 'past_events' => true );
	$events	= bookacti_fetch_events_for_calendar_editor( $events_args );
	bookacti_send_json( array( 
		'status' => 'success', 
		'events' => $events[ 'events' ] ? $events[ 'events' ] : array(),
		'events_data' => $events[ 'data' ] ? $events[ 'data' ] : array()
	), 'fetch_template_events' );
}
add_action( 'wp_ajax_bookactiFetchTemplateEvents', 'bookacti_controller_fetch_template_events' );


/**
 * AJAX Controller - Add new event on calendar
 * @version 1.11.0
 */
function bookacti_controller_insert_event() {
	// Check nonce and capabilities
	$is_nonce_valid	= check_ajax_referer( 'bookacti_edit_template', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'insert_event' ); }
	
	$template_id = intval( $_POST[ 'template_id' ] );
	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'insert_event' ); }

	$event_data = bookacti_sanitize_event_data( $_POST );
	
	$event_id = bookacti_insert_event( $event_data );
	if( ! $event_id ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'not_inserted' ), 'insert_event' ); }
	
	$meta = array_intersect_key( $event_data, bookacti_get_event_default_meta() );
	if( $meta && $event_id ) { bookacti_update_metadata( 'event', $event_id, $meta ); }
	
	$events = bookacti_fetch_events_for_calendar_editor( array( 'events' => array( $event_id ) ) );

	do_action( 'bookacti_event_inserted', $event_id, $events );

	bookacti_send_json( array( 
		'status' => 'success', 
		'event_id' => $event_id,
		'event_data' => $events[ 'data' ][ $event_id ] ? $events[ 'data' ][ $event_id ] : array(),
	), 'insert_event' );
}
add_action( 'wp_ajax_bookactiInsertEvent', 'bookacti_controller_insert_event' );


/**
 * AJAX Controller - Update event dates (move or resize an event in the editor)
 * @since 1.10.0 (was bookacti_controller_move_or_resize_event)
 * @version 1.11.0
 */
function bookacti_controller_update_event_dates() {
	// Check nonce
	$is_nonce_valid = check_ajax_referer( 'bookacti_edit_template', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'update_event_dates' ); }

	// Get event data
	$event_id = intval( $_POST[ 'event_id' ] );
	$old_event = bookacti_get_event_by_id( $event_id );
	
	if( ! $old_event ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'event_not_found' ), 'update_event_dates' ); }
	
	// Check capabilities
	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $old_event->template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'update_event_dates' ); }
	
	// Sanitize update data
	$forced_update		= ! empty( $_POST[ 'forced_update' ] ) ? true : false;
	$send_notifications	= ! empty( $_POST[ 'send_notifications' ] ) ? true : false;
	
	$timezone = bookacti_get_setting_value( 'bookacti_general_settings', 'timezone' );
	$now_dt = new DateTime( 'now', new DateTimeZone( $timezone ) );
	
	// Sanitize new event data
	$new_event_start	= bookacti_sanitize_datetime( $_POST[ 'event_start' ] );
	$new_event_end		= bookacti_sanitize_datetime( $_POST[ 'event_end' ] );
	
	// Compute delta
	$delta_days		= ! empty( $_POST[ 'delta_days' ] ) ? intval( $_POST[ 'delta_days' ] ) : 0;
	$delta_days_di	= new DateInterval( 'P' . abs( $delta_days ). 'D' );
	$delta_days_di->invert = $delta_days < 0 ? 1 : 0;
	$old_event_start_dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $old_event->start, new DateTimeZone( $timezone ) );
	$new_event_start_dt = clone $old_event_start_dt;
	$new_event_start_dt->add( $delta_days_di );
	$new_event_start_dt->setTime( substr( $new_event_start, 11, 2 ), substr( $new_event_start, 14, 2 ), substr( $new_event_start, 17, 2 ) );
	$delta_seconds = $new_event_start_dt->format( 'U' ) - $old_event_start_dt->format( 'U' );
	
	// Get the event's occurrences
	$events_exceptions = bookacti_get_exceptions_by_event( array( 'events' => array( $event_id ), 'types' => array( 'date' ), 'only_values' => 1 ) );
	$event_exceptions = isset( $events_exceptions[ $event_id ] ) ? $events_exceptions[ $event_id ] : array();
	$occurrences = bookacti_get_occurrences_of_repeated_event( $old_event, array( 'exceptions_dates' => $event_exceptions, 'past_events' => 1 ) );
	
	// Get the bookings to reschedule
	$filters = bookacti_format_booking_filters( array( 'event_id' => $event_id ) );
	$old_bookings = bookacti_get_bookings( $filters );
	$active_bookings_nb = 0;
	$notifications_nb = 0;
	$bookings_to_reschedule = array();
	
	foreach( $old_bookings as $booking_id => $old_booking ) {
		// Check if the booking was made on one of the occurrences
		$is_on_occurrence = false;
		foreach( $occurrences as $occurrence ) {
			if( intval( $old_booking->event_id ) === intval( $occurrence[ 'id' ] ) && $old_booking->event_start === $occurrence[ 'start' ] && $old_booking->event_end === $occurrence[ 'end' ] ) {
				$is_on_occurrence = true;
				$bookings_to_reschedule[ $booking_id ] = $old_booking;
				break;
			}
		}

		if( ! $is_on_occurrence || ! $old_booking->active ) { continue; }
		++$active_bookings_nb;

		// If the event is repeated, send notifications for future occurrences only
		$send = true;
		if( $old_event->repeat_freq && $old_event->repeat_freq !== 'none' ) {
			// Compute new booking start
			$new_booking_start_dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $old_booking->event_start, new DateTimeZone( $timezone ) );
			$new_booking_start_dt->add( $delta_days_di );
			$new_booking_start_dt->setTime( substr( $new_event_start, 11, 2 ), substr( $new_event_start, 14, 2 ), substr( $new_event_start, 17, 2 ) );
			if( $new_booking_start_dt <= $now_dt ) { $send = false; }
		}
		
		$send = apply_filters( 'bookacti_send_event_rescheduled_notification_count', $send, $old_booking, $old_event, $delta_seconds );
		if( $send ) { ++$notifications_nb; }
	}
	
	// Check if the event has active bookings
	if( ! $forced_update && $active_bookings_nb > 0 ) {
		$bookings_nb = count( $bookings_to_reschedule ) . ' ' . esc_html( _n( 'booking', 'bookings', count( $bookings_to_reschedule ), 'booking-activities' ) );
		$notifications_nb = $notifications_nb . ' ' . esc_html( _n( 'notification', 'notifications', $notifications_nb, 'booking-activities' ) );
		bookacti_send_json( array( 'status' => 'failed', 'error' => 'has_bookings', 'bookings_nb' => $bookings_nb, 'notifications_nb' => $notifications_nb ), 'update_event_dates' );
	}

	// Delay by the same amount of time the repetion period
	$repeat_from_dt	= $old_event->repeat_from && $old_event->repeat_freq && $old_event->repeat_freq !== 'none' ? DateTime::createFromFormat( 'Y-m-d', $old_event->repeat_from ) : false;
	$repeat_to_dt	= $old_event->repeat_to && $old_event->repeat_freq && $old_event->repeat_freq !== 'none' ? DateTime::createFromFormat( 'Y-m-d', $old_event->repeat_to ) : false;
	
	if( $repeat_from_dt && $repeat_to_dt && $delta_days !== 0 ) { 
		$repeat_from_dt->add( $delta_days_di ); 
		$repeat_to_dt->add( $delta_days_di );
	}
	
	$new_event_repeat_from	= $repeat_from_dt ? $repeat_from_dt->format( 'Y-m-d' ) : 'null';
	$new_event_repeat_to	= $repeat_to_dt ? $repeat_to_dt->format( 'Y-m-d' ) : 'null';
	
	// Update the event
	$event_data = array_merge( (array) $old_event, array( 'start' => $new_event_start, 'end' => $new_event_end, 'repeat_from' => $new_event_repeat_from, 'repeat_to' => $new_event_repeat_to ) );
	$event_data = bookacti_sanitize_event_data( array_merge( $event_data, array( 'exceptions_dates' => $event_exceptions ) ) );
	$updated = bookacti_update_event( $event_data );

	if( $updated === false ){ bookacti_send_json( array( 'status' => 'failed', 'error' => 'not_updated' ), 'update_event_dates' ); }
	if( $updated === 0 )	{ bookacti_send_json( array( 'status' => 'no_changes' ), 'update_event_dates' ); }
	
	// Update exceptions
	$new_event = (object) $event_data;
	$updated_excep = bookacti_update_exceptions( $event_id, $event_data[ 'exceptions_dates' ] );
	
	$new_event_start_time = substr( $new_event->start, 11, 8 );
	$new_event_end_time	= substr( $new_event->end, 11, 8 );
	
	// Maybe update grouped events if the event belong to a group
	bookacti_shift_grouped_event_dates( $event_id, $delta_seconds, $new_event_start_time, $new_event_end_time );

	// Maybe update bookings
	if( $bookings_to_reschedule ) {
		bookacti_shift_bookings_dates( array_keys( $bookings_to_reschedule ), $delta_seconds, $new_event_start_time, $new_event_end_time );
	}
	
	// Maybe send notifications
	if( $forced_update && $send_notifications && $bookings_to_reschedule ) {
		bookacti_send_event_rescheduled_notifications( $old_event, $bookings_to_reschedule, $delta_seconds );
	}

	// Fetch new events
	$interval = ! empty( $_POST[ 'interval' ] ) ? bookacti_sanitize_events_interval( $_POST[ 'interval' ] ) : array();
	$events = bookacti_fetch_events_for_calendar_editor( array( 'events' => array( $event_id ), 'interval' => $interval ) );
	$exceptions	= bookacti_get_exceptions_by_event( array( 'events' => array( $event_id ) ) );
	
	do_action( 'bookacti_event_dates_updated', $old_event, $new_event, $delta_days, $events, $exceptions );

	bookacti_send_json( array( 
		'status' => 'success', 
		'events' => $events[ 'events' ] ? $events[ 'events' ] : array(),
		'event_data' => $events[ 'data' ][ $event_id ] ? $events[ 'data' ][ $event_id ] : array(),
		'exceptions' => $exceptions
	), 'update_event_dates' );
}
add_action( 'wp_ajax_bookactiUpdateEventDates', 'bookacti_controller_update_event_dates' );


/**
 * AJAX Controller - Duplicate an event
 * @since 1.10.0
 * @version 1.11.0
 */
function bookacti_controller_duplicate_event() {
	// Check nonce
	$is_nonce_valid = check_ajax_referer( 'bookacti_edit_template', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'duplicate_event' ); }

	// Get event
	$event_id = intval( $_POST[ 'event_id' ] );
	$event = bookacti_get_event_by_id( $event_id );
	if( ! $event ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'event_not_found' ), 'duplicate_event' ); }
	
	// Check capabilities
	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $event->template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'duplicate_event' ); }
	
	// Compute new event repetition period
	$repeat_from_dt	= $event->repeat_from ? DateTime::createFromFormat( 'Y-m-d', $event->repeat_from ) : false;
	$repeat_to_dt	= $event->repeat_to ? DateTime::createFromFormat( 'Y-m-d', $event->repeat_to ) : false;
	$delta_days		= ! empty( $_POST[ 'delta_days' ] ) ? intval( $_POST[ 'delta_days' ] ) : 0;
	
	// Delay by the same amount of time the repetion period
	if( $event->repeat_freq !== 'none' && $repeat_from_dt && $repeat_to_dt && $delta_days !== 0 ) { 
		$delta_days_di = new DateInterval( 'P' . abs( $delta_days ). 'D' );
		$delta_days_di->invert = $delta_days < 0 ? 1 : 0;
		$repeat_from_dt->add( $delta_days_di ); 
		$repeat_to_dt->add( $delta_days_di );
	}
	
	// Get new event dates
	$new_event_start = bookacti_sanitize_datetime( $_POST[ 'event_start' ] );
	$new_event_end = bookacti_sanitize_datetime( $_POST[ 'event_end' ] );
	$new_event_repeat_from	= $repeat_from_dt ? $repeat_from_dt->format( 'Y-m-d' ) : 'null';
	$new_event_repeat_to	= $repeat_to_dt ? $repeat_to_dt->format( 'Y-m-d' ) : 'null';
	
	// Get event exceptions
	$events_exceptions = bookacti_get_exceptions_by_event( array( 'events' => array( $event_id ), 'types'	=> array( 'date' ), 'only_values' => 1 ) );
	$event_exceptions = isset( $events_exceptions[ $event_id ] ) ? $events_exceptions[ $event_id ] : array();
	
	// Get new event data
	$event_data = array_merge( (array) $event, array( 'start' => $new_event_start, 'end' => $new_event_end, 'repeat_from' => $new_event_repeat_from, 'repeat_to' => $new_event_repeat_to ) );
	$event_data = bookacti_sanitize_event_data( array_merge( $event_data, array( 'exceptions_dates' => $event_exceptions ) ) );
	
	// Insert the new event
	$new_event_id = bookacti_insert_event( $event_data );
	if( ! $new_event_id ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'not_updated' ), 'duplicate_event' ); }
	
	// Duplicate event metadata
	bookacti_duplicate_metadata( 'event', $event_id, $new_event_id );
	
	// Update exceptions
	$updated_excep = bookacti_update_exceptions( $new_event_id, $event_data[ 'exceptions_dates' ] );
	
	// Fetch new events and exceptions
	$interval	= ! empty( $_POST[ 'interval' ] ) ? bookacti_sanitize_events_interval( $_POST[ 'interval' ] ) : array();
	$events		= bookacti_fetch_events_for_calendar_editor( array( 'events' => array( $new_event_id ), 'interval' => $interval ) );
	$exceptions	= bookacti_get_exceptions_by_event( array( 'events' => array( $new_event_id ) ) );

	do_action( 'bookacti_event_duplicated', $event_id, $new_event_id, $events, $exceptions );

	bookacti_send_json( array( 
		'status'		=> 'success', 
		'event_id'		=> $new_event_id, 
		'events'		=> $events[ 'events' ] ? $events[ 'events' ] : array(),
		'event_data'	=> $events[ 'data' ][ $new_event_id ] ? $events[ 'data' ][ $new_event_id ] : array(),
		'exceptions'	=> $exceptions 
	), 'duplicate_event' );
}
add_action( 'wp_ajax_bookactiDuplicateEvent', 'bookacti_controller_duplicate_event' );


/**
 * AJAX Controller - Update event
 * @since 1.2.2 (was bookacti_controller_update_event_data)
 * @version 1.11.0
 */
function bookacti_controller_update_event() {
	// Check nonce
	$is_nonce_valid = check_ajax_referer( 'bookacti_update_event_data', 'nonce_update_event_data', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'update_event' ); }

	$event_id = intval( $_POST[ 'id' ] );
	$old_event = bookacti_get_event_by_id( $event_id );
	if( ! $old_event ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'event_not_found' ), 'update_event' ); }
	
	// Check capabilities
	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && $old_event && bookacti_user_can_manage_template( $old_event->template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'update_event' ); }
	
	// Sanitize repeat_days
	$repeat_days = '';
	if( ! empty( $_POST[ 'repeat_days' ] ) && is_array( $_POST[ 'repeat_days' ] ) ) {
		$repeat_days_array = array();
		foreach( $_POST[ 'repeat_days' ] as $day ) {
			if( is_numeric( $day ) && in_array( intval( $day ), array( 0, 1, 2, 3, 4, 5, 6 ), true ) ) { $repeat_days_array[] = intval( $day ); }
		}
		$repeat_days = implode( '_', array_unique( $repeat_days_array ) );
	}
	
	// Sanitize repeat_monthly_type
	$repeat_monthly_type_raw = ! empty( $_POST[ 'repeat_monthly_type' ] ) ? sanitize_title_with_dashes( $_POST[ 'repeat_monthly_type' ] ) : '';
	$repeat_monthly_type = in_array( $repeat_monthly_type_raw, array( 'nth_day_of_month', 'last_day_of_month', 'nth_day_of_week', 'last_day_of_week' ), true ) ? $repeat_monthly_type_raw : '';
	
	// Set repeat_on according to repeat_freq
	$repeat_freq = ! empty( $_POST[ 'repeat_freq' ] ) ? sanitize_title_with_dashes( $_POST[ 'repeat_freq' ] ) : '';
	$repeat_on = $repeat_freq === 'weekly' ? $repeat_days : ( $repeat_freq === 'monthly' ? $repeat_monthly_type : '' );
	
	// If the event is no longer repeated, update its dates to keep the current occurence
	$event_dates = $old_event->repeat_freq && ( ! $repeat_freq || $repeat_freq === 'none' ) ? array() : array( 'start' => $old_event->start, 'end' => $old_event->end );
	
	// Get new event data
	$event_data = bookacti_sanitize_event_data( array_merge( $_POST, $event_dates, array( 'repeat_on' => $repeat_on ) ) );
	
	// Check if input data are complete and consistent 
	$event_validation = bookacti_validate_event_data( $event_data );
	if( $event_validation[ 'status' ] !== 'success' ) { bookacti_send_json( $event_validation, 'update_event' ); }

	// Update event data
	$updated = bookacti_update_event( $event_data );

	// If event repeat frequency has changed, we must remove this event from all groups
	if( $event_data[ 'repeat_freq' ] !== $old_event->repeat_freq ) {
		bookacti_delete_event_from_groups( $event_id );
	}

	// If the repetition dates have changed, we must delete out of range grouped events
	else if( $event_data[ 'repeat_from' ] !== $old_event->repeat_from || $event_data[ 'repeat_to' ] !== $old_event->repeat_to ) {
		bookacti_delete_out_of_range_occurrences_from_groups( $event_id );
	}
	
	// Update meta
	$meta = array_intersect_key( $event_data, bookacti_get_event_default_meta() );
	if( $meta ) { 
		$updated_meta = bookacti_update_metadata( 'event', $event_id, $meta );
		if( is_numeric( $old_event ) && is_numeric( $updated_meta ) ) { $updated += $updated_meta; }
	}
	
	// Update exceptions
	$updated_excep = bookacti_update_exceptions( $event_id, $event_data[ 'exceptions_dates' ] );
	if( is_numeric( $updated ) && is_numeric( $updated_excep ) ) { $updated += $updated_excep; }
	
	// if one of the elements has been updated, consider as success
	if(	is_numeric( $updated ) && $updated > 0 ){
		// Retrieve new events
		$interval	= ! empty( $_POST[ 'interval' ] ) ? bookacti_sanitize_events_interval( $_POST[ 'interval' ] ) : array();
		$events		= bookacti_fetch_events_for_calendar_editor( array( 'events' => array( $event_id ), 'interval' => $interval ) );

		// Retrieve groups of events
		$groups_events = bookacti_get_groups_events( $old_event->template_id );

		do_action( 'bookacti_event_updated', $event_id, $events );

		bookacti_send_json( array( 
			'status'			=> 'success', 
			'events'			=> $events[ 'events' ] ? $events[ 'events' ] : array(),
			'events_data'		=> $events[ 'data' ] ? $events[ 'data' ] : array(),
			'groups_events'		=> $groups_events,
			'exceptions_dates'	=> $event_data[ 'exceptions_dates' ],
			'updated'			=> $updated
		), 'update_event' ); 

	} else if( $updated === 0 ) { 
		bookacti_send_json( array( 'status' => 'nochanges' ), 'update_event' );

	} else if( $updated === false ) { 
		bookacti_send_json( array( 'status' => 'failed', 'updated' => $updated ), 'update_event' ); 
	}

	bookacti_send_json( array( 'status' => 'failed', 'error' => 'unknown_error', 'updated' => $updated ), 'update_event' ); 
}
add_action( 'wp_ajax_bookactiUpdateEvent', 'bookacti_controller_update_event' );


/**
 * AJAX Controller - Check if the event is booked before deleting it
 * @since 1.10.0
 */
function bookacti_controller_before_delete_event() {
	// Check nonce and capabilities
	$is_nonce_valid = check_ajax_referer( 'bookacti_delete_event', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'before_deactivate_event' ); }
	
	// Check if event exists
	$event_id = intval( $_POST[ 'event_id' ] );
	$event = bookacti_get_event_by_id( $event_id );
	if( ! $event ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'event_not_found', 'message' => esc_html__( 'Invalid event ID.', 'booking-activities' ) ), 'before_deactivate_event' ); }
	
	// Check capabilities
	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $event->template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'before_deactivate_event' ); }
	
	// For repeated event, cancel only future bookings
	$bookings_to_cancel = bookacti_get_removed_event_bookings_to_cancel( $event );
	$notifications_nb = 0;
	
	foreach( $bookings_to_cancel as $booking_id => $booking ) {
		$send = apply_filters( 'bookacti_send_event_cancelled_notification_count', true, $booking, $event );
		if( $send ) { ++$notifications_nb; }
	}
	
	$bookings_nb		= count( $bookings_to_cancel ) . ' ' . esc_html( _n( 'booking', 'bookings', count( $bookings_to_cancel ), 'booking-activities' ) );
	$notifications_nb	= $notifications_nb . ' ' . esc_html( _n( 'notification', 'notifications', $notifications_nb, 'booking-activities' ) );
	$has_bookings		= count( $bookings_to_cancel );
	$is_repeated		= $event->repeat_freq && $event->repeat_freq !== 'none' ? 1 : 0;
	
	bookacti_send_json( array( 'status' => 'success', 'bookings_nb' => $bookings_nb, 'notifications_nb' => $notifications_nb, 'has_bookings' => $has_bookings, 'is_repeated' => $is_repeated ), 'before_deactivate_event' );
}
add_action( 'wp_ajax_bookactiBeforeDeleteEvent', 'bookacti_controller_before_delete_event' );


/**
 * AJAX Controller - Delete an event if it doesn't have bookings
 * @version 1.10.0
 */
function bookacti_controller_delete_event() {
	// Check nonce
	$is_nonce_valid = check_ajax_referer( 'bookacti_delete_event', 'nonce_delete_event', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'deactivate_event' ); }
	
	// Check if event exists
	$event_id = intval( $_POST[ 'event_id' ] );
	$event = bookacti_get_event_by_id( $event_id );
	if( ! $event ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'event_not_found', 'message' => esc_html__( 'Invalid event ID.', 'booking-activities' ) ), 'deactivate_event' ); }
	
	// Check capabilities
	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $event->template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'deactivate_event' ); }

	// Sanitize update data
	$cancel_bookings	= ! empty( $_POST[ 'cancel_bookings' ] ) ? true : false;
	$send_notifications	= ! empty( $_POST[ 'send_notifications' ] ) ? true : false;
	
	do_action( 'bookacti_deactivate_event_before', $event, $cancel_bookings, $send_notifications );
	
	// Deactivate the event
	$deactivated = bookacti_deactivate_event( $event_id );
	if( ! $deactivated ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'not_deleted' ), 'deactivate_event' ); }
	
	// Delete the event from all groups
	bookacti_delete_event_from_groups( $event_id );
	
	// Cancel the active bookings
	if( $cancel_bookings ) {
		$old_bookings = bookacti_get_removed_event_bookings_to_cancel( $event );
		if( $old_bookings ) {
			bookacti_cancel_event_bookings( $event_id, array( 'in__booking_id' => array_keys( $old_bookings ) ) );

			// Maybe send notifications
			if( $send_notifications ) {
				bookacti_send_event_cancelled_notifications( $event, $old_bookings );
			}
		}
	}
	
	do_action( 'bookacti_event_deactivated', $event, $cancel_bookings, $send_notifications );

	bookacti_send_json( array( 'status' => 'success' ), 'deactivate_event' );
}
add_action( 'wp_ajax_bookactiDeleteEvent', 'bookacti_controller_delete_event' );


/**
 * AJAX Controller - Unbind occurrences of an event
 * @version 1.10.0
 */
function bookacti_controller_unbind_occurrences() {
	// Check nonce
	$is_nonce_valid = check_ajax_referer( 'bookacti_unbind_occurrences', 'nonce_unbind_occurrences', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'unbind_occurrences' ); }
	
	// Check if event exists
	$event_id = intval( $_POST[ 'event_id' ] );
	$event = bookacti_get_event_by_id( $event_id );
	$event_start = isset( $_POST[ 'event_start' ] ) ? bookacti_sanitize_datetime( $_POST[ 'event_start' ] ) : '';
	$event_end	 = isset( $_POST[ 'event_end' ] ) ? bookacti_sanitize_datetime( $_POST[ 'event_end' ] ) : '';
	if( ! $event || ! $event_start || ! $event_end ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'event_not_found', 'message' => esc_html__( 'Invalid event ID.', 'booking-activities' ) ), 'unbind_occurrences' ); }
	
	// Check capabilities
	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $event->template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'unbind_occurrences' ); }
	
	$new_events_ids	= array();
	$interval		= ! empty( $_POST[ 'interval' ] ) ? bookacti_sanitize_events_interval( $_POST[ 'interval' ] ) : array();
	$unbind_action	= isset( $_POST[ 'unbind_action' ] ) ? sanitize_title_with_dashes( $_POST[ 'unbind_action' ] ) : '';
	
	do_action( 'bookacti_unbind_event_occurrences_before', $event, $unbind_action, $interval );

	if( $unbind_action === 'selected' ) {
		$new_event_id = bookacti_unbind_selected_occurrence( $event, $event_start, $event_end );
		$new_events_ids = array( $event_id, $new_event_id );

	} else if( $unbind_action === 'booked' ) {
		$new_event_id = bookacti_unbind_booked_occurrences( $event );
		$new_events_ids = array( $event_id, $new_event_id );

	} else if( $unbind_action === 'future' ) {
		$unbind_from = substr( $event_start, 0, 10 );
		$new_event_id = bookacti_unbind_future_occurrences( $event, $unbind_from );
		$new_events_ids = array( $event_id, $new_event_id );
		
	} else if( $unbind_action === 'all' ) {
		$new_events_ids = bookacti_unbind_all_occurrences( $event );
	}

	$new_events_ids = apply_filters( 'bookacti_unbind_event_occurrences_new_events_ids', $new_events_ids, $event, $event_start, $event_end, $unbind_action, $interval );
	if( ! $new_events_ids ) {
		bookacti_send_json( array( 'status' => 'failed', 'error' => 'unknown_error', 'message' => esc_html__( 'An error occurred while trying to unbind the event occurrence(s).', 'booking-activities' ) ), 'unbind_occurrences' );
	}

	// Retrieve affected data
	$events			= bookacti_fetch_events_for_calendar_editor( array( 'events' => $new_events_ids, 'interval' => $interval ) );
	$exceptions		= bookacti_get_exceptions_by_event( array( 'events' => $new_events_ids ) );
	$groups_events	= bookacti_get_groups_events( $event->template_id );
	$booking_nb		= bookacti_get_number_of_bookings_for_booking_system( array( $event->template_id ), $new_events_ids );
	
	$return_data = apply_filters( 'bookacti_event_occurrences_unbound', array( 
		'status'		=> 'success', 
		'new_events_ids'=> $new_events_ids,
		'events'		=> $events[ 'events' ] ? $events[ 'events' ] : array(),
		'events_data'	=> $events[ 'data' ] ? $events[ 'data' ] : array(),
		'exceptions'	=> $exceptions,
		'bookings'		=> $booking_nb,
		'groups_events' => $groups_events
	), $event, $event_start, $event_end, $unbind_action, $interval );
	
	bookacti_send_json( $return_data, 'unbind_occurrences' );
}
add_action( 'wp_ajax_bookactiUnbindOccurrences', 'bookacti_controller_unbind_occurrences' );




// GROUPS OF EVENTS

/**
 * Create a group of events with AJAX
 * @since 1.1.0
 * @version 1.9.0
 */
function bookacti_controller_insert_group_of_events() {
	$template_id	= intval( $_POST[ 'template_id' ] );

	// Check nonce and capabilities
	$is_nonce_valid = check_ajax_referer( 'bookacti_insert_or_update_group_of_events', 'nonce_insert_or_update_group_of_events', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'insert_group_of_events' ); }

	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'insert_group_of_events' ); }

	$category_id	= intval( $_POST[ 'group-of-events-category' ] );
	$category_title	= sanitize_text_field( stripslashes( $_POST[ 'group-of-events-category-title' ] ) );
	$group_title	= sanitize_text_field( stripslashes( $_POST[ 'group-of-events-title' ] ) );
	$events			= bookacti_maybe_decode_json( stripslashes( $_POST[ 'events' ] ) );

	// Validate input data
	$is_group_of_events_valid = bookacti_validate_group_of_events_data( $group_title, $category_id, $category_title, $events );
	if( $is_group_of_events_valid[ 'status' ] !== 'valid' ) {
		bookacti_send_json( array( 'status' => 'not_valid', 'errors' => $is_group_of_events_valid[ 'errors' ] ), 'insert_group_of_events' );
	}

	// Create category if it doesn't exists
	$is_category = bookacti_group_category_exists( $category_id, $template_id );

	if( ! $is_category ) {
		$cat_options_array	= isset( $_POST[ 'groupCategoryOptions' ] ) && is_array( $_POST[ 'groupCategoryOptions' ] ) ? $_POST[ 'groupCategoryOptions' ] : array();
		$category_settings	= bookacti_format_group_category_settings( $cat_options_array );
		$category_id		= bookacti_insert_group_category( $category_title, $template_id, $category_settings );
	}

	if( ! $category_id ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'invalid_category', 'category_id' => $category_id ), 'insert_group_of_events' ); }

	// Insert the new group of event
	$group_options_array	= isset( $_POST[ 'groupOfEventsOptions' ] ) && is_array( $_POST[ 'groupOfEventsOptions' ] ) ? $_POST[ 'groupOfEventsOptions' ] : array();
	$group_settings			= bookacti_format_group_of_events_settings( $group_options_array );
	$group_id				= bookacti_create_group_of_events( $events, $category_id, $group_title, $group_settings );

	if( ! $group_id ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'invalid_events', 'events' => $events, 'group_id' => $group_id ), 'insert_group_of_events' ); }

	$category_data	= bookacti_get_group_category( $category_id );
	$group_data		= bookacti_get_group_of_events( $group_id );
	$group_events	= bookacti_get_group_events( $group_id );
	$group_title_raw= strip_tags( $group_data->title );

	do_action( 'bookacti_group_of_events_inserted', $group_id, $group_events, $group_data, $category_data );

	bookacti_send_json( array( 
		'status' => 'success', 
		'group_id' => $group_id, 
		'group' => $group_data, 
		'group_title_raw' => $group_title_raw, 
		'group_events' => $group_events, 
		'category_id' => $category_id, 
		'category' => $category_data ), 'insert_group_of_events' );
}
add_action( 'wp_ajax_bookactiInsertGroupOfEvents', 'bookacti_controller_insert_group_of_events' );


/**
 * Update group of events data with AJAX
 * @since 1.1.0
 * @version 1.9.0
 */
function bookacti_controller_update_group_of_events() {
	$group_id		= intval( $_POST[ 'group_id' ] );
	$template_id	= bookacti_get_group_of_events_template_id( $group_id );

	// Check nonce and capabilities
	$is_nonce_valid = check_ajax_referer( 'bookacti_insert_or_update_group_of_events', 'nonce_insert_or_update_group_of_events', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'update_group_of_events' ); }

	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'update_group_of_events' ); }

	$category_id	= intval( $_POST[ 'group-of-events-category' ] );
	$category_title	= sanitize_text_field( stripslashes( $_POST[ 'group-of-events-category-title' ] ) );
	$group_title	= wp_kses_post( stripslashes( $_POST[ 'group-of-events-title' ] ) );
	$events			= bookacti_maybe_decode_json( stripslashes( $_POST[ 'events' ] ) );

	// Validate input data
	$is_group_of_events_valid = bookacti_validate_group_of_events_data( $group_title, $category_id, $category_title, $events );
	if( $is_group_of_events_valid[ 'status' ] !== 'valid' ) {
		bookacti_send_json( array( 'status' => 'not_valid', 'errors' => $is_group_of_events_valid[ 'errors' ] ), 'update_group_of_events' );
	}

	// Create category if it doesn't exists
	$is_category = bookacti_group_category_exists( $category_id, $template_id );

	if( ! $is_category ) {
		$cat_options_array	= isset( $_POST[ 'groupCategoryOptions' ] ) && is_array( $_POST[ 'groupCategoryOptions' ] ) ? $_POST[ 'groupCategoryOptions' ] : array();
		$category_settings	= bookacti_format_group_category_settings( $cat_options_array );
		$category_id		= bookacti_insert_group_category( $category_title, $template_id, $category_settings );
	}

	if( ! $category_id ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'invalid_category', 'category_id' => $category_id ), 'update_group_of_events' ); }

	// Insert the new group of event
	$group_options_array	= isset( $_POST[ 'groupOfEventsOptions' ] ) && is_array( $_POST[ 'groupOfEventsOptions' ] ) ? $_POST[ 'groupOfEventsOptions' ] : array();
	$group_settings			= bookacti_format_group_of_events_settings( $group_options_array );
	$updated				= bookacti_edit_group_of_events( $group_id, $category_id, $group_title, $events, $group_settings );

	if( $updated === false ) {
		bookacti_send_json( array( 'status' => 'failed', 'error' => 'invalid_events', 'events' => $events, 'group_id' => $group_id ), 'update_group_of_events' );
	} else if( $updated === 0 ) {
		bookacti_send_json( array( 'status' => 'nochanges' ), 'update_group_of_events' );
	} else if( is_string( $updated ) ) {
		bookacti_send_json( array( 'status' => 'failed', 'error' => $updated ), 'update_group_of_events' );
	}

	$category_data	= bookacti_get_group_category( $category_id );
	$group_data		= bookacti_get_group_of_events( $group_id );
	$group_events	= bookacti_get_group_events( $group_id );
	$group_title_raw= strip_tags( $group_data->title );

	do_action( 'bookacti_group_of_events_updated', $group_id, $group_events, $group_data, $category_data );

	bookacti_send_json( array(
		'status' => 'success', 
		'group' => $group_data, 
		'group_title_raw' => $group_title_raw, 
		'group_events' => $group_events, 
		'category_id' => $category_id, 
		'category' => $category_data ), 'update_group_of_events' );
}
add_action( 'wp_ajax_bookactiUpdateGroupOfEvents', 'bookacti_controller_update_group_of_events' );


/**
 * AJAX Controller - Check if the group of events is booked before deleting it
 * @since 1.10.0
 */
function bookacti_controller_before_delete_group_of_events() {
	// Check nonce
	$is_nonce_valid = check_ajax_referer( 'bookacti_delete_group_of_events', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'before_deactivate_group_of_events' ); }
	
	// Check if group exists
	$event_group_id = intval( $_POST[ 'group_id' ] );
	$groups_of_events = bookacti_get_groups_of_events( array( 'event_groups' => array( $event_group_id ) ) );
	$group_of_events = isset( $groups_of_events[ $event_group_id ] ) ? $groups_of_events[ $event_group_id ] : false;
	if( ! $group_of_events ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'group_of_events_not_found', 'message' => esc_html__( 'Invalid group of events ID.', 'booking-activities' ) ), 'before_deactivate_group_of_events' ); }
	
	// Check capabilities
	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $group_of_events[ 'template_id' ] );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'before_deactivate_group_of_events' ); }
	
	// Check if the group of events is booked
	$timezone = bookacti_get_setting_value( 'bookacti_general_settings', 'timezone' );
	$now_dt = new DateTime( 'now', new DateTimeZone( $timezone ) );
	$filters = bookacti_format_booking_filters( array( 'event_group_id' => $event_group_id, 'from' => $now_dt->format( 'Y-m-d H:i:s' ), 'active' => 1 ) );
	$bookings = bookacti_get_bookings( $filters );
	$booking_groups_ids = array();
	$notifications_nb = 0;
	if( $bookings ) {
		$sent_booking_groups_ids = array();
		foreach( $bookings as $booking ) {
			if( ! in_array( $booking->group_id, $booking_groups_ids, true ) ) { $booking_groups_ids[] = $booking->group_id; }
			
			$send = apply_filters( 'bookacti_send_group_of_events_cancelled_notification_count', true, $booking, $group_of_events );
			if( $send && ! in_array( $booking->group_id, $sent_booking_groups_ids, true ) ) { 
				$sent_booking_groups_ids[] = $booking->group_id;
				++$notifications_nb;
			}
		}
	}
	
	$bookings_nb		= count( $bookings ) . ' ' . esc_html( _n( 'booking', 'bookings', count( $bookings ), 'booking-activities' ) );
	$booking_groups_nb	= count( $booking_groups_ids ) . ' ' . esc_html( _n( 'booking group', 'booking groups', count( $booking_groups_ids ), 'booking-activities' ) );
	$notifications_nb	= $notifications_nb . ' ' . esc_html( _n( 'notification', 'notifications', $notifications_nb, 'booking-activities' ) );
	$has_bookings		= count( $bookings );
	
	bookacti_send_json( array( 'status' => 'success', 'bookings_nb' => $bookings_nb, 'booking_groups_nb' => $booking_groups_nb, 'notifications_nb' => $notifications_nb, 'has_bookings' => $has_bookings ), 'before_deactivate_group_of_events' );
}
add_action( 'wp_ajax_bookactiBeforeDeleteGroupOfEvents', 'bookacti_controller_before_delete_group_of_events' );


/**
 * Delete a group of events with AJAX
 * @since 1.1.0
 * @version 1.10.0
 */
function bookacti_controller_delete_group_of_events() {
	// Check nonce
	$is_nonce_valid = check_ajax_referer( 'bookacti_delete_group_of_events', 'nonce_delete_group_of_events', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'deactivate_group_of_events' ); }
	
	// Check if group exists
	$event_group_id = intval( $_POST[ 'group_id' ] );
	$groups_of_events = bookacti_get_groups_of_events( array( 'event_groups' => array( $event_group_id ) ) );
	$group_of_events = isset( $groups_of_events[ $event_group_id ] ) ? $groups_of_events[ $event_group_id ] : false;
	if( ! $group_of_events ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'group_of_events_not_found', 'message' => esc_html__( 'Invalid group of events ID.', 'booking-activities' ) ), 'deactivate_group_of_events' ); }
	
	// Check capabilities
	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $group_of_events[ 'template_id' ] );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'deactivate_group_of_events' ); }
	
	// Sanitize update data
	$cancel_bookings	= ! empty( $_POST[ 'cancel_bookings' ] ) ? true : false;
	$send_notifications	= ! empty( $_POST[ 'send_notifications' ] ) ? true : false;
	
	do_action( 'bookacti_deactivate_group_of_events_before', $group_of_events, $cancel_bookings, $send_notifications );
	
	// Deactivate the group of events
	bookacti_deactivate_group_of_events( $event_group_id );
	
	// Cancel the bookings
	$bookings = array();
	if( $cancel_bookings ) { 
		// Get bookings before cancelling them
		if( $send_notifications ) {
			$filters = bookacti_format_booking_filters( array( 'event_group_id' => $event_group_id, 'active' => 1 ) );
			$old_booking_groups = bookacti_get_booking_groups( $filters );
			// It is not necessary to filter more the booking groups here because
			// the notification will be sent only if the booking group is cancelled, 
			// and the booking group is cancelled only if it has an active booking starting in the future
		}
		
		// Cancel active and future grouped bookings belonging that (once) belong(ed) to that group of events
		bookacti_cancel_group_of_events_bookings( $event_group_id );
		
		// Maybe send notifications
		if( $send_notifications && $old_booking_groups ) {
			bookacti_send_group_of_events_cancelled_notifications( $group_of_events, $old_booking_groups );
		}
		
		$bookings = bookacti_get_number_of_bookings_for_booking_system( $group_of_events[ 'template_id' ] );
	}
	
	do_action( 'bookacti_group_of_events_deactivated', $group_of_events, $cancel_bookings, $send_notifications );

	bookacti_send_json( array( 'status' => 'success', 'bookings' => $bookings ), 'deactivate_group_of_events' );
}
add_action( 'wp_ajax_bookactiDeleteGroupOfEvents', 'bookacti_controller_delete_group_of_events' );




// GROUP CATEGORIES

/**
 * Update group category data with AJAX
 * @since 1.1.0
 * @version 1.8.0
 */
function bookacti_controller_update_group_category() {
	$category_id = intval( $_POST[ 'category_id' ] );
	$template_id = bookacti_get_group_category_template_id( $category_id );

	// Check nonce and capabilities
	$is_nonce_valid = check_ajax_referer( 'bookacti_insert_or_update_group_category', 'nonce_insert_or_update_group_category', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'update_group_category' ); }

	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'update_group_category' ); }

	$category_title = sanitize_text_field( stripslashes( $_POST[ 'group-category-title' ] ) );
	$is_group_category_valid = bookacti_validate_group_category_data( $category_title );

	// Update template only if its data are consistent
	if( $is_group_category_valid[ 'status' ] !== 'valid' ) {
		bookacti_send_json( array( 'status' => 'not_valid', 'errors' => $is_group_category_valid[ 'errors' ] ), 'update_group_category' );
	}

	$cat_options_array	= isset( $_POST['groupCategoryOptions'] ) && is_array( $_POST['groupCategoryOptions'] ) ? $_POST['groupCategoryOptions'] : array();
	$category_settings	= bookacti_format_group_category_settings( $cat_options_array );

	$updated = bookacti_update_group_category( $category_id, $category_title, $category_settings );

	$category = bookacti_get_group_category( $category_id );

	if( $updated === 0 ) { 
		bookacti_send_json( array( 'status' => 'nochanges', 'category' => $category ), 'update_group_category' );
	} else if ( $updated === false ) { 
		bookacti_send_json( array( 'status' => 'failed' ), 'update_group_category' );
	}

	do_action( 'bookacti_group_category_updated', $category_id, $category );

	bookacti_send_json( array( 'status' => 'success', 'category' => $category ), 'update_group_category' );
}
add_action( 'wp_ajax_bookactiUpdateGroupCategory', 'bookacti_controller_update_group_category' );


/**
 * Delete a group category with AJAX
 * @since 1.1.0
 * @version 1.8.0
 */
function bookacti_controller_delete_group_category() {
	$category_id	= intval( $_POST[ 'category_id' ] );
	$template_id	= bookacti_get_group_category_template_id( $category_id );

	// Check nonce and capabilities
	$is_nonce_valid = check_ajax_referer( 'bookacti_delete_group_category', 'nonce', false );
	if( ! $is_nonce_valid  ) { bookacti_send_json_invalid_nonce( 'deactivate_group_category' ); }

	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'deactivate_group_category' ); }

	$groups_ids = bookacti_get_groups_of_events_ids_by_category( $category_id, true );

	// Check if one of the groups of this category has been booked
	$delete_category = true;
	foreach( $groups_ids as $group_id ) {
		$filters		= bookacti_format_booking_filters( array( 'event_group_id' => $group_id ) );
		$booking_groups = bookacti_get_booking_groups( $filters );

		// Delete groups with no bookings
		if( empty( $booking_groups ) ) {
			bookacti_delete_group_of_events( $group_id );

		// Deactivate groups with bookings
		} else {
			bookacti_deactivate_group_of_events( $group_id );
			$delete_category = false;
		}
	}

	// If one of its groups is booked, do not delete the category, simply deactivate it
	if( $delete_category ) {
		$deleted = bookacti_delete_group_category( $category_id );
	} else {
		$deleted = bookacti_deactivate_group_category( $category_id );
	}

	if( ! $deleted ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'not_deleted' ), 'deactivate_group_category' ); }

	do_action( 'bookacti_group_category_deactivated', $category_id );

	bookacti_send_json( array( 'status' => 'success' ), 'deactivate_group_category' );
}
add_action( 'wp_ajax_bookactiDeleteGroupCategory', 'bookacti_controller_delete_group_category' );




// TEMPLATES

/**
 * AJAX Controller - Create a new template
 * @version	1.9.3
 */
function bookacti_controller_insert_template() {
	// Check nonce and capabilities
	$is_nonce_valid	= check_ajax_referer( 'bookacti_insert_or_update_template', 'nonce_insert_or_update_template', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'insert_template' ); }

	$is_allowed = current_user_can( 'bookacti_create_templates' );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'insert_template' ); }

	$template_title	= sanitize_text_field( stripslashes( $_POST[ 'template-title' ] ) );
	$template_start	= bookacti_sanitize_date( $_POST[ 'template-opening' ] ) ? bookacti_sanitize_date( $_POST[ 'template-opening' ] ) : date( 'Y-m-d' );
	$template_end	= bookacti_sanitize_date( $_POST[ 'template-closing' ] ) ? bookacti_sanitize_date( $_POST[ 'template-closing' ] ) : '2037-12-31';

	// Validate data
	$is_template_valid = bookacti_validate_template_data( $template_title, $template_start, $template_end );
	if( $is_template_valid[ 'status' ] !== 'valid' ) { bookacti_send_json( array( 'status' => 'failed', 'error' => $is_template_valid[ 'errors' ] ), 'insert_template' ); }

	$duplicated_template_id	= ! empty( $_POST[ 'duplicated-template-id' ] ) ? intval( $_POST[ 'duplicated-template-id' ] ) : 0;
	$managers_array			= isset( $_POST[ 'template-managers' ] ) ? bookacti_ids_to_array( $_POST[ 'template-managers' ] ) : array();
	$options_array			= isset( $_POST[ 'templateOptions' ] ) && is_array( $_POST[ 'templateOptions' ] ) ? $_POST[ 'templateOptions' ] : array();

	$template_managers	= bookacti_format_template_managers( $managers_array );
	$template_settings	= bookacti_sanitize_template_settings( $options_array );

	$lastid = bookacti_insert_template( $template_title, $template_start, $template_end, $template_managers, $template_settings, $duplicated_template_id );
	if( ! $lastid ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'not_inserted' ), 'insert_template' ); }

	do_action( 'bookacti_template_inserted', $lastid );

	bookacti_send_json( array( 'status' => 'success', 'template_id' => $lastid ), 'insert_template' );
}
add_action( 'wp_ajax_bookactiInsertTemplate', 'bookacti_controller_insert_template' );


/**
 * AJAX Controller - Update template
 * @version	1.9.3
 */
function bookacti_controller_update_template() {
	$template_id = intval( $_POST[ 'template-id' ] );

	// Check nonce and capabilities
	$is_nonce_valid = check_ajax_referer( 'bookacti_insert_or_update_template', 'nonce_insert_or_update_template', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'update_template' ); }

	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'update_template' ); }

	$template_title	= sanitize_text_field( stripslashes( $_POST[ 'template-title' ] ) );
	$template_start	= bookacti_sanitize_date( $_POST[ 'template-opening' ] ) ? bookacti_sanitize_date( $_POST[ 'template-opening' ] ) : date( 'Y-m-d' );
	$template_end	= bookacti_sanitize_date( $_POST[ 'template-closing' ] ) ? bookacti_sanitize_date( $_POST[ 'template-closing' ] ) : '2037-12-31';

	$is_template_valid = bookacti_validate_template_data( $template_title, $template_start, $template_end );

	// Update template only if its data are consistent
	if( $is_template_valid[ 'status' ] !== 'valid' ) { bookacti_send_json( array( 'status' => 'failed', 'error' => $is_template_valid[ 'errors' ] ), 'update_template' ); }

	$updated_template	= bookacti_update_template( $template_id, $template_title, $template_start, $template_end );
	if( $updated_template === false ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'not_updated' ), 'update_template' ); }

	$updated_metadata = 0;
	if( isset( $_POST[ 'templateOptions' ] ) ) {
		$template_settings	= bookacti_sanitize_template_settings( $_POST[ 'templateOptions' ] );
		$updated_metadata	= bookacti_update_metadata( 'template', $template_id, $template_settings );
	}

	$managers_array		= isset( $_POST[ 'template-managers' ] ) ? bookacti_ids_to_array( $_POST[ 'template-managers' ] ) : array();
	$template_managers	= bookacti_format_template_managers( $managers_array );
	$updated_managers	= bookacti_update_managers( 'template', $template_id, $template_managers );

	if( $updated_template >= 0 || intval( $updated_managers ) >= 0 || intval( $updated_metadata ) >= 0 ) {
		$templates_data = bookacti_get_templates_data( $template_id, true );

		do_action( 'bookacti_template_updated', $template_id, $templates_data[ $template_id ] );

		bookacti_send_json( array( 'status' => 'success', 'template_data' => $templates_data[ $template_id ] ), 'update_template' );
	}

	bookacti_send_json( array( 'status' => 'failed', 'error' => 'unknown' ), 'update_template' );
}
add_action( 'wp_ajax_bookactiUpdateTemplate', 'bookacti_controller_update_template' );


/**
 * AJAX Controller - Deactivate a template
 * @version 1.8.0
 */
function bookacti_controller_deactivate_template() {
	$template_id = intval( $_POST['template_id'] );

	// Check nonce and capabilities
	$is_nonce_valid = check_ajax_referer( 'bookacti_edit_template', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'deactivate_template' ); }

	$is_allowed = current_user_can( 'bookacti_delete_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'deactivate_template' ); }

	$deactivated = bookacti_deactivate_template( $template_id );
	if( ! $deactivated ) { bookacti_send_json( array( 'status' => 'failed' ), 'deactivate_template' ); } 

	do_action( 'bookacti_template_deactivated', $template_id );

	bookacti_send_json( array( 'status' => 'success' ), 'deactivate_template' );
}
add_action( 'wp_ajax_bookactiDeactivateTemplate', 'bookacti_controller_deactivate_template' );


/**
 * AJAX Controller - Change default template
 * @version	1.9.0
 */
function bookacti_controller_switch_template() {
	$template_id = intval( $_POST[ 'template_id' ] );

	// Check nonce and capabilities
	$is_nonce_valid = check_ajax_referer( 'bookacti_edit_template', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'switch_template' ); }

	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'switch_template' ); }

	$updated		= update_user_meta( get_current_user_id(), 'bookacti_default_template', $template_id );
	$activities_list= bookacti_get_template_activities_list( $template_id );
	$groups_list	= bookacti_get_template_groups_of_events_list( $template_id );

	$atts = bookacti_maybe_decode_json( stripslashes( $_POST[ 'attributes' ] ), true );
	$booking_system_data = bookacti_get_editor_booking_system_data( $atts, $template_id );

	bookacti_send_json( array(
		'status'				=> 'success',
		'activities_list'		=> $activities_list, 
		'groups_list'			=> $groups_list,
		'booking_system_data'	=> $booking_system_data
	), 'switch_template' );
}
add_action( 'wp_ajax_bookactiSwitchTemplate', 'bookacti_controller_switch_template' );




// ACTIVITIES

/**
 * AJAX Controller - Create a new activity
 * @version 1.10.0
 */
function bookacti_controller_insert_activity() {
	// Check nonce and capabilities
	$is_nonce_valid	= check_ajax_referer( 'bookacti_insert_or_update_activity', 'nonce_insert_or_update_activity', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'insert_activity' ); }

	$template_id = intval( $_POST[ 'template-id' ] );
	$is_allowed = current_user_can( 'bookacti_create_activities' );
	if( ! $is_allowed || ! $template_id ) { bookacti_send_json_not_allowed( 'insert_activity' ); }

	$activity_title			= sanitize_text_field( stripslashes( $_POST[ 'activity-title' ] ) );
	$activity_color			= function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( $_POST[ 'activity-color' ] ) : stripslashes( $_POST[ 'activity-color' ] );
	$activity_availability	= intval( $_POST[ 'activity-availability' ] );
	$sanitized_duration		= intval( $_POST[ 'activity-duration' ] );
	$activity_duration		= $sanitized_duration ? bookacti_format_duration( $sanitized_duration, 'timespan' ) : '000.01:00:00';
	$managers_array			= isset( $_POST[ 'activity-managers' ] ) ? bookacti_ids_to_array( $_POST[ 'activity-managers' ] ) : array();
	$options_array			= isset( $_POST[ 'activityOptions' ] ) && is_array( $_POST[ 'activityOptions' ] ) ? $_POST[ 'activityOptions' ] : array();

	// Format arrays and check templates permissions
	$activity_managers	= bookacti_format_activity_managers( $managers_array );
	$activity_settings	= bookacti_format_activity_settings( $options_array );

	// Insert the activity and its metadata
	$activity_id = bookacti_insert_activity( $activity_title, $activity_color, $activity_availability, $activity_duration );

	if( ! $activity_id ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'not_inserted' ), 'insert_activity' ); }

	bookacti_insert_managers( 'activity', $activity_id, $activity_managers );
	bookacti_insert_metadata( 'activity', $activity_id, $activity_settings );

	// Bind the activity to the current template
	$bound = bookacti_bind_activities_to_template( $activity_id, $template_id );

	if( ! $bound ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'cannot_bind_to_template' ), 'insert_activity' ); }

	$activity_data	= bookacti_get_activity( $activity_id );
	$activity_list	= bookacti_get_template_activities_list( $template_id );

	do_action( 'bookacti_activity_inserted', $activity_id, $activity_data );

	bookacti_send_json( array( 'status' => 'success', 'activity_id' => $activity_id, 'activity_data' => $activity_data, 'activity_list' => $activity_list ), 'insert_activity' );
}
add_action( 'wp_ajax_bookactiInsertActivity', 'bookacti_controller_insert_activity' );


/**
 * AJAX Controller - Update an activity
 * @version 1.10.0
 */
function bookacti_controller_update_activity() {
	$activity_id	= intval( $_POST['activity-id'] );
	$template_id	= intval( $_POST['template-id'] );

	// Check nonce and capabilities
	$is_nonce_valid	= check_ajax_referer( 'bookacti_insert_or_update_activity', 'nonce_insert_or_update_activity', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'update_activity' ); }

	$is_allowed = current_user_can( 'bookacti_edit_activities' ) && bookacti_user_can_manage_activity( $activity_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'update_activity' ); }

	$activity_title			= sanitize_text_field( stripslashes( $_POST['activity-title'] ) );
	$activity_color			= function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( $_POST['activity-color'] ) : stripslashes( $_POST['activity-color'] );
	$activity_availability	= intval( $_POST['activity-availability'] );
	$sanitized_duration		= intval( $_POST[ 'activity-duration' ] );
	$activity_duration		= $sanitized_duration ? substr( bookacti_format_duration( $sanitized_duration, 'timespan' ), -12 ) : '000.01:00:00';
	$managers_array			= isset( $_POST['activity-managers'] ) ? bookacti_ids_to_array( $_POST['activity-managers'] ) : array();
	$options_array			= isset( $_POST['activityOptions'] ) && is_array( $_POST['activityOptions'] ) ? $_POST['activityOptions'] : array();

	// Format arrays and check templates permissions
	$activity_managers	= bookacti_format_activity_managers( $managers_array );
	$activity_settings	= bookacti_format_activity_settings( $options_array );

	// Update the events title bound to this activity before updating the activty
	$updated_events		= bookacti_update_events_title( $activity_id, $activity_title );
	
	// Update the activity and its metadata
	$updated_activity	= bookacti_update_activity( $activity_id, $activity_title, $activity_color, $activity_availability, $activity_duration );
	$updated_managers	= bookacti_update_managers( 'activity', $activity_id, $activity_managers );
	$updated_metadata	= bookacti_update_metadata( 'activity', $activity_id, $activity_settings );

	if( $updated_activity >= 0 || $updated_events >= 0 || intval( $updated_managers ) >= 0 || intval( $updated_metadata ) >= 0 ){
		$activity_data	= bookacti_get_activity( $activity_id );
		$activity_list	= bookacti_get_template_activities_list( $template_id );

		do_action( 'bookacti_activity_updated', $activity_id, $activity_data );

		bookacti_send_json( array( 'status' => 'success', 'activity_data' => $activity_data, 'activity_list' => $activity_list ), 'update_activity' );
	} else if ( $updated_activity === false && $updated_events >= 0 ){ 
		bookacti_send_json( array( 'status' => 'failed', 'error' => 'activity_not_updated' ), 'update_activity' ); 
	} else if ( $updated_events === false && $updated_activity >= 0 ){ 
		bookacti_send_json( array( 'status' => 'failed', 'error' => 'events_not_updated', 'message' => esc_html__( 'Error occurs when trying to update events bound to the updated activity.', 'booking-activities' ) ), 'update_activity' );
	}
}
add_action( 'wp_ajax_bookactiUpdateActivity', 'bookacti_controller_update_activity' );


/**
 * AJAX Controller - Create an association between existing activities (on various templates) and current template
 * @version 1.8.0
 */
function bookacti_controller_import_activities() {
	$template_id = intval( $_POST[ 'template_id' ] );

	// Check nonce and capabilities
	$is_nonce_valid = check_ajax_referer( 'bookacti_import_activity', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'import_activities' ); }

	$is_allowed = current_user_can( 'bookacti_edit_activities' ) && current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'import_activities' ); }

	$activity_ids	= bookacti_ids_to_array( $_POST[ 'activity_ids' ] );
	if( ! $activity_ids ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'no_activity', 'message' => esc_html__( 'Select at least one activity.', 'booking-activities' ) ), 'import_activities' ); }

	// Check activity permissions, and remove not allowed activity ids
	foreach( $activity_ids as $i => $activity_id ) {
		$can_manage_activity = bookacti_user_can_manage_activity( $activity_id );
		if( ! $can_manage_activity ) {
			unset( $activity_ids[ $i ] );
		}
	}
	if( ! $activity_ids ) { bookacti_send_json_not_allowed( 'import_activities' ); }

	$inserted = bookacti_bind_activities_to_template( $activity_ids, $template_id );

	if( ! $inserted ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'not_inserted', 'activity_ids' => $activity_ids, 'template_id' => $template_id, 'inserted' => $inserted ), 'import_activities' ); }

	$activities_data= bookacti_get_activities_by_template( $template_id, false, true );
	$activity_list	= bookacti_get_template_activities_list( $template_id );

	do_action( 'bookacti_activities_imported', $template_id, $activity_ids, $activities_data );

	bookacti_send_json( array( 'status' => 'success', 'activities_data' => $activities_data, 'activity_list' => $activity_list ), 'import_activities' );
}
add_action( 'wp_ajax_bookactiImportActivities', 'bookacti_controller_import_activities' );


/**
 * AJAX Controller - Deactivate an activity
 * @version 1.8.0
 */
function bookacti_controller_deactivate_activity() {
	$activity_id = intval( $_POST[ 'activity_id' ] );
	$template_id = intval( $_POST[ 'template_id' ] );

	// Check nonce and capabilities
	$is_nonce_valid = check_ajax_referer( 'bookacti_deactivate_activity', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'deactivate_activity' ); }

	$is_allowed = current_user_can( 'bookacti_delete_activities' ) && bookacti_user_can_manage_activity( $activity_id ) && current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'deactivate_activity' ); }

	$deleted = bookacti_delete_templates_x_activities( array( $template_id ), array( $activity_id ) );
	if( ! $deleted ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'cannot_delete_template_association' ), 'deactivate_activity' ); }

	do_action( 'bookacti_activity_template_association_removed', $activity_id, $template_id );

	// If the activity isn't bound to any template, deactivate it
	$templates = bookacti_get_templates_by_activity( $activity_id );
	if( empty( $templates ) ) {
		$deactivated = bookacti_deactivate_activity( $activity_id );
		if( $deactivated ) {
			do_action( 'bookacti_activity_deactivated', $activity_id );
		}
	}

	$delete_events = intval( $_POST[ 'delete_events' ] );
	if( $delete_events ) {
		// Delete the events
		$deactivated1 = bookacti_deactivate_activity_events( $activity_id, $template_id );
		// Delete the events from all groups
		$deactivated2 = bookacti_deactivate_activity_events_from_groups( $activity_id, $template_id );

		if( ! is_numeric( $deactivated1 ) || ! is_numeric( $deactivated2 ) ) {
			bookacti_send_json( array( 'status' => 'failed', 'error' => 'cannot_delete_events_or_groups' ), 'deactivate_activity' );
		}

		do_action( 'bookacti_activity_events_deactivated', $template_id, $activity_id );
	}

	bookacti_send_json( array( 'status' => 'success' ), 'deactivate_activity' );
}
add_action( 'wp_ajax_bookactiDeactivateActivity', 'bookacti_controller_deactivate_activity' );


/**
 * AJAX Controller - Get activities by template
 * @version 1.8.0
 */
function bookacti_controller_get_activities_by_template() {
	$selected_template_id	= intval( $_POST[ 'selected_template_id' ] );
	$current_template_id	= intval( $_POST[ 'current_template_id' ] );

	// Check nonce and capabilities
	$is_nonce_valid = check_ajax_referer( 'bookacti_edit_template', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'get_activities_by_template' ); }

	$is_allowed = current_user_can( 'bookacti_edit_activities' ) && current_user_can( 'bookacti_read_templates' ) && bookacti_user_can_manage_template( $selected_template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'get_activities_by_template' ); }

	if( $selected_template_id === $current_template_id ) { bookacti_send_json( array( 'status' => 'failed', 'error' => 'no_change' ), 'get_activities_by_template' ); }

	$new_activities		= bookacti_get_activities_by_template( $selected_template_id, false, true );
	$current_activities	= bookacti_get_activity_ids_by_template( $current_template_id, false );

	// Check activity permissions, and remove not allowed activity ids
	$user_id = get_current_user_id();
	foreach( $new_activities as $new_activity_id => $new_activity ) {
		if( ! in_array( $new_activity_id, $current_activities ) ) {
			$is_allowed = bookacti_user_can_manage_activity( $new_activity_id, $user_id, $new_activity[ 'admin' ] );
			if( ! $is_allowed || ! $new_activity[ 'active' ] ) {
				unset( $new_activities[ $new_activity_id ] );
			}
		} else {
			unset( $new_activities[ $new_activity_id ] );
		}
	}

	if( is_array( $new_activities ) ) {
		if( $new_activities ) {
			bookacti_send_json( array( 'status' => 'success', 'activities' => $new_activities ), 'get_activities_by_template' );
		} else {
			bookacti_send_json( array( 'status' => 'failed', 'error' => 'no_activity', 'message' => esc_html__( 'No available activities found for this calendar.', 'booking-activities' ) ), 'get_activities_by_template' );
		}
	}

	bookacti_send_json( array( 'status' => 'failed', 'error' => 'invalid_activities', 'activities' => $new_activities ), 'get_activities_by_template' );
}
add_action( 'wp_ajax_bookactiGetActivitiesByTemplate', 'bookacti_controller_get_activities_by_template' );


/**
 * AJAX Controller - Save activities / group categories / groups of events order
 * @version 1.11.0
 */
function bookacti_controller_save_template_items_order() {
	// Check nonce
	$is_nonce_valid = check_ajax_referer( 'bookacti_edit_template', 'nonce', false );
	if( ! $is_nonce_valid ) { bookacti_send_json_invalid_nonce( 'save_template_items_order' ); }

	// Check capabilities
	$template_id = ! empty( $_POST[ 'template_id' ] ) ? intval( $_POST[ 'template_id' ] ) : 0;
	$is_allowed = current_user_can( 'bookacti_edit_templates' ) && bookacti_user_can_manage_template( $template_id );
	if( ! $is_allowed ) { bookacti_send_json_not_allowed( 'save_template_items_order' ); }
	
	$item_type = ! empty( $_POST[ 'item_type' ] ) ? sanitize_title_with_dashes( $_POST[ 'item_type' ] ) : '';
	$item_id = ! empty( $_POST[ 'item_id' ] ) ? intval( $_POST[ 'item_id' ] ) : 0;
	$items_order = ! empty( $_POST[ 'items_order' ] ) ? bookacti_ids_to_array( $_POST[ 'items_order' ] ) : array();
	
	// Get the object and the key to update according to the item type
	$object_type = 'template';
	$object_id = $template_id;
	$meta_key = '';
	
	if( $item_type === 'activities' ) {
		$meta_key = 'activities_order';
	} elseif ( $item_type === 'group_categories' ) {
		$meta_key = 'group_categories_order';
	} elseif ( $item_type === 'groups_of_events' && $item_id ) {
		$object_type = 'group_category';
		$object_id = $item_id;
		$meta_key = 'groups_of_events_order';
	}
	
	if( $meta_key ) {
		if( $items_order ) { bookacti_update_metadata( $object_type, $object_id, array( $meta_key => $items_order ) ); }
		else { bookacti_delete_metadata( $object_type, $object_id, array( $meta_key ) ); }
	} else {
		bookacti_send_json( array( 'status' => 'failed', 'error' => 'invalid_item' ), 'save_template_items_order' );
	}
	
	bookacti_send_json( array( 'status' => 'success' ), 'save_template_items_order' );
}
add_action( 'wp_ajax_bookactiSaveTemplateItemsOrder', 'bookacti_controller_save_template_items_order' );