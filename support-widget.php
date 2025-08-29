<?php
/**
 * Plugin name:       Support Widget
 * Description:       A support widget, enabling easier communication to dev agencies.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            George Stephanis
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       support-widget
 * Domain Path:       /languages
 *
 * @package GeorgeStephanis\SupportWidget
 */

namespace GeorgeStephanis\SupportWidget;

/**
 * Initialization.  Add the dashboard widget, and enqueue the styles and scripts.
 *
 * @return void
 */
function setup_widget() {
	wp_add_dashboard_widget(
		'gs_support_widget',
		__( 'Support', 'support-widget' ),
		__NAMESPACE__ . '\widget'
	);

	$asset_file = include plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

	wp_enqueue_style(
		'support-widget',
		plugins_url( 'build/index.css', __FILE__ ),
		array(),
		$asset_file['version']
	);

	wp_enqueue_script(
		'support-widget',
		plugins_url( 'build/index.js', __FILE__ ),
		$asset_file['dependencies'],
		$asset_file['version'],
		true
	);
}
add_action( 'wp_dashboard_setup', __NAMESPACE__ . '\setup_widget' );

/**
 * Get a list of the available recipients for support messages.
 *
 * @return array The available options, restricted by capability checks.
 */
function get_to_options() {
	$to_options = array(
		'admin'  => array(
			'label' => __( 'Site Admin', 'support-widget' ),
			'to'    => get_option( 'admin_email' ),
			'cap'   => 'edit_posts',
		),
		'george' => array(
			'label' => __( 'George', 'support-widget' ),
			'to'    => 'daljo628@gmail.com',
			'cap'   => 'do_not_allow',
		),
	);

	$to_options = apply_filters( 'gs_support_widget_to_options', $to_options );

	foreach ( $to_options as $slug => $details ) {
		// If there's a capability specified, and the user doesn't have it, don't let them use that recipient.
		if ( ! empty( $details['cap'] ) && ! current_user_can( $details['cap'] ) ) {
			unset( $to_options[ $slug ] );
		}
	}

	return $to_options;
}

/**
 * The dashboard widget itself.
 *
 * @return void
 */
function widget() {
	$to_options = get_to_options();
	$default_to = apply_filters( 'gs_support_widget_default_to', null );
	?>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="gs_contact-support" />
		<?php wp_nonce_field( 'gs_contact-support', '_supportnonce' ); ?>
		<label>
			<?php esc_html_e( 'To:', 'support-widget' ); ?>
			<?php
			if ( count( $to_options ) < 2 ) {
				$to_key     = key( $to_options );
				$to_details = current( $to_options );

				printf(
					'<input type="hidden" name="to" value="%1$s" />%2$s',
					esc_attr( $to_key ),
					esc_html( $to_details['label'] ?? $to_key )
				);
			} else {
				echo '<select name="to" required>';
				echo '<option value="">' . esc_html__( 'Select …', 'support-widget' ) . '</option>';

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
			<?php esc_html_e( 'Priority:', 'support-widget' ); ?>
			<select name="priority">
				<option value="critical"><?php esc_html_e( 'CRITICAL', 'support-widget' ); ?></option>
				<option value="high"><?php esc_html_e( 'High', 'support-widget' ); ?></option>
				<option value="normal" selected><?php esc_html_e( 'Normal', 'support-widget' ); ?></option>
				<option value="low"><?php esc_html_e( 'Low', 'support-widget' ); ?></option>
			</select>
		</label>
		<label>
			<?php esc_html_e( 'Message:', 'support-widget' ); ?>
			<textarea name="message" style="width:100%; min-height:3em; field-sizing:content;" required></textarea>
		</label>
		<label>
			<input type="checkbox" name="extra_data" value="true" checked />
			<?php esc_html_e( 'Submit extra diagnostic data?', 'support-widget' ); ?>
		</label>
		<input type="hidden" name="client" id="gs_support_widget__client" />
		<?php
		submit_button(
			esc_html__( 'Send', 'support-widget' )
		);
		?>
	</form>
	<?php
}

/**
 * Get all the active plugins, including network activated.  For debugging data.
 *
 * Loosely based on a prior implementation I'd done in Jetpack.
 *
 * @return array
 */
function get_active_plugins() {
	$active_plugins = (array) get_option( 'active_plugins', array() );

	if ( is_multisite() ) {
		// Due to legacy code, active_sitewide_plugins stores them in the keys,
		// whereas active_plugins stores them in the values.
		$network_plugins = array_keys( get_site_option( 'active_sitewide_plugins', array() ) );
		if ( $network_plugins ) {
			$active_plugins = array_merge( $active_plugins, $network_plugins );
		}
	}

	sort( $active_plugins );

	return array_unique( $active_plugins );
}

/**
 * Get an array of additional debug data.
 *
 * @return array
 */
function get_extra_diagnostic_data() {
	check_admin_referer( 'gs_contact-support', '_supportnonce' );

	$return = array(
		'client' => array(),
		'server' => array(),
	);

	// Client Data:
	$return['client']                = isset( $_POST['client'] ) ? json_decode( $_POST['client'] ) : array();
	$return['client']['user-agent']  = $_SERVER['HTTP_USER_AGENT'] ?? __( 'Unknown', 'support-widget' );
	$return['client']['remote-addr'] = $_SERVER['REMOTE_ADDR'] ?? __( 'Unknown', 'support-widget' );
	$return['client']['remote-host'] = $_SERVER['REMOTE_HOST'] ?? __( 'Unknown', 'support-widget' );

	// Server Data:
	$return['server']['wpurl']        = get_bloginfo( 'wpurl' );
	$return['server']['wp-version']   = get_bloginfo( 'version' );
	$return['server']['php-version']  = PHP_VERSION;
	$return['server']['os']           = $_SERVER['SERVER_SIGNATURE'] ?? __( 'Unknown', 'support-widget' );
	$return['server']['is-https']     = is_ssl() ? 'https' : 'http';
	$return['server']['language']     = get_bloginfo( 'language' );
	$return['server']['charset']      = get_bloginfo( 'charset' );
	$return['server']['is-multisite'] = is_multisite() ? 'multisite' : 'singlesite';
	$return['server']['stylesheet']   = get_bloginfo( 'stylesheet_url' );
	$return['server']['plugins']      = implode( ', ', get_active_plugins() );
	if ( function_exists( 'get_mu_plugins' ) ) {
		$return['server']['mu-plugins'] = implode( ', ', array_keys( get_mu_plugins() ) );
	}

	if ( function_exists( 'get_space_used' ) ) { // Only available in multisite.
		$space_used = get_space_used();
	} else {
		// This is the same as `get_space_used`, except it does not apply the short-circuit filter.
		$upload_dir = wp_upload_dir();
		$space_used = get_dirsize( $upload_dir['basedir'] ) / MB_IN_BYTES;
	}
	$return['server']['space-used'] = $space_used;

	if ( ! empty( $_SERVER['SERVER_ADDR'] ) || ! empty( $_SERVER['LOCAL_ADDR'] ) ) {
		$return['server']['ip'] = ! empty( $_SERVER['SERVER_ADDR'] ) ? wp_unslash( $_SERVER['SERVER_ADDR'] ) : wp_unslash( $_SERVER['LOCAL_ADDR'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized just below.
	}

	return $return;
}

/**
 * Send the email for a support request.
 *
 * @return void
 */
function send_support_request() {
	check_admin_referer( 'gs_contact-support', '_supportnonce' );

	$user = wp_get_current_user();

	$to         = null;
	$to_options = get_to_options();
	if ( isset( $to_options[ $_POST['to'] ] ) ) {
		$to = $to_options[ $_POST['to'] ];
	}

	if ( ! $to ) {
		return;
	}

	$body = '<pre>' . $_POST['message'] . '</pre>' . "\r\n\r\n";

	if ( ! empty( $_POST['extra_data'] ) ) {
		$extra = get_extra_diagnostic_data();

		$body .= '<h4>' . esc_html__( 'Extra Diagnostic Data:', 'support-widget' ) . '</h4>' . "\r\n";
		$body .= '<table><tbody>' . "\r\n";
		$body .= '<tr><th scope="col" colspan="2">' . esc_html__( 'Client Data', 'support-widget' ) . '</th></tr>' . "\r\n";
		foreach ( $extra['client'] as $key => $value ) {
			$body .= '<tr><th scope="row">' . esc_html( $key ) . '</th><td>' . esc_html( $value ) . '</td></tr>' . "\r\n";
		}
		$body .= '<tr><th scope="col" colspan="2">' . esc_html__( 'Server Data', 'support-widget' ) . '</th></tr>' . "\r\n";
		foreach ( $extra['server'] as $key => $value ) {
			$body .= '<tr><th scope="row">' . esc_html( $key ) . '</th><td>' . esc_html( $value ) . '</td></tr>' . "\r\n";
		}
		$body .= '</tbody></table>' . "\r\n\r\n";
	}

	switch ( $_POST['priority'] ) {
		case 'critical':
			$priority = 1;
			break;
		case 'high':
			$priority = 2;
			break;
		case 'normal':
			$priority = 3;
			break;
		case 'low':
			$priority = 5;
			break;
		default:
			$priority = 3;
	}

	wp_mail(
		sprintf( '%2$s', $to['label'], $to['to'] ),
		sprintf(
			// Translators: 1: Priority level, 2: display name, 3: site title
			__( '%1$s-Priority Support Request from %2$s at %3$s!', 'support-widget' ),
			ucwords( $_POST['priority'] ),
			$user->display_name,
			get_bloginfo( 'name' )
		),
		$body,
		array(
			sprintf( 'Reply-To: %2$s', $user->display_name, $user->email ),
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'X-Priority: %d', $priority ),
		)
	);

	wp_safe_redirect( admin_url( '?didit=itdid' ) );
}
add_action(
	'admin_post_gs_contact-support',
	__NAMESPACE__ . '\send_support_request'
);
