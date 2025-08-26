<?php
/**
 * Plugin name: Support Widget
 */

namespace GeorgeStephanis\SupportWidget;

function setup_widget() {
    wp_add_dashboard_widget(
		'gs_support-widget',
		__( 'Support' ),
		__NAMESPACE__ . '\widget',
		__NAMESPACE__ . '\widget_control'
	);
}
add_action( 'wp_dashboard_setup', __NAMESPACE__ . '\setup_widget' );

function get_to_options() {
	$to = array(
		'admin' => array(
			'label' => __( 'Site Admin' ),
			'to'    => get_option( 'admin_email' ),
		),
		'george' => array(
			'label' => __( 'George' ),
			'to'    => 'daljo628@gmail.com',
		)
	);

	return $to;
}

function widget() {
	$to_options = get_to_options();
	$default_to = 'george';
	?>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="gs_contact-support" />
		<?php wp_nonce_field( 'gs_contact-support', '_supportnonce' ); ?>
		<label>
			<?php esc_html_e( 'To:' ); ?>
			<?php
			if ( sizeof( $to_options ) < 2 ) {
				$to_key     = key( $to_options );
				$to_details = current( $to_options );

				printf(
					'<input type="hidden" name="to" value="%1$s" />%2$s',
					esc_attr( $to_key ),
					esc_html( $to_details['label'] ?? $to_key )
				);
			} else {
				echo '<select name="to" required>';
				echo '<option value="">Select ...</option>';

				foreach ( $to_options as $to_key => $to_details ) {
					printf(
						'<option value="%1$s" %3$s>%2$s</option>',
						esc_attr( $to_key ),
						esc_html( $to_details['label'] ?? $to_key ),
						selected( $to_key, $default_to )
					);
				}
				echo '</select>';
			}
			?>
		</label>
		<label>
			<?php esc_html_e( 'Priority:' ); ?>
			<select name="priority">
				<option value="critical"><?php esc_html_e( 'CRITICAL' ); ?></option>
				<option value="high"><?php esc_html_e( 'High' ); ?></option>
				<option value="normal" selected><?php esc_html_e( 'Normal' ); ?></option>
				<option value="low"><?php esc_html_e( 'Low' ); ?></option>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'Message:' ); ?>
			<textarea name="message" style="width:100%; field-sizing:content;" required></textarea>
		</label>
		<label>
			<input type="checkbox" name="extra_data" value="true" checked />
			<?php esc_html_e( 'Submit extra diagnostic data?' ); ?>
		</label>
		<?php
		submit_button(
			esc_html__( 'Send' )
		);
		?>
	</form>
	<?php
}

function widget_control() {
	_e( 'Control Options...' );
}

function get_extra_diagnostic_data() {

}

function send_support_request() {
	check_admin_referer( 'gs_contact-support', '_supportnonce' );

	$to_options = get_to_options();



	wp_safe_redirect( admin_url( '?didit=itdid' ) );
}
add_action(
	'admin_post_gs_contact-support',
	__NAMESPACE__ . '\send_support_request'
);
