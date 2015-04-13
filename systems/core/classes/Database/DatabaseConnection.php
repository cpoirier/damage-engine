<?php if (defined($inc = "CORE_DATABASECONNECTION_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class DatabaseConnection
  {
    function __construct()
    {
    }
    
    function query()
    {
      abort("override this to return a ResultsSet (always!)");
    }
    
    
    
  //===============================================================================================
  // SECTION: Convenience routines
  
    
    function query_first( $query /* parameters */ )
    {
      $parameters = array_pair_slice(func_get_args(), 1);
      $results    = $this->query($query, $parameters);
      
      return $results->get_first();
    }
    
    
    function query_value( $field, $default, $query /* parameters... */ )
    {
      $parameters = array_pair_slice(func_get_args(), 3);
      $results    = $this->query($query, $parameters);
      
      return $results->get_first_value($field, $default);
    }

      
    function query_exists( $query /* parameters... */ )
    {
      $parameters = array_pair_slice(func_get_args(), 1);
      $results    = $this->query($query, $parameters);
      
      return $results->has_results();
    }
    
    
    function query_all( $query /* parameters... */ )
    {
      $parameters = array_pair_slice(func_get_args(), 1);
      $results    = $this->query($query, $parameters);
      
      return $results->as_list();
    }


    function query_column( $field, $query /* parameters... */ )
    {
      $parameters = array_pair_slice(func_get_args(), 2);
      $results    = $this->query($query, $parameters);
      
      return $results->as_list($field);
    }
    
    
    function query_map( $key_fields, $value_fields, $query /* parameters...*/ )
    {
      $parameters = array_pair_slice(func_get_args(), 3);
      $results    = $this->query($query, $parameters);
      
      return $results->as_map($key_fields, $value_fields);
    }

    
    function query_tree( $program, $query /* parameters...*/ )
    {
      $parameters = array_pair_slice(func_get_args(), 2);
      $results    = $this->query($query, $parameters);
      
      return $results->as_tree($program);
    }
  }