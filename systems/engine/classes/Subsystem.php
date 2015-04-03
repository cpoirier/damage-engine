<?php if (defined($inc = "ENGINE_SUBSYSTEM_INCLUDED")) { return; } else { define($inc, true); }

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


  class Subsystem
  {  
    public $engine;

    function __construct( $engine )
    {
      $this->engine  = $engine;
      $this->aspects = array_slice(array_flatten(func_get_args()), 1);
      $this->ds      = $engine->limit_data_source_age_by($this->aspects);
      $this->caches  = array();
    }
     
        
    function get_parameter( $name, $default = null )
    {
      return $this->engine->get_parameter($name, $default);
    }
    
    
    function get_collection( $name, $default = null )
    {
       new ObjectCache($this->slice_limit);
    }
    
    
  }
