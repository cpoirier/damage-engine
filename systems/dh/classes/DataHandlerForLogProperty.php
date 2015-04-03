<?php if (defined($inc = "DATA_HANDLER_FOR_LOG_PROPERTY_INCLUDED")) { return; } else { define($inc, true); }

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


  class DataHandlerForLogProperty extends DataHandler
  {
    function __construct( $owner, $name, $source, $source_query, $timestamp_field, $namespace = null )
    {
      parent::__construct($owner, $name, $source, $namespace);
      $this->source_query    = $source_query;
      $this->timestamp_field = $timestamp_field;
      $this->change_record   = null;
    }


    function replace()
    {
      $args  = $this->extract(func_get_args());
      $pairs = array_merge($args[0], $this->owner->get_key_pairs());
      array_key_exists($this->timestamp_field, $pairs) or $pairs[$this->timestamp_field] = $this->source->db->format_time();

      $this->change_record = ChangeRecord::capture("replace", $pairs, $this->source_query, $this->source->schema);
      $this->set_value((object)$pairs);

      return true;
    }

    function add()
    {
      return $this->replace(func_get_args());
    }


    function delete()
    {
      abort("delete() not supported for log properties");
    }


    function update()
    {
      return $this->replace(func_get_args());
    }






    function load()
    {
      $value = $this->source->query_first($this->get_cache_key(), $this->source_query);
      $this->set_value($value);
    }

    function has_changed()
    {
      return !is_null($this->change_record);
    }


    function prep_changes()
    {
      return true;
    }


    function validate_changes( $skip_prep = false )
    {
      if( $this->has_changed() )
      {
        $this->change_record->validate_against($this->source->db);
      }

      return true;
    }


    function write_changes( $skip_checks = false, $skip_prep = false )
    {
      if( $this->has_changed() )
      {
        $skip_checks or $this->validate_changes($skip_prep);
        $this->change_record->apply_to($this->source->db, $skip_checks = true);
        $this->write_to_cache();
      }

      return true;
    }


  }
