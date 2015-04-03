<?php if (defined($inc = "DATA_HANDLER_FOR_MAP_PROPERTY_INCLUDED")) { return; } else { define($inc, true); }

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


  require_once "convert_camel_assoc_to_snake_object.php";

  class DataHandlerForMapProperty extends DataHandlerForCollectionProperty
  {
    function __construct( $owner, $name, $source, $source_query, $key_field, $value_field, $namespace = null, $post_processor = null, $load_filter = null, $post_processor_error_handler = null )
    {
      parent::__construct($owner, $name, $source, $source_query, $namespace, $post_processor, $post_processor_error_handler);
      $this->key_field   = $key_field;
      $this->value_field = $value_field;
      $this->load_filter = $load_filter;
    }

    function load()
    {
      $this->engage_post_processor();
      $value = $this->source->query_map($this->get_cache_key(), $this->key_field, $this->value_field, $this->source_query) or $value = array();
      $this->disengage_post_processor();
      $this->set_value($this->load_filter ? Callback::do_call_with_array($this->load_filter, array($value, $this->owner)) : $value);
    }


    //
    // Returns an array of ChangeRecords needed to bring the database up to date with our copy of
    // the map. Note: this routine can be very expensive. Don't use it for regular updates.

    function check_for_discrepancies( $db )
    {
      $in_db  = $db->query_map($this->key_field, $this->value_field, $this->source_query);
      $vector = is_array($this->key_field) ? $this->key_field : array($this->key_field);
      return $this->build_change_set($this->get_value($convert = true), $in_db, $vector, $this->owner->get_key_pairs(), $db);
    }


    //
    // Replaces an existing record. Once unpacked, the first parameters are the key(s), and the
    // final is a map of name/value pairs making up the rest of the record. If the map has a scalar
    // value, you can pass it directly as the last parameter.

    function replace()
    {
      $args  = $this->extract(func_get_args());
      $pairs = $this->owner->get_key_pairs();

      //
      // Add the key field(s) to the pairs.

      $keys       = array();
      $key_fields = is_array($this->key_field) ? $this->key_field : array($this->key_field);
      foreach( $key_fields as $key_field )
      {
        !empty($args) or trigger_error("not enough key parameters supplied", E_USER_ERROR);

        $value = is_array($args[0]) ? (array_key_exists($key_field, $args[0]) ? $args[0][$key_field] : null) : array_shift($args);
        $pairs[$key_field] = $value;
        $keys[] = $value;
      }

      //
      // Add the named parameters to the pairs.

      if( !empty($args) )
      {
        $this->fill_pairs($pairs, $args[0], $this->value_field, $args);
      }

      //
      // Generate the change record and apply it to our source.

      $this->apply_change(ChangeRecord::capture("replace", $pairs, $this->source_query, $this->source->schema));

      //
      // Write the data into the map.

      $value = (is_array($this->value_field) || $this->value_field == "*") ? $this->post_process((object)$pairs) : $pairs[$this->value_field];
      $this->set_to_map($keys, $value);
    }


    //
    // Adds a new record. Once unpacked, the first parameters are the key(s), and the final is a
    // map of name/value pairs making up the rest of the record. If the map has a scalar value,
    // you can pass it directly as the last parameter. You can pass null for any auto-increment
    // key. The key path through the map will be returned, as an array with multiple keys or as
    // a scalar if singular.

    function add()
    {
      $args  = $this->extract(func_get_args());
      $pairs = $this->owner->get_key_pairs();

      //
      // Add the key field(s) to the pairs.

      $keys       = array();
      $null_keys  = array();
      $index      = 0;
      $key_fields = is_array($this->key_field) ? $this->key_field : array($this->key_field);
      foreach( $key_fields as $key_field )
      {
        !empty($args) or trigger_error("not enough key parameters supplied", E_USER_ERROR);

        $value = is_array($args[0]) ? (array_key_exists($key_field, $args[0]) ? $args[0][$key_field] : null) : array_shift($args);
        $pairs[$key_field] = $value;
        $keys[] = $value;

        if( is_null($value) )
        {
          $null_keys[$key_field] = $index;
        }

        $index++;
      }

      //
      // Add the named parameters to the pairs.

      if( !empty($args) )
      {
        $this->fill_pairs($pairs, $args[0], $this->value_field, $args);
      }

      //
      // Generate the change record and apply it to our source.

      $results = $this->apply_change($change_record = ChangeRecord::capture("insert", $pairs, $this->source_query, $this->source->schema), $return_results = true);
      foreach( $change_record->instructions as $instruction )
      {
        $table = $instruction->table;
        if( $table->id_is_autoincrement )
        {
          $value    = $results[$table->name];
          $id_field = $table->id_field;
          $pairs[$id_field] = $value;
          
          if( array_key_exists($id_field, $null_keys) )
          {
            $index        = $null_keys[$id_field];
            $keys[$index] = $value;
          }
        }
      }

      //
      // Write the data into the map.

      $value = (is_array($this->value_field) || $this->value_field == "*") ? $this->post_process((object)$pairs) : $pairs[$this->value_field];
      $this->set_to_map($keys, $value);

      return count($keys) == 1 ? $keys[0] : $keys;
    }


    function delete()
    {
      $args  = $this->extract(func_get_args());
      $pairs = $this->owner->get_key_pairs();

      //
      // Add the key fields to the $pairs

      $keys       = array();
      $key_fields = is_array($this->key_field) ? $this->key_field : array($this->key_field);
      foreach( $key_fields as $key_field )
      {
        !empty($args) or trigger_error("not enough key parameters supplied", E_USER_ERROR);

        $value = is_array($args[0]) ? $args[0][$key_field] : array_shift($args);
        $pairs[$key_field] = $value;
        $keys[]            = $value;
      }

      //
      // Remove the entry from the map.

      $this->delete_from_map($keys);

      //
      // Generate the change record and apply it to the source.

      $this->apply_change(ChangeRecord::capture("delete", $pairs, $this->source_query, $this->source->schema));
    }


    function update()
    {
      $args           = $this->extract(func_get_args());
      $criteria_pairs = $this->owner->get_key_pairs();
      $value_pairs    = array();


      //
      // Add the key fields to the $criteria_pairs and find the entry in the map.

      $keys = array();
      $key_fields = is_array($this->key_field) ? $this->key_field : array($this->key_field);
      foreach( $key_fields as $key_field )
      {
        if( !array_key_exists($key_field, $criteria_pairs) )
        {
          $value = is_array($args[0]) ? $args[0][$key_field] : array_shift($args);
          $criteria_pairs[$key_field] = $value;
          $keys[] = $value;
        }
      }

      $ref =& $this->find_in_map($keys);


      //
      // The next parameter is either a map (if our value_field is "*"), or a single value.

      !empty($args) or trigger_error("value pairs not supplied", E_USER_ERROR);

      $value_pairs = null;
      if( is_array($this->value_field) || $this->value_field == "*" || is_array($args[0]) )
      {
        is_array($args[0]) or trigger_error("value pairs not supplied but required", E_USER_ERROR);
        $value_pairs = (array)array_shift($args);
      }
      else
      {
        is_array($args[0]) and trigger_error("value pairs supplied for value map", E_USER_ERROR);
        $value_pairs = (array)array($this->value_field => array_shift($args));
      }


      //
      // Eliminate any fields that aren't really being changed.

      if( is_array($this->value_field) || $this->value_field == "*" )
      {
        $o = is_object($ref) ? $ref : convert_camel_assoc_to_snake_object($ref);   // TEMPORARY: legacy support

        $changed_pairs = array();
        foreach( $value_pairs as $name => $value )
        {
          if( !is_scalar($o->$name) || !is_scalar($value) )
          {
            $changed_pairs[$name] = $value;
          }
          elseif( is_numeric($o->$name) ^ is_numeric($value) && (string)$o->$name != (string)$value )
          {
            $changed_pairs[$name] = $value;
          }
          elseif( $o->$name != $value )
          {
            $changed_pairs[$name] = $value;
          }
        }

        $value_pairs = $changed_pairs;
      }
      else
      {
        if( $ref == $value_pairs[$this->value_field] )
        {
          $value_pairs = array();
        }
      }

      if( empty($value_pairs) )
      {
        return;   // FLOW CONTROL: no change
      }


      //
      // Generate the change record and add it to our owner.

      if( $change = ChangeRecord::capture("update", array_merge($value_pairs, $criteria_pairs), $this->source_query, $this->source->schema) )
      {
        $this->apply_change($change);
      }
      else
      {
        abort("Unable to build ChangeRecord");
      }


      //
      // Update the map.

      if( is_array($this->value_field) || $this->value_field == "*" )
      {
        foreach( $value_pairs as $name => $value )
        {
          if( is_object($ref) )
          {
            $ref->$name = $value;
          }
          else                                     // TEMPORARY: legacy support
          {
            $ref[convert_snake_to_camel_case($name)] = $value;
          }
        }
      }
      else
      {
        $ref = $value_pairs[$this->value_field];
      }
    }
    
    
    
    function increment()
    {
      $args      = func_get_args();
      $args      = $this->extract($args);
      $pairs     = $args[0];
      $key_pairs = $this->owner->get_key_pairs();
      
      //
      // Determine the keys and use them to look up any existing entry in the map.

      $keys       = array();
      $key_fields = is_array($this->key_field) ? $this->key_field : array($this->key_field);
      foreach( $key_fields as $key_field )
      {
        if( !array_key_exists($key_field, $key_pairs) )
        {
          $value = $pairs[$key_field];
          $key_pairs[$key_field] = $value;
          $keys[] = $value;
        }
      }
      
      //
      // If there is an existing entry, we are adding the value of the first non-key to
      // the existing value of that field.
      
      $remaining_keys = array_diff(array_keys($pairs), array_keys($key_pairs));
      $value_key      = array_shift($remaining_keys);
      $before         = 0;

      if( $ref = $this->find_in_map($keys) )
      {
        if( is_array($this->value_field) || $this->value_field == "*" )
        {
          if( is_object($ref) )
          {
            $before = $ref->$value_key;
            $pairs[$value_key] += $ref->$value_key;
          }
          else                                     // TEMPORARY: legacy support
          {
            $before = $ref[convert_snake_to_camel_case($value_key)];
            $pairs[$value_key] += $ref[convert_snake_to_camel_case($value_key)];
          }
        }
        else
        {
          $before = $ref;
          $pairs[$value_key] += $ref;
        }
        
        $this->update($pairs);
      }
      else
      {
        $this->add($pairs);
      }
      
      return array($before, $pairs[$value_key]);
    }




  //===============================================================================================
  // Map interface support.

    protected function delete_from_map( $keys )
    {
      $map =& $this->get_value();
      return $this->delete_from_map_inner($keys, $map);
    }

    protected function delete_from_map_inner( $keys, &$map )
    {
      $key = array_shift($keys);
      if( array_key_exists($key, $map) )
      {
        if( !empty($keys) && is_array($map[$key]) )
        {
          $this->delete_from_map_inner($keys, $map[$key]);
          if( empty($map[$key]) )
          {
            unset($map[$key]);
          }
        }
        else
        {
          unset($map[$key]);
        }
      }
    }

    protected function set_to_map( $keys, $value )
    {
      $map =& $this->get_value();
      $key = array_shift($keys);
      while( !empty($keys) )
      {
        array_key_exists($key, $map) or $map[$key] = array();
        $map =& $map[$key];
        $key = array_shift($keys);
      }

      if( $this->legacy_mode )
      {
        $value = convert_snake_object_to_camel_assoc($value);
      }

      $map[$key] = $value;
    }

    protected function &find_in_map( $keys )
    {
      $map =& $this->get_value();
      $key = array_shift($keys);
      while( !empty($keys) )
      {
        array_key_exists($key, $map) or $map[$key] = array();
        $map =& $map[$key];
        $key = array_shift($keys);
      }

      return $map[$key];
    }




  //===============================================================================================
  // Database interface support.

    //
    // Given a single value from our map, returns appropriate name/value pairs you can pass to
    // ChangeRecord::capture().

    protected function build_data_pairs( $value )
    {
      $pairs = array();
      if( is_array($this->value_field) )
      {
        foreach( $this->value_field as $name )
        {
          $property = $this->convert_field_name_to_property_name($name);
          $pairs[$name] = is_array($value) ? $value[$property] : $value->$property;
        }
      }
      elseif( $this->value_field == "*" )
      {
        foreach( $this->source_query->get_field_names() as $name )
        {
          $property = $this->convert_field_name_to_property_name($name);
          if( is_array($value) )
          {
            $pairs[$name] = isset($pairs[$property]) ? $value[$property] : $value[$name];
          }
          else
          {
            $pairs[$name] = isset($pairs->$property) ? $value->$property : $value->$name;
          }
        }
      }
      else
      {
        $pairs[$this->value_field] = $value;
      }

      return $pairs;
    }

  }
