<?php if (defined($inc = "ENGINE_ENGINE_INCLUDED")) { return; } else { define($inc, true); }

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


  class Engine
  {
    protected $ds;
    
    function __construct( $ds )
    {
      $this->cache_control = $ds->build_map("EngineCacheControlData", "aspect", "cutoff");
      $this->ds            = $this->get_ds($ds, "everything", "engine");
      $this->parameters    = $this->build_map("EngineParameterDict", "parameter_id", "value");
    }
    
    
    function get_ds()  // Returns a (or the) DataSource with max_age limited by the aspects you pass. Aspects can include EngineCacheControlData aspects, source file names, and times
    {
      $args = array_flatten(func_get_args());
      $base = count($args) > 1 && is_object($args[0]) ? array_shift($args) : $this->ds;

      $limits = array();
      foreach( $args as $aspect )
      {
        if( is_numeric($aspect) )                                    //<<<<<<< A direct time() value
        {
          $limits[] = (int)$aspect;
        }
        elseif( strpos($aspect, "/") !== false )                     //<<<<<<< A file path
        {
          $limits[] = filectime(substr($aspect, 0, 1) == "/" ? $aspect : path($aspect));   
        }
        elseif( array_has_member($this->cache_control, $aspect) )    //<<<<<<< An aspect name
        {
          $limits[] = $this->cache_control[$aspect];
        }
        elseif( ($timestamp = @strtotime($aspect)) !== false )       //<<<<<<< A convertible time string ("now", "10:00", etc.)
        {
          $limits[] = (int)$timestamp;
        }
      }

      return $base->filter_by("age", $limits);
    }
    
    
    function build_map( $name, $key, $value, $query_source = null )
    {
      $query_source or $query_source = $this;
      $snake_name = convert_pascal_to_snake_case($name);
      $query_builder = m("build_{$snake_name}_query", $this);
      $query_builder->is_defined() or $query_builder = function ($ds) { $ds->from($name)->buid_map($name, $key, $value); }
      
      return $this->get_ds($name)->build_static_map($query_builder, $key, $value);
    }
    
    
    
    
    
  }