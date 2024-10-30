<?php
namespace LWS\USERS;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_USERS_INCLUDES . '/dialog.php';

class Widget extends \WP_Widget
{

	public function __construct()
	{
		parent::__construct('lws_users_widget', __('User login', 'lws-users'), array('description' => __('A login/logout widget.', 'lws-users')));
	}

	/** Display the widget,
	 *	display parameters in $args
	 *	get option from $instance */
	public function widget($args, $instance)
	{
		echo $args['before_widget'];
		echo $args['before_title'];
		$title = is_user_logged_in() ? '' : __("Connexion", 'lws-users');
		// It is possible to get widget settings if proposed to user in form() function via $instance
		if( !empty($instance['title']) )
			$title = $instance['title'];
		echo apply_filters( 'widget_title', $title );
		echo $args['after_title'];
		$this->display();
		echo $args['after_widget'];
	}

	/** Widget parameters */
	public function form($instance)
	{
		$title = isset($instance['title']) ? $instance['title'] : '';
?>
	<p>
		<label for="<?php echo $this->get_field_name( 'title' ); ?>"><?php _e( 'Title:', 'lws-users' ); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo  $title; ?>" />
	</p>
<?php
	}

	protected function getTextOption($id, $dft='')
	{
		$opt = get_option($id);
		if( empty($opt) )
			$opt = $dft;
		return $opt;
	}

	protected function display()
	{
		if( !is_user_logged_in() )
		{
			$phMsg = Dialog::replaceCustomTags(get_option('lws_users_wlogin_logmessage',''));
			$phLogError = LogAction::error('login');
			$remember = !empty(get_option('lws_users_wlogin_remember', 'on'));

			$ph = array(
				$this->getTextOption('lws_users_username_translate', __("User name", 'lws-users')),
				$this->getTextOption('lws_users_password_translate', __("Password", 'lws-users')),
				$this->getTextOption('lws_users_remember_translate', __("Remember me ?", 'lws-users')),
				$this->getTextOption('lws_users_submit_translate', __("Connect", 'lws-users'))
			);

			if( !empty($phLogError) ) echo "<p class='lw_p_error'>$phLogError</p>";
			?>
			<table class='lwss_selectable lws-widget-login'>
			<form name='loginform' method='post' action=''>
				<input type='hidden' name='lws-login-action' value='login'>
				<?php if( !empty($phMsg) ): ?>
					<tr class='lwss_selectable lws-wlogin-fieldline'>
						<td colspan='2' class='lwss_selectable lws-wlogin-label'>
							<p class='lwss_selectable lws-wlogin-text'><?php echo $phMsg ?></p>
						</td>
					</tr>
				<?php endif ?>
				<tr class='lwss_selectable lws-wlogin-fieldline'>
					<td class='lwss_selectable lws-wlogin-label'><span><?php echo $ph[0]; ?></span></td>
					<td class='lwss_selectable lws-wlogin-label'><input class='lwss_selectable lws-wlogin-input' name='log' <?php Dialog::eValue('log') ?> type='text' size='20'/></td>
				</tr>
				<tr class='lwss_selectable lws-wlogin-fieldline'>
					<td class='lwss_selectable lws-wlogin-label'><span><?php echo $ph[1]; ?></span></td>
					<td class='lwss_selectable lws-wlogin-label'><input class='lwss_selectable lws-wlogin-input' name='pwd' type='password' size='20'/></td>
				</tr>
				<?php if( $remember ): ?>
				<tr class='lwss_selectable lws-wlogin-fieldline'>
					<td class='lwss_selectable lws-wlogin-label'><input class='lwss_selectable lws-wlogin-cb' name='rememberme' <?php Dialog::eChecked('rememberme') ?> type='checkbox'/></td>
					<td class='lwss_selectable lws-wlogin-label'><span><?php echo $ph[2]; ?></span></td>
				</tr>
				<?php endif ?>
				<tr class='lwss_selectable lws-wlogin-footer'>
					<td class='lwss_selectable lws-wlogin-label'>
						<?php echo apply_filters('lws_users_wlogin_lost_pwd', ''); ?>
					</td>
					<td class='lwss_selectable lws-wlogin-label'>
						<input type='submit' class='lwss_selectable button lw_form_button lws-wlogin-button lw_login_button lw_form_submit' value='<?php echo $ph[3]; ?>'>
					</td>
				</tr>
			</form>
			</table>
			<?php
		}
		else
		{
			$phMsg = Dialog::replaceCustomTags(get_option('lws_users_wlogin_outmessage',''));
			$phBtn = esc_attr(get_option('lws_users_logout_translate'));
			if( empty($phBtn) ) $phBtn = esc_attr(__("Logout", 'lws-users'));
			?>
			<table class='lwss_selectable lws-widget-login'>
			<form name='loginform' method='post' action=''>
				<input type='hidden' name='lws-login-action' value='logout'>
				<?php if( !empty($phMsg) ): ?>
				<tr class='lwss_selectable lws-wlogin-fieldline'>
					<td colspan='2' class='lwss_selectable lws-wlogin-label'>
						<p class='lwss_selectable lws-wlogin-text'><?php echo $phMsg ?></p>
					</td>
				</tr>
				<?php endif ?>
				<tr class='lwss_selectable lws-wlogin-footer'>
					<td colspan='2' class='lwss_selectable lws-wlogin-label'>
						<input type='submit' class='lwss_selectable button lw_form_button lws-wlogin-button lw_logout_button lw_form_submit' value='<?php echo $phBtn; ?>'>
					</td>
				</tr>
			</form>
			</table>
			<?php
		}

		wp_enqueue_style('lws_users_css_widget', LWS_USERS_CSS."/widget.css?stygen=lws_users_css_widget", array(), LWS_USERS_VERSION);
	}

}

?>
