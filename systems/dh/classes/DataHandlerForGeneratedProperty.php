<?php if (defined($inc = "DATA_HANDLER_FOR_GENERATED_PROPERTY_INCLUDED")) { return; } else { define($inc, true); }

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


  class DataHandlerForGeneratedProperty extends DataHandler
  {
    function __construct( $owner, $name, $source, $source_callback, $namespace = null, $post_processor = null, $post_processor_error_handler = null )
    {
      parent::__construct($owner, $name, $source, $namespace, $post_processor, $post_processor_error_handler);
      $this->source_callback = $source_callback;
      $this->changes = array();
    }


    function replace()
    {
      $args  = $this->extract(func_get_args());
      $pairs = array_merge(array_shift($args), $this->owner->get_key_pairs());

      $this->has_changed = true;
      $this->set_value((object)$pairs);

      return true;
    }
    

    function add()
    {
      return $this->replace(func_get_args());
    }


    function update( $value_pairs )
    {
      $args           = $this->extract(func_get_args());
      $value_pairs    = array_shift($args);
      $criteria_pairs = $this->owner->get_key_pairs();
      $ref =& $this->get_value();

      $stored = $this->get_value();
      foreach( $value_pairs as $name => $value )
      {
        $stored->$name = $value;
      }
      
      $this->has_changed = true;
      $this->set_value($stored);

      return true;
    }


    function load()
    {
      $this->engage_post_processor();
      $value = Callback::do_callback($this->source_callback, $this);
      $this->disengage_post_processor();
      $this->set_value($value);
    }
  }
