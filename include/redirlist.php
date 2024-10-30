<?php
namespace LWS\USERS;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

require_once LWS_USERS_INCLUDES . '/redirection.php';
use \LWS\Adminpanel as AP;

/** Role redirections are stored in wordpress options. */
class RedirList extends AP\EditList\Source
{
	private $m_Id = 'LWS_USERS_RedirList';

	function input()
	{
		$ph = $this->trad(true);
		$opt = $this->trad(false);
		$str = "<fieldset class='lws-editlist-fieldset col30'>";
		$str .= "<input type='hidden' name='id'>";
		$str .= "<div class='lws-editlist-title'>".__("Add/Modify redirection", 'lws-users')."</div>";
		// Type
		$str .= "<label><span class='lws-editlist-opt-title'>{$ph[0]}</span>";
		$str .= "<span class='lws-editlist-opt-input'><select class='lws-input lws-select-input lws-adm-selectmenu' name='type'>";
		$str .= $this->options($opt[0]);
		$str .= "</select></span></label><br/>";
		// Action log in or out
		$str .= "<label><span class='lws-editlist-opt-title'>{$ph[1]}</span>";
		$str .= "<span class='lws-editlist-opt-input'><select class='lws-input lws-select-input lws-adm-selectmenu' name='action'>";
		$str .= $this->options($opt[1]);
		$str .= "</select></span></label>";
		$str .= "</fieldset>";
		$str .= "<fieldset class='lws-editlist-fieldset col35'>";
		$str .= "<div class='lws-editlist-title'>".__("Role/User", 'lws-users')."</div>";
		// User
		$str .= "<label class='lws-adm-field-opt lws-adm-field-opt-1'><span class='lws-editlist-opt-title'>{$ph[2]}</span>";
		$str .= "<span class='lws-editlist-opt-input'>";
		$str .= AP\Pages\Field\Autocomplete::compose('user_id', array('name'=>'user_name', 'predefined'=>'user'));
		$str .= "</span></label>";
		// Role
		$str .= "<label class='lws-adm-field-opt lws-adm-field-opt-2'><span class='lws-editlist-opt-title'>{$ph[3]}</span>";
		$str .= "<span class='lws-editlist-opt-input'>";
		$str .= "<select class='lws-input lws-select-input lws-adm-selectmenu' name='role'>";
		$roles = \wp_roles();
		foreach( $roles->role_names as $role_name => $role_info )
			$str .= "<option value='$role_name'>".translate_user_role($role_info)."</option>";
		$str .= "</select></span></label>";
		$str .= "</fieldset>";
		$str .= "<fieldset class='lws-editlist-fieldset col35'>";
		$str .= "<div class='lws-editlist-title'>".__("Redirection Target", 'lws-users')."</div>";
		// Target
		$str .= "<label><span class='lws-editlist-opt-title'>{$ph[4]}</span>";
		$str .= "<span class='lws-editlist-opt-input'>";
		$str .= AP\Pages\Field\Autocomplete::compose('page_id', array('name'=>'page_title', 'predefined'=>'page'));
		$str .= "</span></label><br/>";
		// Url
		$str .= "<label><span class='lws-editlist-opt-title'>{$ph[5]}</span>";
		$str .= "<span class='lws-editlist-opt-input'>";
		$str .= "<input class='lws-input' type='url' name='url' placeholder='URL' />";
		$str .= "</span></label>";

		$str .= "</fieldset>";
		return $str;
	}

	/** $key => $label */
	function labels()
	{
		return array(
			"_type" => __("Type", 'lws-users'),
			"_action" => array(__("Action", 'lws-users'), "10%"),
			"_trigger" => array(__("Role/User", 'lws-users'), "10%"),
			"_target" => array(__("Page/URL", 'lws-users'), "50%")
		);
	}

	private function byVal($line, $val)
	{
		$line[is_numeric($val) ? 'page_id' : 'url'] = htmlentities($val);
		return $line;
	}

	/** @param $complete true add human readable information */
	function read($limit)
	{
		global $wpdb;
		$metaKey = Redirection::toKey();
		$tmp = array();

		$filter = isset($_GET['triggertype']) ? sanitize_text_field($_GET['triggertype']) : '';
		if( empty($filter) || $filter == 'dflt' || $filter == 'role' )
		{
			$perc = empty($filter) ? '%' : ($filter == 'role' ? '2_%' : '0_%');
			$sql = "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '$metaKey$perc'";
			$this->getResults($sql, $tmp, $limit);

			if( !is_null($limit) )
				$limit->offset -= $this->totalOption($metaKey);
		}

		if( empty($filter) || $filter == 'user' )
		{
			$sql = "SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE meta_key LIKE '$metaKey%'";
			$this->getResults($sql, $tmp, $limit);
		}

		if( !empty($tmp) )
		{
			$opt = $this->trad(false);
			for( $i=0 ; $i<count($tmp) ; $i++ )
				$this->expends($tmp[$i], $opt);
		}
		return $tmp;
	}

	/** @param $sql should starts as "select key, value ..."
	 * @param $tmp (IN|OUT) formated result is appended.
	 * @param $limit (IN|OUT) null or a RowLimit instance,  */
	private function getResults($sql, &$tmp, &$limit)
	{
		global $wpdb;
		if( !is_null($limit) )
		{
			if( !$limit->valid() )
				return;
			$sql .= $limit->toMysql();
		}

		$results = $wpdb->get_results($sql, ARRAY_N);
		foreach( $results as $r )
			$tmp[] = $this->byVal( Redirection::fromKey($r[0]), $r[1] );
	}

	private function totalOption($metaKey)
	{
		global $wpdb;
		$sql = "SELECT COUNT(option_id) FROM {$wpdb->options} WHERE option_name LIKE '$metaKey%'";
		return $wpdb->get_var($sql);
	}

	private function totalUser($metaKey)
	{
		global $wpdb;
		$sql = "SELECT COUNT(umeta_id) FROM {$wpdb->usermeta} WHERE meta_key LIKE '$metaKey%'";
		return $wpdb->get_var($sql);
	}

	public function total()
	{
		$metaKey = Redirection::toKey();
		return $this->totalOption($metaKey) + $this->totalUser($metaKey);
	}

	function erase( $line )
	{
		if( !$this->keyConsistency($line) ) return false;
		$line = Redirection::fromKey( $line['id'], false );
		if( $line['type'] == '1' )
			return delete_user_meta( $line['user_id'], Redirection::toKey(false, '', $line['user_id'], $line['action']) );
		else
			return delete_option( Redirection::toKey($line['type']==0, $line['role'], null, $line['action']) );
	}

	/// @return db option_id or umeta_id if any to update or null
	private function getOptionId($lineId)
	{
		global $wpdb;
		$id = null;
		if( !empty($lineId) )
		{
			$line = Redirection::fromKey( $lineId, false );
			if( $line['type'] == '1' ) // umeta_id
			{
				$k = Redirection::toKey(false, '', $line['user_id'], $line['action']) ;
				return $wpdb->get_var("SELECT umeta_id FROM {$wpdb->usermeta} WHERE meta_key = '$k'");
			}
			else // option_id
			{
				$k = Redirection::toKey($line['type']==0, $line['role'], null, $line['action']);
				return $wpdb->get_var("SELECT option_id FROM {$wpdb->options} WHERE option_name = '$k'");
			}
		}
		return $id;
	}

	function write( $line )
	{
		global $wpdb;
		$newLine = false;
		$line = $this->lineConsistency($line);
		if( $line !== false )
		{
			$optId = $this->getOptionId($line['id']);
			$line['id'] = Redirection::toKey($line['type']==0, $line['role'], $line['user_id'], $line['action'], false);
			$val = empty($line['url']) ? $line['page_id'] : $line['url'];

			if( $line['type'] == '1' )
			{
				$k = Redirection::toKey(false, '', $line['user_id'], $line['action']);
				if( empty($optId) )
				{
					if( update_user_meta( $line['user_id'], $k, $val ) )
						$newLine = $line;
				}
				else
				{
					if( $wpdb->update($wpdb->usermeta, array('meta_key'=>$k, 'meta_value'=>$val), array('umeta_id'=>$optId) ) !== false )
						$newLine = $line;
				}
			}
			else
			{
				$k = Redirection::toKey($line['type']==0, $line['role'], null, $line['action']);
				if( empty($optId) )
				{
					if( update_option( $k, $val ) )
						$newLine = $line;
				}
				else
				{
					if( $wpdb->update($wpdb->options, array('option_name'=>$k, 'option_value'=>$val), array('option_id'=>$optId) ) !== false )
						$newLine = $line;
				}
			}
		}

		if( $newLine !== false )
			return $this->expends($newLine, $this->trad(false));
		else
			return false;
	}

	function defaultValues()
	{
		return array(
			"type" => 0,
			"action" => 0,
			"role" => array_keys(\wp_roles()->role_names)[0]
		);
	}

	private function options($opt, $selected=0)
	{
		$str = "";
		for( $i=0 ; $i<count($opt) ; $i++ )
		{
			$sel = ($i == $selected ? " selected='selected'" : "");
			$str .= "<option value='$i'$sel>{$opt[$i]}</option>";
		}
		return $str;
	}

	private function trad($label = true)
	{
		if( $label )
		{
			$ph = array(
				_x("Redir. Type", "label redirection", 'lws-users'),
				_x("Trigger Action", "label redirection", 'lws-users'),
				_x("User", "label redirection", 'lws-users'),
				_x("Role", "label redirection", 'lws-users'),
				_x("Target", "label redirection", 'lws-users'),
				_x("or URL", "label redirection", 'lws-users')
			);
			return $ph;
		}
		else
		{
			$opt = array(
				array(_x("Default", "redirection", 'lws-users'),_x("User", "redirection", 'lws-users'), _x("Role", "redirection", 'lws-users')),
				array(_x("Login", "redirection", 'lws-users'), _x("Logout", "redirection", 'lws-users'))
			);
			return $opt;
		}
	}

	private function roleName($role)
	{
		global $wp_roles;
		if( isset($wp_roles->roles[$role]) )
			return $wp_roles->roles[$role]['name'];
		return empty($role) ? "" : _x("Unknown", "get role error", 'lws-users');
	}

	private function userName($user_id)
	{
		$user = (!empty($user_id) && is_numeric($user_id)) ? get_user_by('id', $user_id) : false;
		if( !empty($user) )
			return html_entity_decode($user->user_login);
		return empty($user_id) ? "" : _x("Unknown", "get user error", 'lws-users');
	}

	private function pageTitle($page_id)
	{
		$title = (!empty($page_id) && is_numeric($page_id)) ? get_the_title($page_id) : false;
		if( !empty($title) )
			return html_entity_decode($title);
		return empty($page_id) ? "" : _x("Unknown", "get page error", 'lws-users');
	}

	private function expends(&$line, $opt)
	{
		$line['_type'] = $this->getTrad($line['type'], $opt[0]);
		$line['_action'] = $this->getTrad($line['action'], $opt[1]);

		if( $line['type'] == 0 )
			$line['_trigger'] = htmlentities(_x("All", "redirection", 'lws-users'));
		else if( $line['type'] == 1 ) // User
		{
			$line['_trigger'] = htmlentities($this->userName($line['user_id']));
			$line['user_name'] = $line['_trigger'];
		}
		else if( $line['type'] == 2 ) // Role
			$line['_trigger'] = htmlentities($this->roleName($line['role']));

		// Target
		if( empty($line['url']) )
		{
			$line['_target'] = htmlentities($this->pageTitle($line['page_id']));
			$line['page_title'] = $line['_target'];
		}
		else
			$line['_target'] = htmlentities($line['url']);
		return $line;
	}

	private function getTrad($index, $trad)
	{
		if( array_key_exists($index, $trad) )
			return htmlentities($trad[$index]);
		else
		{
			error_log(__FUNCTION__ . ":" . __LINE__ ." > Traduction index out of range ($index).");
			return '';
		}
	}

	/** @return a line where useless fields are cleared (eg. role='' if type is user).
	 * return false if an error exists in line. */
	private function lineConsistency($line)
	{
		if( !$this->keyConsistency($line) ) return false;

		if( empty($line['type']) )
			$line['type'] = 0;
		if( empty($line['action']) )
			$line['action'] = 0;

		if( $line['type'] == 0 )
			$line['user_id'] = $line['role'] = '';
		else if( $line['type'] == 1 ) // User
			$line['role'] = '';
		else if( $line['type'] == 2 ) // Role
			$line['user_id'] = '';

		$line['url'] = trim($line['url']);
		return $line;
	}

	private function keys()
	{
		return array('id', 'type', 'action', 'user_id', 'role', 'page_id', 'url');
	}

	private function keyConsistency($line)
	{
		if( !is_array($line) ) return false;
		foreach( $this->keys() as $k )
		{
			if( !array_key_exists($k, $line) )
				return false;
		}
		return true;
	}
}

?>
