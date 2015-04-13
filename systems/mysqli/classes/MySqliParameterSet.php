<?php if (defined($inc = "MYSQLIPARAMETERSET_INCLUDED")) { return; } else { define($inc, true); }

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

  class MySqliParameterSet extends SqlParameterSet    // adds parameter sequentialization (for prepared statements)  
  {
    
    static function sequentialize_parameters( $query_string, $parameters )
    {
      $parameter_set = new static($parameters, $throw_on_missing = false);
      $data          = (object)array("query" => "", "parameter_order" => array(), "parameter_converters" => array(), "parameter_types" => array(), "has_literals" => false);
      $callback      = Callback::for_method($parameter_set, "sequentialize_parameter", $data)->get_php_callback();
      $data->query   = preg_replace_callback($parameter_set->parameter_pattern, $callback, $query_string);
    
      return $data;
    }
  
  
    function sequentialize_parameter( $data, $parts )
    {
      $name       = tree_fetch($parts, "name"      , "");
      $type       = tree_fetch($parts, "type"      , "");
      $comparison = tree_fetch($parts, "comparison", "");
      $operator   = tree_fetch($parts, "operator"  , "");   $operator = strtolower($operator);
      $not        = tree_fetch($parts, "not"       , "");   $not      = strtolower($not     );
      $format     = $comparison ? "$comparison%s" : "%s";
    
      empty($name) and abort("your parameter pattern must ensure a parameter name is present", "pattern", $this->parameter_pattern);
    
      if( $type == "literal" )
      {
        $data->has_literals = true;
        if( tree_has($this->parameters, $name) )
        {
          return sprintf("%s%s", $comparison, (string)$this->parameters[$name]);
        }
        else  // the parameter is not specified, and we deal with the situation appropriately
        {
          throw new SqlParameterMissingException($name);
        }
      }
      else
      {
        @list($type, $converter) = $this->determine_parameter_type($type, tree_fetch($this->parameters, $name));
        $data->parameter_order[]      = $name;
        $data->parameter_types[]      = $type;
        $data->parameter_converters[] = $converter ?: null;

        return sprintf("%s%s", $comparison, "?");
      }
    }
    

    function determine_parameter_type( $type, $exemplar )
    {
      if( empty($type) )
      {
        if( is_bool($exemplar) )
        {
          return "i";
        }
        elseif( is_integer($exemplar) and preg_match('/^\d+$/', "$value") )
        {
          return "i";
        }
        elseif( is_numeric($exemplar) and preg_match('/^\d*\.\d*$/', $value) )
        {
          return "d";
        }
        else
        {
          return "s";
        }
      }
      else
      {
        switch( $type )
        {
          case "time":
          case "datetime":
            return array("s", "format_time");

          case "date":
            return array("s", "format_date");

          case "bool":
          case "boolean":
            return "i";

          case "float":
          case "real":
            return "d";

          case "int":
          case "integer":
            return "i";
            
          default:
            return "s";
        }
      }
    }
    
  }