<?php
namespace LWS\USERS;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** accpetion $_POST with
 * lws-login-action in {'login', 'logout', 'register'}
 * redirect_to as URL */
class LogAction
{
	private static $Instance = null;
	protected static $Actions = array('login', 'logout', 'register');

	public function __construct()
	{
		if( self::$Instance == null )
			self::$Instance = $this;
		else
			error_log("An instance of \\LWS\\USERS\\Dialog already exists.");

		// check login action as soon as possible
		add_action('after_setup_theme', array($this, 'logAction'), -999999);

		$this->RedirectTo = '';
		$this->ErrorMsg = array();
		$this->User = false;
		$this->Action = -1;

		$this->ErrorMsg = array();
		add_action('lws_users_after_register_action', array($this, 'addUserActivationLock'), 10, 4);
		add_filter('lws_users_login_error', array($this, 'loginError'), 9999);
		add_filter('lws_users_signon', array($this, 'userSignOn'));
		add_filter('lws_users_register_error', array($this, 'registError'));

		// avoid connexion if account is still not registered.
		add_filter( 'authenticate', array($this, 'checkForActivation'), 9999, 3 );
		// allow account confirmation
		add_filter( 'do_parse_request', array( $this, 'parseRequestForAction') );
	}

	public static function instance()
	{
		if( self::$Instance == null )
			self::$Instance = new Dialog(false);
		return self::$Instance;
	}

	public static function error($action, $dft='')
	{
		if( array_key_exists($action, LogAction::instance()->ErrorMsg) )
			return LogAction::instance()->ErrorMsg[$action];
		else
			return $dft;
	}

	protected function logIn()
	{
		$this->User = false;
		$this->ErrorMsg = array();

		$secure_cookie = '';
		$user_name = isset($_POST['log']) ? sanitize_user($_POST['log']) : '';
		$rememberme = isset($_POST['rememberme']) ? !empty(sanitize_text_field($_POST['rememberme'])) : false;
		$user = get_user_by( 'login', $user_name );

		if ( !$user && strpos( $user_name, '@' ) )
			$user = get_user_by( 'email', $user_name );

		if( $user ) // last chance to add controle and return false to avoid connection
			$user = apply_filters('lws_users_login_action_check', $user);

		if( $user )
		{
			if ( get_user_option('use_ssl', $user->ID) )
			{
				$secure_cookie = true;
				force_ssl_admin(true);
			}

			$params = array(
				'user_login' => $user->user_login,
				'user_password' => isset($_POST['pwd']) ? $_POST['pwd'] : '',
				'remember' => $rememberme
			);

			global $current_user;
			unset($current_user);
			$user = wp_signon( $params, $secure_cookie );
			$user = apply_filters('lws_users_signon', $user);
			if( !is_wp_error($user) )
			{
				wp_set_current_user($user->ID);
				$this->User = $user;
			}
			else
				$this->ErrorMsg['login'] = apply_filters('lws_users_login_error', __("Check your login/password.", 'lws-users'));
		}

		do_action('lws_users_after_login_action', $this->User);
		return !is_wp_error($this->User);
	}

	protected function logReg()
	{
		$this->User = false;
		$user_login = sanitize_user(isset($_POST['log']) ? sanitize_user($_POST['log']) : '');
		$user_email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
		$user_pwd = isset($_POST['pwd']) ? $_POST['pwd'] : '';
		$user_pwd2 = isset($_POST['pwd2']) ? $_POST['pwd2'] : '';

		$user_email = apply_filters( 'user_registration_email', $user_email );

		if( $this->regInfoValidity($user_login, $user_email, $user_pwd, $user_pwd2) )
		{
			$user_id = new \WP_Error();
			do_action( 'register_post', $user_login, $user_email, $user_id );
			$user_id = apply_filters( 'registration_errors', $user_id, $user_login, $user_email );

			if( !$user_id->get_error_code() )
				$user_id = wp_create_user( $user_login, $user_pwd, $user_email );

			if( !$user_id || is_wp_error($user_id) )
			{
				if( is_wp_error($user_id) )
					error_log($user_id->get_error_message());
				$this->ErrorMsg['register'] = apply_filters('lws_users_register_error', sprintf( __( "Couldn't register you. Please contact the <a href='mailto:%s'>webmaster</a> !", 'lws-users'), get_option( 'admin_email' ) ));
			}
			else
			{
				// clear cookie, just to be sure.
				wp_clear_auth_cookie();
				if( has_action('lws_users_after_register_action') )
				{
					do_action('lws_users_after_register_action', $user_id, $user_pwd, $user_email, $user_login);
					$this->ErrorMsg = apply_filters('lws_users_register_error', $this->ErrorMsg);
				}
				else
				{
					do_action( 'register_new_user', $user_id );
					$this->autoConnect($user_id, $user_login, $user_pwd);
				}
			}
		}
		return empty($this->ErrorMsg);
	}

	protected function autoConnect($user_id, $user_login, $user_pwd)
	{
		$secure_cookie = '';
		if ( get_user_option('use_ssl', $user_id) )
		{
			$secure_cookie = true;
			force_ssl_admin(true);
		}
		$params = array(
			'user_login' => $user_login,
			'user_password' => $user_pwd
		);
		global $current_user;
		unset($current_user);
		$user = wp_signon( $params, $secure_cookie );
		if( !is_wp_error($user) )
		{
			wp_set_current_user($user->ID);
			$this->User = $user;
		}
		else
			$this->ErrorMsg['register'] = apply_filters('lws_users_register_error', __("Connexion failed, please retry later.", LWS_USERS_DOMAIN));
	}

	protected function regInfoValidity($user_login, $email, $pwd, $pwd2)
	{
		$this->ErrorMsg = array();

		if( empty($user_login) )
			$this->ErrorMsg['register'] = apply_filters('lws_users_register_error', __( 'A login is required.', 'lws-users') );
		else if( empty($email) )
			$this->ErrorMsg['register'] = apply_filters('lws_users_register_error', __( 'A valid email is required.', 'lws-users') );
		else if( empty($pwd) )
			$this->ErrorMsg['register'] = apply_filters('lws_users_register_error', __( 'A password is required.', 'lws-users') );
		else if( $pwd != $pwd2 )
			$this->ErrorMsg['register'] = apply_filters('lws_users_register_error', __( 'Password and confirmation must be the same.', 'lws-users') );
		else if( username_exists($user_login) )
			$this->ErrorMsg['register'] = apply_filters('lws_users_register_error', __( 'This username is already registered. Please choose another one.', 'lws-users') );
		else if ( !validate_username( $user_login ) )
			$this->ErrorMsg['register'] = apply_filters('lws_users_register_error', __( 'This username is invalid because it uses illegal characters. Please enter a valid username.', 'lws-users') );
		else if( email_exists($email) )
			$this->ErrorMsg['register'] = apply_filters('lws_users_register_error', __( 'This email is already registered, please choose another one.', 'lws-users') );
		else if( !is_email($email) )
			$this->ErrorMsg['register'] = apply_filters('lws_users_register_error', __( 'Given email is not valid.', 'lws-users') );
		else {
			/** This filter is documented in wp-includes/user.php */
			$illegal_user_logins = array_map( 'strtolower', (array) apply_filters( 'illegal_user_logins', array() ) );
			if ( in_array( strtolower( $user_login ), $illegal_user_logins ) ) {
				$this->ErrorMsg['register'] = apply_filters('lws_users_register_error', __('Sorry, that username is not allowed.', 'lws-users') );
			}
		}

		return empty($this->ErrorMsg);
	}

	protected function logOut()
	{
		$this->User = wp_get_current_user();
		if( (get_class($this->User) == 'WP_User') && ($this->User->ID != 0) )
		{
			do_action('lws_users_before_logout_action', $this->User);
			wp_logout();
			global $current_user;
			unset($current_user);
			wp_set_current_user( 0 );
		}
		else
			$this->User = false;
		return ($this->User !== false);
	}

	protected function redirect()
	{
		/** lws_users_redirection
		 * @param 0 : default redirection url
		 * @param 1 : WP_User logged or previously logged if logout
		 * @prama 2 : is user logs in. */
		$this->RedirectTo = apply_filters('lws_users_redirection', $this->RedirectTo, $this->User, ($this->Action != 1));
		if( !empty($this->RedirectTo) )
		{
			wp_redirect($this->RedirectTo);
			exit();
		}
	}

	public function logAction()
	{
		$this->Action = -1;

		if( isset($_POST['lws-login-action']) && in_array(sanitize_text_field($_POST['lws-login-action']), self::$Actions) )
		{
				$this->Action = array_search(sanitize_text_field($_POST['lws-login-action']), self::$Actions);
				if( $this->Action === 0 )
					$this->logIn();
				else if( $this->Action === 1 )
					$this->logOut();
				else if( $this->Action === 2 )
					$this->logReg();

			if( empty($this->RedirectTo) )
			{
				$this->RedirectTo = isset($_POST['redirect_to']) ? wp_validate_redirect(wp_sanitize_redirect($_POST['redirect_to']), apply_filters( 'wp_safe_redirect_fallback', admin_url(), 302) ) : '';
				if( $this->User !== false )
					$this->redirect();
			}
			else
			{
				wp_redirect($this->RedirectTo);
				exit();
			}
		}
	}

	function addUserActivationLock($user_id, $user_pwd, $user_email, $user_login)
	{
		remove_action( 'register_new_user', 'wp_send_new_user_notifications' ); // since we manage mail ourself, but other pulgin sould use this hook.
		if( !empty(get_option('lws_users_pregister_alert_admin', 'on')) )
			wp_new_user_notification( $user_id, null, 'admin' ); // alert admin
		do_action( 'register_new_user', $user_id );

		// if confirm register by email is checked, send activation link.
		if( !empty(get_option('lws_users_plogin_activation', '')) )
			$this->emailValidation($user_id, $user_login, $user_email);
		else // connect immediately
			$this->autoConnect($user_id, $user_login, $user_pwd);
	}

	function loginError($err)
	{
		if( isset($this->ErrorMsg['login']) && !empty($this->ErrorMsg['login']) )
			return $this->ErrorMsg['login'];
		else
			return $err;
	}

	function registError($err)
	{
		if( !empty($this->ErrorMsg) )
			return $this->ErrorMsg;
		else
			return $err;
	}

	function userSignOn($user)
	{
		if( is_wp_error($user) && in_array('activation_required', $user->get_error_codes()) )
		{
			$txt = __("Your email must be validated first. Click [activation_link]here[/activation_link] to re-send the activation mail.", LWS_USERS_DOMAIN);
			$this->ErrorMsg['login'] = preg_replace_callback('#\[activation_link\]((.*)\[/activation_link\])?#', array($this, 'replaceSendActivationLink'), $txt);
		}
		return $user;
	}

	function replaceSendActivationLink($match)
	{
		$txt = count($match) > 2 ? $match[2] : _x("link", "<a/> text for send activation mail link", LWS_USERS_DOMAIN);
		$link = '';
		if( isset($this->UserName) )
		{
			$key = bin2hex($this->UserName);
			$link = esc_attr(network_site_url("?lws-users-resend-activation=$key"));
		}
		return "<a href='$link' class='lws-send-activation-mail-link'>$txt</a>";
	}

	/** lock the user account, send a mail and warm user about it. */
	protected function emailValidation($user_id, $user_login, $user_email)
	{
		$token = $this->lockUserAccountForActivation($user_id, $user_email);
		$this->sendActivationMail($user_email, $token, $user_login);

		$page = get_option('lws_users_activation_mail_sent', '');
		if( !empty($page) && is_numeric($page) )
		{
			wp_safe_redirect(add_query_arg(array('page_id'=>$page), network_site_url()));
			exit(); // stop WordPress workflow
		}
		else
		{
			$this->ErrorMsg['login'] = "<div class='lw_plogin_check_mail'>";
			$this->ErrorMsg['login'] .= __("Registration is ok, we sent you a validation email.", LWS_USERS_DOMAIN);
			$this->ErrorMsg['login'] .= __("This email contains a link to activate your account.", LWS_USERS_DOMAIN);
			$this->ErrorMsg['login'] .= "</div>";
		}
	}

	protected function sendActivationMail($user_email, $token, $user_login)
	{
		$switched_locale = switch_to_locale( get_locale() );
		$msg = get_option('lws_users_plugin_actmail');
		if( empty($msg) )
			$msg = "[activation_link]";
		$this->UserName = $user_login;
		$this->Token = $token;

		add_shortcode( 'activation_link', array($this, 'shortcodeActivationLink') );
		if( !($un = shortcode_exists( 'user_name' )) )
			add_shortcode( 'user_name', array($this, 'shortcodeUserName') );

		$msg = do_shortcode($msg);

		remove_shortcode('activation_link');
		if( !$un )
			remove_shortcode('user_name');

		$subject = sprintf(__("Activate your account on %s", LWS_USERS_DOMAIN), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$mail = @wp_mail($user_email, $subject, $msg, $headers);
		if( !$mail )
			error_log("The new register activation mail cannot be sent");

		if( $switched_locale )
			restore_previous_locale();
		return $mail;
	}

	function shortcodeActivationLink($atts, $content = null)
	{
		$link = esc_attr(isset($this->Token) ? network_site_url("?lws-users-activation-token={$this->Token}") : '');
		$txt = !empty($content) ? $content : _x("Activation", "activation link label", LWS_USERS_DOMAIN);
		return "<a target='_blank' href='$link'>$txt</a>";
	}

	function shortcodeUserName($atts, $content = null)
	{
		return isset($this->UserName) ? $this->UserName : '';
	}

	/** hook 'authenticate' */
	public function checkForActivation( $user, $username, $password )
	{
		if( !empty($username) && !is_wp_error($user) )
		{
			$tmp = get_user_by('login', $username);
			$value = get_user_meta($tmp->ID, 'lws-users_activation_delayer', true);
			if( $value != null )
			{

				$this->UserId = $tmp->ID;
				$this->UserName = $username;
				$this->Token = $value;
				$user = new \WP_Error( 'activation_required', __("<strong>ERROR</strong>: Your account hasn't been activated. Please check your emails", LWS_USERS_DOMAIN) );
				remove_action('authenticate', 'wp_authenticate_username_password', 20, 3); //key found - don't proceed!
			}
		}
		return $user;
	}

	/** hook 'do_parse_request' */
	public function parseRequestForAction($result)
	{
		if ( current_filter() === 'do_parse_request' )
		{
			$url = $this->getCurrentUrl();
			$url = substr($url, 1, max(strpos($url, '?')-1, 0));

			if( empty($url) )
			{
				if( isset($_GET['lws-users-activation-token']) )
				{
					if( $this->activateUserAccount($_GET['lws-users-activation-token']) )
						$page = get_option('lws_users_account_activated', '');
					else
						$page = get_option('lws_users_account_activation_error', '');

					if( !empty($page) && is_numeric($page) )
					{
						wp_safe_redirect(add_query_arg(array('page_id'=>$page), network_site_url()));
						exit(); // stop WordPress workflow
					}
				}
				else if( isset($_GET['lws-users-resend-activation']) )
				{
					$username = hex2bin($_GET['lws-users-resend-activation']);
					global $wpdb;
					$query = "SELECT u.`user_email`, u.`user_login`, m.`meta_value`";
					$query .= " FROM {$wpdb->users} as u, {$wpdb->usermeta} as m";
					$query .= " WHERE m.`meta_key`='lws-users_activation_delayer' AND u.`user_login`=%s;";

					$result = $wpdb->get_row( $wpdb->prepare($query, $username), ARRAY_N );
					if( !empty($result) )
						$this->sendActivationMail($result[0], $result[2], $result[1]); // email, token, name

					$page = get_option('lws_users_activation_mail_sent', '');
					wp_safe_redirect(add_query_arg(array('page_id'=>$page), network_site_url()));
					exit(); // stop WordPress workflow
				}
			}
		}
		return $result;
	}

	private function getCurrentUrl()
	{
		$home_path = rtrim( parse_url( home_url(), PHP_URL_PATH ), '/' );
		$path = rtrim( substr( add_query_arg( array() ), strlen( $home_path ) ), '/' );
		return ( $path === '' ) ? '/' : $path;
	}

	/** active le compte correspondant à cette clé de confirmation.
	 *	@return si l'activation a réussi. */
	protected function activateUserAccount( $hkey )
	{
		$confirmed = false;
		global $wpdb;
		$hkey = esc_sql( $hkey );
		$user_id = $wpdb->get_var( "SELECT `user_id` FROM $wpdb->usermeta WHERE `meta_key`='lws-users_activation_delayer' AND `meta_value`='" . $hkey . "';" );
		if( !empty($user_id) )
		{
			// on active le compte en supprimant la clé de confirmation
			$confirmed = delete_user_meta( $user_id, 'lws-users_activation_delayer', $hkey );
		}
		return $confirmed;
	}

	/** Ajoute une clé de confirmation à l'utilisateur. */
	function lockUserAccountForActivation( $user_id, $user_email )
	{
		$user = get_user_by('id', $user_id);
		$key = $user_id . $user_email . bin2hex(random_bytes(64));
		$hkey = hash('sha256', $key);
		update_user_meta($user_id, 'lws-users_activation_delayer', $hkey);
		return $hkey;
	}

}


?>
