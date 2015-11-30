<?php if (defined($inc = "CORE_SQLTABLE_INCLUDED")) { return; } else { define($inc, true); }

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

  

  // Captures the definition of a table and provides services thereon.

  class SqlTable
  {
    function __construct( $name, $schema_name = null )   // We avoid memory cycles by using name-based lookup for the schema
    {
      $this->name        = $name;
      $this->schema_name = $schema_name;
      $this->fields      = array();
      $this->checks      = array();      // list of checks, first element of each is a field or list of fields, second is check name
      $this->keys        = array();
      $this->snake_name  = Symbol::convert_pascal_to_snake_case($name);
    }
    
    function get_schema()
    {
      return SqlSchema::fetch($this->schema_name);
    }
    
    function get_engine()
    {
      return $this->get_schema()->get_engine();
    }
    
    
    function format_record( $values )
    {
      $values = array_pair(func_get_args());
      $record = array();
      
      foreach( $this->fields as $name => $field )
      {
        $record[$name] = $field->format_value(tree_fetch($values, $name));
      }
      
      return $record;
    }
    
    
    function unformat_record( $record )
    {
      $values = array();
      foreach( $this->fields as $name => $field )
      {
        $values[$name] = $field->unformat_value(tree_fetch($record, $name));
      }
      
      return $values;
    }
    
    
    
    
  //===============================================================================================
  // SECTION: Field information
  
  
    function get_field_names()
    {
      return array_keys($this->fields);
    }
  
  
    function get_field( $name )
    {
      return tree_fetch($this->fields, $name);
    }
  
  
    function has_field( $name )
    {
      return tree_has($this->fields, $name);
    }




  //===============================================================================================
  // SECTION: Definition
  
  
    function define_field( $name, $type, $default, $allow_null )
    {
      return $this->register_field(new SqlTableField($name, $type, $allow_null, $default));
    }
    
    
    function register_field( $field )
    {
      tree_has($this->fields, $field->name) and throw_exception("sql_table_field_already_defined", "name", $field->name);

      $field->table_name  = $this->table_name;
      $field->schema_name = $this->schema_name;
      $this->fields[$field->name] = $field;
      
      return $field;
    }
    
    
  
  
  
  














    function get_pk_field_names()
    {
      return empty($this->keys) ? $this->get_field_names() : $this->keys[0];
    }

    function add_check( /* $field, $check, ... */ )
    {
      $args = func_get_args();
      $check = $args[1];
      if( method_exists($this, "check_$check") )
      {
        $this->checks[] = $args;

        if( $args[1] == "unique" )
        {
          $fields = $args[0];
          $this->keys[] = is_array($fields) ? $fields : array($fields);
        }
      }
      else
      {
        trigger_error("Table check $check not defined", E_USER_ERROR);
      }
    }

    function add_custom_filter( $callback )
    {
      $this->custom_filters[] = $callback;
    }

    function has_field( $name )
    {
      return array_key_exists($name, $this->field_defaults);
    }
  
    function is_nullable( $name )
    {
      return $this->has_field($name) && $this->nulls[$name];
    }

    function pick_fields_from( $map )
    {
      $selected = array();
      foreach( $map as $field => $value )
      {
        if( array_key_exists($field, $this->field_defaults) )
        {
          $selected[$field] = $map[$field];
        }
      }

      return $selected;
    }

    function do_fields_cover_key( $fields )
    {
      if( empty($this->keys) )
      {
        $key = $this->get_field_names();
        return count(array_intersect($key, $fields)) == count($key) ? $key : false;
      }
      else
      {
        foreach( $this->keys as $key )
        {
          if( count(array_intersect($key, $fields)) == count($key) )
          {
            return $key;
          }
        }

        if( $this->id_is_autoincrement )
        {
          return array($this->id_field);
        }
      }

      return false;
    }


  //===============================================================================================
  // SECTION: Query generation.

    function to_query()
    {
      if( !$this->query )
      {
        $this->query = new SQLQuery($this);
      }

      return $this->query;
    }

    function get_insert_statement( $db, $fields, $replace = false )
    {
      return SQLInsert::build_sql($this->name($db), $fields, $db, $replace);
    }

    function get_replace_statement( $db, $fields )
    {
      return $this->get_insert_statement($db, $fields, $replace = true);
    }

    function get_delete_statement( $db, $criteria )
    {
      return SQLDelete::build_sql($this->name($db), $criteria, $db);
    }

    function get_update_statement( $db, $fields, $criteria, $no_empty_criteria = false )
    {
      return SQLUpdate::build_sql($this->name($db), $fields, $criteria, $db, $no_empty_criteria);
    }

    function get_set_statement( $db, $fields, $key_names )
    {
      return SQLSet::build_sql($this->name($db), $fields, $key_names, $db);
    }





  //===============================================================================================
  // SECTION: Filters.

    function filter_to_string ( $value, $parameters ) { return "$value";        }
    function filter_to_boolean( $value, $parameters ) { return (bool)$value;    }
    function filter_to_real   ( $value, $parameters ) { return is_numeric($value) ? $value : (float)$value; }
    function filter_to_integer( $value, $parameters ) { return (integer)$value; }
    function filter_to_time   ( $value, $parameters ) { return $value;          }

    function filter_to_nullable_boolean( $value, $parameters ) { return is_null($value) ? $value : $this->filter_to_boolean($value, null); }
    function filter_to_nullable_integer( $value, $parameters ) { return is_null($value) ? $value : $this->filter_to_integer($value, null); }

    function filter_to_date( $value, $parameters )
    {
      if( preg_match('/\d{4}-\d{2}-\d{2}/', $value) )
      {
        return $value;
      }
      else
      {
        $time = is_numeric($value) ? $value : strtotime($value);
        return date("Y-m-d", $time);
      }
    }

    function filter_to_datetime( $value, $parameters )
    {
      if( preg_match('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $value) )
      {
        return $value;
      }
      else
      {
        $time = is_numeric($value) ? $value : strtotime($value);
        return date("Y-m-d H:i:s", $time);
      }
    }

    function filter_empty_to_null( $value, $parameters )
    {
      return empty($value) ? null : $value;
    }

    function filter_epoch_to_null( $value, $parameters )
    {
      return ($value == 0 || (is_string($value) && @strtotime($value) == 0)) ? null : $value;
    }

    function filter_epoch_to_now( $value, $parameters )
    {
      return ($value == 0 || (is_string($value) && @strtotime($value) == 0)) ? date("Y-m-d H:i:s") : $value;
    }

    function filter_null_to_epoch( $value, $parameters )
    {
      return is_null($value) ? date("Y-m-d H:i:s", 0) : $value;
    }
  

    function filter_to_lowercase( $value, $parameters ) { return strtolower($value); }
    function filter_to_uppercase( $value, $parameters ) { return strtoupper($value); }
    function filter_to_ucwords  ( $value, $parameters ) { return ucwords($value);    }

    function filter_one_of( $value, $parameters )
    {
      $options = $parameters[0];
      return in_array($value, $options) ? $value : $options[0];
    }

    function filter_real_to_decimal( $value, $parameters )
    {
      $right = $parameters[0];
      return sprintf("%." . $right . "F", $value);
    }



  //===============================================================================================
  // SECTION: Checks.

    function check_read_only( $record, $subject, $db, $parameters )
    {
      return false;
    }

    function check_not_null( $record, $subject, $db, $parameters )
    {
      return array_key_exists($subject, $record) && !is_null($record[$subject]);
    }

    function check_not_empty( $record, $subject, $db, $parameters )
    {
      return !empty($record[$subject]);
    }

    function check_not_epoch( $record, $subject, $db, $parameters )
    {
      return !($record[$subject] == 0 || $record[$subject] == "1969-12-31" || $record[$subject] == "1969-12-31 19:00:00");
    }

    function check_unique( $record, $subject, $db, $parameters )
    {
      return true;

      // Removed by Chris Poirier on Apr 25, 2012: due to changes in the overall Table code,
      // (we no longer require every table have an ID field), this code will no longer work.
      //
      // $criteria = array();
      // if( is_array($subject) )
      // {
      //   $fields = $subject;
      //   foreach( $fields as $field )
      //   {
      //     $criteria[] = $db->format("`$field` = ?", $record[$field]);
      //   }
      // }
      // else
      // {
      //   $field = $subject;
      //   $criteria[] = $db->format("`$field` = ?", $record[$field]);
      // }
      //
      // $table = $this->name($db);
      // $found = $db->query_first("SELECT * FROM $table WHERE " . implode(" and ", $criteria));
      //
      // if( $found )
      // {
      //   if( $this->id_field && array_key_exists($this->id_field, $record) )
      //   {
      //     return $record[$this->id_field] = $found[$this->id_field];
      //   }
      //   else
      //   {
      //     trigger_error("NYI: how do we check unique without an id field?", E_USER_ERROR);
      //   }
      // }
      // else
      // {
      //   return true;
      // }
    }

    function check_min_date( $record, $subject, $db, $parameters )
    {
      $date = array_shift($parameters);
      return strtotime($record[$subject]) > strtotime($date);
    }

    function check_max_length( $record, $subject, $db, $parameters )
    {
      return strlen($record[$subject]) <= $parameters[0];
    }

    function check_min_length( $record, $subject, $db, $parameters )
    {
      return strlen($record[$subject]) >= $parameters[0];
    }

    function check_max( $record, $subject, $db, $parameters )
    {
      return is_null($record[$subject]) ? true : $record[$subject] <= $parameters[0];
    }

    function check_min( $record, $subject, $db, $parameters )
    {
      return is_null($record[$subject]) ? true : $record[$subject] >= $parameters[0];
    }

    function check_between( $record, $subject, $db, $parameters )
    {
      return is_null($record[$subject]) ? true : $record[$subject] >= $parameters[0] && $record[$subject] <= $parameters[1];
    }

    function check_one_of( $record, $subject, $db, $parameters )
    {
      return in_array($record[$subject], $parameters);
    }

    function check_member_of( $record, $subject, $db, $parameters )
    {
      list($referenced_table, $referenced_field) = $parameters;

      $criteria = array();
      if( is_array($subject) )
      {
        foreach( $subject as $index => $field )
        {
          $referenced_name = $referenced_field[$index];
          if( is_null($record[$field]) )
          {
            return true;
          }
          $criteria[] = $db->format("`$referenced_name` = ?", $record[$field]);
        }
      }
      else
      {
        $field = $subject;
        if( !array_key_exists($field, $record) || is_null($record[$field]) )
        {
          return true;
        }
        $criteria[] = $db->format("`$referenced_field` = ?", $record[$field]);
      }

      $table = isset($db->$referenced_table) ? $db->$referenced_table : $referenced_table;
      return $db->query_exists("SELECT * FROM $table WHERE " . implode(" and ", $criteria));
    }



  //===============================================================================================
  // SECTION: Internals.

    function name( $db )
    {
      $name = $this->name;
      return isset($db->$name) ? $db->$name : $name;
    }

  
    function signal_change( $data, $count )
    {
      Script::signal("table_changed", $this->name, $this, $data, $count);
      Script::signal($this->snake_name . "_table_changed", $this, $data, $count);
    }


    function filter( $db, $fields, $canonicalize = false )
    {
      if( $canonicalize )
      {
        foreach( $this->field_types as $field => $type )
        {
          if( !isset($fields[$field]) || is_null($fields[$field]) )
          {
            $fields[$field] = $this->field_defaults[$field];
          }
        }
      }

      foreach( $fields as $field => $value )
      {
        if( isset($this->filters[$field]) )
        {
          foreach( $this->filters[$field] as $parameters )
          {
            $filter = array_shift($parameters);
            $method = "filter_$filter";
            $fields[$field] = $this->$method($fields[$field], $parameters);
          }
        }
      }

      foreach( $this->custom_filters as $custom_filter )
      {
        $fields = call_user_func($custom_filter, $fields);
      }

      $fields = Script::filter("table_fields", $fields, $this);

      return $fields;
    }


    function check( $db, $fields, $filter = true, $canonicalize = false, $throw = true )
    {
      $filter and $fields = $this->filter($db, $fields, $canonicalize);

      $field_names = array_keys($fields);
      foreach( $this->checks as $parameters )
      {
        $subject = array_shift($parameters);
        $applies = (is_string($subject) && array_key_exists($subject, $fields)) || (is_array($subject) && count($subject) == count(array_intersect($field_names, $subject)));
        if( $applies )
        {
          $check   = array_shift($parameters);
          $method  = "check_$check";
          $result  = $this->$method($fields, $subject, $db, $parameters);

          if( $result !== true )
          {
            if( $throw )
            {
              throw new TableValidationCheckFailed($this->name, $check, $subject, $result);
            }
            else
            {
              return array("failed_check", $check, $subject, $result);
            }
          }
        }
      }

      return null;
    }


  }



  class TableValidationCheckFailed extends Exception
  {
    public $name;
    public $subject;
    public $result;

    function __construct( $table, $name, $subject, $result )
    {
      parent::__construct( "Table validation check $table.$subject $name failed");

      $this->name    = $name;
      $this->subject = $subject;
      $this->result  = $result;
    }
  }
