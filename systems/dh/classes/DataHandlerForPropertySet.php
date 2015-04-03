<?php if (defined($inc = "DATA_HANDLER_FOR_PROPERTY_SET_INCLUDED")) { return; } else { define($inc, true); }

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


  class DataHandlerForPropertySet extends DataHandler
  {
    function __construct( $owner, $name, $source, $source_query, $namespace = null, $post_processor = null, $post_processor_error_handler = null )
    {
      parent::__construct($owner, $name, $source, $namespace, $post_processor, $post_processor_error_handler);
      $this->source_query      = $source_query;
      $this->original          = null;
      $this->changes           = array();
      $this->change_record     = null;
      $this->legacy_exceptions = null;
    }
    
    function use_legacy_mode( $flag = true )
    {
      if( is_array($flag) )
      {
        $this->legacy_exceptions = array();
        foreach( $flag as $exception )
        {
          $this->legacy_exceptions[$exception] = $exception;
        }
        
        $flag = true;
      }
      
      parent::use_legacy_mode($flag);
    }


    function load()
    {
      $this->engage_post_processor();
      if( $this->original = $this->source->query_first($this->get_cache_key(), $this->source_query) )
      {
        foreach( $this->original as $name => $value )
        {
          $property = $this->convert_field_name_to_property_name($name); 
          $this->owner->$property = $value;
        }
      }
      elseif( $this->original = Script::dispatch("load_property_set", $this) )
      {
        foreach( $this->original as $name => $value )
        {
          $property = $this->convert_field_name_to_property_name($name); 
          $this->owner->$property = $value;
        }
      }
      $this->disengage_post_processor();
      

      return (boolean)$this->original;
    }

    function reconnect( $source = null )
    {
      if( $source )
      {
        abort();
      }
      else
      {
        $this->original = new stdClass();
        foreach( $this->source_query->get_field_names() as $name )
        {
          $property = $this->convert_field_name_to_property_name($name);
          $this->original->$name = @$this->owner->$property;
        }
      }
    }


    function has_changed()
    {
      return !!$this->determine_changes();
    }


    function prep_changes()
    {
      $this->capture_changes();
      return true;
    }


    function validate_changes( $skip_prep = false )
    {
      $skip_prep or $this->prep_changes();

      if( $this->change_record )
      {
        $this->change_record->validate_against($this->source->db);
      }

      return true;
    }


    function write_changes( $skip_checks = false, $skip_prep = false )
    {
      $skip_checks or $this->validate_changes($skip_prep);

      if( $this->change_record )
      {
        //
        // Write the data back to the database.

        $this->change_record->apply_to($this->source->db, $skip_checks = true);

        //
        // Write the changes back into the original, as they are the new original.

        $this->original or $this->original = new stdClass();
        foreach( $this->changes as $name => $value )
        {
          $this->original->$name = $value;
        }

        //
        // Save the record to the cache.

        $this->write_to_cache($this->original);
      }

      return true;
    }
    
    
    function write_to_cache( $value = null )
    {
      if( !$value )
      {
        $value = (object)null;
        foreach( $this->source_query->get_field_names() as $name )
        {
          $property = $this->convert_field_name_to_property_name($name);
          $value->$name = $this->owner->$property;
        }
      }

      return parent::write_to_cache($value);
    }


    function capture_changes()
    {
      $this->change_record = null;

      if( $changes = $this->determine_changes() )
      {
        $changes = array_merge($changes, $this->owner->get_key_pairs());
        if( empty($this->original) )
        {
          $this->change_record = ChangeRecord::capture("insert", $changes, $this->source_query, $this->source->schema);
        }
        else
        {
          $this->change_record = ChangeRecord::capture("update", $changes, $this->source_query, $this->source->schema);
        }
      }

      return $this->change_record;
    }


    function determine_changes()
    {
      $this->changes = array();
      if( empty($this->original) )
      {
        foreach( $this->source_query->get_field_names() as $name )
        {
          $property = $this->convert_field_name_to_property_name($name);
          $this->changes[$name] = $this->owner->$property;
        }
      }
      else
      {
        foreach( $this->original as $name => $value )
        {
          $property = $this->convert_field_name_to_property_name($name);
          if( isset($this->owner->$property) && $value != $this->owner->$property )
          {
            $this->changes[$name] = $this->owner->$property;
          }
        }
      }

      return $this->changes;
    }


    //
    // Verifies that the in-memory data matches up against the underlying database. Returns an
    // array of ChangeRecords needed to bring the database in line with the memory copy. Note that
    // using this routine means you can only save the discrepancies when

    function check_for_discrepancies( $db )
    {
      $change_records = array();
      $this->original = $db->query_first($this->source_query);
      if( $change_record = $this->capture_changes($db) )
      {
        $change_records[] = $change_record;
      }

      return $change_records;
    }


    function disengage()
    {
      if( $this->original )
      {
        foreach( $this->original as $name => $value )
        {
          unset($this->owner->$name);
        }
      }

      $this->owner = null;
    }
    
    
    
    protected function convert_field_name_to_property_name( $field_name )
    {
      if( $this->legacy_mode && !array_key_exists($field_name, $this->legacy_exceptions) )
      {
        return convert_snake_to_camel_case($field_name);
      }
      
      return $field_name;
    }

    protected function convert_property_name_to_field_name( $property_name )
    {
      if( $this->legacy_mode && !array_key_exists($property_name, $this->legacy_exceptions) )
      {
        return convert_camel_to_snake_case($property_name);
      }

      return $property_name;
    }

    

  }
