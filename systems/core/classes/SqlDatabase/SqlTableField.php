<?php if (defined($inc = "CORE_SQLTABLEFIELD_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class SqlTableField
  {
    function __construct( $name, $type, $allow_null, $default, $table_name = null, $schema_name = null )   // We avoid memory cycles by using name-based lookup for the table and schema
    {
      $this->name        = $name;
      $this->table_name  = $table_name;
      $this->schema_name = $schema_name;
      $this->type        = $type;
      $this->default     = $default;
      $this->allow_null  = $allow_null;
    }
    
    function get_schema()
    {
      return SqlSchema::fetch($this->schema_name);
    }
    
    function get_table()
    {
      return $this->get_schema()->get_table($this->table_name);
    }
    
    function get_engine()
    {
      return $this->get_schema()->get_engine();
    }
    
    function format_value( $value = null )
    {
      if( is_null($value) and !$allow_null )
      {
        $value = $this->default;
      }
      
      return $this->get_engine()->format_value($value, $this->type, $this->allow_null);
    }
    
    function unformat_value( $value )
    {
      if( is_null($value) and $allow_null )
      {
        return $value;
      }
      
      return $this->get_engine()->unformat_value($value, $this->type);
    }
  }
