<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * NestedSelectors
 *
 * @author Anthony Short
 * @dependencies None
 **/
class NestedSelectors extends Plugins
{
	
	/**
	 * The main processing function called by Scaffold. MUST return $css!
	 *
	 * @author Anthony Short
	 * @return $css string
	 */
	public static function parse()
	{
		$xml = CSS::to_xml();
		
		$css = "";
		
		foreach($xml->rule as $key => $value)
		{
			$css .= self::parse_rule($value);
		}

		CSS::$css = CSS::convert_entities('decode', $css);
	}
	
	/**
	 * Parse the css selector rule
	 *
	 * @author Anthony Short
	 * @param $rule
	 * @return return type
	 */
	public static function parse_rule($rule, $parent = '')
	{
		$css_string = "";
		$property_list = "";
		$parent = trim($parent);
	
		# Get the selector and store it away
		foreach($rule->attributes() as $type => $value)
		{
			$child = (string)$value;
			
			# if its NOT a root selector and has parents
			if($parent != "")
			{
				$parent = explode(",", $parent);
				
				foreach($parent as $parent_key => $parent_value)
				{
					$parent_value = trim($parent_value);
					$parent[$parent_key] = self::parse_selector($parent_value, $child);
				}
				
				$parent = implode(",", $parent);
			}
			
			# Otherwise it's a root selector
			else
			{
				$parent = $child;
			}
		}

		foreach($rule->property as $p)
		{
			$property = (array)$p->attributes(); 
			$property = $property['@attributes'];
			
			$property_list .= $property['name'].":".$property['value'].";";
		}
		
		# Just in case...
		if(!is_array($parent))
		{
			$css_string .= $parent . "{" . $property_list . "}";
		}

		foreach($rule->rule as $inner_rule)
		{
			$css_string .= self::parse_rule($inner_rule, $parent);
		}
		
		return $css_string;
	}
	
	/**
	 * Parses the parent and child to find the next parent
	 * to pass on to the function
	 *
	 * @author Anthony Short
	 * @param $parent
	 * @param $child
	 * @return string
	 */
	public static function parse_selector($parent, $child)
	{
		# If there are listed parents eg. #id, #id2, #id3
		if(strstr($child, ","))
		{
			$parent = self::split_children($child, $parent);
		}
		
		# If the child references the parent selector
		elseif (strstr($child, "#SCAFFOLD-PARENT#"))
		{						
			$parent = str_replace("#SCAFFOLD-PARENT#", $parent, $child);	
		}
		
		# Otherwise, do it normally
		else
		{
			$parent = "$parent $child";
		}
		
		return $parent;
	}
	
	/**
	 * Splits selectors with , and adds the parent to each
	 *
	 * @author Anthony Short
	 * @param $children
	 * @param $parent
	 * @return string
	 */
	public static function split_children($children, $parent)
	{
		$children = explode(",", $children);
												
		foreach($children as $key => $child)
		{
			# If the child references the parent selector
			if (strstr($child, "#SCAFFOLD-PARENT#"))
			{
				$children[$key] = str_replace("#SCAFFOLD-PARENT#", $parent, $child);	
			}
			else
			{
				$children[$key] = "$parent $child";
			}
		}
		
		return implode(",",$children);
	}

}