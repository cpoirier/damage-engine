<?php if (defined($inc = "CORE_SQLSTATEMENT_INCLUDED")) { return; } else { define($inc, true); }

  // Damage Engine Copyright 2012-2015 Massive Damage, Inc.
  // Based on work Copyright 2011 1889 Labs
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

  
  class SqlStatement
  {
    static function build( $text, $parameters = array() )
    {
      return new static($text, array_pair_slice(func_get_args(), 2));
    }
    
    function __construct( $text, $parameters = array() )
    {
      $this->text       = $text;
      $this->parameters = $parameters;
    }
    
    
    function get_text()
    {
      if( is_object($this->text) and is_a($this->text, "Callback") )
      {
        return $this->text->call();
      }
      else
      {
        return (string)$this->text;
      }
    }
    

    function to_string()    // Formats a query with a map of (named) parameters.
    {
      $text = $this->get_text($engine);
      if( (empty($this->parameters) and func_num_args() == 0) or $engine == null ) 
      { 
        return $text;
      }
      else
      {
        $parameters = array_merge_keys($this->parameters, array_pair(func_get_args()));
        return $engine->expand_parameters($text, $parameters);
      }
    }
    
    
    
    function __toString()
    {
      return $this->to_string();
    }
    
    
    
  }