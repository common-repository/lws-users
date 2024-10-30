<?php
namespace LWS\USERS;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_USERS_INCLUDES . '/redirlist.php';

class Admin
{
	public function __construct()
	{
		lws_register_pages($this->pages());
	}

	protected function pages()
	{
		$pa = array(
			array(
				'id' => 'options-general.php'
			),
			array(
				'id' => lws_clean_slug_from_mainfile(LWS_USERS_FILE), // id of the page
				'title' => __("The User Plugin", LWS_USERS_DOMAIN),
				'rights' => 'manage_options', // acces restriction to visit the page
				'tabs' => array(
					'main' => array(
						'id' => 'main',
						'title' => __("General Settings", LWS_USERS_DOMAIN),
						'groups' =>	$this->grpMain()
					),
					'page' => array(
						'id' => 'page',
						'title' => __("Page Login", LWS_USERS_DOMAIN),
						'groups' =>	$this->grpPage()
					),
					'widget' => array(
						'id' => 'widget',
						'title' => __("Widget Login", LWS_USERS_DOMAIN),
						'groups' =>	$this->grpWidget()
					),
					'menu' => array(
						'id' => 'menu',
						'title' => __("Menu Login", LWS_USERS_DOMAIN),
						'groups' =>	$this->grpMenu()
					),
					'redir' => array(
						'id' => 'redirections',
						'title' => __("Redirections", LWS_USERS_DOMAIN),
						'tabs' => array(
							array(
								'id' => 'settings',
								'title' => __("Settings", LWS_USERS_DOMAIN),
								'groups' => $this->grpRedirSettings()
							),
							array(
								'id' => 'redirections',
								'title' => __("Redirection List", LWS_USERS_DOMAIN),
								'groups' => $this->grpRedirect()
							)
						)
					)
				)
			)
		);
		return $pa;
	}

	private function redirFilter()
	{
		global $wpdb;
		$dflts = $wpdb->get_var("SELECT COUNT(option_id) FROM {$wpdb->options} WHERE option_name LIKE 'lws_user_redir_0_%'");
		$roles = $wpdb->get_var("SELECT COUNT(option_id) FROM {$wpdb->options} WHERE option_name LIKE 'lws_user_redir_2_%'");
		$users = $wpdb->get_var("SELECT COUNT(umeta_id) FROM {$wpdb->usermeta} WHERE meta_key LIKE 'lws_user_redir_1_%'");
		$all = $dflts + $roles + $users;
		$counts = _x('(%d)', "Redirections count", LWS_USERS_DOMAIN);
		$trad = array(
			_x('All', "All redirections", LWS_USERS_DOMAIN) => array(),
			_x('Default', "Redirections", LWS_USERS_DOMAIN) => array('triggertype'=>'dflt'),
			_x('Roles', "Redirections", LWS_USERS_DOMAIN) => array('triggertype'=>'role'),
			_x('Users', "Redirections", LWS_USERS_DOMAIN) => array('triggertype'=>'user')
		);
		return new \LWS\Adminpanel\EditList\FilterSimpleLinks(
			array(
				'all' => array(),
				'dflt' => array('triggertype'=>'dflt'),
				'role' => array('triggertype'=>'role'),
				'user' => array('triggertype'=>'user')
			), array(
				sprintf($counts,$all),
				sprintf($counts,$dflts),
				sprintf($counts,$roles),
				sprintf($counts,$users)
			),
			"lws-editlist-filter-redirection",
			array(
				'all' => _x('All', "All redirections", LWS_USERS_DOMAIN),
				'dflt' => _x('Default', "Redirections", LWS_USERS_DOMAIN),
				'role' => _x('Roles', "Redirections", LWS_USERS_DOMAIN),
				'user' => _x('Users', "Redirections", LWS_USERS_DOMAIN)
			)
		);
	}

	protected function grpMain()
	{
		return array(
			'trads' => $this->subGrpTrad(),
			'lostpwd' => $this->subGrpLostPwd(),
			//'google' => lws_google_api_key_group(),
		);
	}

	private function subGrpLostPwd()
	{
		return array(
			'title' => __("Lost Password", LWS_USERS_DOMAIN),
			'text' => __("Customize the 'Lost Password' procedure.", LWS_USERS_DOMAIN),
			'fields' => array(
				array(
					'id' => 'lws_users_pwd_lost_page',
					'title' => __("Lost password page", LWS_USERS_DOMAIN),
					'type' => 'autocomplete',
					'extra' => array(
						'predefined' => 'page',
						'help' => sprintf(__("
							Page where a user can ask for a new password.<br/>
							Use <b>%s</b> in your page to create the 'Send new password' form.
						", LWS_USERS_DOMAIN), '[send_password_form]')
					)
				),
				array(
					'id' => 'lws_users_pwd_lost_mail_content',
					'title' => __("New password mail content", LWS_USERS_DOMAIN),
					'type' => 'textarea',
					'extra' => array('rows'=>3, 'help' => sprintf(__("
						Use <b>%s</b> and <b>%s</b> to send the user his new credentials.<br/>
						Use <b>%s</b> to provide a 'change password' link to the user.<br/>
					", LWS_USERS_DOMAIN), '[login]', '[password]', '[change_pwd_page]'))
				),
				array(
					'id' => 'lws_users_pwd_change_page',
					'title' => __("Change password page", LWS_USERS_DOMAIN),
					'type' => 'autocomplete',
					'extra' => array(
						'predefined' => 'page',
						'help' => sprintf(__("
							Page where users can change their password.<br/>
							If you don't have one, use <b>%s</b> on a new page to create the form.
						", LWS_USERS_DOMAIN), '[change_password_form]')
					)
				),
				array(
					'id' => 'lws_users_css_password',
					'type' => 'stygen',
					'extra' => array(
						'html' => LWS_USERS_SNIPPETS . "/pwd.html",
						'css' => LWS_USERS_CSS . "/pwd.css"
					)
				)
			)
		);
	}

	protected function subGrpTrad()
	{
		return array(
			'title' => __("Translations", LWS_USERS_DOMAIN),
			'text' => __("Enter the text translations that front-end users will see in the different login objects", LWS_USERS_DOMAIN),
			'fields' => array(
				array(
					'id' => 'lws_users_login_translate',
					'title' => __("Login", LWS_USERS_DOMAIN),
					'type' => 'text'
				),
				array(
					'id' => 'lws_users_register_translate',
					'title' => __("Register", LWS_USERS_DOMAIN),
					'type' => 'text'
				),
				array(
					'id' => 'lws_users_username_translate',
					'title' => __("Username", LWS_USERS_DOMAIN),
					'type' => 'text'
				),
				array(
					'id' => 'lws_users_password_translate',
					'title' => __("Password", LWS_USERS_DOMAIN),
					'type' => 'text'
				),
				array(
					'id' => 'lws_users_confirm_translate',
					'title' => __("Confirm", LWS_USERS_DOMAIN),
					'type' => 'text'
				),
				array(
					'id' => 'lws_users_email_translate',
					'title' => __("Email", LWS_USERS_DOMAIN),
					'type' => 'text'
				),
				array(
					'id' => 'lws_users_remember_translate',
					'title' => __("Remember me", LWS_USERS_DOMAIN),
					'type' => 'text'
				),
				array(
					'id' => 'lws_users_submit_translate',
					'title' => __("Submit", LWS_USERS_DOMAIN),
					'type' => 'text'
				),
				array(
					'id' => 'lws_users_logout_translate',
					'title' => __("Logout", LWS_USERS_DOMAIN),
					'type' => 'text'
				),
				array(
					'id' => 'lws_users_welcome_translate',
					'title' => __("Welcome", LWS_USERS_DOMAIN),
					'type' => 'text'
				),
				array(
					'id' => 'lws_users_lost_translate',
					'title' => __("Lost your password ?", LWS_USERS_DOMAIN),
					'type' => 'text'
				)
			)
		);
	}

	function pageJs()
	{
		wp_enqueue_script( 'jquery-ui' );
		wp_enqueue_script( 'jquery-ui-tabs' );
		wp_enqueue_script( 'lws-users-login', LWS_USERS_JS.'/login.js', array('jquery', 'jquery-ui-tabs'), LWS_USERS_VERSION, true );
	}

	protected function grpPage()
	{
		return array(
			array(
				'id' => 'lws_users_admin_page_features',
				'title' => __("Features", LWS_USERS_DOMAIN),
				'fields' => array(
					array(
						'id' => 'lws_users_plogin_usage',
						'type' => 'help',
						'extra' => array(
							'help'=>__("
							Inside a page, you can use the shortcode <b>[lws_users]</b><br/>
							By default, it will use the settings you've specified in the 'Page Login' Section. But if you need to, you can override default settings using shortcode options as follows :<br/>
							<ul>
								<li><strong>enable_register=yes/no</strong>: Define if the register feature is enabled on the form or not</li>
								<li><strong>enable_remember=yes/no</strong>: Define if the 'Remember me' checkbox is enabled on the form or not</li>
								<li><strong>enable_lost=yes/no</strong>: Define if the 'Lost Password' link is enabled on the form or not</li>
							</ul>
							You can specify no option, some of them or all, depending on your needs.<br/>
							The shortcode is only available inside pages and posts. For widget or Menu Login, use the settings in the Appeareance Configuration<br/><br/>
							<h2>Example</h2><br/>
							<strong>[lws_users enable_register=yes enable_lost=no enable_remember=yes]</strong>
							<br/><br/>"
							, LWS_USERS_DOMAIN)
						)
					),
					array(
						'id' => 'lws_users_plogin_register',
						'title' => __("Allow users to register", LWS_USERS_DOMAIN),
						'type' => 'box'
					),
					array(
						'id' => 'lws_users_plogin_remember',
						'title' => __("Enable Remember me", LWS_USERS_DOMAIN),
						'type' => 'box',
						'extra' => array('checked'=>true)
					),
					array(
						'id' => 'lws_users_plogin_lost',
						'title' => __("Enable Password Lost", LWS_USERS_DOMAIN),
						'type' => 'box',
					)
				)
			),
			array(
				'id' => 'lws_users_admin_page_registration',
				'title' => __("Registration", LWS_USERS_DOMAIN),
				'text' => __("If you have enabled registration, you can go further and want users to confirm their email before allowing them to connect to your website", LWS_USERS_DOMAIN ),
				'fields' => array(
					array(
						'id' => 'lws_users_pregister_alert_admin',
						'title' => __("Notify administrator about registrations", LWS_USERS_DOMAIN),
						'type' => 'box'
					),
					array(
						'id' => 'lws_users_plogin_activation',
						'title' => __("Request user activation", LWS_USERS_DOMAIN),
						'type' => 'box'
					),
					array(
						'id' => 'lws_users_activation_mail_sent',
						'title' => __("After Registration page", LWS_USERS_DOMAIN),
						'type' => 'autocomplete',
						'extra' => array(
							'predefined' => 'page',
							'help' => __("Future users are redirected to this page after registration and before activation", LWS_USERS_DOMAIN)
						)
					),
					array(
						'id' => 'lws_users_account_activated',
						'title' => __("Account activated page", LWS_USERS_DOMAIN),
						'type' => 'autocomplete',
						'extra' => array(
							'predefined' => 'page',
							'help' => __("Users are redirected to this page when they click on the activation link", LWS_USERS_DOMAIN)
						)
					),
					array(
						'id' => 'lws_users_account_activation_error',
						'title' => __("Account activation error page", LWS_USERS_DOMAIN),
						'type' => 'autocomplete',
						'extra' => array(
							'predefined' => 'page',
							'help' => __("Users are redirected to this page when they fail to activate their account", LWS_USERS_DOMAIN)
						)
					),
					array(
						'id' => 'lws_users_plugin_actmail',
						'title' => __("Activation Email Text", LWS_USERS_DOMAIN),
						'type' => 'textarea',
						'extra' => array(
							'rows'=>3,
							'help' => __("Use <em>[activation_link]</em> shortcode to include a link to activate the account.<br/><em>[user_name]</em> will be replaced by the registered login.", LWS_USERS_DOMAIN)
						)
					)
				)
			),
			array(
				'id' => 'lws_users_admin_page_customisation',
				'title' => __("Customisation", LWS_USERS_DOMAIN),
				'text' => __("Here you can customize the content of the Login/Register Control.<br/><b>[user_name]</b> is the logged user name or an empty text if none.", LWS_USERS_DOMAIN ),
				'fields' => array(
					array(
						'id' => 'lws_users_plogin_logo',
						'title' => __("Login form logo", LWS_USERS_DOMAIN),
						'type' => 'media',
						'extra' => array('type' => 'image')
					),
					array(
						'id' => 'lws_users_plogin_logmessage',
						'title' => __("Login form message", LWS_USERS_DOMAIN),
						'type' => 'textarea',
						'extra' => array('rows'=>3)
					),
					array(
						'id' => 'lws_users_plogin_regmessage',
						'title' => __("Register form message", LWS_USERS_DOMAIN),
						'type' => 'textarea',
						'extra' => array('rows'=>3)
					),
					array(
						'id' => 'lws_users_plogin_outmessage',
						'title' => __("Logout form message", LWS_USERS_DOMAIN),
						'type' => 'textarea',
						'extra' => array('rows'=>3)
					)
				)
			),
			array(
				'title' => __("Styling", LWS_USERS_DOMAIN),
				'text' => __("Here you can customize the look of the Login/Register Control.", LWS_USERS_DOMAIN ),
				'fields' => array(
					array(
						'id' => 'lws_users_css_page',
						'type' => 'stygen',
						'extra' => array(
							'html' => LWS_USERS_SNIPPETS . "/page.html",
							'css' => LWS_USERS_CSS . "/page.css"
						)
					)
				),
				'function' => array($this, 'pageJs')
			),
			array(
				'id' => 'lws_users_admin_page_css',
				'title' => __("CSS", LWS_USERS_DOMAIN),
				'text' => __("Here you can specify extra CSS for the Login/Register Control.", LWS_USERS_DOMAIN ),
				'fields' => array(
					array(
						'id' => 'lws_users_pcss_inline',
						'title' => __("CSS code", LWS_USERS_DOMAIN),
						'type' => 'ace',
						'extra' => array('mode' => 'ace/mode/css')
					)
				)
			)
		);
	}

	protected function grpWidget()
	{
		return array(
			array(
				'id' => 'lws_users_admin_widget_features',
				'title' => __("Features", LWS_USERS_DOMAIN),
				'text' => __("Define the features of the widget login form", LWS_USERS_DOMAIN ),
				'fields' => array(
					array(
						'id' => 'lws_users_wlogin_remember',
						'title' => __("Enable Remember me", LWS_USERS_DOMAIN),
						'type' => 'box',
						'extra' => array('checked'=>true)
					),
					array(
						'id' => 'lws_users_wlogin_lost',
						'title' => __("Enable Password Lost", LWS_USERS_DOMAIN),
						'type' => 'box',
					)
				)
			),
			array(
				'id' => 'lws_users_admin_widget_customisation',
				'title' => __("Customisation", LWS_USERS_DOMAIN),
				'text' => __("Here you can customize the Login Widget's content.<br/><b>[user_name]</b> is the logged user name (empty if none)", LWS_USERS_DOMAIN ),
				'fields' => array(
					array(
						'id' => 'lws_users_wlogin_logmessage',
						'title' => __("Login form message", LWS_USERS_DOMAIN),
						'type' => 'textarea',
						'extra' => array('rows'=>3)
					),
					array(
						'id' => 'lws_users_wlogin_outmessage',
						'title' => __("Logout form message", LWS_USERS_DOMAIN),
						'type' => 'textarea',
						'extra' => array('rows'=>3)
					)
				)
			),
			array(
				'title' => __("Styling", LWS_USERS_DOMAIN),
				'text' => __("Here you can customize the look of the Widget Control.", LWS_USERS_DOMAIN ),
				'fields' => array(
					array(
						'id' => 'lws_users_css_widget',
						'type' => 'stygen',
						'extra' => array(
							'html' => LWS_USERS_SNIPPETS . "/widget.html",
							'css' => LWS_USERS_CSS . "/widget.css"
						)
					)
				)
			),
			array(
				'id' => 'lws_users_admin_widget_css',
				'title' => __("CSS", LWS_USERS_DOMAIN),
				'text' => __("Here you can specify extra CSS for the widget Control.", LWS_USERS_DOMAIN ),
				'fields' => array(
					array(
						'id' => 'lws_users_wcss_inline',
						'title' => __("CSS code", LWS_USERS_DOMAIN),
						'type' => 'ace',
						'extra' => array('mode' => 'ace/mode/css')
					)
				)
			)
		);
	}

	protected function grpMenu()
	{
		return array(
			array(
				'id' => 'lws_users_admin_menu_features',
				'title' => __("Features", LWS_USERS_DOMAIN),
				'text' => __("Define the features of the menu login form", LWS_USERS_DOMAIN ),
				'fields' => array(
					array(
						'id' => 'lws_users_mlogin_remember',
						'title' => __("Enable Remember me", LWS_USERS_DOMAIN),
						'type' => 'box',
						'extra' => array('checked'=>true)
					),
					array(
						'id' => 'lws_users_mlogin_lpage',
						'title' => __("Simple link only", LWS_USERS_DOMAIN),
						'type' => 'box',
						'extra' => array(
							'help' => __("Hide the form and only create a link to the Login Page", LWS_USERS_DOMAIN )
						)
					),
					array(
						'id' => 'lws_users_mlogin_login_page',
						'title' => __("Login Page", LWS_USERS_DOMAIN),
						'type' => 'autocomplete',
						'extra' => array(
							'predefined' => 'page',
							'help' => __("If specified, the menu title will link to this page", LWS_USERS_DOMAIN )
						)
					)
				)
			),
			array(
				'title' => __("Styling", LWS_USERS_DOMAIN),
				'text' => __("Here you can customize the look of the Menu Login.", LWS_USERS_DOMAIN ),
				'fields' => array(
					array(
						'id' => 'lws_users_css_menu',
						'type' => 'stygen',
						'extra' => array(
							'html' => LWS_USERS_SNIPPETS . "/menu.html",
							'css' => LWS_USERS_CSS . "/menu.css"
						)
					)
				)
			),
			array(
				'id' => 'lws_users_admin_menu_css',
				'title' => __("CSS", LWS_USERS_DOMAIN),
				'text' => __("Here you can specify extra CSS for the menu Control.", LWS_USERS_DOMAIN ),
				'fields' => array(
					array(
						'id' => 'lws_users_mcss_inline',
						'title' => __("CSS code", LWS_USERS_DOMAIN),
						'type' => 'ace',
						'extra' => array('mode' => 'ace/mode/css')
					)
				)
			)
		);
	}

	protected function grpRedirSettings()
	{
		return array(
			array(
				'title' => __("Redirection settings", LWS_USERS_DOMAIN),
				'fields' => array(
					array(
						'id' => 'lws_users_fresh_comming_redirection',
						'type' => 'box',
						'title' => __("Enabled", LWS_USERS_DOMAIN),
						'extra' => array(
							'help' => __("Redirect remembered users when they come back from another site.", LWS_USERS_DOMAIN)
						)
					),
					array(
						'id' => 'lws_users_avoid_redirection_scheme',
						'type' => 'textarea',
						'title' => __("Excluded URLs", LWS_USERS_DOMAIN),
						'extra' => array(
							'rows' => 2,
							'help' => __("If the user comes from the following URL (you can specify only a part of a url), he will not be redirected (usefull for multi-site).<br/>Semicolon (<b>;</b>) separated URLs.<br/>Current blog URL as origin will never trigger redirection, no need to add it.", LWS_USERS_DOMAIN)
						)
					)
				)
			)
		);
	}

	protected function grpRedirect()
	{
		return array(
			array(
				'title' => __("Redirections", LWS_USERS_DOMAIN),
				'text' => __("Here you can edit or create a new redirection. Make sure Default redirections for Login and Logout actions are always set", LWS_USERS_DOMAIN),
				'editlist' => lws_editlist(
					'LWS_USERS_RedirList',
					'id',
					new RedirList(),
					\LWS\Adminpanel\EditList::ALL,
					apply_filters('lws_users_admin_redirlist_filters', array($this->redirFilter()))
				),
				'function' => array($this, 'enqueueScript')
			)
		);
	}

	public function enqueueScript()
	{
		wp_enqueue_script( 'lws-users-admin', LWS_USERS_JS.'/admin.js', array('jquery'), LWS_USERS_VERSION, true );
	}

}

?>
