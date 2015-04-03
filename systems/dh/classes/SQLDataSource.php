<?php if (defined($inc = "SQL_DATA_SOURCE_INCLUDED")) { return; } else { define($inc, true); }

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


  
  class SQLDataSource extends DataSource
  {
    
    function build_map( $query_builder, $key, $value, $flags = null )
    {
      
      
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