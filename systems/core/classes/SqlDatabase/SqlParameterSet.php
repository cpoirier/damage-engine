<?php if (defined($inc = "CORE_SQLPARAMETERSET_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class SqlParameterSet
  {
    static function build( $parameters, $connection, $throw_on_missing = false )
    {
      if( is_object($parameters) and is_a($parameters, "SqlParameterSet") )
      {
        if( $parameters->connection == $connection )
        {
          return $parameters;
        }
        else
        {
          return new static($parameters->parameters, $connection, $throw_on_missing);
        }
      }
      else
      {
        return new static(array_pair($parameters), $connection, $throw_on_missing);
      }
    }
    
    
    static function process( $sql_string, $parameters, $connection, $throw_on_missing = false )
    {
      $set = static::build($parameters, $connection, $throw_on_missing);
      return $set->expand_into($sql_string);
    }
    

    
    
  //===============================================================================================
  // SECTION: Externals
  
    function __construct( $pairs, $connection, $throw_on_missing = false )
    {
      $this->parameters       = $pairs;
      $this->connection       = $connection;
      $this->throw_on_missing = $throw_on_missing;
    }
    
    
    function get_parameter_count()
    {
      return count($this->parameters);
    }
    
    
    function has_parameter( $name )
    {
      return array_has_member($this->parameters, $name);
    }
    
    
    function get_parameter( $name, $default = null )
    {
      return tree_fetch($this->parameters, $name, $default);
    }
    
    
    function expand_into( $sql_string )
    {
      $scanner = $this->connection->get_parameter_scanner();
      return $scanner->process_parameters($sql_string, Callback::for_method($this, "expand_parameter"));
    }
    
    
    
    
    
  //===============================================================================================
  // SECTION: Internals
  
    function expand_parameter( $parts )     // Callback for SqlParameterScanner::process_parameters to expand a single parameter into a query string
    {
      $name       = tree_fetch($parts, "name"      , "");
      $type       = tree_fetch($parts, "type"      , "");
      $comparison = tree_fetch($parts, "comparison", "");
      $operator   = tree_fetch($parts, "operator"  , "");   $operator = strtolower($operator);
      $not        = tree_fetch($parts, "not"       , "");   $not      = strtolower($not     );
      $format     = $comparison ? "$comparison%s" : "%s";
    
      empty($name) and abort("your parameter pattern must ensure a parameter name is present", "pattern", $this->parameter_pattern);
    
      if( tree_has($this->parameters, $name) )
      {
        $value = tree_fetch($this->parameters, $name);
      
        if( $operator == "like" )
        {
          return $this->format_like_clause($value, $type, !empty($not));
        }
        elseif( $operator == "in" )
        {
          return $this->format_in_clause($value, $type, !empty($not));
        }
        elseif( is_null($value) )
        {
          if( $comparison )
          {
            return $this->format_null_comparison($operator, !empty($not) || in_array($operator, array("!=", "<>", "<", ">")));
          }
          else
          {
            return "null";
          }
        }
        elseif( is_array($value) )
        {
          return $this->format_in_clause($value, $type, in_array($operator, array("!=", "<>", "<", ">")));
        }
        else
        {
          return sprintf($format, $this->connection->format_value($value, $type));
        }
      }
      else  // the parameter is not specified, and we deal with the situation appropriately
      {
        $this->throw_on_missing and Script::throw_exception("sql_parameter_set_missing_parameter", "parameter", $name);
        return sprintf($format, "''");
      }
    }

  
  
  
  
  //===============================================================================================
  // SECTION: Formatters (built for MySQL; override as necessary)
  
    function format_in_clause( $value, $type, $not_in = false )
    {
      $set = array();
      foreach( (array)$value as $element )
      {
        if( !is_null($element) )
        {
          $set[] = $this->connection->format_value($element, $type);
        }
      }
  
      if( empty($set) )
      {
        return $this->format_null_comparison("in", $not_in);
      }
      else
      {
        $comparison = $not_in ? "not in" : "in";
        return sprintf("$comparison (%s)", implode(", ", $set));
      }
    }


    function format_null_comparison( $operator, $not = false )
    {
      if( $operator == "!=" or $operator == "<>" or $not )
      {
        return "is not null";
      }
      else
      {
        return "is null";
      }
    }


    function format_like_clause( $value, $type, $not_like = false )
    {
      $comparison = $not_like ? "not like" : "like";
    
      if( is_null($value) )
      {
        return $this->format_null_comparison("like", $not_like);
      }
      elseif( $type == "like" or substr($type, 0, 5) == "like:" )   // a format string was provided
      {
        $format = substr($type, 5) ?: "%s";
        $from   = array("%"  , "_"  );
        $to     = array("\\%", "\\_");
        $string = str_replace($from, $to, $this->connection->escape_string_value($value));
      
        return sprintf("$comparison '$format'", $string);
      }
      else                                                          // no format string was provided, so use the string verbatim
      {
        return sprintf("$comparison %s", $this->connection->format_string_value($value));
      }
    
    }
  
  
    
  
  
  
  
    
    
  }