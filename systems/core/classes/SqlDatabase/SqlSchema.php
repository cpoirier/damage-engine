<?php if (defined($inc = "CORE_SQLSCHEMA_INCLUDED")) { return; } else { define($inc, true); }

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


  // Provides user-specific access to a particular SQL database. Master provider for: 
  //  * SqlDatabaseConnector
  //  * SqlTable
  //  * SqlQuery (on tables)
  //
  // Use define() and fetch() to access schemas by name. Needed to avoid creating object
  // trees and memory cycles in the schema data structures.

  class SqlSchema
  {
    protected static $schemas = null;
    
    static function define( $name, $connector )
    {
      return static::register(new static($name, $connector));
    }
    
    static function register( $schema )
    {
      $name = $schema->get_name();
      
      is_array(static::$schemas) or static::$schemas = array();
      tree_has(static::$schemas, $name) and throw_exception("sql_schema_already_defined", "name", $name);
      
      static::$schemas[$name] = $schema;
      return $schema;
    }
    
    static function fetch( $name )
    {
      $schema = (is_string($name) ? array_fetch_value(static::$schemas, $name) : $name) or Script::throw_exception("sql_schema_not_defined", "name", $name);
      return $schema;
    }




  //===============================================================================================
  // SECTION: Basics
  
    function __construct( $name, $connector )
    {
      $this->name         = $name;
      $this->connector    = $connector;
      $this->tables       = array();
      $this->base_queries = array();
      $this->schema_epoch = 0;
    }
    
    function get_name()
    {
      return $this->name;
    }
    
    function get_engine()
    {
      return $this->connector->get_engine();
    }
    
    function get_connector()
    {
      return $this->connector;
    }

  
  

  //===============================================================================================
  // SECTION: Connection
  
    function connect_for_writing( $throw_on_failure = null )
    {
      return $this->connect($for_writing = true, $throw_on_failure);
    }

    function connect_for_reading( $throw_on_failure = null )
    {
      return $this->connect($for_writing = false, $throw_on_failure);
    }

    function connect( $for_writing = true, $throw_on_failure = null )
    {
      $this->connector->connect($for_writing, $throw_on_failure);
    }




  //===============================================================================================
  // SECTION: Structure information
  
    
    function get_schema_epoch( $connection = nil )
    {
      if( !$this->schema_epoch )
      {
        if( $connection or $connection = $connector->connect_for_reading() )
        {
          $this->schema_epoch = $connection->get_schema_epoch();
        }
      }
    
      return $this->schema_epoch;
    }


    function get_table( $name, $connection = null )
    {
      $table = null;
      
      if( array_key_exists($name, $this->tables) )
      {
        $table = $this->tables[$name];
      }
      else
      {
        $cache_key    = sprintf("sql_table(%s, %s)", $this->name, $name);
        $schema_epoch = $this->get_schema_epoch($connection);
        $table        = Script::get_from_system_cache($this->cache_key, $this->schema_epoch);
        
        if( !$table )
        {
          if( $connection or $connection = $this->connect_for_reading() )
          {
            if( $table = $connection->get_table_structure($name) )
            {
              Script::set_to_system_cache($cache_key, $table);
              $this->tables[$name] = $table;
            }
          }
        }
      }

      return $table;
    }
    
    
    function get_table_or_fail( $name, $connection = null )
    {
      $table = $this->get_table($name, $connection) or throw_exception("sql_schema_table_undefined", "name", $name);
      return $table;
    }


    function get_base_query( $name, $connection = null )
    {
      $base_query = null;
      
      if( array_key_exists($name, $this->base_queries) )
      {
        $base_query = $this->base_queries[$name];
      }
      else
      {
        $cache_key    = sprintf("sql_table_query(%s, %s)", $this->name, $name);
        $schema_epoch = $this->get_schema_epoch($connection);
        $base_query   = Script::get_from_system_cache($this->cache_key, $this->schema_epoch);

        if( !$base_query and $table = $this->get_table($name, $connection) )
        {
          if( $base_query = $table->to_query() )
          {
            Script::set_to_system_cache($cache_key, $base_query);
            $this->base_queries[$name] = $base_query;
          }
        }
      }
      
      return $base_query;
    }
    
    
    function get_base_query_or_fail( $name, $connection = null )
    {
      $base_query = $this->get_base_query($name, $connection) or throw_exception("sql_schema_table_undefined", "name", $name);
      return $table;
    }




  //===============================================================================================
  // SECTION: Internals

    function __sleep()    // This class is intentionally not serializable. 
    {
      return array();
    }
  }
