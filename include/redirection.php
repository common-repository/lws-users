<?php
namespace LWS\USERS;

// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

class Redirection
{
	public function __construct()
	{
		add_action( 'template_redirect', array($this, 'freshComming'), -99999 ); // 'template_redirect' not trigger in admin
		add_filter( 'lws_users_redirection', array($this, 'userRedirection'), 10, 3 );
	}


	/** This function does not work and I don't know why.  */
	public function freshComming()
	{
		if( !empty(get_option('lws_users_fresh_comming_redirection')) )
		{
			if( $this->fromOutside() )
			{
				$user = wp_get_current_user();
				if( !empty($user) && !empty($user->ID) )
				{
					global $wp;
					$destination = home_url(add_query_arg(array(),$wp->request));
					$destination = apply_filters('lws_users_redirection', $destination, $user);

					if( $this->needRedirect(trim($destination)) )
					{
						wp_redirect($destination);
						exit();
					}
				}
			}
		}
	}

	/** return true if user seems to come from out of this site. */
	private function fromOutside()
	{
		$origin = array_key_exists('HTTP_ORIGIN', $_SERVER) ? $_SERVER['HTTP_ORIGIN'] : NULL;
		if( empty($origin) )
			$origin = array_key_exists('HTTP_REFERER', $_SERVER) ? $_SERVER['HTTP_REFERER'] : NULL;
		$pattern = parse_url($origin, PHP_URL_HOST) . parse_url($origin, PHP_URL_PATH);

		$excluded = array(parse_url(home_url(), PHP_URL_HOST) . parse_url(home_url(), PHP_URL_PATH));
		$tmp = get_option('lws_users_avoid_redirection_scheme','');
		if( !empty($tmp) )
			$excluded =  array_merge( array(home_url()), explode(';',$Tmp) );

		foreach($excluded as $scheme)
		{
			$scheme = trim($scheme);
			if( !empty($scheme) && false !== strpos($pattern, $scheme) )
				return false;
		}

		return true;
	}

	/** are we not already on the right page?
	 * @param $destination url where we want to redirect the user.
	 * @return true if redirect must be done. */
	private function needRedirect($destination)
	{
		if( empty($destination) )
			return false;
		$cur = parse_url('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . "{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
		$dst = parse_url($destination);

		foreach( array('host', 'path', 'query', 'fragment') as $component )
		{
			if( isset($dst[$component]) )
			{
				if( !isset($cur[$component]) )
					return true;
				else if( $dst[$component] != $cur[$component] )
					return true;
			}
		}
		return false;
	}

	public function userRedirection($redirectTo, $user, $isLogged=true)
	{
		return $this->redirect($user, $isLogged, $redirectTo);
	}

	// get the redirection page for the given trigger (user+action).
	protected function redirect($user, $isLogin=true, $default='')
	{
		$redirect = '';
		$action = $isLogin ? 0 : 1;

		if( !is_null($user) && ($user !== false) && (get_class($user) == 'WP_User') )
		{
			// user specific
			$redirect = $this->getRedirection($this->findUser($user->ID, $action));

			if( strlen(trim($redirect)) == 0 )
			{
				// if none, check for roles
				foreach( $user->roles as $role )
				{
					$redirect = $this->getRedirection($this->findRole($role, $action));
					if( strlen(trim($redirect)) > 0 )
					{
						break;
					}
				}
			}
		}

		if( strlen(trim($redirect)) == 0 )
		{
			$redirect = $this->getRedirection($this->findDefault($action));
		}

		if( strlen(trim($redirect)) == 0 )
		{
			$redirect = $default;
		}
		return $redirect;
	}

	protected function getRedirection($redir)
	{
		if( $redir !== false )
		{
			if( !empty($redir) && is_numeric($redir) )
			{
				$post = get_post($redir);
				if( !is_null($post) )
					return $this->getLink($post);
				else
					return '';
			}
			else
				return $redir;
		}
		return '';
	}

	/** since we are too soon to have global $wp_rewrite set, we cannot use get_permalink() */
	protected function getLink($post)
	{
		$link = '';
		if( is_object( $post ) && !empty($post->ID) )
		{
			$link = home_url( '?page_id=' . $post->ID );
		}
		return $link;
	}

	public static function toKey($default=null,$role='',$userId=null, $login=null, $prefix=true)
	{
		$k = ($prefix ? 'lws_user_redir_' : '');
		if( !is_null($default) )
		{
			if( $default == true )
				$k .= '0_';
			else if( !empty($role) )
				$k .= '2_';
			else if( !is_null($userId) )
				$k .= '1_';
			else
				$k .= '\d_';
			if( is_null($login) )
				$k .= '\d';
			else
				$k .= esc_sql($login);

			if( $default == false )
			{
				if( !empty($role) )
					$k .= '_' . esc_sql($role);
				else if( !is_null($userId) && !empty($userId) )
					$k .= '_' . esc_sql($userId);
			}
		}
		return $k;
	}

	public static function fromKey($k, $prefix=true)
	{
		if( $prefix )
			$k = substr($k, strlen('lws_user_redir_'));
		$line = array();
		$line['id'] = $k;
		$line['type'] = substr($k, 0, 1);
		$line['action'] = substr($k, 2, 1);
		$line['role'] = '';
		if( $line['type'] == '2' )
			$line['role'] = htmlentities(substr($k, 4));
		$line['user_id'] = '';
		if( $line['type'] == '1' )
			$line['user_id'] = htmlentities(substr($k, 4));
		$line['page_id'] = '';
		$line['url'] = '';
		return $line;
	}

	/** @param $action (0:login, 1:logout)
	 * @return the redirection value (page_id or url) or false if not found. */
	protected function findUser( $userId, $action )
	{
		$k = self::toKey(false, '', $userId, $action);
		$val = get_user_meta($userId, $k, true);
		if( empty($val) )
			return false;
		else
			return $val;
	}

	/** @param $action (0:login, 1:logout)
	 * @return the redirection value (page_id or url) or false if not found. */
	protected function findRole( $role, $action )
	{
		$k = self::toKey(false, $role, null, $action);
		return get_option($k, false);
	}

	/** @param $action (0:login, 1:logout)
	 * @return the redirection value (page_id or url) or false if not found. */
	protected function findDefault( $action )
	{
		$k = self::toKey(true, '', null, $action);
		return get_option($k, false);
	}

}

?>
