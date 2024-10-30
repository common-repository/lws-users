<?php
namespace LWS\USERS;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_USERS_INCLUDES . '/logaction.php';

class Dialog
{

	public function __construct()
	{
		add_shortcode( 'lws_users', array($this, 'widget') );
		add_shortcode( 'send_password_form', array($this, 'formSendPwd') );
		add_shortcode( 'change_password_form', array($this, 'formChangePwd') );

		add_filter('lws_users_plogin_lost_pwd', array($this, 'lostPasswordOnPage'), 10, 2);
		add_filter('lws_users_wlogin_lost_pwd', array($this, 'lostPasswordOnWidget'));
		add_filter('lws_users_mlogin_lost_pwd', array($this, 'lostPasswordOnMenu'));

		add_action('lws_users_enqueue_login_script', array($this, 'enqueueScripts'));
		add_action('wp_enqueue_scripts', array($this, 'addInlineCSS'));
	}

	public function addInlineCSS()
	{
		$inlineCss = trim(get_option('lws_users_pcss_inline'));
		$inlineCss .= trim(get_option('lws_users_wcss_inline'));
		$inlineCss .= trim(get_option('lws_users_mcss_inline'));
		if( !empty($inlineCss) )
		{
			wp_enqueue_style('lws_users_inline_css_dummy', LWS_USERS_CSS.'/dummy.css', array(), null);
			wp_add_inline_style('lws_users_inline_css_dummy', esc_html($inlineCss));
		}
	}

	protected function once($incr=0)
	{
		static $Once = 0;
		$Once += $incr;
		return $Once;
	}

	private function getLogoUrl()
	{
		$phLogoId = get_option('lws_users_plogin_logo');
		$phLogoUrl = !empty($phLogoId) && is_numeric($phLogoId) ? wp_get_attachment_url($phLogoId) : false;
		return $phLogoUrl;
	}

	public function widget($args=array(), $content = null)
	{
		$args = $this->options($args);

		$html = "";
		$phLogoUrl = $this->getLogoUrl();
		$html .=  "<div class='lwss_selectable lws-page-login'>";
		if( !empty($phLogoUrl) && $phLogoUrl !== false )
			$html .= "<div class='lwss_selectable lws-head-logo'><img class='lwss_selectable lws-head-img' src='$phLogoUrl'/></div>";

		if( !is_user_logged_in() )
			$html .= $this->loginForm($args);
		else
			$html .= $this->logoutForm();

		do_action('lws_users_enqueue_login_script');
		wp_enqueue_style('dashicons');
		wp_enqueue_style('lws_users_css_page', LWS_USERS_CSS."/page.css?stygen=lws_users_css_page", array('dashicons'), LWS_USERS_VERSION);

		$html .= "</div>";
		$this->once(1);
		return $html;
	}

	/** Check values and set default if not */
	protected function options(&$args)
	{
		if( !is_array($args) )
			$args = array();

		if( !array_key_exists('enable_register', $args) )
			$args['enable_register'] = !empty(get_option('lws_users_plogin_register', ''));
		else
			$args['enable_register'] = filter_var($args['enable_register'], FILTER_VALIDATE_BOOLEAN);

		if( !array_key_exists('enable_remember', $args) )
			$args['enable_remember'] = !empty(get_option('lws_users_plogin_remember', 'on'));
		else
			$args['enable_remember'] = filter_var($args['enable_remember'], FILTER_VALIDATE_BOOLEAN);

		if( !array_key_exists('register', $args) )
			$args['register'] = false;
		else
			$args['register'] = filter_var($args['register'], FILTER_VALIDATE_BOOLEAN);

		return $args;
	}

	public static function replaceCustomTags($txt)
	{
		$user = wp_get_current_user();
		if( !(is_object($user) && (get_class($user) == 'WP_User') && ($user->ID != 0)) )
			$user = false;

		$tags = array();
		$tags['[user_name]'] = htmlentities(( $user !== false ? $user->display_name : '' ));
		return strtr( $txt, $tags );
	}

	protected function getTextOption($id, $dft='')
	{
		$opt = get_option($id);
		if( empty($opt) )
			$opt = $dft;
		return $opt;
	}

	protected function loginForm($args=array(), $dest='')
	{
		$html = "";
		if( $args['enable_register'] )
		{
			$once = $this->once();
			$phConnect = $this->getTextOption('lws_users_login_translate', __("Connection", 'lws-users'));
			$phRegister = $this->getTextOption('lws_users_register_translate', __("Register", 'lws-users'));
			$active = (empty(LogAction::error('register')) && !$args['register']) ? "0" : "1";

			$html .= "<div class='lws_users_tabs' data-active='$active'><ul class='lws_users_ul'>";
			$html .= "<li class='lwss_selectable lws-page-tab'><a href='#tab-login-$once' class='lwss_selectable lws-tab-link'>$phConnect</a></li>";
			$html .= "<li class='lwss_selectable lws-page-tab'><a href='#tab-signin-$once' class='lwss_selectable lws-tab-link'>$phRegister</a></li>";
			$html .= "</ul>";

			$html .= "<div id='tab-login-$once' class='lwss_selectable lws-plogin-tab-content'>";
			$html .=$this->loginTab($args, $dest);
			$html .= "</div>";
			$html .= "<div id='tab-signin-$once' class='lwss_selectable lws-plogin-tab-content'>";
			$html .=$this->registerTab($args, $dest);
			$html .= "</div>";

			$html .= "</div>";
		}
		else
		{
			$html .= "<div class='lwss_selectable lws-plogin-tab-content'>";
			$html .= $this->loginTab($args, $dest);
			$html .= "</div>";
		}
		return $html;
	}

	protected function loginTab($args, $dest)
	{
		$html = "";
		global $wp;
		$phLogMsg = self::replaceCustomTags(get_option('lws_users_plogin_logmessage',''));
		$phLogError = LogAction::error('login');
		$ph = array(
			$this->getTextOption('lws_users_username_translate', __("User name", 'lws-users')),
			$this->getTextOption('lws_users_password_translate', __("Password", 'lws-users')),
			$this->getTextOption('lws_users_remember_translate', __("Remember me ?", 'lws-users')),
			esc_attr($this->getTextOption('lws_users_submit_translate', __("Connect", 'lws-users')))
		);
		$log = Dialog::_Value('log');
		$rememberme = Dialog::_Checked('rememberme');

		$html .= "<form class='lw_login_form' name='loginform' method='post' action='$dest'>";
		$html .= "<input type='hidden' name='lws-login-action' value='login'>";
		$html .= "<table class='lwss_selectable lws-page-login' data-type='Main Table'>";

		if( !(empty($phLogMsg) && empty($phLogError)) )
		{
			$html .= "<tr class='lwss_selectable lws-plogin-fieldline' data-type='Table Line'><td colspan='2' class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
			if( !empty($phLogMsg) )
				$html .= "<p class='lwss_selectable lws-plogin-text'>$phLogMsg</p>";
			if( !empty($phLogError) )
				$html .= "<p class='lwss_selectable lws-plogin-error'>$phLogError</p>";
			$html .= "</td></tr>";
		}

		$html .= "<tr class='lwss_selectable lws-plogin-fieldline'>";
		$html .= "<td class='lwss_selectable lws-plogin-label'><span>{$ph[0]}</span></td>";
		$html .= "<td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
		$html .= "<input class='lwss_selectable lws-plogin-input' name='log' $log type='text' size='20' required/>";
		$html .= "</td></tr>";

		$html .= "<tr class='lwss_selectable lws-plogin-fieldline'>";
		$html .= "<td class='lwss_selectable lws-plogin-label'><span>{$ph[1]}</span></td>";
		$html .= "<td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
		$html .= "<input class='lwss_selectable lws-plogin-input' name='pwd' type='password' size='20' required/>";
		$html .= "</td></tr>";

		if( $args['enable_remember'] )
		{
			$html .= "<tr class='lwss_selectable lws-plogin-fieldline'>";
			$html .= "<td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
			$html .= "<input class='lwss_selectable lws-plogin-cb' name='rememberme' $rememberme type='checkbox'/>";
			$html .= "</td><td class='lwss_selectable lws-plogin-label'><span>{$ph[2]}</span></td>";
			$html .= "</tr>";
		}
		do_action('lws_users_login_form');
		$html .= "<tr class='lwss_selectable lws-plogin-footer'>";
		$lost = apply_filters('lws_users_plogin_lost_pwd', '', $args);
		$html .= "<td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>$lost</td>";
		$html .= "<td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
		$html .= "<input type='submit' class='lwss_selectable lws-plogin-button button lw_form_button lw_form_submit' value='{$ph[3]}'>";
		$html .= "</td></tr>";
		$html .= "</table></form>";

		return $html;
	}

	protected function registerTab($args, $dest)
	{
		$html = "";
		global $wp;
		$phRegMsg = self::replaceCustomTags(get_option('lws_users_plogin_regmessage',''));
		$phRegError = LogAction::error('register');
		$ph = array(
			$this->getTextOption('lws_users_username_translate', __("User name(*)", 'lws-users')),
			$this->getTextOption('lws_users_email_translate', __("Email Address(*)", 'lws-users')),
			$this->getTextOption('lws_users_password_translate', __("Password(*)", 'lws-users')),
			$this->getTextOption('lws_users_confirm_translate', __("Confirm password(*)", 'lws-users')),
			__("(*) Mandatory", 'lws-users'),
			$this->getTextOption('lws_users_submit_translate', __("Register", 'lws-users'))
		);
		$log = Dialog::_Value('log');
		$email = Dialog::_Value('email');
		$redir = home_url(add_query_arg(array(),$wp->request));

		$html .= "<form class='lw_login_form' name='loginform' method='post' action='$dest'>";
		$html .= "<input type='hidden' name='lws-login-action' value='register'>";
		$html .= "<input type='hidden' name='redirect_to' value='$redir'>";
		$html .= "<table class='lwss_selectable lws-page-login' data-type='Main Table'>";

		if( !(empty($phLogMsg) && empty($phLogError)) )
		{
			$html .= "<tr class='lwss_selectable lws-plogin-fieldline' data-type='Table Line'><td colspan='2' class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
			if( !empty($phRegMsg) )
				$html .= "<p class='lwss_selectable lws-plogin-text'>$phRegMsg</p>";
			if( !empty($phRegError) )
				$html .= "<p class='lwss_selectable lws-plogin-error'>$phRegError</p>";
			$html .= "</td></tr>";
		}

		$html .= "<tr class='lwss_selectable lws-plogin-fieldline'>";
		$html .= "<td class='lwss_selectable lws-plogin-label'><span>{$ph[0]}</span></td>";
		$html .= "<td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
		$html .= "<input class='lwss_selectable lws-plogin-input' name='log' $log type='text' size='30' required/>";
		$html .= "</td></tr>";

		$html .= "<tr class='lwss_selectable lws-plogin-fieldline'>";
		$html .= "<td class='lwss_selectable lws-plogin-label'><span>{$ph[1]}</span></td>";
		$html .= "<td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
		$html .= "<input class='lwss_selectable lws-plogin-input' name='email' $email type='email' size='30' required/>";
		$html .= "</td></tr>";

		$html .= "<tr class='lwss_selectable lws-plogin-fieldline'>";
		$html .= "<td class='lwss_selectable lws-plogin-label'><span>{$ph[2]}</span></td>";
		$html .= "<td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
		$html .= "<input class='lwss_selectable lws-plogin-input' name='pwd' type='password' size='20' required/>";
		$html .= "</td></tr>";

		$html .= "<tr class='lwss_selectable lws-plogin-fieldline'>";
		$html .= "<td class='lwss_selectable lws-plogin-label'><span>{$ph[3]}</span></td>";
		$html .= "<td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
		$html .= "<input class='lwss_selectable lws-plogin-input' name='pwd2' type='password' size='20' required/>";
		$html .= "</td></tr>";

		do_action('lws_users_register_form');
		$html .= "<tr class='lwss_selectable lws-plogin-footer'>";
		$html .= "<td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
		$html .= "<span class='lw_mandatory'>{$ph[4]}</span>";
		$html .= "</td><td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
		$html .= "<input type='submit' class='lwss_selectable lws-plogin-button button lw_form_button lw_form_submit' value='{$ph[5]}'>";
		$html .= "</td></tr>";
		$html .= "</table></form>";

		return $html;
	}

	protected function logoutForm($dest='')
	{
		$html = "";
		$phMsg = self::replaceCustomTags(get_option('lws_users_plogin_outmessage',''));
		$phBtn = $this->getTextOption('lws_users_logout_translate', __("Logout", 'lws-users'));

		$html .= "<div class='lw_logout lws-plogin-tab-content'>";
		$html .= "<form  name='loginform' method='post' action='$dest'>";
		$html .= "<input type='hidden' name='lws-login-action' value='logout'>";
		$html .= "<table class='lwss_selectable lws-page-login' data-type='Main Table'>";
		if( !empty($phMsg) )
		{
			$html .= "<tr class='lwss_selectable lws-plogin-fieldline' data-type='Table Line'><td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
			$html .= "<p class='lwss_selectable lws-plogin-text'>$phMsg</p>";
			$html .= "</td></tr>";
		}
		$html .= "<tr class='lwss_selectable lws-plogin-footer lw-page-main-input'><td class='lwss_selectable lws-plogin-label' data-type='Table Cell'>";
		$html .= "<input type='submit' class='lwss_selectable lws-plogin-button button lw_form_button lw_logout_button lw_form_submit' value='$phBtn'>";
		$html .= "</td></tr></table></form></div>";
		return $html;
	}

	public static function eValue($name)
	{
		echo Dialog::_Value($name);
	}

	public static function eChecked($name)
	{
		echo Dialog::_Checked($name);
	}

	public static function _Value($name)
	{
		return isset($_POST[$name]) ? "value='" . esc_attr($_POST[$name]) . "'" : "";
	}

	public static function _Checked($name)
	{
		return ( isset($_POST[$name]) && !empty($_POST[$name]) ) ? "checked='checked'" : "";
	}

	public function enqueueScripts()
	{
		wp_enqueue_script( 'jquery-ui' );
		wp_enqueue_script( 'jquery-ui-tabs' );
		wp_enqueue_script( 'lws-users-login', LWS_USERS_JS.'/login.js', array('jquery', 'jquery-ui-tabs'), LWS_USERS_VERSION, true );
	}

	public function lostPasswordOnPage($content, $args)
	{
		if( !array_key_exists('enable_lost', $args) )
			$args['enable_lost'] = !empty(get_option('lws_users_plogin_lost', ''));
		else
			$args['enable_lost'] = filter_var($args['enable_lost'], FILTER_VALIDATE_BOOLEAN);

		if( $args['enable_lost'] ) // if paid, $lostPwd is the url. in Free version, means nothing
		{
			$lostPwd = (array_key_exists('lost_page', $args) ? $args['lost_page'] : '');
			$lostPwd = !empty($lostPwd) && is_numeric($lostPwd) ? get_permalink($lostPwd) : $lostPwd;
			if( empty($lostPwd) )
				$lostPwd = $this->getLostPwdUrl();
			$ph = $this->getTextOption('lws_users_lost_translate', __("Lost password", LWS_USERS_DOMAIN));

			$content .= "<a class='lwss_selectable lws-plogin-lost' href='$lostPwd'>$ph</a>";
		}
		return $content;
	}

	public function lostPasswordOnWidget($content)
	{
		if( !empty(get_option('lws_users_wlogin_lost', '')) )
		{
			$lostPwd = $this->getLostPwdUrl();
			$ph = $this->getTextOption('lws_users_lost_translate', __("Lost password", LWS_USERS_DOMAIN));
			$content .= "<a class='lwss_selectable lws-wlogin-lost' href='$lostPwd'>$ph</a>";
		}
		return $content;
	}

	public function lostPasswordOnMenu($content)
	{
		if( !empty(get_option('lws_users_mlogin_lost', '')) )
		{
			$lostPwd = $this->getLostPwdUrl();
			$ph = $this->getTextOption('lws_users_lost_translate', __("Lost password", LWS_USERS_DOMAIN));
			$content .= "<a class='lwss_selectable lws-mlogin-lost' href='$lostPwd'>$ph</a>";
		}
		return $content;
	}

	public static function getPageUrl($optId)
	{
		$urlId = get_option($optId);
		$pageUrl = !empty($urlId) && is_numeric($urlId) ? get_permalink($urlId) : false;
		return $pageUrl;
	}

	public function getLostPwdUrl()
	{
		$page = self::getPageUrl('lws_users_pwd_lost_page');
		if( !$page )
			return esc_url( wp_lostpassword_url() );
		else
			return 	$page;
	}

	/** display a form to get an email and reset pwd.
	 * Use 3 div's (form, success, error) and ajax. */
	public function formSendPwd($args=array(), $content = null)
	{
		$phRetry = __("Retry", LWS_USERS_DOMAIN);
		$ph = $this->getTextOption('lws_users_email_translate', __("Email Address(*)", 'lws-users'));
		$phBtn = $this->getTextOption('lws_users_submit_translate', __("Apply", 'lws-users'));
		$nonce = wp_create_nonce('formSendPwd');
		$html = "<div class='lwss_selectable lws-pwd-frame'>";

		// form
		$html .= "<div><form name='lostpwdform' class='pwdform'>";
		$html .= "<input type='hidden' value='$nonce' name='nonce'>";
		$html .= "<table class='lwss_selectable lws-pwd-form' data-type='Main Table'>";
		$html .= "<tr class='lwss_selectable lws-pwd-fieldline'><td class='lwss_selectable lws-pwd-label'><span>$ph</span></td>";
		$html .= "<td class='lwss_selectable lws-pwd-label' data-type='Table Cell'>";
		$html .= "<input class='lwss_selectable lws-pwd-input' name='email' type='email' size='30' required/>";
		$html .= "</td></tr>";
		$html .= "<tr class='lwss_selectable lws-pwd-footer'>";
		$html .= "<td colspan='2' class='lwss_selectable lws-pwd-label' data-type='Table Cell'>";
		$html .= "<input type='submit' class='button lws-pwd-button lw_form_button lw_form_submit' value='$phBtn'>";
		$html .= "</td></tr></table></form></div>";
		// success
		$html .= "<div class='lwss_selectable lws-pwd-success' style='display:none;'><div class='lwss_selectable lws-return-description'></div></div>";
		// error
		$html .= "<div class='lwss_selectable lws-pwd-error' style='display:none;'><div class='lwss_selectable lws-return-description'></div>";
		$html .= "<div class='lwss_selectable lws-pwd-footer'>";
		$html .= "<input type='button' class='button lws-pwd-retry-button lws-pwd-button' value='$phRetry'>";
		$html .= "</div></div>";

		$html .= "</div>";
		wp_enqueue_script( 'lws-users-pwd', LWS_USERS_JS.'/pwd.js', array('jquery'), LWS_USERS_VERSION, true );
		wp_localize_script('lws-users-pwd', 'ajaxurl', admin_url( 'admin-ajax.php' ) );
		wp_enqueue_style('lws_users_css_password', LWS_USERS_CSS."/pwd.css?stygen=lws_users_css_password", array(), LWS_USERS_VERSION);
		return $html;
	}

	/** display a form to change pwd.
	 * Use 3 div's (form, success, error) and ajax. */
	public function formChangePwd($args=array(), $content = null)
	{
		$phRetry = __("Retry", LWS_USERS_DOMAIN);
		$phBtn = $this->getTextOption('lws_users_submit_translate', __("Apply", 'lws-users'));
		$ph = array(
			$this->getTextOption('lws_users_password_translate', __("Password(*)", 'lws-users')),
			$this->getTextOption('lws_users_confirm_translate', __("Confirm password(*)", 'lws-users'))
		);
		$nonce = wp_create_nonce('formChangePwd' . get_current_user_id());
		$html = "<div class='lwss_selectable lws-pwd-frame'>";

		// form
		$html .= "<div><form name='changepwdform' class='pwdform'>";
		$html .= "<input type='hidden' value='$nonce' name='nonce'>";
		$html .= "<table class='lwss_selectable lws-pwd-form' data-type='Main Table'>";
		$html .= "<tr class='lwss_selectable lws-pwd-fieldline'><td class='lwss_selectable lws-pwd-label'><span>{$ph[0]}</span></td>";
		$html .= "<td class='lwss_selectable lws-pwd-label' data-type='Table Cell'>";
		$html .= "<input class='lwss_selectable lws-pwd-input' name='pwd' type='password' size='20' required/>";
		$html .= "</td></tr>";
		$html .= "<tr class='lwss_selectable lws-pwd-fieldline'><td class='lwss_selectable lws-pwd-label'><span>{$ph[1]}</span></td>";
		$html .= "<td class='lwss_selectable lws-pwd-label' data-type='Table Cell'>";
		$html .= "<input class='lwss_selectable lws-pwd-input' name='pwd2' type='password' size='20' required/>";
		$html .= "</td></tr>";
		$html .= "<tr class='lwss_selectable lws-pwd-footer'>";
		$html .= "<td colspan='2' class='lwss_selectable lws-pwd-label' data-type='Table Cell'>";
		$html .= "<input type='submit' class='button lws-pwd-button lw_form_button lw_form_submit' value='$phBtn'>";
		$html .= "</td></tr></table></form></div>";
		// success
		$html .= "<div class='lwss_selectable lws-pwd-success' style='display:none;'><div class='lwss_selectable lws-return-description'></div></div>";
		// error
		$html .= "<div class='lwss_selectable lws-pwd-error' style='display:none;'><div class='lwss_selectable lws-return-description'></div>";
		$html .= "<div class='lwss_selectable lws-pwd-footer'>";
		$html .= "<input type='button' class='button lws-pwd-retry-button lws-pwd-button' value='$phRetry'>";
		$html .= "</div></div>";

		$html .= "</div>";
		do_action('lws_users_enqueue_login_script');
		wp_enqueue_script( 'lws-users-pwd', LWS_USERS_JS.'/pwd.js', array('jquery'), LWS_USERS_VERSION, true );
		wp_localize_script('lws-users-pwd', 'ajaxurl', admin_url( 'admin-ajax.php' ) );
		wp_enqueue_style('dashicons');
		wp_enqueue_style('lws_users_css_page', LWS_USERS_CSS."/page.css?stygen=lws_users_css_page", array('dashicons'), LWS_USERS_VERSION);
		wp_enqueue_style('lws_users_css_password', LWS_USERS_CSS."/pwd.css?stygen=lws_users_css_password", array('lws_users_css_page'), LWS_USERS_VERSION);
		return $html;
	}

}

?>
