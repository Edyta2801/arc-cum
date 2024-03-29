<?php

class ITSEC_Hide_Backend {
	private $disable_filters = false;
	private $token_var = 'itsec-hb-token';

	private $settings;

	/**
	 * Bootstrap Hide Backend functionality if the module is active.
	 *
	 * @return void
	 */
	public function run() {
		$this->settings = ITSEC_Modules::get_settings( 'hide-backend' );

		add_filter( 'itsec_notifications', array( $this, 'register_notification' ) );
		add_filter( 'itsec_hide-backend_notification_strings', array( $this, 'notification_strings' ) );

		if ( ! $this->settings['enabled'] || ITSEC_Core::is_temp_disable_modules_set() ) {
			return;
		}

		add_action( 'itsec_initialized', array( $this, 'handle_specific_page_requests' ), 1000 );
		add_action( 'signup_hidden_fields', array( $this, 'add_token_to_registration_form' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'login_enqueue' ) );

		add_filter( 'site_url', array( $this, 'filter_generated_url' ), 100, 2 );
		add_filter( 'network_site_url', array( $this, 'filter_generated_url' ), 100, 2 );
		add_filter( 'admin_url', array( $this, 'filter_admin_url' ), 100, 2 );
		add_filter( 'wp_redirect', array( $this, 'filter_redirect' ) );
		add_filter( 'comment_moderation_text', array( $this, 'filter_comment_moderation_text' ) );
		add_filter( 'itsec_notify_admin_page_url', array( $this, 'filter_notify_admin_page_urls' ) );

		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
	}

	/**
	 * Filters emailed comment moderation links to use modified login links with redirection.
	 *
	 * Comment moderation links link directly to wp-admin pages. Since direct requests to wp-admin are blocked by Hide
	 * Backend, these links are updated to link to the login page with a redirect to the wp-admin page.
	 *
	 * @since 4.5
	 *
	 * @param string $text Comment moderation email text.
	 *
	 * @return string Comment moderation email text.
	 */
	public function filter_comment_moderation_text( $text ) {
		if ( $this->disable_filters ) {
			return $text;
		}

		// The email is plain text and the links are at the end of lines, so a lazy match can be used.
		if ( preg_match_all( '|(https?:\/\/((.*)wp-admin(.*)))|', $text, $urls ) ) {
			foreach ( $urls[0] as $url ) {
				$url  = trim( $url );
				$text = str_replace( $url, wp_login_url( $url ), $text );
			}
		}

		return $text;
	}

	/**
	 * Ensure that login and registration pages and their aliases are handled properly.
	 *
	 * This function is responsible for identifying if the current page request is for wp-login.php, wp-signup.php, a
	 * canonical alias for one of those pages, a wp-admin request, or one of Hide Backend's replacements pages. If a
	 * matching page page is found, the appropriate function is called to handle the rest of the processing.
	 *
	 * @since 4.0
	 *
	 * @return void
	 */
	public function handle_specific_page_requests() {
		if ( ITSEC_Core::is_api_request() ) {
			return;
		}

		$request_path = ITSEC_Lib::get_request_path();

		if ( strpos( $request_path, '/' ) !== false ) {
			list( $request_path ) = explode( '/', $request_path );
		}

		$check = array_unique( array(
			$request_path,
			urldecode( $request_path ),
			ITSEC_Lib::unfwdslash( substr( $_SERVER['SCRIPT_FILENAME'], strlen( ABSPATH ) ) ),
		) );

		foreach ( $check as $path ) {
			$this->handle_request_path( $path );
		}
	}

	/**
	 * Handle determining if we need to block access to the request path.
	 *
	 * @param string $request_path
	 */
	private function handle_request_path( $request_path ) {
		if ( $request_path === $this->settings['slug'] ) {
			$this->handle_login_alias();
		} elseif ( in_array( $request_path, array( 'wp-login', 'wp-login.php' ) ) ) {
			$this->handle_canonical_login_page();
		} elseif ( 'wp-admin' === $request_path || ITSEC_Lib::str_starts_with( $request_path, 'wp-admin/' ) ) {
			$this->handle_wp_admin_page();
		} elseif ( $request_path === $this->settings['register'] && $this->allow_access_to_wp_signup() ) {
			$this->handle_registration_alias();
		} elseif ( 'wp-signup.php' === $request_path ) {
			$this->handle_canonical_signup_page();
		}
	}

	/**
	 * Handle a request for the Hide Backend replacement login page slug.
	 *
	 * @return void
	 */
	private function handle_login_alias() {
		if ( isset( $_GET['action'] ) && $_GET['action'] === trim( $this->settings['post_logout_slug'] ) ) {
			// I'm not sure if this feature is still needed or if anyone still uses it. - Chris
			do_action( 'itsec_custom_login_slug' );
		}

		$this->do_redirect_with_token( 'login', 'wp-login.php' );
	}

	/**
	 * Handle a request for wp-login.php or a canonical alias for it.
	 *
	 * @return void
	 */
	private function handle_canonical_login_page() {
		$action = isset( $_GET['action'] ) ? $_GET['action'] : '';

		if ( 'postpass' === $action ) {
			return;
		}

		if ( 'register' === $action ) {
			if ( 'wp-signup.php' !== $this->settings['register'] ) {
				$this->block_access( 'register' );
			}

			return;
		}

		if ( 'jetpack_json_api_authorization' === $action && has_filter( 'login_form_jetpack_json_api_authorization' ) ) {
			// Jetpack handles authentication for this action. Processing is left to it.
			return;
		}

		if ( 'jetpack-sso' === $action && has_filter( 'login_form_jetpack-sso' ) ) {
			// Jetpack's SSO redirects from wordpress.com to wp-login.php on the site. Only allow this process to
			// continue if they successfully log in, which should happen by login_init in Jetpack which happens just
			// before this action fires.
			add_action( 'login_form_jetpack-sso', array( $this, 'block_access' ) );

			return;
		}

		$this->block_access( 'login' );
	}

	/**
	 * Handle a request for the Hide Backend replacement register page slug.
	 *
	 * @return void
	 */
	private function handle_registration_alias() {

		if ( 'wp-signup.php' === $this->settings['register'] ) {
			return;
		}

		if ( get_option( 'users_can_register' ) ) {
			if ( is_multisite() ) {
				$this->do_redirect_with_token( 'register', 'wp-signup.php' );
			} else {
				$this->do_redirect_with_token( 'register', 'wp-login.php?action=register' );
			}
		}
	}

	/**
	 * Handle a request for wp-signup.php.
	 *
	 * @return void
	 */
	private function handle_canonical_signup_page() {
		$this->block_access( 'register' );
	}

	/**
	 * Handle a request for any wp-admin directory request.
	 *
	 * @return void
	 */
	private function handle_wp_admin_page() {
		$request_path = ITSEC_Lib::get_request_path();

		if ( 'wp-admin/maint/repair.php' === $request_path && defined( 'WP_ALLOW_REPAIR' ) ) {
			// Make sure to only allow access if the page would function.
			return;
		}

		$this->block_access( 'login' );
	}

	/**
	 * Block access to the page if the visitor is not a logged in user and the request fails validation.
	 *
	 * @param string $type The type of request to be validated.
	 *
	 * @return void
	 */
	public function block_access( $type = 'login' ) {
		if ( is_user_logged_in() || $this->is_validated( $type ) ) {
			return;
		}

		if ( $this->settings['theme_compat'] ) {
			// The "Enable Redirection" setting is enabled. Redirect to the "Redirection Slug" setting.
			wp_redirect( ITSEC_Lib::get_home_root() . $this->settings['theme_compat_slug'], 302 );
			exit;
		} else {
			// The "Enable Redirection" setting is disabled. Return a 403 error.
			wp_die( __( 'This has been disabled.', 'better-wp-security' ), 403 );
		}
	}

	/**
	 * Redirect to requested path with the token query arg added to ensure that the redirected request is validated.
	 *
	 * This function will also set an appropriate cookie when doing the redirect. The presence of the cookie and query
	 * arg should ensure that the redirect request validates properly.
	 *
	 * @param string $type The type of request to add an access token for.
	 * @param string $path The path to redirect to.
	 *
	 * @return void
	 */
	private function do_redirect_with_token( $type, $path ) {
		// Set the cookie so that access via unknown integrations works more smoothly.
		$this->set_cookie( $type );

		// Preserve existing query vars and add access token query arg.
		$query_vars                     = $_GET;
		$query_vars[ $this->token_var ] = $this->get_access_token( $type );
		$query                          = http_build_query( $query_vars, null, '&' );

		// Disable the Hide Backend URL filters to prevent infinite loops when calling site_url().
		$this->disable_filters = true;

		if ( false === strpos( $path, '?' ) ) {
			$url = site_url( "$path?$query" );
		} else {
			$url = site_url( "$path&$query" );
		}

		wp_redirect( $url );
		exit;
	}

	/**
	 * Filter generated login and signup URLs to include the access token query arg.
	 *
	 * @param string $url  The complete URL to be filtered.
	 * @param string $path The path submitted by the originating function call.
	 *
	 * @return string The complete URL with conditionally added access token query arg.
	 */
	public function filter_generated_url( $url, $path ) {
		if ( $this->disable_filters ) {
			return $url;
		}

		list( $clean_path ) = explode( '?', $path );

		if ( 'wp-login.php' === $clean_path && 'wp-login.php' !== $this->settings['slug'] ) {

			$request_path = ITSEC_Lib::get_request_path();

			if ( false !== strpos( $path, 'action=postpass' ) ) {
				// No special handling is needed for a password-protected post.
				return $url;
			} elseif ( false !== strpos( $path, 'action=register' ) ) {
				$url = $this->add_token_to_url( $url, 'register' );
			} elseif ( false !== strpos( $path, 'action=rp' ) ) {
				$url = $this->add_token_to_url( $url, 'login' );
			} elseif ( 'wp-login.php' !== $request_path || empty( $_GET['action'] ) || 'register' !== $_GET['action'] ) {
				$url = $this->add_token_to_url( $url, 'login' );
			}
		} elseif ( 'wp-signup.php' === $clean_path && 'wp-signup.php' !== $this->settings['register'] ) {
			$url = $this->add_token_to_url( $url, 'register' );
		}

		return $url;
	}

	/**
	 * Filter the admin URL to include hide backend tokens when necessary.
	 *
	 * @param string $url  Complete admin URL.
	 * @param string $path Path passed to the admin_url function.
	 *
	 * @return string
	 */
	public function filter_admin_url( $url, $path ) {

		if ( 0 === strpos( $path, 'profile.php?newuseremail=' ) ) {
			$url = $this->add_token_to_url( $url, 'login' );
		}

		return $url;
	}

	/**
	 * Filter redirection URLs to login and signup pages to include the access token query arg.
	 *
	 * @param string $location The relative path to redirect to.
	 *
	 * @return string The location with conditionally added access token query arg.
	 */
	public function filter_redirect( $location ) {
		return $this->filter_generated_url( $location, $location );
	}

	/**
	 * Filter URLs to admin pages in emails to include the access token query arg.
	 *
	 * This ensures that users are redirected to the correct login page if they are logged-out.
	 *
	 * @param string $location
	 *
	 * @return string
	 */
	public function filter_notify_admin_page_urls( $location ) {
		return $this->add_token_to_url( $location, 'login' );
	}

	/**
	 * Add the access token query arg to the URL.
	 *
	 * @param string $url  The URL to modify.
	 * @param string $type The type of request to add an access token for.
	 *
	 * @return string The URL with the added access token query arg.
	 */
	private function add_token_to_url( $url, $type ) {
		$token = $this->get_access_token( $type );

		$url .= ( false === strpos( $url, '?' ) ) ? '?' : '&';
		$url .= $this->token_var . '=' . urlencode( $token );

		return $url;
	}

	/**
	 * Add a hidden input containing the appropriate access token name and value.
	 *
	 * This function is only used on multisite user signup pages. It is needed since the code that generates the form on
	 * that page does not use site_url() or network_site_url() to generate a full URL for form's action URL.
	 *
	 * @param string $context The type of signup form being rendered.
	 *
	 * @return null
	 */
	public function add_token_to_registration_form( $context ) {
		if ( 'validate-user' === $context ) {
			echo '<input type="hidden" name="' . esc_attr( $this->token_var ) . '" value="' . esc_attr( $this->get_access_token( 'register' ) ) . '" />' . "\n";
		}
	}

	/**
	 * Hide the navigation links on the registration page.
	 *
	 * These links have their security tokens removed in PHP. We only hide them for UX purposes as they would
	 * lead to a 404 page.
	 */
	public function login_enqueue() {
		if ( ! empty( $_GET['action'] ) && 'register' === $_GET['action'] ) {
			wp_enqueue_style( 'itsec-hide-backend-login-page', plugins_url( 'css/login-page.css', __FILE__ ) );
		}
	}

	/**
	 * Register the New Login URL notification.
	 *
	 * @param array $notifications
	 *
	 * @return array
	 */
	public function register_notification( $notifications ) {

		if ( ITSEC_Modules::get_setting( 'hide-backend', 'enabled' ) ) {
			$notifications['hide-backend'] = array(
				'subject_editable' => true,
				'message_editable' => true,
				'schedule'         => ITSEC_Notification_Center::S_NONE,
				'recipient'        => ITSEC_Notification_Center::R_USER_LIST,
				'tags'             => array( 'login_url', 'site_title', 'site_url' ),
				'module'           => 'hide-backend',
			);
		}

		return $notifications;
	}

	/**
	 * Register the strings for the Hide Backend change notification.
	 *
	 * @return array
	 */
	public function notification_strings() {
		return array(
			'label'       => esc_html__( 'Hide Backend – New Login URL', 'better-wp-security' ),
			'description' => sprintf( esc_html__( '%1$sHide Backend%2$s will notify the chosen recipients whenever the login URL is changed.', 'better-wp-security' ), '<a href="#" data-module-link="hide-backend">', '</a>' ),
			'subject'     => esc_html__( 'WordPress Login Address Changed', 'better-wp-security' ),
			'message'     => esc_html__( 'The login address for {{ $site_title }} has changed. The new login address is {{ $login_url }}. You will be unable to use the old login address.', 'better-wp-security' ),
			'tags'        => array(
				'login_url'  => esc_html__( 'The new login link.', 'better-wp-security' ),
				'site_title' => esc_html__( 'The WordPress Site Title. Can be changed under Settings -> General -> Site Title', 'better-wp-security' ),
				'site_url'   => esc_html__( 'The URL to your website.', 'better-wp-security' ),
			),
		);
	}

	/**
	 * Creates a cookie to validate future requests.
	 *
	 * @param string $type     The type of request to add an access token for.
	 * @param int    $duration Number of seconds that the key will be valid.
	 *
	 * @return null
	 */
	private function set_cookie( $type, $duration = 3600 /* 1 hour */ ) {
		$expires = time() + $duration;
		setcookie( "itsec-hb-$type-" . COOKIEHASH, $this->get_access_token( $type ), $expires, ITSEC_Lib::get_home_root(), COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Checks to see if a cookie or query arg value validates the current request for the type being checked.
	 *
	 * @param string $type The type of request to add an access token to validate.
	 *
	 * @return bool true if the request is validated, false otherwise.
	 */
	private function is_validated( $type ) {
		$token = $this->get_access_token( $type );

		if ( isset( $_REQUEST[ $this->token_var ] ) && $_REQUEST[ $this->token_var ] === $token ) {
			$this->set_cookie( $type );

			return true;
		} elseif ( isset( $_COOKIE[ "itsec-hb-$type-" . COOKIEHASH ] ) && $_COOKIE[ "itsec-hb-$type-" . COOKIEHASH ] === $token ) {
			return true;
		}

		return false;
	}

	/**
	 * The access token to use for the specific request.
	 *
	 * @param string $type The type of request to create an access token for.
	 *
	 * @return string The access token.
	 */
	private function get_access_token( $type ) {
		if ( isset( $this->settings[ $type ] ) ) {
			return $this->settings[ $type ];
		}

		return $this->settings['slug'];
	}

	private function allow_access_to_wp_signup() {

		if ( is_multisite() ) {
			// Multisite will show its own error message and without links if signups are disabled.
			return true;
		}

		if ( get_option( 'users_can_register' ) ) {
			return true;
		}

		return false;
	}
}
