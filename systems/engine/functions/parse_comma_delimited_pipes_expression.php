<?php if (defined($inc = "ENGINE_PARSE_COMMA_DELIMITED_PIPES_EXPRESSION_INCLUDED")) { return; } else { define($inc, true); }

  // Damage Engine Copyright 2012-2015 Massive Damage, Inc.
  //
  // Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except 
  // in compliance with the License. You may obtain a copy of the License at
  //
  //     http://www.apache.org/licenses/LICENSE-2.0
  //
  // Unless required by applicable law or agreed to in writing, software distributed under the License 
  // is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express 
  // or implied. See the License for the specific language governing permissions and limitations under 
  // the License.



  // return an array of stdObjects with properties named according to $labels, or 0-indexed if no labels supplied.
  // if $strict is true, throws a warning if an item lacks the a number of elements which matches the number of $labels

  function parse_comma_delimited_pipes_expression( $expression, $labels = null, $strict = false )  // Parses data of form '0|1|2|3,1|2|3|4'
  {
    $return_list = array();
    if( $expression )
    {
      $defaults = array();
      if( empty($labels) )
      {
        $labels = array();
      }
      elseif( $keys = array_keys($labels) and !is_numeric(array_shift($keys)) )
      {
        $pairs  = $labels;
        $labels = array();
        foreach( $pairs as $label => $default )
        {
          $labels[]   = $label;
          $defaults[] = $default;
        }
      }

      $label_count = count($labels);
      $comma_list  = explode(',', $expression);
      foreach( $comma_list as $item )
      {
        $s_obj = new stdClass();
        $elements = explode('|', $item);
        if(!$strict || $label_count == count($elements))
        {
          foreach($elements as $index => $element)
          {
            if(isset($labels[$index]))
            {
              $s_obj->$labels[$index] = array_key_exists($index, $defaults) ? TypeConverter::coerce_type($element, $defaults[$index]) : $element;
            }
            else
            {
              $s_obj->$index = $element;
            }
          }
          $return_list[] = $s_obj;
        }
        else
        {
          trigger_error("Incorrect number of parsed elements with strict true: '$item'", E_USER_WARNING);
        }
      }
    }
    return $return_list;
  }
