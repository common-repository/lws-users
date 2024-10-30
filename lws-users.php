<?php
/**
 * Plugin Name: The User Plugin
 * Description: Provide a login/logout solution available on pages, posts, widgets and menus. Manage user redirection on login/logout by role or user.
 * Plugin URI: https://plugins.longwatchstudio.com
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 1.4.7
 * Text Domain: lws-users
 *
 * Copyright (c) 2017 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.
 *
 */

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();


/**
 * @class LWS_Users The class that holds the entire plugin
 */
final class LWS_Users
{

	public static function init()
	{
		static $instance = false;
		if( !$instance )
		{
			$instance = new self();
			$instance->defineConstants();
			$instance->load_plugin_textdomain();

			add_action( 'lws_adminpanel_register', array($instance, 'admin') );
			add_action( 'lws_adminpanel_plugins', array($instance, 'register') );
			add_filter( 'plugin_action_links_'.plugin_basename( __FILE__ ), array($instance, 'extensionListActions'), 10, 2 );

			$instance->install();

			register_activation_hook( __FILE__, 'LWS_Users::activate' );
			$instance->update();
		}
		return $instance;
	}

	public function v()
	{
		static $version = '';
		if( empty($version) ){
			if( !function_exists('get_plugin_data') ) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$data = \get_plugin_data(__FILE__, false);
			$version = (isset($data['Version']) ? $data['Version'] : '0');
		}
		return $version;
	}

	public function extensionListActions($links, $file)
	{
		$label = __('Settings'); // use standart wp sentence, no text domain
		$url = add_query_arg(array('page'=>'lws-users'), admin_url('options-general.php'));
		array_unshift($links, "<a href='$url'>$label</a>");
		$label = __('Help'); // use standart wp sentence, no text domain
		$url = esc_attr($this->addDocUrl(''));
		$links[] = "<a href='$url'>$label</a>";
		return $links;
	}

	public function addDocUrl($url)
	{
		return __("https://plugins.longwatchstudio.com/en/documentation-en/the-user-plugin/", 'woorewards');
	}

	/** Load translation file
	 * If called via a hook like this
	 * @code
	 * add_action( 'plugins_loaded', array($instance,'load_plugin_textdomain'), 1 );
	 * @endcode
	 * Take care no text is translated before. */
	function load_plugin_textdomain() {
		load_plugin_textdomain( 'lws-users', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Define the plugin constants
	 *
	 * @return void
	 */
	private function defineConstants()
	{
		define( 'LWS_USERS_VERSION', $this->v() );
		define( 'LWS_USERS_FILE', __FILE__ );
		define( 'LWS_USERS_DOMAIN', 'lws-users' );

		define( 'LWS_USERS_PATH', dirname( LWS_USERS_FILE ) );
		define( 'LWS_USERS_INCLUDES', LWS_USERS_PATH . '/include' );
		define( 'LWS_USERS_SNIPPETS', LWS_USERS_PATH . '/snippets' );
		define( 'LWS_USERS_ASSETS', LWS_USERS_PATH . '/assets' );

		define( 'LWS_USERS_URL', plugins_url( '', LWS_USERS_FILE ) );
		define( 'LWS_USERS_JS', plugins_url( '/js', LWS_USERS_FILE ) );
		define( 'LWS_USERS_CSS', plugins_url( '/css', LWS_USERS_FILE ) );
	}

	function admin()
	{
		require_once LWS_USERS_INCLUDES . '/admin.php';
		new \LWS\USERS\Admin();
	}

	private function install()
	{
		if( !defined('DOING_AJAX') )
		{
			require_once LWS_USERS_INCLUDES . '/dialog.php';
			new \LWS\USERS\Dialog();

			require_once LWS_USERS_INCLUDES . '/menuitem.php';
			new \LWS\USERS\MenuItem();

			require_once LWS_USERS_INCLUDES . '/redirection.php';
			$this->redir = new \LWS\USERS\Redirection();

			require_once LWS_USERS_INCLUDES . '/logaction.php';
			new \LWS\USERS\LogAction();
		}
		else
		{
			require_once LWS_USERS_INCLUDES . '/ajax.php';
			new \LWS\USERS\Ajax();
		}

		require_once LWS_USERS_INCLUDES . '/widget.php';
		add_action('widgets_init', function(){register_widget('\LWS\USERS\Widget');});

		add_action( 'vc_before_init', array($this, 'integrateVisualComposer') );
	}

	public function register()
	{
		lws_extension_showcase(__FILE__);
	}

	/** add relevent default values. Values are NOT overwriten if already exist. */
	public static function activate()
	{
		add_option('lws_users_pregister_alert_admin', 'on');
	}

	/** add relevent default values. Values are NOT overwriten if already exist. */
	private function update()
	{
		$old_vers = get_site_option('lws_users_version', '0');

		if( version_compare($old_vers, '1.4.0', '<') )
			$this->update010400();

		if( version_compare($old_vers, $this->v(), '<') )
			update_site_option('lws_users_version', $this->v());
	}

	private function update010400()
	{
		add_option('lws_users_lost_translate', __("Lost your password ?", LWS_USERS_DOMAIN));

		add_option('lws_users_plugin_actmail', __("<html><body><p>Wecome [user_name]!</p><p>To activate your account, click [activation_link]here[/activation_link].</p></body></html>", LWS_USERS_DOMAIN));
		add_option('lws_users_plogin_activation', 'on');

		add_option('lws_users_pwd_lost_mail_content', __("<html><body>
<p>A new password have been generated. To connect, Use <ul><li>Login: [login]</li><li>Password: [password]</li></ul>.</p>
<p>You can define a new pasword at anytime on this page: [change_pwd_page].</p>
</body></html>", LWS_USERS_DOMAIN));

		self::createPageIfNotExist('lws_users_activation_mail_sent', __("Activation mail sent", LWS_USERS_DOMAIN), __("An activation mail have been sent to you.", LWS_USERS_DOMAIN));
		self::createPageIfNotExist('lws_users_account_activated', __("Account activated", LWS_USERS_DOMAIN), __("Your account is activated. Now you can connect.", LWS_USERS_DOMAIN));
		self::createPageIfNotExist('lws_users_account_activation_error', __("Account activation error", LWS_USERS_DOMAIN), __("An error occured during activation. Please retry later or contact the administrator.", LWS_USERS_DOMAIN));

		self::createPageIfNotExist('lws_users_pwd_lost_page', __("Lost password", LWS_USERS_DOMAIN), __("<p>A new password will be sent to your email address.</p>[send_password_form]", LWS_USERS_DOMAIN));
		self::createPageIfNotExist('lws_users_pwd_change_page', __("Password settings", LWS_USERS_DOMAIN), __("<p>Change your password</p>[change_password_form]", LWS_USERS_DOMAIN));
	}

	/** create page if not exist and set admin option default value. */
	protected static function createPageIfNotExist($pageName, $title, $content, $adminOption='')
	{
		if( empty($adminOption) )
			$adminOption = $pageName;

		$sites = array(0);
		if( function_exists('get_sites') )
			$sites = \get_sites(array('fields'=>'ids'));
		else if( is_multisite() )
			error_log("Cannot get_sites() on a multisite installation. Code below could crash.");

		foreach($sites as $siteId)
		{
			if( !empty($siteId) ) \switch_to_blog($siteId);

			global $wpdb;
			$page = $wpdb->get_var( $wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE `post_type`='page' AND `post_name`=%s;", $pageName) );
			if( empty($page) )
			{
				$data = array(
					'post_type' => 'page',
					'post_status' => 'publish',
					'post_category' => array(),
					'post_name' => $pageName,
					'post_title' => $title,
					'post_content' => $content
				);
				$page = wp_insert_post($data);
				if( empty($page) || is_wp_error($page) )
					error_log("Error when inserting default page '$pageName'.");
			}
			if( !empty($page) && !is_wp_error($page) )
				add_option($adminOption, $page);

			if( !empty($siteId) ) restore_current_blog();
		}
	}

	/** register our shortcodes in VisualComposer base if vc plugin installed. */
	function integrateVisualComposer()
	{
		if( function_exists('vc_map') )
		{
			/// @see http://www.wpelixir.com/how-to-create-new-element-in-visual-composer/
			$lws_users = array(
				"name" => __("User login form", LWS_USERS_DOMAIN),
				"base" => "lws_users",
				"class" => "",
				"category" => __("Long Watch Studio", LWS_USERS_DOMAIN),
				"description" => __("Display a login/register form", LWS_USERS_DOMAIN),
				"icon" => LWS_USERS_CSS.'/icon-lws.png',
				"params" => array(
					array(
						"type"              => "dropdown",
						"heading"           => __("Registration page", LWS_USERS_DOMAIN),
						"param_name"        => "enable_register",
						"value"             => array(
							__("Show", LWS_USERS_DOMAIN)		=> "1",
							__("Hide", LWS_USERS_DOMAIN)		=> "0"
						),
						"admin_label"		=> true,
						"description"       => __("Display a tab allowing visitor to register.", LWS_USERS_DOMAIN)
					),
					array(
						"type"              => "dropdown",
						"heading"           => __("Remember me checkbox", LWS_USERS_DOMAIN),
						"param_name"        => "enable_remember",
						"value"             => array(
							__("Show", LWS_USERS_DOMAIN)		=> "1",
							__("Hide", LWS_USERS_DOMAIN)		=> "0"
						),
						"admin_label"		=> true,
						"description"       => __("Display a checkbox to keep user connected between two visits.", LWS_USERS_DOMAIN)
					)
				)
			);
			vc_map($lws_users);

			$send_password_form = array(
				"name" => __("Password lost", LWS_USERS_DOMAIN),
				"base" => "send_password_form",
				"class" => "",
				"category" => __("Long Watch Studio", LWS_USERS_DOMAIN),
				"description" => __("Display a form to send a new password", LWS_USERS_DOMAIN),
				"icon" => LWS_USERS_CSS.'/icon-lws.png'
			);
			vc_map($send_password_form);

			$change_password_form = array(
				"name" => __("Change Password", LWS_USERS_DOMAIN),
				"base" => "change_password_form",
				"class" => "",
				"category" => __("Long Watch Studio", LWS_USERS_DOMAIN),
				"description" => __("Display a form to update user password", LWS_USERS_DOMAIN),
				"icon" => LWS_USERS_CSS.'/icon-lws.png'
			);
			vc_map($change_password_form);
		}
	}
}

LWS_Users::init();

@include_once dirname(__FILE__) . '/assets/lws-adminpanel/lws-adminpanel.php';

?>
