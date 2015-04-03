<?php if (defined($inc = "DATA_HANDLER_INCLUDED")) { return; } else { define($inc, true); }

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


  // Base class for things that can load and save data based on source descriptions.

  class DataHandler
  {
    function __construct( $owner, $name, $source, $namespace = null, $post_processor = null, $post_processor_error_handler = null )
    {
      $this->owner          = $owner;
      $this->name           = $name;
      $this->source         = $source;
      $this->namespace      = null;
      $this->cache_key      = $name;
      $this->property       = $name;
      $this->has_changed    = false;
      $this->post_processor = $post_processor;
      $this->post_processor_error_handler = $post_processor_error_handler;
      $this->filter_id      = null;
      $this->legacy_mode    = false;
      
      $namespace and $this->set_namespace($namespace);
    }
    
    function use_legacy_mode( $flag = true )
    {
      if( $flag )
      {
        $this->legacy_mode = $flag;
        $this->property    = $this->convert_field_name_to_property_name($this->property);
      }
    }
    

    function get_cache_key()
    {
      return $this->cache_key;
    }


    function set_namespace( $namespace )
    {
      $this->namespace = $namespace;
      $this->cache_key = $namespace ? sprintf("%s:%s", $namespace, $this->name) : $this->name;
    }


    function &get_value( $convert_from_legacy_if_appropriate = false )
    {
      $property = $this->property;
      $value = $this->owner->$property;

      if( $this->legacy_mode && $convert_from_legacy_if_appropriate )
      {
        $value = convert_camel_assoc_to_snake_object($value);
      }

      return $value;
    }

    function set_value( $value, $convert_to_legacy_if_appropriate = true )
    {
      if( $this->legacy_mode && $convert_to_legacy_if_appropriate )
      {
        $value = convert_snake_object_to_camel_assoc($value);
      }

      $property = $this->property;
      $this->owner->$property = $value;
    }


    function has_changed()
    {
      return $this->has_changed;
    }

    function prep_changes()
    {
      return true;
    }

    function validate_changes( $skip_prep = false )
    {
      return true;
    }

    function write_changes( $skip_checks = false, $skip_prep = false )
    {
      if( $this->has_changed )
      {
        $this->write_to_cache();
        $this->has_changed = false;
      }

      return true;
    }

    function write_to_cache( $value = null )
    {
      if( $value or $value = $this->get_value() )
      {
        $this->source->set($this->cache_key, $value);
      }
      else
      {
        $this->source->invalidate($this->cache_key);
      }
      
      return true;
    }

    function invalidate_cache()
    {
      $this->source->invalidate($this->cache_key);
      $this->disengage();
    }

    protected function extract( $args )
    {
      while( is_array($args) && count($args) == 1 && array_key_exists(0, $args) && is_array($args[0]) && array_key_exists(0, $args[0]) )
      {
        $args = $args[0];
      }

      return $args;
    }

    function disengage()
    {
      $property = $this->property;
      
      if( $this->owner )
      {
        unset($this->owner->$property);
        $this->owner->drop_handler($this->name);
        $this->owner = null;
      }
    }


    protected function apply_change( $change_record, $return_results = false )
    {
      $db = $this->source->db;

      $change_record->validate_against($db);   // throws exceptions on failure
      $results = $change_record->apply_to($this->source->db);
      $this->has_changed = true;

      return $return_results ? $results : null;
    }


    protected function engage_post_processor()
    {
      $this->post_processor and $this->source->on_next_query_results($this->post_processor);
      $this->post_processor_error_handler and $this->source->on_next_query_results_filter_error($this->post_processor_error_handler);
      
      return true;
    }

    protected function disengage_post_processor()
    {
      ($this->post_processor || $this->post_processor_error_handler) and $this->source->clear_next_query_results_filters();
      return true;
    }
    
    
    protected function post_process( $row )
    {
      $row = Script::filter("query_result", (object)$row, $this->source->db->name, $this->source_query->to_string(), $is_first_row = true);
      $this->post_processor and $row = Callback::do_call_with_array($this->post_processor, array($row, $is_first_row = true));
      
      return $row;
    }
    
    
    protected function convert_field_name_to_property_name( $field_name )
    {
      return $this->legacy_mode ? convert_snake_to_camel_case($field_name) : $field_name;
    }

    protected function convert_property_name_to_field_name( $property_name )
    {
      return $this->legacy_mode ? convert_camel_to_snake_case($property_name) : $property_name;
    }



  }

