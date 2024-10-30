<?php
namespace LWS\USERS;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_USERS_INCLUDES . '/dialog.php';

/** Add a new kind of menu item to add in user menus.
* This code is mainly adapted from http://www.johnmorrisonline.com/how-to-add-a-fully-functional-custom-meta-box-to-wordpress-navigation-menus/.
* If we need to add custom fields (options) in our menu entry, @see http://www.wpexplorer.com/adding-custom-attributes-to-wordpress-menus/
*
* @see D:\Projects\www\longwatchstudio.com\wp-admin\includes\ajax-actions.php:1150 function wp_ajax_add_menu_item()
*		for nav menu item appending.
*	@see D:\Projects\www\longwatchstudio.com\wp-admin\includes\class-walker-nav-menu-edit.php
*		For nav menu item display in admin menu.
*
*	Add custom field (option in added nav item metabox) could be tricky.
* Since you have to extends Walker_Nav_Menu_Edit class andd use 'wp_edit_nav_menu_walker' filter to replace instanciated walker.
* @see https://github.com/kucrut/wp-menu-item-custom-fields, plugin "Menu Item Custom Fields"
* But this can produce confict with other plugins as "if menu" which already do that to provide a hook (do_action), allowing it to add fields.
* So, doing this can break others, or other can break our depending of filter priority.
* Keep this idea deprecated until wordpress provide a hook in his official walker.
*/
class MenuItem
{
	protected $ItemClasses = array();
	protected $Title = '';
	protected $JustLinkLoginPage = false;
	const INDEX_WIDGET = 0;
	const INDEX_LOGGED_IN = 1;
	const INDEX_LOGGED_OUT = 2;
	const INDEX_CHILDREN = 3;

	const INDEX_CLASS = 0;
	const INDEX_MENU_TYPE = 1; // keep empty or 'custom'
	const INDEX_TITLE = 2;
	const INDEX_URL = 3;

	public function __construct()
	{
		$this->Title = __( 'The User Plugin', 'lws-users' );
		$this->ItemClasses = array( /// {css_class, menu_type, title, url}
			array('lws-users-menuitem-login', '', __("Login Menu", 'lws-users'), ''),
			array('lws-users-menuitem-user-logged', 'custom', __("Logged in Menu", 'lws-users'), ''),
			array('lws-users-menuitem-nopriv', 'custom', __("Not logged in menu", 'lws-users'), ''),
			array('lws-users-menuitem-login-children', 'custom', null, '')
		);

		// Add endpoints custom URLs in Appearance > Menus > Pages
		add_action( 'admin_init', array( $this, 'add_nav_menu_meta_boxes' ) );
		// Allows filter menu element list before any usage, such like remove for conditional, etc.
		add_filter('wp_nav_menu_objects', array($this, 'wp_nav_menu_objects'), 10, 2);
		// Allows replacing the menu item outpout
		add_filter('walker_nav_menu_start_el', array($this, 'walker_nav_menu_start_el'), 10, 4);
		// Change iten data before display in menu admin.
		add_filter('wp_setup_nav_menu_item', array($this, 'wp_setup_nav_menu_item'));

		// Filter a little bit random but called for nav-menu page, before default user option initialisation
		add_filter('wp_get_nav_menus', array($this, 'default_hidden_meta_boxes'));

		if( !empty(get_option('lws_users_mlogin_lpage', '')) )
			$this->JustLinkLoginPage = true;
	}

	/** Set metaboxhidden_nav-menus without our menus if not already set.
	 * Tricky since wp does not provide any filter for default values.
	 * So we do it just before wordpress and show all by default.
	 * @see ./wp-admin/includes/nav-menu.php:163 : function wp_initial_nav_menu_meta_boxes()  */
	public function default_hidden_meta_boxes($menus)
	{
		if ( get_user_option( 'metaboxhidden_nav-menus' ) === false )
		{
			$hidden_meta_boxes = apply_filters('initial_metaboxhidden_nav-menus', array());
			$user = wp_get_current_user();
			update_user_option( $user->ID, 'metaboxhidden_nav-menus', $hidden_meta_boxes, true );
		}
		return $menus;
	}

	/** Add custom nav meta box. */
	public function add_nav_menu_meta_boxes()
	{
		// add the meta box setting “nav-menus” as the $post_type parameter.
		add_meta_box( 'lws_users_nav_login_widget', $this->Title, array( $this, 'nav_menu_links' ), 'nav-menus', 'side', 'low' );
	}

	/**
	 * Output menu links.
	 * can get tricky in order to make the adding of our custom link item to the nav menu.
	 * The JavaScript that actually processes the adding of the link to the menu selects your link item in a specific way,
	 * so, the following code works, take care if you alter it.
	 *	for more choices, copy this whole <li/> and just decrement the index "menu-item[{this one: so -2, -3, ...}][...]"
	 *	the menu-item-classes IS important, used to find it out later and apply changes.
	 *	The main div ID and submit button ID must match. So button is id="submit-{main_div_id}".
	 */
	public function nav_menu_links()
	{
		$MenuClass = 'posttype-lws-users';
		?>
		<div id="<?php echo $MenuClass; ?>" class="posttypediv">
			<div id="tabs-panel-lws-users" class="tabs-panel tabs-panel-active">
				<ul id="lws-users-checklist" class="categorychecklist form-no-clear">
					<?php
					$i = 0;
					foreach( $this->ItemClasses as $arg )
					{
						if( !is_null($arg[self::INDEX_TITLE]) )
						{
							$i--;
							$this->menuEntryLine($i, $arg);
						}
					}
					?>
				</ul>
			</div>
			<p class="button-controls">
				<span class="list-controls">
					<a href="<?php echo admin_url( 'nav-menus.php?page-tab=all&selectall=1#posttype-lws-users' ); ?>" class="select-all"><?php _e( 'Select All', 'lws-users' ); ?></a>
				</span>
				<span class="add-to-menu">
					<input type="submit" class="button-secondary submit-add-to-menu right" value="<?php esc_attr_e( 'Add to Menu', 'lws-users' ); ?>" name="add-post-type-menu-item" id="submit-<?php echo $MenuClass; ?>">
					<span class="spinner"></span>
				</span>
			</p>
		</div>
		<?php
	}

	/** an entry item selection is out here. @see ItemClasses
	 *	Don't change it since it fall in work as it is. */
	protected function menuEntryLine($index, $items)
	{
		?>
		<li>
			<label class="menu-item-title">
				<input type="checkbox" checked class="menu-item-checkbox" name="menu-item[<?php echo $index ?>][menu-item-object-id]" value="<?php echo $index ?>" /> <?php echo $items[MenuItem::INDEX_TITLE] ?>
			</label>
			<input type="hidden" class="menu-item-type" name="menu-item[<?php echo $index ?>][menu-item-type]" value="<?php echo $items[MenuItem::INDEX_MENU_TYPE] ?>">
			<input type="hidden" class="menu-item-title" name="menu-item[<?php echo $index ?>][menu-item-title]" value="<?php echo $items[MenuItem::INDEX_TITLE] ?>">
			<input type="hidden" class="menu-item-attr-title" name="menu-item[<?php echo $index ?>][menu-item-attr-title]" value="<?php echo $items[MenuItem::INDEX_TITLE] ?>">
			<input type="hidden" class="menu-item-url" name="menu-item[<?php echo $index ?>][menu-item-url]" value="<?php echo $items[MenuItem::INDEX_URL] ?>">
			<input type="hidden" class="menu-item-classes" name="menu-item[<?php echo $index ?>][menu-item-classes]" value="<?php echo $items[MenuItem::INDEX_CLASS] ?>">
		</li>
		<?php
	}

	/**	Change type label displayed in nav menu item metabox
	 *	@see D:\Projects\www\longwatchstudio.com\wp-includes\nav-menu.php:853 */
	public function wp_setup_nav_menu_item($menu_item)
	{
		foreach( $this->ItemClasses as $k => $itemclass )
		{
			if( $this->isOurItem($menu_item, $k) )
				$menu_item->type_label = sprintf(__("%s - %s", 'lws-users'), $this->Title, $itemclass[MenuItem::INDEX_TITLE]);
		}
		return $menu_item;
	}

	private function getPageUrl($optId)
	{
		$urlId = get_option($optId);
		$pageUrl = !empty($urlId) && is_numeric($urlId) ? get_permalink($urlId) : false;
		return $pageUrl;
	}

	/** Exclude menu object if user logged in or out. */
	public function wp_nav_menu_objects($objects, $args)
	{
		$loggedIn = is_user_logged_in();
		$exclusion = $loggedIn ? MenuItem::INDEX_LOGGED_OUT : MenuItem::INDEX_LOGGED_IN;
		$rem = array();
		$remids = array(); // used to destroy children
		$add = array();
		foreach( $objects as $k => &$object )
		{
			if( $this->isOurItem($object, $exclusion) )
			{
				$rem[] = $k;
				$remids[] = $object->ID;
			}
			else if( in_array($object->menu_item_parent, $remids) )
			{
				$rem[] = $k;
				$remids[] = $object->ID;
			}
			else if( $this->isOurItem($object, MenuItem::INDEX_WIDGET) )
			{
				$object->description = $object->post_content = '';//$object->post_title;
				if( !$loggedIn ){
					if( empty($object->post_title) )
						$object->post_title = __("My account", 'menu entry logged off', 'lws-users');
				}else if( $this->JustLinkLoginPage ){
					$object->post_title = $this->getTextOption('lws_users_logout_translate', __("Logout", 'lws-users'));
				}else{
					$user = wp_get_current_user();
					$object->post_title = sprintf(__("%s", 'menu entry %s=username', 'lws-users'), !empty($user->display_name)?$user->display_name:$user->user_login);
				}
				$object->title = $object->post_title;
				$object->post_excerpt = $object->attr_title = "";

				$pageUrl = $this->getPageUrl('lws_users_mlogin_login_page');
				if( !empty($pageUrl) )
				{
					$object->object = 'page';
					$object->type = 'post_type';
					$object->type_label = 'Page';
					$object->url = $pageUrl;
				}
				if( !$this->JustLinkLoginPage ) // add a children
				{
					$add[] = $this->createMenuChild($object, count($objects)+count($add)+1);
					$this->itemClasses($object, true);
				}
				else if( $loggedIn )
					$object->url = '';
			}
		}
		foreach( $rem as $k )
			unset( $objects[$k] );
		foreach( $add as $k )
			$objects[] = $k;
		return $objects;
	}

	/** Hook the menu outpout to replace the content. */
	public function walker_nav_menu_start_el($item_output, $item, $depth, $args)
	{
		if( $this->JustLinkLoginPage && is_user_logged_in() && $this->isOurItem($item, MenuItem::INDEX_WIDGET) )
			$outpout = $this->logoutItem($args, $depth, $item_output);
		else if( $this->isOurItem($item, MenuItem::INDEX_CHILDREN) )
			$outpout = $this->childItem($args, $depth, $item_output);
		else
			$outpout = $item_output;
		return $outpout;
	}

	protected function itemClasses(&$object, $hasChildren)
	{
		$object->object = $object->type = 'custom';
		$object->classes = array(
			'menu-item',
			'menu-item-type-' . $object->object,
			'menu-item-object-' . $object->type
		);
		if($hasChildren)
			$object->classes[] = 'menu-item-has-children';
	}

	protected function createMenuChild($parent, $menuOrder = PHP_INT_MAX, $indexChildren=MenuItem::INDEX_CHILDREN)
	{
		$child = clone $parent;
		$child->ID = $child->object_id = $child->db_id = 0;
		$child->object = $child->type = 'custom';
		$child->menu_item_parent = $parent->ID;
		$child->menu_order = $menuOrder;
		$child->guid = add_query_arg( array('p'=>$child->ID), home_url() );
		$this->itemClasses($child, false);
		$child->classes[] = $this->ItemClasses[$indexChildren][MenuItem::INDEX_CLASS];
		$child->classes[] = 'lws-users-menu-item-mode-' . (is_user_logged_in()?'loggedin':'loggedout');
		$child->title = $child->post_title = $this->getTextOption('lws_users_logout_translate', __("Logout", 'lws-users'));
		$child->post_excerpt = $child->attr_title = "";
		return $child;
	}

	/** @param $item is a menu_nav objet.
	 *	@param $login 0: login entry, 1: logged entry, 2: anonymous visitor. */
	protected function isOurItem( $item, $login=null )
	{
		if( is_null($login) )
		{
			foreach( $this->ItemClasses as $itemclass )
			{
				if( in_array( $itemclass[MenuItem::INDEX_CLASS], $item->classes ) )
					return true;
			}
			return false;
		}
		else if( in_array($login, array_keys($this->ItemClasses)) )
		{
			$itemclass = $this->ItemClasses[$login];
			return in_array( $itemclass[MenuItem::INDEX_CLASS], $item->classes );
		}
		else
			return false;
	}

	protected function childItem($args, $depth, $item_output)
	{
		if( !is_user_logged_in() )
			return $this->loginItem($args, $depth, $item_output);
		else
			return $this->logoutItem($args, $depth, $item_output);
	}

	protected function getTextOption($id, $dft='')
	{
		$opt = get_option($id);
		if( empty($opt) )
			$opt = $dft;
		return $opt;
	}

	/** It is here we write our form.
	 *	@return the widget html */
	protected function loginItem($args, $depth, $item_output)
	{
		$out = "";

		$phLogError = LogAction::error('login');
		$log = Dialog::_Value('log');
		$rem = Dialog::_Checked('rememberme');
		$remember = !empty(get_option('lws_users_mlogin_remember', 'on'));

		$ph = array(
			$this->getTextOption('lws_users_username_translate', __("User name", 'lws-users')),
			$this->getTextOption('lws_users_password_translate', __("Password", 'lws-users')),
			$this->getTextOption('lws_users_remember_translate', __("Remember me ?", 'lws-users')),
			$this->getTextOption('lws_users_submit_translate', __("Connect", 'lws-users'))
		);

		$out .= "<table class='lwss_selectable lws-menu-login'>";
		$out .= "<form name='loginform' method='post' action=''>";
		$out .= "<input type='hidden' name='lws-login-action' value='login'>";
		if( !empty($phLogError) )
		{
			$out .= "<tr class='lwss_selectable lws-mlogin-fieldline'><td class='lwss_selectable lws-mlogin-label'>";
			$out .= "<p class='lws-mlogin-text'>$phLogError</p>";
			$out .= "</td></tr>";
		}

		$out .= "<tr class='lwss_selectable lws-mlogin-fieldline'>";
		$out .= "<td class='lwss_selectable lws-mlogin-label'><span>{$ph[0]}</span></td>";
		$out .= "<td class='lwss_selectable lws-mlogin-label'><input class='lwss_selectable lws-mlogin-input' name='log' $log type='text' size='20'/></td>";
		$out .= "</tr>";

		$out .= "<tr class='lwss_selectable lws-mlogin-fieldline'>";
		$out .= "<td class='lwss_selectable lws-mlogin-label'><span>{$ph[1]}</span></td>";
		$out .= "<td class='lwss_selectable lws-mlogin-label'><input class='lwss_selectable lws-mlogin-input' name='pwd' type='password' size='20'/></td>";
		$out .= "</tr>";

		if( $remember )
		{
			$out .= "<tr class='lwss_selectable lws-mlogin-fieldline'>";
			$out .= "<td class='lwss_selectable lws-mlogin-label'><input class='lwss_selectable lws-mlogin-cb' name='rememberme' $rem type='checkbox'/></td>";
			$out .= "<td class='lwss_selectable lws-mlogin-label'><span>{$ph[2]}</span></td>";
			$out .= "</tr>";
		}

		$out .= "<tr class='lwss_selectable lws-mlogin-footer'>";
		$out .= "<td class='lwss_selectable lws-mlogin-label'>";
		$out .= apply_filters('lws_users_mlogin_lost_pwd', '');
		$out .= "</td>";
		$out .= "<td class='lwss_selectable lws-mlogin-label'><input type='submit' class='lwss_selectable button lws-mlogin-button lw_form_submit' value='{$ph[3]}'></td>";
		$out .= "</tr>";

		$out .= "</form>";
		$out .= "</table>";

		$this->enqueueScripts();
		return $out;
	}

	/**	@return the widget html */
	protected function logoutItem($args, $depth, $item_output)
	{
		$out = "<form class='lw_form_submit_on_next' name='loginform' method='post' action=''>";
		$out .= "<input type='hidden' name='lws-login-action' value='logout'>";
		$out .= "</form>";
		$out .= $item_output;

		$this->enqueueScripts();
		return $out;
	}

	public function enqueueScripts()
	{
		wp_enqueue_script( 'lws-users-menu', LWS_USERS_JS.'/menu.js', array('jquery', 'jquery-ui-tabs'), LWS_USERS_VERSION, true );
		wp_enqueue_style('lws_users_css_menu', LWS_USERS_CSS."/menu.css?stygen=lws_users_css_menu", array(), LWS_USERS_VERSION);
	}


}

?>
