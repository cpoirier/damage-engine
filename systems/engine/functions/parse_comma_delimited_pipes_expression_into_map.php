<?php if (defined($inc = "ENGINE_PARSE_COMMA_DELIMITED_PIPES_EXPRESSION_INTO_MAP_INCLUDED")) { return; } else { define($inc, true); }

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

  require_once "parse_comma_delimited_pipes_expression.php";
  
  
  function parse_comma_delimited_pipes_expression_into_map( $expression, $labels, $key, $value, $strict = false )
  {
    $map = array();
    if( $data = parse_comma_delimited_pipes_parameter($expression, $labels, $strict) )
    {
      foreach( $data as $object )
      {
        if( property_exists($object, $key) && property_exists($object, $value) )
        {
          $map[$object->$key] = $object->$value;
        }
        elseif( $strict )
        {
          static::fail("unable_to_map_cdp_parameter");
        }
      }
    }
  
    return $map;
  }
  