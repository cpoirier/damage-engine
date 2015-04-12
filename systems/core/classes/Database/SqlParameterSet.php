<?php if (defined($inc = "CORE_SQLSTATEMENT_INCLUDED")) { return; } else { define($inc, true); }

  // Damage Engine Copyright 2012-2015 Massive Damage, Inc.
  // Based on work Copyright 2011 1889 Labs
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
    public $parameters;
    public $throw_on_missing;
    public $parameter_pattern;
    
    function __construct( $parameters, $throw_on_missing = false, $parameter_pattern = null )
    {
      $this->parameters        = $parameters;
      $this->throw_on_missing  = $throw_on_missing;
      $this->parameter_pattern = $parameter_pattern ?: '/(?P<comparison>(?P<not>\bnot\s+)?(?P<operator>in|like|=|==|!=|<>|<=|>=|>|<)\s+)?{(?P<name>\w+)(?::(?P<type>[^}]+))?}/i';
    }
    
    
    
  //===============================================================================================
  // SECTION: Parameter expansion
  
  
    static function expand_parameters( $query_string, $parameters, $throw_on_missing = false )    // Used by SqlStatement to expand all parameters in a query
    {
      $parameter_set = new static($parameters, $throw_on_missing);
      $callback      = Callback::for_method($parameter_set, "expand_parameter")->get_php_callback();
      $query         = preg_replace_callback($parameter_set->parameter_pattern, $callback, $query_string);
      
      return $query;
    }
    
    
    function expand_parameter( $parts )     // Used by expand_parameters (via preg_replace_callback) to expand a single parameter into a query string
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
          return sprintf($format, $this->format_parameter($value, $type));
        }
      }
      else  // the parameter is not specified, and we deal with the situation appropriately
      {
        if( $this->throw_on_missing )
        {
          throw new SqlParameterMissingException($name);
        } 
        else
        { 
          return sprintf($format, "''");
        }
      }
    }
    
    
    
    
  //===============================================================================================
  // SECTION: Formatters (built for MySQL; override as necessary)
    
    function format_parameter( $value, $type = null )
    {
      if( empty($type) )
      {
        if( is_bool($value) )
        {
          return $value ? 1 : 0;
        }
        elseif( is_integer($value) and preg_match('/^\d+$/', "$value") )
        {
          return (int)$value;
        }
        elseif( is_numeric($value) and preg_match('/^\d*\.\d*$/', $value) )
        {
          return $value;  // we don't reformat float/decimal data, as it it might lose precision
        }
        else
        {
          return $this->format_string_parameter($value);
        }
      }
      else
      {
        switch( $type )
        {
          case "time":
          case "datetime":
            return $this->format_string_parameter($this->format_time($value));

          case "date":
            return $this->format_string_parameter($this->format_date($value));

          case "bool":
          case "boolean":
            return ($value ? 1 : 0);

          case "literal":
            return $value;

          case "float":
          case "real":
            return (float)$value;

          case "int":
          case "integer":
            return (int)$value;
            
          default:
            return $this->format_string_parameter($value);
        }
      }
      
      abort("should be unreachable");
    }
    
    
    function format_string_parameter( $value )
    {
      return sprintf("'%s'", $this->format_string($value));
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
        $string = str_replace($from, $to, $this->format_string($value));
        
        return sprintf("$comparison '$format'", $string);
      }
      else                                                          // no format string was provided, so use the string verbatim
      {
        return sprintf("$comparison %s", $this->format_string_parameter($value));
      }
      
    }
  
    
    function format_in_clause( $value, $type, $not_in = false )
    {
      $set = array();
      foreach( (array)$value as $element )
      {
        if( !is_null($element) )
        {
          $set[] = $this->format_parameter($element, $type);
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
    
    
    function format_time( $time = null )
    {
      if( preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $time) )
      {
        return $time;
      }
      else
      {
        is_string($time) and $time = strtotime($time);
        is_null($time) and $time = time();
        return date("Y-m-d H:i:s", $time);
      }
    }


    function format_datetime( $time = null )
    {
      return $this->format_time($time);
    }


    function format_date( $time = null )
    {
      if( preg_match('/\d{4}-\d{2}-\d{2}/', $time) )
      {
        return $time;
      }
      else
      {
        is_string($time) and $time = strtotime($time);
        is_null($time) and $time = time();
        return date("Y-m-d", $time);
      }
    }


    function format_string( $value )
    {
      $from = array("\\"  , "'"  , "\""  , "\0" , "\b" , "\n" , "\r" , "\t" );
      $to   = array("\\\\", "\\'", "\\\"", "\\0", "\\b", "\\n", "\\r", "\\t");
      
      return str_replace($from, $to, (string)$value);
    }
    
  }
