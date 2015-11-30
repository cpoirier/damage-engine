<?php if (defined($inc = "CORE_SQLPARAMETERPARSER_INCLUDED")) { return; } else { define($inc, true); }

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
  
  class SqlParameterScanner
  {
    public $pattern;
    
    function __construct( $pattern = null )
    {
      $this->pattern = $pattern ?: '/(?P<comparison>(?P<not>\bnot\s+)?(?P<operator>in|like|=|==|!=|<>|<=|>=|>|<)\s+)?{(?P<name>\w+)(?::(?P<type>[^}]+))?}/i';
    }
    
    
    function find_parameters( $source )
    {
      $matches = array();
      preg_match_all($this->pattern, $source, $matches);
      
      return $matches;
    }
    
    
    function process_parameters( $source, $callback )
    {
      return preg_replace_callback($this->pattern, is_object($callback) ? $callback->get_php_callback() : $callback, $source);
    }


    
    function to_string()
    {
      return $this->pattern;
    }
    

    function __toString()
    {
      return $this->to_string();
    }
  }
    
    