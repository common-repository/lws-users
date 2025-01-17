<?php
namespace LWS\Adminpanel\Pages\Field;
if( !defined( 'ABSPATH' ) ) exit();


/** extra is an array of value=>text for each option */
class Radio extends \LWS\Adminpanel\Pages\Field
{
	public function input()
	{
		$name = $this->m_Id;
		$value = get_option($name, false);

		if( $value === false )
		{
			if( $this->hasExtra('value') )
				$value = $this->extra['value'];
			else if( $this->hasExtra('default') )
				$value = $this->extra['default'];
		}

		echo "<div class='{$this->style}-radio-group'>";
		foreach( (($this->hasExtra('options') && is_array($this->extra['options'])) ? $this->extra['options'] : $this->extra) as $key => $opt )
		{
			$checked = ($key == $value ? 'checked' : '');
			echo "<label><input class='{$this->style}' type='radio' name='$name' value='$key' $checked> $opt</label><br/>";
		}
		echo "</div>";
	}
}

?>
