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


  if( !defined("DOCUMENT_ROOT_MADE_REAL") )
  {
    $_SERVER["DOCUMENT_ROOT"] = realpath($_SERVER["DOCUMENT_ROOT"]);
    define("DOCUMENT_ROOT_MADE_REAL", true);
  }

  @include $_SERVER["DOCUMENT_ROOT"] . "/includes/engine_environment.php";
  require_once __DIR__ . "/engine_environment.php";
  
  
  // Handle development stubs. If we are still here after, we have real work to do.
  
  if( !Features::enabled("production_mode") && Features::enabled("stub_mode") )
  {
    require_once __DIR__ . "/stub_environment.php";
  }


  // Pick up the service parameter from the mod_rewrite rules on the URL.

  $directory = "*";
  if( $s = Script::get_parameter("service") )
  {
    if( substr($s, 0, 10) == "resources/" )
    {
      $resource = preg_replace('/(^|\/)\.\.(\/|$)/', "", $s);
      if( $path = Script::find_system_component($resource) )
      {
        $type = "text/plain";
        switch( $extension = pathinfo($path, PATHINFO_EXTENSION) )
        {
          case "css": $type = "text/css"       ; break;
          case "js" : $type = "text/javascript"; break;
          case "jpg": $type = "image/jpeg"     ; break; 
          case "png": $type = "image/png"      ; break; 
        }

        header("Content-type: $type");
        readfile($path);
        exit;
      }
    }
    else
    {
      function convert_service_name_to_snake_case( $identifier )
      {
        return substr(strtolower(preg_replace('/([A-Z])/', "_\\1", $identifier)), 1);
      }
      
      function route_service_call( $service )
      {
        static $first = true;
        
        @list($directory, $script) = explode("/", $service);
        $script_name = sprintf("%s/%s", $directory, $script);
      
        if( $directory and preg_match('/^\w+$/', $directory) and $script and preg_match('/^\w+$/', $script) )
        {
          $directory = convert_service_name_to_snake_case($directory);
          $script    = convert_service_name_to_snake_case($script   );

          foreach( array("services", "redirects") as $bucket )
          {
            if( $path = Script::find_system_component("{$bucket}/{$directory}/{$script}.php") )
            {
              if( $first )
              {
                Script::set_script_name($script_name);
                $first = false;
              }
              else
              {
                abort("NYI: rework on template path");
                Script::add_to_template_path($script_name);
              }
              
              $_SERVER["SCRIPT_FILENAME"] = $path;
              return $path;
            }
          }
        }
        
        if( Features::enabled("production_mode") )
        {
          Script::send_internal_server_error();
        }
        else
        {
          Script::send_not_found(ServiceResponse::build_failure("not_found", "Not found"));
        }
        
        exit;
      }

      @list($directory, $script) = explode("/", $s);
      if( $script )
      {
        $service_path = route_service_call($s);
      
        require_once Script::find_system_component("/includes/service_environment.php");
        Script::note_time("service_booted");

        require $service_path;
        exit;
      }
      else
      {
        $directory = convert_service_name_to_snake_case($directory);
      }
    }
  }

  include __DIR__ . "/service_index_toc.php";

  Script::send_not_found();
  exit;
  
  