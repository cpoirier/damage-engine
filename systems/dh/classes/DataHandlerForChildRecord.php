<?php if (defined($inc = "DATA_HANDLER_FOR_CHILD_RECORD_INCLUDED")) { return; } else { define($inc, true); }

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


  class DataHandlerForChildRecord extends DataHandler
  {
    function __construct( $owner, $name, $source, $source_query, $namespace = null, $post_processor = null, $post_processor_error_handler = null )
    {
      parent::__construct($owner, $name, $source, $namespace, $post_processor, $post_processor_error_handler);
      $this->source_query   = $source_query;
      $this->changes = array();
    }


    function replace()
    {
      $args  = $this->extract(func_get_args());
      $pairs = array_merge(array_shift($args), $this->owner->get_key_pairs());

      $this->changes[] = ChangeRecord::capture("replace", $pairs, $this->source_query, $this->source->schema);
      $this->set_value((object)$pairs);

      return true;
    }

    function add()
    {
      return $this->replace(func_get_args());
    }


    function delete()
    {
      $this->changes[] = ChangeRecord::capture("delete", $this->owner->get_key_pairs(), $this->source_query, $this->source->schema);
      $this->set_value(null);
      return true;
    }


    function update( $value_pairs )
    {
      $args           = $this->extract(func_get_args());
      $value_pairs    = array_shift($args);
      $criteria_pairs = $this->owner->get_key_pairs();
      $ref =& $this->get_value();

      //
      // Eliminate any fields that aren't really being changed.

      $changed_pairs = array();
      foreach( $value_pairs as $name => $value )
      {
        if( $ref->$name != $value )
        {
          $changed_pairs[$name] = $value;
        }
      }

      $value_pairs = $changed_pairs;

      if( empty($value_pairs) )
      {
        return;   // FLOW CONTROL: no change
      }


      //
      // Generate the change record and add it to list.

      if( $change = ChangeRecord::capture("update", array_merge($value_pairs, $criteria_pairs), $this->source_query, $this->source->schema) )
      {
        $this->changes[] = $change;
      }
      else
      {
        abort("Unable to build ChangeRecord");
      }

      $stored = $this->get_value();
      foreach( $value_pairs as $name => $value )
      {
        $stored->$name = $value;
      }
      $this->set_value($stored);

      return true;
    }






    function load()
    {
      $this->engage_post_processor();
      $value = $this->source->query_first($this->get_cache_key(), $this->source_query);
      $this->disengage_post_processor();
      $this->set_value($value);
    }

    function has_changed()
    {
      return !empty($this->changes);
    }


    function prep_changes()
    {
      return true;
    }


    function validate_changes( $skip_prep = false )
    {
      $skip_prep or $this->prep_changes();

      if( !empty($this->changes) )
      {
        foreach( $this->changes as $change )
        {
          $change->validate_against($this->source->db);
        }
      }

      return true;
    }


    function write_changes( $skip_checks = false, $skip_prep = false )
    {
      $skip_checks or $this->validate_changes($skip_prep);

      if( !empty($this->changes) )
      {
        $invalidated = false;
        while( !empty($this->changes) )
        {
          $change = array_shift($this->changes);
          $change->apply_to($this->source->db);
        }

        $this->write_to_cache();
      }

      return true;
    }



  }
