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

function setup_widget() {
    wp_add_dashboard_widget(
		'gs_support-widget',
		__( 'Support', 'support-widget' ),
		__NAMESPACE__ . '\widget'
	);


	$asset_file = include( plugin_dir_path( __FILE__ ) . 'build/index.asset.php');

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
		'admin' => array(
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

	$to_options = apply_filters( 'gs_support_widget-to_options', $to_options );

	foreach ( $to_options as $slug => $details ) {
		// If there's a capability specified, and the user doesn't have it, don't let them use that recipient.
		if ( ! empty( $details['cap'] ) && ! current_user_can( $details['cap'] ) ) {
			unset( $to_options[ $slug ] );
		}
	}

	return $to_options;
}

function widget() {
	$to_options = get_to_options();
	$default_to = 'george';
	?>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="gs_contact-support" />
		<?php wp_nonce_field( 'gs_contact-support', '_supportnonce' ); ?>
		<label>
			<?php esc_html_e( 'To:', 'support-widget' ); ?>
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
		<input type="hidden" name="client" id="gs_support-widget__client" />
		<?php
		submit_button(
			esc_html__( 'Send', 'support-widget' )
		);
		?>
	</form>
	<?php
}

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
	$return = array(
		'client' => array(),
		'server' => array(),
	);

	// Client Data:
	$return['client']                   = isset( $_POST['client'] ) ? json_decode( $_POST['client'] ) : array();
	$return['client']['user-agent']     = $_SERVER['HTTP_USER_AGENT'] ?? __( 'Unknown', 'support-widget' );
	$return['client']['remote-addr']    = $_SERVER['REMOTE_ADDR'] ?? __( 'Unknown', 'support-widget' );
	$return['client']['remote-host']    = $_SERVER['REMOTE_HOST'] ?? __( 'Unknown', 'support-widget' );

	// Server Data:
	$return['server']['wpurl']          = get_bloginfo( 'wpurl' );
	$return['server']['wp-version']     = get_bloginfo( 'version' );
	$return['server']['php-version']    = PHP_VERSION;
	$return['server']['os']             = $_SERVER['SERVER_SIGNATURE'] ?? __( 'Unknown', 'support-widget' );
	$return['server']['is-https']       = is_ssl() ? 'https' : 'http';
	$return['server']['language']       = get_bloginfo( 'language' );
	$return['server']['charset']        = get_bloginfo( 'charset' );
	$return['server']['is-multisite']   = is_multisite() ? 'multisite' : 'singlesite';
	$return['server']['stylesheet']     = get_bloginfo( 'stylesheet_url' );
	$return['server']['plugins']        = implode( ', ', get_active_plugins() );
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

function send_support_request() {
	check_admin_referer( 'gs_contact-support', '_supportnonce' );

	$user = wp_get_current_user();

	$to = null;
	$to_options = get_to_options();
	if ( isset( $to_options[ $_POST['to'] ] ) ) {
		$to = $to_options[ $_POST['to'] ];
	}

	if ( ! $to ) {
		return;
	}

	$body = $_POST['message'];

	if ( ! empty( $_POST['extra_data'] ) ) {
		$body .= "\r\n\r\n" . print_r( get_extra_diagnostic_data(), true );
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
			__( '%1$s-Priority Support Request from %2$s at %3$s!', 'support-widget' ),
			ucwords( $_POST['priority'] ),
			$user->display_name,
			get_bloginfo( 'name' )
		),
		$body,
		array(
			sprintf( 'Reply-To: %2$s', $user->display_name, $user->email ),
			'Content-type: text/plain',
			sprintf( 'X-Priority: %d', $priority ),
		)
	);

	wp_safe_redirect( admin_url( '?didit=itdid' ) );
}
add_action(
	'admin_post_gs_contact-support',
	__NAMESPACE__ . '\send_support_request'
);
