<?php

/**
 * Sync-Connection Execution
 *
 * Handles the Sync Connection Interstitial
 *
 */
class ITSEC_Sync_Connect {

	/** @var string */
	private $sync_api = 'https://sync.ithemes.com/plugin-api/authenticate-token';

	const R_SYNC_TOKEN = 'itsec_sync_connect_token';
	const PLUGIN_SLUG = 'ithemes-sync/init.php';

	public function run() {
		add_action( 'itsec_login_interstitial_init', array( $this, 'register_interstitial' ) );
		add_action( 'login_form', array( $this, 'ferry_sync_connect_token' ) );
		add_action( 'itsec_initialize_login_interstitial_session_from_global_state', array( $this, 'set_sync_token_meta' ) );
		add_filter( 'itsec_rest_supports', array( $this, 'add_sync_connect_to_rest_supports' ) );
	}

	/**
	 * Register the sync connect interstitial.
	 *
	 * @param ITSEC_Lib_Login_Interstitial $lib
	 */
	public function register_interstitial( $lib ) {
		require_once( __DIR__ . '/class-itsec-sync-connect-interstitial.php' );

		$lib->register( 'sync-connect', new ITSEC_Sync_Connect_Interstitial( $this ) );
	}

	/**
	 * Is the given plugin installed.
	 *
	 * @return bool
	 */
	public function is_plugin_installed() {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$plugins = get_plugins();

		return ! empty( $plugins[ self::PLUGIN_SLUG ] );
	}

	/**
	 * Install the plugin contained in a zip file.
	 *
	 * @return true|WP_Error
	 */
	public function install_plugin() {
		require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
		require_once( __DIR__ . '/includes/upgrader-skin.php' );

		$skin      = new ITSEC_Ithemes_Sync_Upgrader_Skin();
		$upgrader  = new Plugin_Upgrader( $skin );
		$installed = $upgrader->install( 'https://downloads.wordpress.org/plugin/ithemes-sync.zip' );

		if ( true !== $installed && ! is_wp_error( $installed ) ) {
			$error = new WP_Error();

			foreach ( $skin->errors as $additional ) {
				if ( is_wp_error( $additional ) ) {
					ITSEC_Lib::add_to_wp_error( $error, $additional );
				} elseif ( is_string( $additional ) ) {
					$error->add( '', $additional );
				}
			}

			if ( ! $error->has_errors() ) {
				$error->add( 'installation_failed_unknown_reason', __( 'Installation failed for an unknown reason.', 'better-wp-security' ) );
			}

			if ( $messages = $skin->get_upgrade_messages() ) {
				$error->add( 'feedback', implode( ' ', $messages ) );
			}

			return $error;
		}

		return $installed;
	}

	/**
	 * Check if the user has permission to install and activate plugins.
	 *
	 * @param WP_User $user
	 *
	 * @return bool
	 */
	public function user_can_install_and_activate( WP_User $user ) {
		return $user->has_cap( 'install_plugins' ) && $user->has_cap( 'activate_plugins' ) && $user->has_cap( 'activate_plugin', self::PLUGIN_SLUG );
	}

	/**
	 * Send the activation request to iThemes Sync.
	 *
	 * @param string $username   Username of the WordPress user installing Sync.
	 * @param string $token      The secure token to pass back to iThemes Sync.
	 * @param string $sync_nonce Generated by sync plugin to verify connection from Sync dashboard.
	 *
	 * @return array|WP_Error
	 */
	public function send_activation_request( $username, $token, $sync_nonce ) {

		$data = array(
			'site'  => get_home_url(),
			'u'     => $username,
			'token' => $token,
			'wp'    => get_bloginfo( 'version' ),
			'nonce' => $sync_nonce
		);

		$remote_post_args = array(
			'method'      => 'POST',
			'timeout'     => 30,
			'body'        => $data,
			'data_format' => 'body'
		);

		$request = wp_remote_post( $this->sync_api, $remote_post_args );

		if ( is_wp_error( $request ) ) {
			if ( 'connect() timed out!' === $request->get_error_message() ) {
				return new WP_Error( 'http_request_failed', __( 'The server was unable to be contacted.', 'better-wp-security' ) );
			}

			return $request;
		}

		if ( $request['response']['code'] !== 200 ) {
			return new WP_Error( 'itsec-sync-connect-invalid-response', sprintf( __( 'Invalid response from the server (Code: %d). Please manually activate the plugin.', 'better-wp-security' ), $request['response']['code'] ) );
		}

		$response_body = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( ! $response_body ) {
			return new WP_Error( 'itsec-sync-connect-invalid-json', __( 'Invalid JSON response from Sync API. Please manually activate the plugin.', 'better-wp-security' ) );
		}

		if ( ! $response_body['success'] ) {
			return new WP_Error( 'itsec-sync-connect-invalid-token', 'Sync user or connection token could not be validated.' );
		}

		return $response_body;
	}

	/**
	 * Ferry the sync connection token into the form
	 *
	 * @internal
	 */
	public function ferry_sync_connect_token() {
		if ( ! empty( $_REQUEST[ self::R_SYNC_TOKEN ] ) ) {
			echo '<input type="hidden" name="' . esc_attr( self::R_SYNC_TOKEN ) . '" value="' . esc_attr( $_REQUEST[ self::R_SYNC_TOKEN ] ) . '">';
		}
	}

	/**
	 * Saves the sync token as meta for the interstitial.
	 *
	 * When Sync redirects the user to the login page, capture the secure sync token provided in the URL.
	 *
	 * @param ITSEC_Login_Interstitial_Session $session
	 */
	public function set_sync_token_meta( ITSEC_Login_Interstitial_Session $session ) {
		if ( ! empty( $_REQUEST[ self::R_SYNC_TOKEN ] ) ) {
			$session->set_meta( self::R_SYNC_TOKEN, $_REQUEST[ self::R_SYNC_TOKEN ] );

			if ( ! in_array( 'sync-connect', $session->get_show_after() ) ) {
				$session->add_show_after( 'sync-connect' );
			}
		}
	}

	public function add_sync_connect_to_rest_supports( $supported ) {
		$supported[] = 'sync-connect';

		return $supported;
	}
}
