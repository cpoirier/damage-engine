<?php if (defined($inc = "ENGINE_SERVICERESPONSE_INCLUDED")) { return; } else { define($inc, true); }

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


  // For JSON services, this is the standard response, capable of capturing all of the common
  // elements in a way that helps ensure quality operation. Subclass this to add additional
  // data (or just add to an instance directly).

  class ServiceResponse
  {
    static function build_success( $response_data = null, $data_is_content = null )
    {
      return new static($success = true, $response_data, $data_is_content);
    }
    
    static function build_failure( $error_code )
    {
      $messages = array_flatten(array_slice(func_get_args(), 1));
      return new static($success = false, (object)array("error_code" => $error_code, "messages" => $messages));
    }
    
    
    function __construct( $success = true, $response_data = null, $data_is_content = null )
    {
      $this->data     = (object)array("request" => null, "response" => (object)array("success" => $success, "content" => (object)null));
      $this->contexts = array();
      
      if( $response_data )
      {
        is_null($data_is_context) and $data_is_content = $success;
        $method = $data_is_content ? "merge_content" : "merge";
        $this->$method($response_data);
      } 
    }
    
    function get_content_type()
    {
      return "application/json";
    }
    
    function to_string()
    {
      $data = deep_copy($this->data);
      $data->request = Script::get_script_name();

      $data = Script::filter("service_response_data", $data, $this);
      Features::enabled("debugging") and $data->report = ScriptReporter::format_script_report_as_array();
        
      return @json_encode($this->data);
    }
    
    function __toString()
    {
      return $this->to_string();
    }
    
    


  //===============================================================================================
  // SECTION: Response construction.


    function &get( $path, $in = "" )   // Gets the value at $in.$path.
    {
      $ref =& $this->find($in . "." . $path);
      return $ref;
    }
    

    function set( $path, $value, $in = "" )  // Sets $value at $in.$path. Replaces any old value.
    {
      $ref =& $this->find($in . "." . $path);
      $ref = $value;
    }


    function set_content( $path, $value )
    {
      return $this->set($path, $value, $in = "content");
    }

    
    function set_in_root( $element, $value )
    {
      $this->data->$element = $value;
    }


    function append( $path, $value, $in = "" )   // Appends $value to an array at $path. [] is added to $path if not present.
    {
      $path = $in . "." . $path;
      $array =& $this->find(substr($path, -2) == "[]" ? $path : $path . "[]");
      $array[] = $value;
    }


    function append_content( $path, $value, $in = "" )
    {
      return $this->append("content." . $path, $value, $in);
    }


    function merge( $data, $path = "" )    // Merges the data from the supplied object into the container at $path.
    {
      foreach( $data as $name => $value )
      {
        $this->set($name, $value, $path);
      }
    }

    function merge_content( $data, $path = "" )
    {
      return $this->merge($data, "content.$path");
    }


    function increment( $path, $value = 1, $in = "" )   // Increments $in.$path by $value (can be negative).
    {
      $ref =& $this->find("$in.$path");
      if( is_numeric($ref) )
      {
        $ref += $value;
      }
      else
      {
        $ref = $value;
      }
    }


    function increment_content( $path, $value = 1 )
    {
      return $this->increment_content($path, $value, $in = "content");
    }


    // Examples:
    //  ""                -- response
    //  "content"         -- response.content
    //  "content.x"       -- response.content.x[] -- converts it to an array, if necessary
    //  "content.x[].10   -- response.content.x[10]
    //  "content.x[].10.y -- resposne.content.x[10].y
    //  "."               -- response
    //  "content."        -- response.content
    //  ".content...x"    -- response.content.x

    function &find( $path )   // Returns a referency to an element within the response. Any element suffixed [] will ensure that an array is available at that location.
    {
      $container = $this->payload;
      $path = $this->get_current_path($path);
      if( !empty($path) )
      {
        foreach( explode(".", $path) as $step )
        {
          if( $step != "" )
          {
            $expect_array = false;
            if( substr($step, -2) == "[]" )
            {
              $expect_array = true;
              $step = substr($step, 0, -2);
            }

            //
            // Try to figure out the type of object.

            if( !is_array($container) )
            {
              $container = (object)$container;
              if( property_exists($container, $step) )
              {
                if( $expect_array && is_scalar($step) )
                {
                  $container->$step = (array)$container->$step;
                }
              }
              else
              {
                $container->$step = $expect_array ? array() : (object)null;
              }

              $container =& $container->$step;
            }
            else
            {
              if( array_key_exists($step, $container) )
              {
                if( $expect_array && is_scalar($step) )
                {
                  $container[$step] = (array)$container[$step];
                }
              }
              else
              {
                $container[$step] = $expect_array ? array() : (object)null;
              }

              $container =& $container[$step];
            }
          }
        }
      }

      return $container;
    }




  //===============================================================================================
  // SECTION: Response construction helpers.


    function add_message( $message, $parameters = array(), $translate = true )   // Adds a text message to the content. This is primarily for sending error messages in the content.
    {
      if( is_bool($parameters) )
      {
        $translate  = $parameters;
        $parameters = array();
      }
      
      if( $translate )
      {
        $game    = Script::fetch("game");
        $message = $game->translate($message, $parameters);
      }

      $this->append("messages", $message);
    }


    function add_menu_of_matching_scripts( $pattern, $directory, $query_string, $exclude = null )
    {
      $this->data->menu = array();

      foreach( glob(path($pattern, $directory)) as $path )
      {
        if( $path !== $exclude )
        {
          $basename = basename($path, ".php");
          $bucket   = Symbol::convert_snake_to_pascal_case(basename(dirname($path)));
          $script   = Symbol::convert_snake_to_pascal_case($basename);

          $this->data->menu[] = (object)array("href" => "/$bucket/$script?o_O&$query_string", "title" => str_replace("_", " ", $basename));
        }
      }
    }




  //===============================================================================================
  // SECTION: 
  
    //
    // Causes any set/append/merge/increment until the matching end_redirect_into() to be written
    // into the response at $path (offset by any previous redirect).

    function begin_redirect_into( $path )
    {
      $this->contexts[] = $this->get_current_path($path);
    }


    //
    // Ends the last redirect. $path must match what you passed to begin_redirect_into().

    function end_redirect_into( $path )
    {
      assert('$this->get_current_path() == $this->get_previous_path($path)');
      array_pop($this->contexts);
    }

  
  

  //===============================================================================================
  // SECTION: Internals.


    protected function get_current_path( $offset = null )
    {
      $depth = count($this->contexts);
      return $this->make_path($depth <= 0 ? "" : $this->contexts[$depth - 1], $offset);
    }


    protected function get_previous_path( $offset = null )
    {
      $depth = count($this->contexts);
      return $this->make_path($depth <= 1 ? "" : $this->contexts[$depth - 2], $offset);
    }


    protected function make_path( $base, $rest )
    {
      if( empty($base) || $base == "." )
      {
        return $rest;
      }
      elseif( empty($rest) || $rest == "." )
      {
        return $base;
      }
      else
      {
        return $base . "." . $rest;
      }
    }


  }
