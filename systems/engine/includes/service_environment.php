<?php if (defined($inc = "ENGINE_SERVICE_ENVIRONMENT_INCLUDED")) { return; } else { define($inc, true); }

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


  require_once __DIR__ . "/engine_environment.php";




//=================================================================================================
// SECTION: Manual access.

  if( Script::has_parameter("o_O") )
  {
    $manual_access_password = Configuration::get("MANUAL_ACCESS_PASSWORD", "");    
    if( !Features::enabled("security") or Features::enabled("manual_access") or (!empty($manual_access_password) and Script::get_parameter("o_O") == $manual_access_password) )
    {
      $manual_access_key = "o_O=" . urlencode(Script::get_parameter("o_O"));
      include __DIR__ . "manual_access.php";
    }
  }




//=================================================================================================
// SECTION: JSON environment.

//   Script::set_response(new JSONREsponseset_response( /* [response code], */ $response)
//   enter_json_mode();
//   Script::$response = $response = new JSONResponse();
//   Script::register_event_handlers_and_filters_from(Script::$response);
//
//
//   // Send uncaught EngineExceptions directly to the user and end the script, without a dump to the
//   // alert or error log, as they really aren't programming failures. Well, unless they are.
//
//   function handle_uncaught_reportable_exceptions( $exception )
//   {
//     if( is_a($exception, "EngineException") && $exception->is_reportable() )
//     {
//       debug(Script::$script_name . " RETURNING ERROR: ", $exception);
//       Script::signal("request_failed", $exception->identifier, $exception);
//
//       if( ($level = $exception->get_effective_level()) > Logger::level_debug )
//       {
//         Logger::log($level, $exception);
//       }
//
//       Script::$response->reset();
//       Script::respond_failure($exception);
//       exit;
//     }
//   }
//
//   Script::register_event_handler("dying_from_uncaught_exception", "handle_uncaught_reportable_exceptions");
//
//
//
//
// //=================================================================================================
// // SECTION: Debug environment.
//
//   if( Features::enabled("debugging") )
//   {
//     require_once path("service_debug_environment.php", __FILE__);
//   }
//
//   if( Features::enabled("debugging") || Features::enabled("capture") )
//   {
//     require_once path("service_capture_environment.php", __FILE__);
//   }
//
//
//
//
// //=================================================================================================
// // SECTION: General environment set up (functions, event handlers, etc.).
//
//   //
//   // Pull in some useful tools.
//
//   require_once "project.php";
//   require_once "project_away.php";
//   require_once "explode_comma_list.php";
//
//


