<?php if (defined($inc = "CORE_SCRIPTEXCEPTION_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class ScriptException extends Exception
  {
    function __construct( $identifier, $data = array(), $previous = null )
    {
      parent::__construct($message = $identifier, $code = 0, $previous);
    
      $this->identifier = $identifier;
      $this->data       = $data;
    }
  
    function get_message()
    {
      return sprintf("%s %s", $this->identifier, @json_encode($this->data));
    }
    
  
  
  
  
    // Examples $parameters:
    //   array("something_bad_happened", "user_id", $player->user_id, "location_id", $location->location_id);
    //   array("something_bad_happened", "user_id", $player->user_id, "location_id", $location->location_id, $previous_exception);
    //   array("some_error", array("user_id" => $player->user_id));
    //   array("some_error", array("user_id" => $player->user_id), $previous_exception);

    static function build( /* $identifier, parameters... */ )
    {
      $args       = array_unpack(func_get_args());
      $identifier = array_shift($args);
      
      if( is_object($identifier) )
      {
        return $identifier;
      }
      else
      {
        $last_arg = $args ? $args[count($args) - 1] : null;
        $cause    = is_object($last_arg) && is_a($last_arg, "Exception") ? array_pop($args) : null;
        $pairs    = array_pair($args);

        return new static($identifier, $pairs, $cause);
      }
    }
  }

