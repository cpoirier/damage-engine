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
    static function build( $text, $parameters = array(), $parameter_set_class = "SqlParameterSet" )    // builds a SqlStatement for your use
    {
      return new static($text, $parameters, $parameter_set_class);
    }
    
    static function compile( $text, $parameters = array(), $parameter_set_class = "SqlParameterSet" )  // builds a SqlStatement and returns the string it compiles to
    {
      return static::build($text, $parameters, $parameter_set_class)->to_string();
    }
    


    
    function __construct( $text, $parameters = array(), $parameter_set_class = "SqlParameterSet" )
    {
      $this->text                = $text;
      $this->parameters          = $parameters;
      $this->parameter_set_class = $parameter_set_class;
    }
    
    
    function to_string( $parameters = array() )    // Formats a query with a map of (named) parameters.
    {
      if( empty($this->parameters) and empty($parameters) ) 
      { 
        return (string)$this->query;
      }
      else
      {
        $parameters = array_merge_keys($this->parameters, $parameters);
        $class      = $this->parameter_set_class;
        $text      = $class::expand_parameters($this->text, $parameters);

        return $text;
      }
    }
    
    
    function __toString()
    {
      return $this->to_string();
    }
  }