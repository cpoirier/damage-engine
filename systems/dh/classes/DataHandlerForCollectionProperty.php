<?php if (defined($inc = "DATA_HANDLER_FOR_COLLECTION_PROPERTY_INCLUDED")) { return; } else { define($inc, true); }

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


  // Base class for collection handlers.

  class DataHandlerForCollectionProperty extends DataHandler
  {
    function __construct( $owner, $name, $source, $source_query, $namespace = null, $post_processor = null, $post_processor_error_handler = null )
    {
      parent::__construct($owner, $name, $source, $namespace, $post_processor, $post_processor_error_handler);
      $this->source_query = $source_query;
    }


    //
    // A collection-aware version of get_value().

    function &get_value( $convert_from_legacy_if_appropriate = false )
    {
      $property = $this->property;
      $value =& $this->owner->$property;

      if( is_array($value) && $this->legacy_mode && $convert_from_legacy_if_appropriate )
      {
        $converted = array();
        foreach( $value as $k => $v )
        {
          $converted[$k] = convert_camel_assoc_to_snake_object($v);
        }
        $value = $converted;
      }

      return $value;
    }




  //===============================================================================================
  // Database interface support.

    protected function fill_pairs( &$pairs, $data, $layout, $legacy_set = null )
    {
      $raw = array();

      if( is_scalar($data) && is_string($layout) && $layout != "*" )
      {
        $raw = array($layout => $data);
      }
      elseif( !is_scalar($data) )
      {
        $raw = $data;
      }
      else
      {
        if( $legacy_set )   // BUG: This whole chunk is a hack to be compatible with the old PSC code; it needs to go away
        {
          foreach( $this->source_query->get_field_names() as $field_name )
          {
            if( !empty($legacy_set) && !array_key_exists($field_name, $pairs) && !array_key_exists($field_name, $raw) )
            {
              $raw[$field_name] = array_shift($legacy_set);
            }
          }
        }
        else
        {
          abort("data should be the single field or a map of pairs");
        }
      }

      foreach( Script::filter("data_handler_fields", $raw, $this->source_query, $this->source) as $name => $value )
      {
        is_string($name) or trigger_error("expected field name", E_USER_ERROR);
        $pairs[$name] = $value;
      }
    }


    //
    // Given a single value from our map, returns appropriate name/value pairs you can pass to
    // ChangeRecord::capture().

    protected function build_data_pairs( $value )
    {
      return (array)$value;
    }


    protected function &build_map_from_list_of_objects( $objects, $key_fields )
    {
      $map = array();
      $key_count = count($key_fields);
      foreach( $objects as $object )
      {
        $ref =& $map;

        for( $i = 0; $i < $key_count; $i++ )
        {
          $key_field = $key_fields[$i];
          $key_value = is_array($object) ? $object[$key_field] : $object->$key_field;

          if( $i + 1 == $key_count )
          {
            $ref[$key_value] = $object;
          }
          else
          {
            array_key_exists($key_value, $ref) or $ref[$key_value] = array();
            $ref =& $ref[$key_value];
          }
        }
      }

      return $map;
    }


    protected function build_vector_for_substructure( $vector, $substructure )
    {
      return null;
    }



  //===============================================================================================
  // Change detection routines. SHOULD NOT be used for normal operations.


    //
    // Iterates over (some subsection of) the map and builds a list of ChangeRecords need to bring
    // the database in line with the memory version. $vector is a list of unused key field names,
    // and $key_pairs the name/value pairs of the keys that have already been processed.

    protected function build_change_set( &$in_memory, &$in_db, $vector, $key_pairs, $db )
    {
      if( !is_array($in_memory) || !is_array($in_db) )
      {
        $path_here = implode("", array_map(create_function('$a', 'return "[" . $a . "]";'), array_keys($key_pairs)));
        $path_here and $path_here = "at " . $path_here;

        if( !is_array($in_memory) )
        {
          throw new DataHandlerIncompatibleStructuresError($this->owner, $this->name, "memory copy $path_here is not a map");
        }
        else
        {
          throw new DataHandlerIncompatibleStructuresError($this->owner, $this->name, "memory copy $path_here is a map, but database isn't");
        }
      }

      $change_records = array();
      $key_field      = array_shift($vector);
      $in_memory_keys = array_keys($in_memory);
      $in_db_keys     = array_keys($in_db    );

      if( empty($key_field) )
      {
        abort();
      }

      sort($in_memory_keys);
      sort($in_db_keys    );

      while( !(empty($in_memory_keys) && empty($in_db_keys)) )
      {
        if( empty($in_db_keys) || (!empty($in_memory_keys) && $in_memory_keys[0] < $in_db_keys[0]) )
        {
          $key_pairs[$key_field] = $key = array_shift($in_memory_keys);
          $change_records[] = $this->build_change_set_or_record($in_memory[$key], null, $vector, $key_pairs, $db);
        }
        elseif( empty($in_memory_keys) || (!empty($in_db_keys) && $in_memory_keys[0] > $in_db_keys[0]) )
        {
          $key_pairs[$key_field] = $key = array_shift($in_db_keys);
          $change_records[] = $this->build_change_set_or_record(null, $in_db[$key], $vector, $key_pairs, $db);
        }
        else // if( $in_memory_keys[0] == $in_db_keys[0] )
        {
          $key_pairs[$key_field] = $key = array_shift($in_memory_keys); array_shift($in_db_keys);
          $change_records[] = $this->build_change_set_or_record($in_memory[$key], $in_db[$key], $vector, $key_pairs, $db);
        }
      }

      return array_filter(array_flatten($change_records));
    }


    //
    // Calls build_change_set() or build_change_record() appropriately for the contents of $vector.

    protected function build_change_set_or_record( $in_memory_value, $in_db_value, $vector, $key_pairs, $db )
    {
      $change_records = array();

      if( empty($vector) )
      {
        if( $records = $this->build_change_records($in_memory_value, $in_db_value, $key_pairs, $db) )
        {
          $change_records[] = $records;
        }
      }
      else
      {
        $in_memory_value or $in_memory_value = array();
        $in_db_value     or $in_db_value     = array();

        if( $records = $this->build_change_set($in_memory_value, $in_db_value, $vector, $key_pairs, $db) )
        {
          $change_records = $records;
        }
      }

      return $change_records;
    }


    //
    // Builds one or more ChangeRecords for a single value in the map. Returns array() if there is
    // no change. Throws DataHandlerIncompatibleKeysError or DataHandlerIncompatibleStructuresError
    // if there is a problem.

    protected function build_change_records( $value_in_memory, $value_in_db, $key_pairs, $db )
    {
      if( !empty($value_in_memory) && !empty($value_in_db) && (is_scalar($value_in_memory) ^ is_scalar($value_in_db)) )
      {
        throw new DataHandlerIncompatibleStructuresError($this->owner, $this->name, "memory value and database value have different structures");
      }

      $change_records = $this->build_change_set_for_substructures($value_in_memory, $value_in_db, $key_pairs, $db);

      if( is_null($value_in_memory) && is_null($value_in_db) )
      {
        // no op
      }
      elseif( is_null($value_in_db) )
      {
        $pairs = array_merge($this->build_data_pairs($value_in_memory), $key_pairs);
        $change_records[] = ChangeRecord::capture("replace", $pairs, $this->source_query, $db->schema);
      }
      elseif( is_null($value_in_memory) )
      {
        $pairs = array_merge($this->build_data_pairs($value_in_db), $key_pairs);
        $change_records[] = ChangeRecord::capture("delete", $pairs, $this->source_query, $db->schema);
      }
      elseif( is_scalar($value_in_memory) && is_scalar($value_in_db) )
      {
        if( $value_in_memory != $value_in_db )
        {
          $pairs = array_merge($this->build_data_pairs($value_in_memory), $key_pairs);
          $change_records[] = ChangeRecord::capture("update", $pairs, $this->source_query, $db->schema);
        }
      }
      elseif( !is_scalar($value_in_memory) && !is_scalar($value_in_db) )
      {
        $pairs = array();
        $value_in_memory = $this->build_data_pairs($value_in_memory);
        foreach( $value_in_db as $name => $value )
        {
          if( !is_array($value) )
          {
            if( array_key_exists($name, $value_in_memory) )
            {
              if( !array_key_exists($name, $key_pairs) )
              {
                if( $value_in_memory[$name] != $value )
                {
                  $pairs[$name] = $value_in_memory[$name];
                }
              }
            }
            else
            {
              // After some thought, we collectively decided the best option in this case is to ignore the missing field.
            }
          }
        }

        if( !empty($pairs) )
        {
          $change_records[] = ChangeRecord::capture("update", array_merge($pairs, $key_pairs), $this->source_query, $db->schema);
        }
      }

      return $change_records;
    }


    function build_change_set_for_substructures( $value_in_memory, $value_in_db, $key_pairs, $db )
    {
      if( (empty($value_in_memory) && empty($value_in_db)) || (is_scalar($value_in_memory) && is_scalar($value_in_db)) )
      {
        return array();
      }

      $change_set = array();
      $structure  = empty($value_in_db) ? $value_in_memory : $value_in_db;
      if( is_array($structure) || is_object($structure) )
      {
        foreach( $structure as $name => $value )
        {
          if( is_array($value) && ($vector = $this->build_vector_for_substructure(array_keys($key_pairs), $name)) )
          {
            // print "\n==== RECURSING ON ====\n";
            // var_dump($value);
            // var_dump($value_in_memory);
            // var_dump($value_in_memory->$name);
            // var_dump($vector);
            // var_dump($key_pairs);
            // print "\n======================\n\n\n\n";

            if( !is_array($other[$name]) && !empty($other[$name]) )
            {
              throw new DataHandlerIncompatibleStructuresError($this->owner, $this->name, "memory and database differ on substructure");
            }

            $lh_value = empty($value_in_memory->$name) ? array() : $value_in_memory->$name;
            $rh_value = empty($value_in_db->$name    ) ? array() : $value_in_db->$name;

            $change_set[] = $this->build_change_set($lh_value, $rh_value, $vector, $key_pairs, $db);
          }
        }
      }

      return $change_set;
    }
  }
