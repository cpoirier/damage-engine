<?php

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
  // Used by get_script_parameter_desriptors() and manual access to describe a single parameter.

  class ScriptParameterDescriptor
  {
    function __construct( $name, $comment = null, $method = false, $required = false )
    {
      if( $comment )
      {
        $comment = trim($comment);
        if( $comment == "required" )
        {
          $required = true;
          $comment  = false;
        }
        elseif( strpos($comment, "required;") === 0 )
        {
          $required = true;
          $comment  = trim(substr($comment, 0, 9));
        }
      }

      $this->name        = preg_replace('/["\']/', "", $name);
      $this->method      = strtoupper($method) == "POST" ? "POST" : "GET";
      $this->description = $comment;
      $this->is_required = $required;
    }

    function make_required( $yes = true )
    {
      if( $yes )
      {
        $this->is_required = true;
      }
    }

    function add_description( $clause )
    {
      $this->description = $this->description ? sprintf("%s; %s", $this->description, trim($clause)) : $clause;
    }
  }
