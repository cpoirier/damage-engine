<?php if (defined($inc = "SQL_QUERY_SIMPLIFIED_INCLUDED")) { return; } else { define($inc, true); }

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



  //
  // A simplified SQLQuery that gives up functionality to reduce its memory footprint.

  class SQLQuerySimplified
  {
    function __construct( $sql_query )
    {
      $this->sql_string      = $sql_query->to_string();
      $this->parameters      = $sql_query->parameters;
      $this->source_mappings = $sql_query->source_mappings;
      $this->write_mappings  = $sql_query->get_write_mappings();
      $this->field_names     = $sql_query->get_field_names();
      $this->order_by_fields = $sql_query->order_by_fields;
      $this->limit           = $sql_query->limit;
    }

    function get_field_names()
    {
      return $this->field_names;
    }
    
    function get_write_mappings()
    {
      return $this->write_mappings;
    }

    function to_sql( $db )
    {
      $parameters = func_get_args();
      while( count($parameters) && is_array($parameters[0]) )
      {
        $parameters = $parameters[0];
      }

      return $db->format($this->sql_string, array_merge($this->parameters, $parameters));
    }

    function to_string()
    {
      return $this->sql_string;
    }

    function simplify()
    {
      return $this;
    }
  }
