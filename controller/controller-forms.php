<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { exit; }

// DISPLAY FORM FIELDS

/**
 * Display the form field 'calendar'
 * @since 1.5.0
 * @param array $field
 * @param int $form_id
 * @param string $instance_id
 * @param string $context
 */
function bookacti_display_form_field_calendar( $field, $instance_id, $context ) {
	// Do not keep ID and class (already used for the container)
	$field[ 'id' ] = $instance_id; 
	$field[ 'class' ] = '';
	
	// Display the booking system
	bookacti_get_booking_system( $field, true );
}
add_action( 'bookacti_display_form_field_calendar', 'bookacti_display_form_field_calendar', 10, 3 );


/**
 * Display the form field 'login'
 * @since 1.5.0
 * @param string $html
 * @param array $field
 * @param int $form_id
 * @param string $instance_id
 * @param string $context
 * @return string
 */
function bookacti_display_form_field_login( $html, $field, $instance_id, $context ) {
	
	if( $context !== 'edit' && is_user_logged_in() ) { return ''; }
	
	$field_id		= ! empty( $field[ 'id' ] ) ? esc_attr( $field[ 'id' ] ) : esc_attr( 'bookacti-form-field-' . $field[ 'type' ] . '-' . $field[ 'field_id' ] . '-' . $instance_id );
	$field_class	= 'bookacti-form-field-container';
	if( ! empty( $field[ 'name' ] ) )		{ $field_class .= ' bookacti-form-field-name-' . esc_attr( $field[ 'name' ] ); } 
	if( ! empty( $field[ 'field_id' ] ) )	{ $field_class .= ' bookacti-form-field-id-' . esc_attr( $field[ 'field_id' ] ); }
	if( ! empty( $field[ 'class' ] ) )		{ $field_class .= ' ' . esc_attr( $field[ 'class' ] ); }
	ob_start();
	?>
	<div class='<?php echo $field_class; ?>' id='<?php echo $field_id; ?>' >
		<div class='bookacti-form-field-login-field-container' id='<?php echo $field_id; ?>-email-container' >
			<div class='bookacti-form-field-label' >
				<label for='<?php echo $field_id . '-email'; ?>' >
				<?php 
					echo esc_html( apply_filters( 'bookacti_translate_text', $field[ 'label' ][ 'email' ] ) ); 
					if( $field[ 'required_fields' ][ 'email' ] ) {
						echo '<span class="bookacti-required-field" title="' . esc_attr__( 'Required field', BOOKACTI_PLUGIN_NAME ) . '"></span>';
					}
				?>
				</label>
			<?php if( ! empty( $field[ 'tip' ][ 'email' ] ) ) { bookacti_help_tip( esc_html( apply_filters( 'bookacti_translate_text', $field[ 'tip' ][ 'email' ] ) ) ); } ?>
			</div>
			<div class='bookacti-form-field-content' >
			<?php 
				$args = array(
					'type'			=> 'email',
					'name'			=> 'bookacti_email',
					'id'			=> $field_id . '-email',
					'class'			=> 'bookacti-form-field bookacti-email',
					'placeholder'	=> esc_attr( apply_filters( 'bookacti_translate_text', $field[ 'placeholder' ][ 'email' ] ) )
				);
				bookacti_display_field( $args );
			?>
			</div>
		</div>
		<div class='bookacti-form-field-login-field-container' id='<?php echo $field_id; ?>-password-container' >
			<div class='bookacti-form-field-label' >
				<label for='<?php echo $field_id . '-password'; ?>' >
				<?php 
					echo esc_html( apply_filters( 'bookacti_translate_text', $field[ 'label' ][ 'password' ] ) ); 
					if( $field[ 'required_fields' ][ 'password' ] ) {
						echo '<span class="bookacti-required-field" title="' . __( 'Required field', BOOKACTI_PLUGIN_NAME ) . '"></span>';
					}
				?>
				</label>
			<?php if( ! empty( $field[ 'tip' ][ 'password' ] ) ) { bookacti_help_tip( esc_html( apply_filters( 'bookacti_translate_text', $field[ 'tip' ][ 'password' ] ) ) ); } ?>
			</div>
			<div class='bookacti-form-field-content' >
			<?php 
				$args = array(
					'type'			=> 'password',
					'name'			=> 'bookacti_password',
					'id'			=> $field_id . '-password',
					'class'			=> 'bookacti-form-field bookacti-password',
					'placeholder'	=> esc_attr( apply_filters( 'bookacti_translate_text', $field[ 'placeholder' ][ 'password' ] ) )
				);
				bookacti_display_field( $args );
			
				if( ! empty( $field[ 'displayed_fields' ][ 'forgotten_password' ] ) ) { 
				?>
					<div class='bookacti-forgotten-password' >
						<a href='#' class='bookacti-forgotten-password-link' data-field-id='<?php echo $field_id; ?>' ><?php echo esc_html( apply_filters( 'bookacti_translate_text', $field[ 'label' ][ 'forgotten_password' ] ) ) ?></a>
					<?php
						if( ! empty( $field[ 'tip' ][ 'forgotten_password' ] ) ) {
							bookacti_help_tip( esc_html( apply_filters( 'bookacti_translate_text', $field[ 'tip' ][ 'forgotten_password' ] ) ) );
						}
					?>
					</div>
					<div data-field-id='<?php echo $field_id; ?>' class='bookacti-forgotten-password-dialog bookacti-form-dialog' title='<?php esc_html_e( 'Forgotten password', BOOKACTI_PLUGIN_NAME ); ?>' style='display:none;' >
						<div class='bookacti-forgotten-password-dialog-description' >
							<p>
							<?php
								echo apply_filters( 'bookacti_forgotten_password_description', esc_html__( 'Please enter your email address. You will receive a link to create a new password via email.', BOOKACTI_PLUGIN_NAME ), $field, $instance_id, $context );
							?>
							</p>
						</div>
						<div class='bookacti-forgotten-password-dialog-fields' >
							<input type='hidden' class='bookacti-nonce-forgotten-password' name='nonce_forgotten_password' value='<?php echo wp_create_nonce( 'bookacti_forgotten_password' ); ?>' />
							<?php
								$forgotten_pw_fields = apply_filters( 'bookacti_forgotten_password_fields', array(
									'forgotten_password_email' => array(
										'type'			=> 'email',
										'name'			=> 'forgotten_password_email',
										'id'			=> 'bookacti-forgotten-password-email-' . $field_id,
										'class'			=> 'bookacti-forgotten-password-email',
										'placeholder'	=> esc_html__( 'Your email address', BOOKACTI_PLUGIN_NAME ),
									)
								), $field, $instance_id, $context );
								
								bookacti_display_fields( $forgotten_pw_fields );
							?>
						</div>
					</div>
				<?php 
				}
				?>
			</div>
		</div>
		<?php 
		if( ! empty( $field[ 'displayed_fields' ][ 'new_account' ] ) ) { 
			?>
			<div class='bookacti-form-field-login-field-container' id='<?php echo $field_id; ?>-register-container' >
				<div class='bookacti-form-field-content' >
					<input type='hidden' name='bookacti_register' value='0' />
					<input type='checkbox' name='bookacti_register' value='1' id='<?php echo $field_id; ?>-register' class='bookacti-form-field bookacti-register' />
					<label for='<?php echo $field_id; ?>-register'><?php echo esc_html( apply_filters( 'bookacti_translate_text', $field[ 'label' ][ 'new_account' ] ) ); ?></label>
					<?php 
						if( ! empty( $field[ 'tip' ][ 'new_account' ] ) ) {
							bookacti_help_tip( esc_html( apply_filters( 'bookacti_translate_text', $field[ 'tip' ][ 'new_account' ] ) ) );
						}
					?>
				</div>
			</div>
			<?php 
			// Display registration fields if any
			$register_fields = bookacti_get_register_fields_default_data();
			if( in_array( 1, array_values( array_intersect_key( $field[ 'displayed_fields' ], $register_fields ) ) ) ) { ?>
				<fieldset class='bookacti-register-fields' id='<?php echo $field_id; ?>-register-fields' style='<?php if( $context !== 'edit' ) { echo 'display:none;'; } ?>' >
				<?php foreach( $register_fields as $register_field_name => $register_field ) {
						if( ! empty( $field[ 'displayed_fields' ][ $register_field_name ] ) ) { 
						?>
						<div class='bookacti-form-field-login-field-container' id='<?php echo esc_attr( $field_id . '-' . $register_field_name ); ?>-container' >
							<?php if( $register_field[ 'type' ] !== 'checkbox' ) { ?>
								<div class='bookacti-form-field-label' >
									<label for='<?php echo esc_attr( $field_id . '-' . $register_field_name ); ?>' >
									<?php 
										echo esc_html( apply_filters( 'bookacti_translate_text', $field[ 'label' ][ $register_field_name ] ) ); 
										if( $field[ 'required_fields' ][ $register_field_name ] ) {
											echo '<span class="bookacti-required-field" title="' . esc_attr__( 'Required field', BOOKACTI_PLUGIN_NAME ) . '"></span>';
										}
									?>
									</label>
								<?php if( ! empty( $field[ 'tip' ][ $register_field_name ] ) ) { bookacti_help_tip( esc_html( apply_filters( 'bookacti_translate_text', $field[ 'tip' ][ $register_field_name ] ) ) ); } ?>
								</div>
							<?php } ?>
							<div class='bookacti-form-field-content' >
							<?php 
								$args = array(
									'type'			=> $register_field[ 'type' ],
									'name'			=> esc_attr( 'bookacti_' . $register_field_name ),
									'id'			=> esc_attr( $field_id . '-' . $register_field_name ),
									'class'			=> esc_attr( 'bookacti-form-field bookacti-' . $register_field_name ),
									'required'		=> esc_attr( $field[ 'required_fields' ][ $register_field_name ] ),
									'placeholder'	=> esc_attr( apply_filters( 'bookacti_translate_text', $field[ 'placeholder' ][ $register_field_name ] ) )
								);
								if( $register_field[ 'type' ] === 'checkbox' ) { 
									$args[ 'label' ]= esc_html( apply_filters( 'bookacti_translate_text', $field[ 'label' ][ $register_field_name ] ) ); 
									$args[ 'tip' ]	= esc_html( apply_filters( 'bookacti_translate_text', $field[ 'tip' ][ $register_field_name ] ) ); 
								}
								bookacti_display_field( $args );
							?>
							</div>
						</div>
					<?php 
						}
					}
				?>
				</fieldset>
			<?php 
			} 
			?>
	<?php 
		} 
	?>
	</div>
	<?php
	return ob_get_clean();
}
add_filter( 'bookacti_html_form_field_login', 'bookacti_display_form_field_login', 10, 4 );


/**
 * Display the form field 'quantity'
 * @since 1.5.0
 * @param array $field
 * @param int $form_id
 * @param string $instance_id
 * @param string $context
 */
function bookacti_display_form_field_quantity( $field, $instance_id, $context ) {
	$args = array(
		'type'			=> 'number',
		'name'			=> 'quantity',
		'class'			=> 'bookacti-form-field bookacti-quantity',
		'placeholder'	=> ! empty( $field[ 'placeholder' ] ) ? esc_attr( apply_filters( 'bookacti_translate_text', $field[ 'placeholder' ] ) ) : '',
		'options'		=> array( 'min' => 1 ),
		'value'			=> 1
	);
	bookacti_display_field( $args );
}
add_action( 'bookacti_display_form_field_quantity', 'bookacti_display_form_field_quantity', 10, 3 );


/**
 * Display the form field 'submit'
 * @since 1.5.0
 * @param array $field
 * @param int $form_id
 * @param string $instance_id
 * @param string $context
 */
function bookacti_display_form_field_submit( $html, $field, $instance_id, $context ) {
	$field_id		= ! empty( $field[ 'id' ] ) ? esc_attr( $field[ 'id' ] ) : esc_attr( 'bookacti-form-field-' . $field[ 'type' ] . '-' . $field[ 'field_id' ] . '-' . $instance_id );
	$field_class	= 'bookacti-form-field-container bookacti-form-field-name-submit';
	if( ! empty( $field[ 'name' ] ) )		{ $field_class .= ' bookacti-form-field-name-' . esc_attr( $field[ 'name' ] ); } 
	if( ! empty( $field[ 'field_id' ] ) )	{ $field_class .= ' bookacti-form-field-id-' . esc_attr( $field[ 'field_id' ] ); }
	if( ! empty( $field[ 'class' ] ) )		{ $field_class .= ' ' . esc_attr( $field[ 'class' ] ); }
	ob_start();
	?>
	<div class='<?php echo $field_class; ?>' id='<?php echo $field_id; ?>' >
		<input type='<?php echo $context === 'edit' ? 'button' : 'submit'; ?>'
			class='bookacti-submit-form button' 
			value='<?php echo esc_attr( apply_filters( 'bookacti_translate_text', $field[ 'value' ] ) ); ?>' />
	</div>
	<?php
	return ob_get_clean();
}
add_filter( 'bookacti_html_form_field_submit', 'bookacti_display_form_field_submit', 10, 4 );


/**
 * Display the form field 'free_text'
 * @since 1.5.0
 * @param array $field
 * @param int $form_id
 * @param string $instance_id
 * @param string $context
 */
function bookacti_display_form_field_free_text( $field, $instance_id, $context ) {
	$field_id = ! empty( $field[ 'id' ] ) ? esc_attr( $field[ 'id' ] ) : esc_attr( 'bookacti-form-field-' . $field[ 'type' ] . '-' . $field[ 'field_id' ] . '-' . $instance_id );
	?>
	<div id='<?php echo $field_id; ?>' class='bookacti-form-free-text <?php echo esc_attr( $field[ 'class' ] ); ?>' >
	<?php 
		$html = wpautop( apply_filters( 'bookacti_translate_text', $field[ 'value' ] ) );
		echo $context === 'edit' ? $html : do_shortcode( $html ); 
	?>
	</div>
	<?php
}
add_action( 'bookacti_display_form_field_free_text', 'bookacti_display_form_field_free_text', 10, 3 );


/**
 * Add a compulsory quantity input for correct booking form functionning
 * @since 1.5.0
 * @param array $fields
 * @param array $form
 * @param string $instance_id
 * @param string $context
 * @return array
 */
function bookacti_display_compulsory_quantity_form_field( $fields, $form, $instance_id, $context ) {
	if( $context !== 'display' ) { return $fields; }
	
	// If there is no "quantity" input, add a default hidden quantity input
	$fields_types = array();
	foreach( $fields as $field ) { if( ! empty( $field[ 'type' ] ) ) { $fields_types[] = $field[ 'type' ]; } }
	if( in_array( 'submit', $fields_types, true ) && ! in_array( 'quantity', $fields_types, true ) ) {
		$field = bookacti_get_default_form_fields_data( 'quantity' );
		$field[ 'id' ] .= ' bookacti-compulsory-quantity-field';
		$field[ 'class' ] .= ' bookacti-hidden-field';
		$fields[] = $field;
	}
	
	return $fields;
}
add_filter( 'bookacti_displayed_form_fields', 'bookacti_display_compulsory_quantity_form_field', 10, 4 );




// FORM
/**
 * AJAX Controller - Get a booking form
 * @since 1.5.0
 */
function bookacti_controller_get_form() {
	// Check nonce
	$form_id		= intval( $_POST[ 'form_id' ] );
	$is_nonce_valid	= check_ajax_referer( 'bookacti_get_form', 'nonce', false );

	if( ! $is_nonce_valid ) { wp_send_json( array( 'status' => 'failed', 'error' => 'not_allowed' ) ); }
	
	$instance_id	= ! empty( $_POST[ 'instance_id' ] ) ? sanitize_title_with_dashes( $_POST[ 'instance_id' ] ) : '';
	$context		= ! empty( $_POST[ 'context' ] ) ? sanitize_title_with_dashes( $_POST[ 'context' ] ) : 'display';
	
	// Get the form
	$form_html		= bookacti_display_form( $form_id, $instance_id, $context, false );
	
	// Feedback error
	if( ! $form_html ) { wp_send_json( array( 'status' => 'failed', 'error' => 'no_form' ) ); }
	
	wp_send_json( array( 'status' => 'success', 'form_html' => $form_html ) );
}
add_action( 'wp_ajax_bookactiGetForm', 'bookacti_controller_get_form' );
add_action( 'wp_ajax_nopriv_bookactiGetForm', 'bookacti_controller_get_form' );


/**
 * AJAX Controller - Send the forgotten password email
 * @since 1.5.0
 */
function bookacti_controller_forgotten_password() {
	// Check nonce
	$is_nonce_valid	= check_ajax_referer( 'bookacti_forgotten_password', 'nonce', false );

	if( ! $is_nonce_valid ) { wp_send_json( array( 'status' => 'failed', 'error' => 'not_allowed' ) ); }
	
	$email = sanitize_email( $_POST[ 'email' ] );
	
	if( ! is_email( $email ) ) { wp_send_json( array( 'status' => 'failed', 'error' => 'invalid_email', 'message' => esc_html__( 'Invalid email address.', BOOKACTI_PLUGIN_NAME ) ) ); }
	
	$user = get_user_by( 'email', $email );
	
	if( ! $user ) { wp_send_json( array( 'status' => 'failed', 'error' => 'user_not_found', 'message' => esc_html__( 'This email address doesn\'t match any account.', BOOKACTI_PLUGIN_NAME ) ) ); }
	
	wp_send_new_user_notifications( $user->ID, apply_filters( 'bookacti_forgotten_password_notify', 'user', $user ) );
	
	$message = sprintf( esc_html__( 'An email has been sent to %s, please check your inbox.', BOOKACTI_PLUGIN_NAME ), $email );
	
	wp_send_json( array( 'status' => 'success', 'message' => $message ) );
}
add_action( 'wp_ajax_bookactiForgottenPassword', 'bookacti_controller_forgotten_password' );
add_action( 'wp_ajax_nopriv_bookactiForgottenPassword', 'bookacti_controller_forgotten_password' );




// FORM EDITOR PAGE

/**
 * Add form editor meta boxes
 * @since 1.5.0
 */
function bookacti_form_editor_meta_boxes() {
	if( empty( $_REQUEST[ 'action' ] ) || ! in_array( $_REQUEST[ 'action' ], array( 'edit', 'new' ), true ) ) { return; }
	
	// Sidebar
	add_meta_box( 'bookacti_form_publish', __( 'Publish', BOOKACTI_PLUGIN_NAME ), 'bookacti_display_form_publish_meta_box', 'booking-activities_page_bookacti_forms', 'side', 'high' );
	add_meta_box( 'bookacti_form_managers', __( 'Managers', BOOKACTI_PLUGIN_NAME ), 'bookacti_display_form_managers_meta_box', 'booking-activities_page_bookacti_forms', 'side', 'default' );

	add_meta_box( 'bookacti_form_integration_tuto', __( 'How to integrate this form', BOOKACTI_PLUGIN_NAME ), 'bookacti_display_form_integration_tuto_meta_box', 'booking-activities_page_bookacti_forms', 'side', 'low' );
}
add_action( 'add_meta_boxes_booking-activities_page_bookacti_forms', 'bookacti_form_editor_meta_boxes' );


/*
 * Allow metaboxes on for editor
 * @since 1.5.0
 */
function bookacti_allow_meta_boxes_in_form_editor() {
	
	if( empty( $_REQUEST[ 'action' ] ) || ! in_array( $_REQUEST[ 'action' ], array( 'edit', 'new' ), true ) ) { return; }
	
    /* Trigger the add_meta_boxes hooks to allow meta boxes to be added */
    do_action( 'add_meta_boxes_booking-activities_page_bookacti_forms', null );
    do_action( 'add_meta_boxes', 'booking-activities_page_bookacti_forms', null );
	
	/* Enqueue WordPress' script for handling the meta boxes */
	wp_enqueue_script( 'postbox' );
}
add_action( 'load-booking-activities_page_bookacti_forms', 'bookacti_allow_meta_boxes_in_form_editor' );
 

/**
 * Print metabox script to make it work on form editor
 * @since 1.5.0
 */
function bookacti_print_metabox_script_in_form_editor_footer() {
	if( empty( $_REQUEST[ 'action' ] ) || ! in_array( $_REQUEST[ 'action' ], array( 'edit', 'create' ), true ) ) { return; }
	?>
		<script>$j( document ).ready( function(){ postboxes.add_postbox_toggles(pagenow); } );</script>
	<?php
}
add_action( 'admin_footer-booking-activities_page_bookacti_forms', 'bookacti_print_metabox_script_in_form_editor_footer' );




// EDITOR - FORMS

/**
 * Create a new form
 * @since 1.5.0
 * @return int|false
 */
function bookacti_create_form() {
	// Insert form
	$form_id = bookacti_insert_form( '', 'auto-draft', 0 );
	
	if( $form_id === false ) { return $form_id; }
	
	// Insert default form fields
	bookacti_insert_default_form_fields( $form_id );
	
	// Insert default form managers
	$form_managers = bookacti_format_form_managers();
	bookacti_update_managers( 'form', $form_id, $form_managers );
	
	do_action( 'bookacti_form_inserted', $form_id );
	
	return $form_id;
}


/**
 * AJAX Controller - Update a booking form
 * @since 1.5.0
 */
function bookacti_controller_update_form() {
	// Check nonce and capabilities
	$form_id		= intval( $_REQUEST['form_id'] );
	$is_nonce_valid	= check_ajax_referer( 'bookacti_update_form', 'nonce_update_form', false );
	$is_allowed		= current_user_can( 'bookacti_edit_forms' ) && bookacti_user_can_manage_form( $form_id );

	if( ! $is_nonce_valid || ! $is_allowed ) {
		wp_send_json( array( 'status' => 'failed', 'error' => 'not_allowed' ) );
	}
	
	$was_active		= intval( $_REQUEST['is_active'] );
	$form_title		= sanitize_text_field( stripslashes( $_REQUEST['form_title'] ) );
	$managers_array	= isset( $_REQUEST['form-managers'] ) ? bookacti_ids_to_array( $_REQUEST['form-managers'] ) : array();
	$form_managers	= bookacti_format_form_managers( $managers_array );
	
	// Create the form
	$updated = bookacti_update_form( $form_id, $form_title, -1, '', 'publish', 1 );

	// Feedback error
	if( $updated === false ) {
		wp_send_json( array( 'status' => 'failed', 'error' => 'query_failed' ) );
	}

	// Insert Managers
	bookacti_update_managers( 'form', $form_id, $form_managers );
	
	do_action( 'bookacti_form_updated', $form_id );

	wp_send_json( array( 'status' => 'success', 'message' => ! $was_active ? esc_html__( 'The booking form is published.', BOOKACTI_PLUGIN_NAME ) : esc_html__( 'The booking form has been updated.', BOOKACTI_PLUGIN_NAME ) ) );
}
add_action( 'wp_ajax_bookactiUpdateForm', 'bookacti_controller_update_form' );


/**
 * Trash / Remove / Restore a booking form according to URL parameters and display an admin notice to feedback
 * @since 1.5.0
 */
function bookacti_controller_remove_form() {
	if( empty( $_REQUEST[ 'form_id' ] ) || empty( $_REQUEST[ 'action' ] ) || empty( $_REQUEST[ 'page' ] ) 
		|| $_REQUEST[ 'page' ] !== 'bookacti_forms' 
		|| ! is_numeric( $_REQUEST[ 'form_id' ] )
		|| ! in_array( $_REQUEST[ 'action' ], array( 'trash', 'restore', 'delete' ), true ) ) { return; }
	
	$form_id = intval( $_REQUEST[ 'form_id' ] );
	
	// Check nonces
	if( ! wp_verify_nonce( $_REQUEST[ '_wpnonce' ], $_REQUEST[ 'action' ] . '-form_' . $form_id ) ) {
	?>
		<div class='notice notice-error is-dismissible bookacti-form-notice' >
			<p>
				<?php _e( 'You are not allowed to do that.', BOOKACTI_PLUGIN_NAME ); ?>
			</p>
		</div>
	<?php
		return;
	}
	
	// Remove a booking form
	if( $_REQUEST[ 'action' ] === 'trash' || $_REQUEST[ 'action' ] === 'delete' ) {
		
		// Check if current user is allowed to remove the booking form
		$can_delete_form = current_user_can( 'bookacti_delete_forms' ) && bookacti_user_can_manage_form( $form_id );
		if( ! $can_delete_form ) {
		?>
			<div class='notice notice-error is-dismissible bookacti-form-notice' >
				<p>
					<?php _e( 'You are not allowed to remove a booking form.', BOOKACTI_PLUGIN_NAME ); ?>
				</p>
			</div>
		<?php
			return;
		}
		
		// Whether to trash or remove permanently
		$removed = false;
		if( $_REQUEST[ 'action' ] === 'trash' && $form_id ) {
			$removed = bookacti_deactivate_form( $form_id );
		}
		else if( $_REQUEST[ 'action' ] === 'delete' && $form_id ) {
			$removed = bookacti_delete_form( $form_id );
		}
		
		// Feedback success
		if( $removed ) {
			do_action( 'bookacti_form_removed', $form_id );
		?>
			<div class='notice notice-success is-dismissible bookacti-form-notice' >
				<p>
					<?php _e( 'The booking form has been removed.', BOOKACTI_PLUGIN_NAME ); ?>
				</p>
			</div>
		<?php
		
		// Feedback failure
		} else {
		?>
			<div class='notice notice-error is-dismissible bookacti-form-notice' >
				<p>
					<?php _e( 'An error occured while trying to delete a booking form.', BOOKACTI_PLUGIN_NAME ); ?>
				</p>
			</div>
		<?php
		}
	}
	
	// Restore a booking form
	else if( $_REQUEST[ 'action' ] === 'restore' ) {
		
		// Check if current user is allowed to restore the booking form
		$can_edit_form = current_user_can( 'bookacti_edit_forms' );
		if( ! $can_edit_form ) {
		?>
			<div class='notice notice-error is-dismissible bookacti-form-notice' >
				<p>
					<?php _e( 'You are not allowed to restore a booking form.', BOOKACTI_PLUGIN_NAME ); ?>
				</p>
			</div>
		<?php
			return;
		}
		
		$restored = bookacti_activate_form( $form_id );
		
		// Feedback success
		if( $restored ) {
			do_action( 'bookacti_form_restored', $form_id );
		?>
			<div class='notice notice-success is-dismissible bookacti-form-notice' >
				<p>
					<?php _e( 'The booking form has been restored.', BOOKACTI_PLUGIN_NAME ); ?>
				</p>
			</div>
		<?php
		
		// Feedback failure
		} else {
		?>
			<div class='notice notice-error is-dismissible bookacti-form-notice' >
				<p>
					<?php _e( 'An error occured while trying to restore a booking form.', BOOKACTI_PLUGIN_NAME ); ?>
				</p>
			</div>
		<?php
		}
	}
}
add_action( 'all_admin_notices', 'bookacti_controller_remove_form', 10 );


/**
 * AJAX Controller - Update form meta
 * @since 1.5.0
 */
function bookacti_controller_update_form_meta() {
	
	$form_id = intval( $_POST[ 'form_id' ] );
	
	// Check nonce and capabilities
	$is_nonce_valid	= check_ajax_referer( 'bookacti_update_form', 'nonce', false );
	$is_allowed		= current_user_can( 'bookacti_edit_forms' ) && bookacti_user_can_manage_form( $form_id );
	
	if( $is_nonce_valid && $is_allowed && $form_id ) {
		
		// Sanitize data
		$sanitized_data	= bookacti_sanitize_form_data( $_POST );
		
		// Extract metadata only
		$form_meta = array_intersect_key( $sanitized_data, bookacti_get_default_form_meta() );
		
		if( ! $form_meta ) { wp_send_json( array( 'status' => 'failed', 'error' => 'empty_data' ) ); }
		
		// Update form metadata
		$updated = bookacti_update_metadata( 'form', $form_id, $form_meta );
		
		if( $updated !== false ) {
			
			do_action( 'bookacti_form_meta_updated', $form_id );
			
			// Get form data
			$form_data = bookacti_get_form_data( $form_id );
			
			wp_send_json( array( 'status' => 'success', 'form_data' => $form_data ) );
		} else {
			wp_send_json( array( 'status' => 'failed', 'error' => 'not_updated' ) );
		}
		
	} else {
		wp_send_json( array( 'status' => 'failed', 'error' => 'not_allowed' ) );
	}
}
add_action( 'wp_ajax_bookactiUpdateFormMeta', 'bookacti_controller_update_form_meta', 10 );




// EDITOR - FIELDS

/**
 * AJAX Controller - Insert a form field
 * @since 1.5.0
 */
function bookacti_controller_insert_form_field() {
	$form_id = intval( $_POST[ 'form_id' ] );
	
	// Check nonce and capabilities
	$is_nonce_valid	= check_ajax_referer( 'bookacti_insert_form_field', 'nonce', false );
	$is_allowed		= current_user_can( 'bookacti_edit_forms' ) && bookacti_user_can_manage_form( $form_id );
	
	if( $is_nonce_valid && $is_allowed && $form_id ) {
		
		$field_name = $_POST[ 'field_name' ];
		
		// Check if the field is known
		$fields_data = bookacti_get_default_form_fields_data();
		if( ! in_array( $field_name, array_keys( $fields_data ), true ) ) {
			wp_send_json( array( 'status' => 'failed', 'error' => 'unknown_field' ) );
		}
		
		// Check if the field already exists
		$form_fields = bookacti_get_form_fields_data( $form_id );
		$field_already_added = array();
		foreach( $form_fields as $form_field ) { $field_already_added[] = $form_field[ 'name' ]; }
		if( in_array( $field_name, $field_already_added, true ) && $fields_data[ $field_name ][ 'unique' ] ) {
			wp_send_json( array( 'status' => 'failed', 'error' => 'field_already_added' ) );
		}
		
		// Insert form field
		$field_id = bookacti_insert_form_field( $form_id, $field_name );
		
		if( $field_id !== false ) {
			
			$fields_data[ $field_name ][ 'field_id' ] = $field_id;
			
			// Update field order
			$field_order	= bookacti_get_metadata( 'form', $form_id, 'field_order', true );
			$field_order[]	= $field_id;
			bookacti_update_metadata( 'form', $form_id, array( 'field_order' => $field_order ) );
			
			do_action( 'bookacti_form_field_inserted', $field_id );
			
			// Get field data and HTML for editor
			$field_data	= bookacti_get_form_field_data( $field_id );
			$field_html = bookacti_display_form_field_for_editor( $field_data, false );
			
			wp_send_json( array( 'status' => 'success', 'field_id' => $field_id, 'field_data' => $field_data, 'field_html' => $field_html, 'field_order' => $field_order ) );
		} else {
			wp_send_json( array( 'status' => 'failed', 'error' => 'not_updated' ) );
		}
		
	} else {
		wp_send_json( array( 'status' => 'failed', 'error' => 'not_allowed' ) );
	}
}
add_action( 'wp_ajax_bookactiInsertFormField', 'bookacti_controller_insert_form_field', 10 );


/**
 * AJAX Controller - Remove a form field
 * @since 1.5.0
 */
function bookacti_controller_remove_form_field() {
	
	$field_id	= intval( $_POST[ 'field_id' ] );
	$field		= bookacti_get_form_field( $field_id );
	
	// Check nonce and capabilities
	$is_nonce_valid	= check_ajax_referer( 'bookacti_remove_form_field', 'nonce', false );
	$is_allowed		= current_user_can( 'bookacti_edit_forms' ) && bookacti_user_can_manage_form( $field[ 'form_id' ] );
	
	if( $is_nonce_valid && $is_allowed && $field_id ) {
		
		// Remove the form field and its metadata
		$removed = bookacti_delete_form_field( $field_id );
		
		if( $removed !== false ) {
			
			do_action( 'bookacti_form_field_removed', $field );
			
			// Update field order
			$field_order	= bookacti_get_metadata( 'form', $field[ 'form_id' ], 'field_order', true );
			$order_index	= array_search( $field_id, $field_order );
			
			if( $order_index !== false ) {
				unset( $field_order[ $order_index ] );
				$field_order = array_values( $field_order );
				bookacti_update_metadata( 'form', $field[ 'form_id' ], array( 'field_order' => $field_order ) );
			}
			
			wp_send_json( array( 'status' => 'success', 'field_order' => $field_order ) );
		} else {
			wp_send_json( array( 'status' => 'failed', 'error' => 'not_updated' ) );
		}
		
	} else {
		wp_send_json( array( 'status' => 'failed', 'error' => 'not_allowed' ) );
	}
}
add_action( 'wp_ajax_bookactiRemoveFormField', 'bookacti_controller_remove_form_field', 10 );


/**
 * AJAX Controller - Save form field order
 * @since 1.5.0
 */
function bookacti_controller_save_form_field_order() {
	$form_id = intval( $_POST[ 'form_id' ] );
	
	// Check nonce and capabilities
	$is_nonce_valid	= check_ajax_referer( 'bookacti_form_field_order', 'nonce', false );
	$is_allowed		= current_user_can( 'bookacti_edit_forms' ) && bookacti_user_can_manage_form( $form_id );
	
	if( $is_nonce_valid && $is_allowed && $form_id ) {
		
		$field_order	= bookacti_sanitize_form_field_order( $form_id, $_POST[ 'field_order' ] );
		$updated		= bookacti_update_metadata( 'form', $form_id, array( 'field_order' => $field_order ) );
		
		do_action( 'bookacti_form_field_order_updated', $form_id, $field_order );
		
		if( $updated !== false ) {
			wp_send_json( array( 'status' => 'success', 'field_order' => $field_order ) );
		} else {
			wp_send_json( array( 'status' => 'failed', 'error' => 'not_updated' ) );
		}
		
	} else {
		wp_send_json( array( 'status' => 'failed', 'error' => 'not_allowed' ) );
	}
}
add_action( 'wp_ajax_bookactiSaveFormFieldOrder', 'bookacti_controller_save_form_field_order', 10 );


/**
 * AJAX Controller - Update a field
 * @since 1.5.0
 */
function bookacti_controller_update_form_field() {
	
	$field_id	= intval( $_POST[ 'field_id' ] );
	$field		= bookacti_get_form_field( $field_id );
	$form_id	= $field[ 'form_id' ];
	
	// Check nonce and capabilities
	$is_nonce_valid	= check_ajax_referer( 'bookacti_update_form_field', 'nonce', false );
	$is_allowed		= current_user_can( 'bookacti_edit_forms' ) && bookacti_user_can_manage_form( $form_id );
	
	if( $is_nonce_valid && $is_allowed && $form_id ) {
		
		// Sanitize data
		$_POST[ 'name' ] = $field[ 'name' ]; $_POST[ 'type' ] = $field[ 'type' ];
		$sanitized_data	= bookacti_sanitize_form_field_data( $_POST );
		
		// Update form field
		$updated = bookacti_update_form_field( $sanitized_data );
		
		if( $updated !== false ) {
			
			// Extract metadata only
			$field_meta = array_intersect_key( $sanitized_data, bookacti_get_default_form_fields_meta( $field[ 'name' ] ) );
			
			if( $field_meta ) {
				// Update field metadata
				bookacti_update_metadata( 'form_field', $field_id, $field_meta );
			}
			
			do_action( 'bookacti_form_field_updated', $field, $sanitized_data );
			
			// Get field data and HTML for editor
			$field_data	= bookacti_get_form_field_data( $field_id );
			$field_html = bookacti_display_form_field_for_editor( $field_data, false );
			
			wp_send_json( array( 'status' => 'success', 'field_data' => $field_data, 'field_html' => $field_html ) );
		} else {
			wp_send_json( array( 'status' => 'failed', 'error' => 'not_updated' ) );
		}
		
	} else {
		wp_send_json( array( 'status' => 'failed', 'error' => 'not_allowed' ) );
	}
}
add_action( 'wp_ajax_bookactiUpdateFormField', 'bookacti_controller_update_form_field', 10 );


/**
 * AJAX Controller - Reset form meta
 * @since 1.5.0
 */
function bookacti_controller_reset_form_field() {
	
	$field_id	= intval( $_POST[ 'field_id' ] );
	$field		= bookacti_get_form_field_data( $field_id );
	$form_id	= $field[ 'form_id' ];
	
	// Check nonce and capabilities
	$is_nonce_valid	= check_ajax_referer( 'bookacti_update_form_field', 'nonce', false );
	$is_allowed		= current_user_can( 'bookacti_edit_forms' ) && bookacti_user_can_manage_form( $form_id );
	
	if( $is_nonce_valid && $is_allowed && $form_id ) {
		
		// Update form field with default values
		$defaults_data	= bookacti_sanitize_form_field_data( bookacti_get_default_form_fields_data( $field[ 'name' ] ) );
		$defaults_data[ 'field_id' ] = $field_id;
		$updated		= bookacti_update_form_field( $defaults_data );
		
		if( $updated !== false ) {
			
			// Delete all metadata to apply default
			$defaults_meta = bookacti_get_default_form_fields_meta( $field[ 'name' ] );
			if( $defaults_meta ) {
				$deleted = bookacti_delete_metadata( 'form_field', $field_id, array_keys( $defaults_meta ) );
			}
			
			do_action( 'bookacti_form_field_reset', $field );
			
			// Get field data and HTML for editor
			$field_data	= bookacti_get_form_field_data( $field_id );
			$field_html = bookacti_display_form_field_for_editor( $field_data, false );
			
			wp_send_json( array( 'status' => 'success', 'field_data' => $field_data, 'field_html' => $field_html ) );
		} else {
			wp_send_json( array( 'status' => 'failed', 'error' => 'not_updated' ) );
		}
		
	} else {
		wp_send_json( array( 'status' => 'failed', 'error' => 'not_allowed' ) );
	}
}
add_action( 'wp_ajax_bookactiResetFormField', 'bookacti_controller_reset_form_field', 10 );


/**
 * Keep calendars meta while reseting calendar form field metadata
 * @since 1.5.0
 * @param array $field_data
 * @param array $old_field_data
 */
function bookacti_reset_calendar_form_field_exceptions( $field_data ) {
	// Keep calendars
	if( $field_data[ 'type' ] === 'calendar' && isset( $_POST[ 'calendars' ] ) ) {
		// Sanitize data
		$_POST[ 'name' ]	= $field_data[ 'name' ]; $_POST[ 'type' ] = $field_data[ 'type' ];
		$sanitized_data		= bookacti_format_booking_system_attributes( $_POST );
		// Update calendars
		if( isset( $sanitized_data[ 'calendars' ] ) ) {
			$meta_to_update = array( 'calendars' => $sanitized_data[ 'calendars' ] );
			bookacti_update_metadata( 'form_field', $field_data[ 'field_id' ], $meta_to_update );
		}
	}
}
add_action( 'bookacti_form_field_reset', 'bookacti_reset_calendar_form_field_exceptions', 10, 1 );