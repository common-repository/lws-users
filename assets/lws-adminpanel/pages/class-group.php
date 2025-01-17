<?php
namespace LWS\Adminpanel\Pages;
if( !defined( 'ABSPATH' ) ) exit();
if( !class_exists("\\" . __NAMESPACE__ . "\\Group") ) :

require_once dirname( __FILE__ ).'/class-field.php';
require_once dirname( __FILE__ ).'/../editlist.php';

/**  */
class Group
{
	private $m_FieldArray = array();

	/** @param $data fulfill the Group::format */
	function __construct($data, $page)
	{
		$this->page = $page;
		$this->id = isset($data['id']) ? $data['id'] : '';
		$this->title = $data['title'];
		$this->helpBanner = isset($data['text']) ? $data['text'] : '';
		$this->customBehavior = isset($data['function']) ? $data['function'] : null;
		$this->customDelayedBehavior = isset($data['delayedFunction']) ? $data['delayedFunction'] : null;
		$this->editlist = isset($data['editlist']) ? $data['editlist'] : null;

		if( isset($data['fields']) )
		{
			foreach($data['fields'] as $field)
			{
				if( \LWS\Adminpanel\Pages::test($field, self::fieldFormat(), "$page ... fields") )
					$this->addField($field);
			}
		}
	}

	/** @return a well formated format array for Pages::test()
	 * @see Pages::test() */
	public static function format()
	{
		return array(
			'title'			=> \LWS\Adminpanel\Pages::format('title',			false, 'string', "Display a group title."),
			'id'				=> \LWS\Adminpanel\Pages::format('id',				true, 'string', "Identify a group"),
			'rights'		=> \LWS\Adminpanel\Pages::format('rights',		true, 'string', "User capacity required to access to this group. Usually 'manage_options'. A tab could be locally more restrictive"),
			'text'			=> \LWS\Adminpanel\Pages::format('text',			true, 'string', "A free text displayed at top of the group, after the title."),
			'fields'		=> \LWS\Adminpanel\Pages::format('fields',		true, 'array', "Option fields"),
			'editlist'	=> \LWS\Adminpanel\Pages::format('editlist',	true, 'LWS\Adminpanel\EditList', "An editlist instance"),
			'function'	=> \LWS\Adminpanel\Pages::format('function',	true, 'callable', "A function to echo a custom feature."),
			'delayedFunction'	=> \LWS\Adminpanel\Pages::format('delayedFunction',	true, 'callable', "Same as function but executed after usual fields display."),
		);
	}

	/** @return a well formated format array for Pages::test()
	 * @see Pages::test() */
	public static function fieldFormat()
	{
		return array(
			'id'		=> \LWS\Adminpanel\Pages::format('id',		false, 'string', "used with update_option and get_option."),
			'type'	=> \LWS\Adminpanel\Pages::format('type',	false, 'string', "A known field type."),
			'title'	=> \LWS\Adminpanel\Pages::format('title',	true, 'string', "Field title."),
			'extra'	=> \LWS\Adminpanel\Pages::format('extra',	true, 'array', "type specific."),
		);
	}

	public function addField($data)
	{
		$extra = isset($data['extra']) ? $data['extra'] : array();
		$id = isset($data['id']) ? $data['id'] : '';
		$title = isset($data['title']) ? $data['title'] : '';
		$f = Field::create(strtolower($data['type']), $id, $title, $extra);

		if( !is_null($f) )
			$this->m_FieldArray[] = $f->register($this->page);
		return $f;
	}

	public function title($maxlen=0, $etc='...')
	{
		if( $maxlen <= 0 || strlen($this->title) <= $maxlen )
			return $this->title;
		else
			return substr($this->title, 0, ($maxlen - strlen($etc))) . $etc;
	}

	public function targetId()
	{
		return 'lws_group_targetable_' . $this->id;
	}

	public function titleId()
	{
		return 'lws_group_title_' . $this->id;
	}

	protected function simpleTitle()
	{
		$ttlid = $this->titleId();
		if( empty($this->title) )
			$str = "<div id='$ttlid'></div>";
		else
			$str = "<h2><div id='$ttlid' class='lws-simple-title'><p class='lws-simple-title-text'>{$this->title}</p></div></h2>";
		return $str;
	}

	private static function mainspace($classname)
	{
		$path = explode("\\", __NAMESPACE__);
		$path[count($path)-1] = $classname;
		return implode("\\", $path);
	}

	/** echo the group content */
	public function eContent()
	{
		echo $this->simpleTitle();

		$txtid = $this->targetId();
		echo "<div class='lws-form-div' id='{$txtid}'>";
		if( !empty($this->helpBanner) )
			echo "<p class='lws-group-descr'><span class='lws-blue-icon lws-icon-lw_help'></span>{$this->helpBanner}</p>";

		if( $this->customBehavior != null && is_callable($this->customBehavior) )
			call_user_func( $this->customBehavior, $this->id );

		echo "<table class='form-table'>";
		foreach( $this->m_FieldArray as $field )
		{
			if( !$field->isHidden() )
			{
				$help = $field->help();
				if( !empty($help) )
					echo "<tr><td colspan='2'><div class='lws-field-help'><span class='lws-blue-icon lws-icon-lw_help'></span>{$help}</div></td></tr>";

				echo "<tr>";
				$id = esc_attr($field->id());

				$colspan = '';
				if( !empty($field->title()) )
				{
					$label = $field->label();
					echo "<th scope='row'><label for='$id'>$label</label></th>";
				}
				else
					$colspan = " colspan='2'";

				echo "<td$colspan>";
				$field->input();
				echo "</td></tr>";
			}
			else
				$field->input();
		}
		echo "</table>";

		if( !is_null($this->editlist) && is_a($this->editlist, self::mainspace('EditList')) )
			$this->editlist->display();

		if( $this->customDelayedBehavior != null && is_callable($this->customDelayedBehavior) )
			call_user_func( $this->customDelayedBehavior, $this->id );

		echo "</div>";
	}

	public function hasFields($excludeGizmo=false)
	{
		if( $excludeGizmo )
		{
			foreach( $this->m_FieldArray as $f )
			{
				if( !$f->isGizmo() )
					return true;
			}
			return false;
		}
		else
			return !empty($this->m_FieldArray);
	}

	public function mergeFields(&$fields)
	{
		foreach( $this->m_FieldArray as $f )
			$fields[] = $f;
	}

}

endif
?>