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

  
  // The Damage Engine database layer allows for a lot more freedom in parameterizing a statement
  // than does the mysqli database API:
  // * DE statements can parameterize table names and any other part of the query
  // * DE statements reference parameters by name, possibly with a type conversion, whereas 
  //   mysqli supports only ordered parameters
  //
  // The MysqliParameterizedStatement is used the MysqliConnection to convert DE parameterized 
  // statements into something the mysqli API can prepare and execute. Literal parameters are 
  // expanded in place, and all other parameters are collected into a sequence for use.

  class MysqliParameterizedStatement
  { 
    static function build( $sql, $parameter_set, $connection )
    {
      return new static($sql, $parameter_set, $connection);
    }
    
    
    
  //===============================================================================================
  // SECTION: Primary API
  
    public $sql        = "";
    public $parameters = array();
    public $bind_types = array();
  
    function __construct( $sql, $parameter_set, $connection )
    {
      if( $parameter_set == null or $parameter_set->get_parameter_count() == 0 )
      {
        $this->sql = $sql;
      }
      else
      {
        $scanner   = $connection->get_parameter_scanner();
        $processor = Callback::for_method($this, "sequentialize_parameter", $parameter_set, $connection);

        $this->sql = $scanner->process_parameters($sql, $processor);
      }
    }
    
    
    function bind_to( $statement )
    {
      if( $count = count($this->parameters) )
      {
        $s = $statement;
        $t = implode("", $this->bind_types);
        $p =& $this->parameters;
        
        switch( $count )
        {
          case 1: $s->bind_param($t, $p[0]); break;
          case 2: $s->bind_param($t, $p[0], $p[1]); break;
          case 3: $s->bind_param($t, $p[0], $p[1], $p[2]); break;
          case 4: $s->bind_param($t, $p[0], $p[1], $p[2], $p[3]); break;
          case 5: $s->bind_param($t, $p[0], $p[1], $p[2], $p[3], $p[4]); break;
          default:
            $args = array_merge(array($t), $this->parameters);
            Callback::do_call_with_array(Callback::for_method($s, "bind_param"), $args);
            break;
        }
      }
    }
    
  
  
  
  //===============================================================================================
  // SECTION: Internals

  
    function sequentialize_parameter( $parameter_set, $connection, $tokens )     // Internal callback, ultimately used by preg_replace_callback() to replace parameters in the base query; not for general use
    {
      $name       = tree_fetch($tokens, "name"      , "");
      $type       = tree_fetch($tokens, "type"      , "");
      $comparison = tree_fetch($tokens, "comparison", "");
      $operator   = tree_fetch($tokens, "operator"  , "");   $operator = strtolower($operator);
      $not        = tree_fetch($tokens, "not"       , "");   $not      = strtolower($not     );
      $format     = $comparison ? "$comparison%s" : "%s";
    
      empty($name) and abort("your parameter pattern must ensure a parameter name is present");
    
      if( !$parameter_set->has_parameter($name) )
      {
        Script::throw_exception("sql_parameter_set_missing_parameter", "parameter", $name);
      }
      elseif( $type == "literal" )
      {
        return sprintf("%s%s", $comparison, (string)$parameter_set->get_parameter($name));
      }
      else
      {
        $this->parameters[] = $value = $this->format_parameter($parameter_set->get_parameter($name), $type, $connection);
        $this->bind_types[] = $this->choose_bind_type($value, $type, $connection);
        
        return sprintf("%s%s", $comparison, "?");
      }
    }
    

    // For the most part, we leave data conversion up to the mysqli API. However, in the case
    // of explicit type requests (in the statement text), we may need to do extra work.
    
    function format_parameter( $value, $type, $connection )   
    {
      if( $type )
      {
        switch( $type )
        {
          case "time":
          case "datetime":
            return $connection->format_datetime_value($value);

          case "date":
            return $connection->format_date_value($value);
            
          case "string":
            return (string)$value;

          default:
            return $connection->format_value($value, $type, $allow_null = true);
        }
      }
      else
      {
        return $value;
      }
    }
    
    
    function choose_bind_type( $value, $type, $connection )
    {
      if( is_null($value) )
      {
        return "s";
      }
      elseif( !is_string($value) )
      {
        return is_float($value) ? "d" : "i";
      }
      else
      {
        return strlen($value) > 4096 ? "b" : "s";
      }
    }
    
  }