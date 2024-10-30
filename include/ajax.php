<?php
namespace LWS\USERS;
if( !defined( 'ABSPATH' ) ) exit();

/** action: lws_users_lost_pwd
 * action: lws_users_change_pwd */
class Ajax
{

	public function __construct()
	{
		add_action( 'wp_ajax_lws_users_change_pwd', array( $this, 'changePwd') );
		add_action( 'wp_ajax_nopriv_lws_users_lost_pwd', array( $this, 'sendLostPwd') );
	}

	/** find user, generate a new password and send a mail about that. */
	public function sendLostPwd()
	{
		$json = array('ok'=>false, 'html'=>__("Internal error, please retry later.", LWS_USERS_DOMAIN));
		if( !isset($_POST['nonce']) || (1 != wp_verify_nonce($_POST['nonce'], 'formSendPwd'))
			|| !isset($_POST['email']) || empty($_POST['email']) )
		{
			$json['html'] = __("Security tests failed. Action ignored.", LWS_USERS_DOMAIN);
		}
		else if( !is_email($_POST['email']) )
		{
			$json['html'] = __("Given value is not a valid email.", LWS_USERS_DOMAIN);
		}
		else
		{
			$email = $_POST['email'];
			// get user based on email
			$current_user = get_user_by('email', $email);

			if( $current_user )
			{
				$this->userLogin = $current_user->user_login;
				// change pwd
				global $wpdb;
				$user_pass = wp_hash_password( $this->getNewPassword() );
				if( $wpdb->update( $wpdb->users, array('user_pass' => $user_pass), array('ID' => $current_user->ID) ) )
				{
					if( $this->sendPwdMail($email) )
					{
						$json['ok'] = true;
						$json['html'] = __("A mail has been send to $email with a new password.", LWS_USERS_DOMAIN);
					}
					else
						$json['html'] = __("Cannot send the 'lost password' mail. Please contact the administrator.", LWS_USERS_DOMAIN);
				}
				else
					$json['html'] = __("An error occured during the password update.", LWS_USERS_DOMAIN);
			}
			else
				$json['html'] = __("Mail unknown.", LWS_USERS_DOMAIN);
		}

		wp_send_json($json);
	}

	/** update connected user pwd. */
	public function changePwd()
	{
		$json = array('ok'=>false, 'html'=>__("Internal error, please retry later.", LWS_USERS_DOMAIN));
		// check Nonces, hook ensure user is connected, nonce is liked to session and a user always can change its own pwd.
		if( !isset($_POST['nonce']) || (1 != wp_verify_nonce($_POST['nonce'], 'formChangePwd' . get_current_user_id()))
			|| !(isset($_POST['pwd']) && isset($_POST['dup'])) )
		{
			$json['html'] = __("Security tests failed. Action ignored.", LWS_USERS_DOMAIN);
		}
		else if( $_POST['pwd'] !== $_POST['dup'] )
		{
			$json['html'] = __("Password and confirmation are different. Please, try again.", LWS_USERS_DOMAIN);
		}
		else if( strlen($_POST['pwd']) < 8 )
		{
			$json['html'] = __("<p>Password is too short. Please, try again.</p>
<p>Prefer a Passphrase, a sequence of word with no obvious link between them.</p>
<p>Typical advice about choosing a passphrase includes suggestions that it should be:<ul>
<li>Long enough to be hard to guess</li>
<li>Not a famous quotation from literature, holy books, et cetera</li>
<li>Hard to guess by intuition—even by someone who knows the user well</li>
<li>Easy to remember and type accurately</li>
<li>For better security, any easily memorable encoding at the user's own level can be applied.</li>
<li>Not reused between sites, applications and other different sources.</li>
</ul><br/>See <a href='https://en.wikipedia.org/wiki/Passphrase'>Passphrase</a> for information about security.</p>", LWS_USERS_DOMAIN);
		}
		else
		{
			global $wpdb;
			$user_pass = wp_hash_password( $_POST['pwd'] );
			$current_user = wp_get_current_user();
			// change the pwd
			if( $wpdb->update( $wpdb->users, array('user_pass' => $user_pass), array('ID' => $current_user->ID) ) )
			{
				// rebuild auth cookie
				wp_clear_auth_cookie();
				$logged_in_cookie    = wp_parse_auth_cookie( '', 'logged_in' );
				$default_cookie_life = apply_filters( 'auth_cookie_expiration', ( 2 * DAY_IN_SECONDS ), $current_user->ID, false );
				$remember            = ( ( $logged_in_cookie['expiration'] - time() ) > $default_cookie_life );
				wp_set_auth_cookie( $current_user->ID, $remember );

				$json['ok'] = true;
				$json['html'] = __("Password updated.", LWS_USERS_DOMAIN);
			}
			else
			{
				$json['html'] = __("An error occured during the password update.", LWS_USERS_DOMAIN);
			}
		}

		wp_send_json($json);
	}

	/** @return (bool) Whether the email contents were sent successfully. */
	protected function sendPwdMail($user_email)
	{
		$switched_locale = switch_to_locale( get_locale() );
		$msg = get_option('lws_users_pwd_lost_mail_content');
		if( empty($msg) )
			$msg = "[password]";

		add_shortcode( 'login', array($this, 'userLogin') );
		add_shortcode( 'password', array($this, 'getNewPassword') );
		add_shortcode( 'change_pwd_page', array($this, 'getChangePasswordPage') );
		$msg = do_shortcode($msg);
		remove_shortcode('login');
		remove_shortcode('password');
		remove_shortcode('change_pwd_page');

		$subject = sprintf(__("Reset your password on %s", LWS_USERS_DOMAIN), wp_specialchars_decode(get_option('blogname'), ENT_QUOTES));
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$mail = @wp_mail($user_email, $subject, $msg, $headers);
		if( !$mail )
			error_log("The mail about new password cannot be sent");

		if( $switched_locale )
			restore_previous_locale();
		return $mail;
	}

	/** If not generated, create one and store it.
	 * @return the last generated pwd. */
	public function getNewPassword()
	{
		static $pwd = '';
		if( empty($pwd) )
			$pwd = wp_generate_password();
		return $pwd;
	}

	public function userLogin()
	{
		return isset($this->userLogin) ? $this->userLogin : '';
	}

	public function getChangePasswordPage()
	{
		require_once LWS_USERS_INCLUDES . '/dialog.php';
		return Dialog::getPageUrl('lws_users_pwd_change_page');
	}

}

?>
