<?php if (defined($inc = "ENGINE_EXCEPTION_INCLUDED")) { return; } else { define($inc, true); }

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


  // Base class for things exceptions that are thrown by the engine.

  class EngineException extends Exception
  {
    function __construct( $identifier, $data = array(), $level = null, $previous = null )
    {
      parent::__construct($identifier . " " . @json_encode($data), $code = 0, $previous);
    
      $this->identifier      = $identifier;
      $this->data            = $data;
      $this->level           = $level;
      $this->effective_level = null;
    }
  
    function get_effective_level()
    {
      if( is_null($this->effective_level) )
      {
        $level = $this->level or $level = ($this->is_reportable() ? Logger::level_debug : Logger::level_error);
        $this->effective_level = Script::filter("engine_exception_effective_level", $level, $this->identifier, $this);
      }
    
      return $this->effective_level;
    }
  
    function add_to_json_response( $response )
    {
      if( $this->is_reportable() )
      {
        if( @array_key_exists("+status", $this->data) )
        {
          $response->set_status($this->data["+status"]);
        }

        if( @array_key_exists("+response", $this->data) )
        {
          foreach( (array)$this->data["+response"] as $input => $output )
          {
            is_numeric($input) and $input = $output;
            $response->set($output, @$this->data[$input]);
          }
        }

        $response->set("error_code", tree_fetch($this->data, "error_code", $this->identifier));
        $response->add_message($this->identifier, $this->data);
      }
    }
  
    function get_message()
    {
      $game = Script::fetch("game");
      return $this->is_reportable() ? $game->translate($this->identifier, $this->data) : $this->identifier;
    }

    function is_reportable()
    {
      $game = Script::fetch("game");
      return $game->has_translation($this->identifier);
    }
  
  
  
  
  
    // Examples $parameters:
    //   array("something_bad_happened", "user_id", $player->user_id, "location_id", $location->location_id);
    //   array("something_bad_happened", "user_id", $player->user_id, "location_id", $location->location_id, $previous_exception);
    //   array("some_error", array("user_id" => $player->user_id));
    //   array("some_error", array("user_id" => $player->user_id), $previous_exception);

    static function build( $parameters, $level = Logger::level_none )
    {
      $data     = array();
      $previous = null;
    
      //
      // First parameter is the identifier.

      $identifier = array_shift($parameters);
      $data       = array_pair($parameters);

      //
      // Return the exception (we don't throw it).

      return new EngineException($identifier, $data, $level, $previous);
    }
  }

