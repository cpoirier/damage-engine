<?php if (defined($inc = "CORE_SQLDATABASECONNECTION_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class SqlDatabaseConnection
  {
    protected $schema_name;
    
    function __construct( $schema_name )
    {
      $this->schema_name = $schema_name;      // We dump the SqlDatabaseConnector lots; best not to have the connection details as part of the dump
    }
    
    function query( $query /*, parameter pairs...*/ )
    {
      abort("override this to return a ResultsSet (always!)");
    }
    
    function execute( $query /*, parameter pairs...*/ )
    {
      abort("override this to return a count of records affected");
    }
    
    function execute_and_return_id( $query /*, parameter pairs...*/ )
    {
      abort("override this to execute a statement that returns an ID");
    }
    
    function get_table_structure( $table_name )
    {
      abort("override this to return a SqlTable object for the named table");
    }
    
    function get_schema_epoch()
    {
      abort("override this to return the time_t the database schema was last changed");
    }
        
    function rollback()
    {
      abort("override this to rollback the current transaction");
    }
    
    function commit()
    {
      abort("override this to commit the current transaction (and automatically start the next)");
    }
    
    
    
    
    
  //===============================================================================================
  // SECTION: Convenience routines
  
    
    function query_first( $query /* parameters */ )
    {
      $parameters = array_pair_slice(func_get_args(), 1);
      $results    = $this->query($query, $parameters);
      
      return $results->get_first();
    }
    
    
    function query_value( $field, $default, $query /* parameters... */ )
    {
      $parameters = array_pair_slice(func_get_args(), 3);
      $results    = $this->query($query, $parameters);
      
      return $results->get_first_value($field, $default);
    }

      
    function query_exists( $query /* parameters... */ )
    {
      $parameters = array_pair_slice(func_get_args(), 1);
      $results    = $this->query($query, $parameters);
      
      return $results->has_results();
    }
    
    
    function query_all( $query /* parameters... */ )
    {
      $parameters = array_pair_slice(func_get_args(), 1);
      $results    = $this->query($query, $parameters);
      
      return $results->as_list();
    }


    function query_column( $field, $query /* parameters... */ )
    {
      $parameters = array_pair_slice(func_get_args(), 2);
      $results    = $this->query($query, $parameters);
      
      return $results->as_list($field);
    }
    
    
    function query_map( $key_fields, $value_fields, $query /* parameters...*/ )
    {
      $parameters = array_pair_slice(func_get_args(), 3);
      $results    = $this->query($query, $parameters);
      
      return $results->as_map($key_fields, $value_fields);
    }

    
    function query_tree( $program, $query /* parameters...*/ )
    {
      $parameters = array_pair_slice(func_get_args(), 2);
      $results    = $this->query($query, $parameters);
      
      return $results->as_tree($program);
    }
    
    
    
  //===============================================================================================
  // SECTION: Parameter expansion
  
    function format_value( $value, $type = null, $allow_null = false, $default = null )
    {
      if( is_null($value) )
      {
        if( $allow_null )
        {
          return "null";
        }
        else
        {
          $value = $default;
        }
      }
  
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
          return is_null($value) ? 0.0 : $value;  // we don't reformat float/decimal data, as it it might lose precision
        }
        else
        {
          return $this->format_string_value($value);
        }
      }
      else
      {
        switch( $type )
        {
          case "time":
          case "datetime":
            return $this->format_string_value($this->format_time_value($value));

          case "date":
            return $this->format_string_value($this->format_date_value($value));

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
            return $this->format_string_value($value);
        }
      }
  
      abort("should not be reachable");
    }

  
  
    function format_string_value( $value )
    {
      return sprintf("'%s'", $this->escape_string_value($value));
    }
    

    function escape_string_value( $value )
    {
      $from = array("\\"  , "'"  , "\""  , "\0" , "\b" , "\n" , "\r" , "\t" );
      $to   = array("\\\\", "\\'", "\\\"", "\\0", "\\b", "\\n", "\\r", "\\t");
  
      return str_replace($from, $to, (string)$value);
    }
    
    
    function format_time_value( $time = null )
    {
      if( preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $time) )
      {
        return $time;
      }
      else
      {
        is_null($time)    and $time = time();
        is_numeric($time)  or $time = strtotime($time);
        is_null($time)    and $time = time();

        return date("Y-m-d H:i:s", $time);
      }
    }


    function format_datetime_value( $time = null )
    {
      return $this->format_time_value($time);
    }


    function format_date_value( $time = null )
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
    
  
    function unformat_value( $value, $type )
    {
      switch( $type )
      {
        case "time":
        case "datetime":
          return strtotime($value);

        case "date":
          return strtotime($value);

        case "bool":
        case "boolean":
          return $value ? true : false;

        case "float":
        case "real":
          return (float)$value;

        case "int":
        case "integer":
          return (int)$value;
          
        default:
          return $value;
      }
    }
    
    

  //===============================================================================================
  // SECTION: Machinery

    function get_schema()
    {
      return SqlSchema::fetch($this->schema_name);
    }
  
  
    function get_connector()
    {
      return $this->get_schema()->get_connector();
    }
  
  
    function get_parameter_scanner()
    {
      return new SqlParameterScanner();
    }


    function build_parameter_set( $parameters )
    {
      return SqlParameterSet::build($parameters, $this);
    }

  
    function __sleep()     // Intentionally not serializable
    {
      return array();
    }
  
    
  }