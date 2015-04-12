<?php if (defined($inc = "ENGINE_ENVIRONMENT_INCLUDED")) { return; } else { define($inc, true); }

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




//===============================================================================================
// SECTION: Pre-environment initialization

  if( function_exists("mb_internal_encoding") )
  {
    mb_internal_encoding("UTF-8");  // encoding for data
    mb_regex_encoding("UTF-8");     // encoding for the regex as specified in code
  }




//=================================================================================================
// SECTION: Load the core.

  // Load the core environment.

  require_once __DIR__ . "/../../core/includes/core_environment.php";


  // Load important functions (from the now-configured path)
  
  require_once "simplify_script_name.php";




//=================================================================================================
// SECTION: Define core functions for the game environment

  function now( $offset = null )
  {
    is_numeric($offset) or $offset = strtotime($offset);
    return date("Y-m-d H:i:s", $offset < (25 * ONE_YEAR) ? time() + $offset : $offset);
  }


  function now_u( $offset = null )
  {
    list($partial, $seconds) = explode(" ", microtime());
    $micros = substr($partial, 2);

    return sprintf("%s.%s", now($offset), $micros);
  }


  function deny_access()
  {
    warn("Access denied to " . Script::$script_name);
  
    header("HTTP/1.0 403 Forbidden");
    exit;
  }


  // Convenience wrapper on throw EngineException::build(). Any EngineException for which 
  // there is a translation will go to the user, in addition to any logging the system chooses to do. 

  function throw_exception()
  {
    throw EngineException::build(func_get_args());
  }

  function throw_alert()   { throw EngineException::build(func_get_args(), Logger::level_critical); }
  function throw_error()   { throw EngineException::build(func_get_args(), Logger::level_error   ); }
  function throw_warning() { throw EngineException::build(func_get_args(), Logger::level_warning ); }
  function throw_notice()  { throw EngineException::build(func_get_args(), Logger::level_notice  ); }




//===============================================================================================
// SECTION: Configure the environment for Engine policies

  // Allow run-time configuration to alter EngineException effective logging level.

  function filter_engine_exception_effective_level( $level, $identifier, $exception )
  {
    if( $new = Configuration::get("REPORT_{$identifier}_AT") )
    {
      if( $value = Logger::get_level_by_name($new) )
      {
        $level = $value;
      }
    }
  
    return $level;
  }

  Script::register_filter("engine_exception_effective_level", "filter_engine_exception_effective_level");


  // Configure the Script with the basics.

  global $script_name;
  Script::set_script_name(isset($script_name) ? $script_name : simplify_script_name());
  Script::register_handler("script_failing", "throw_exception");




//=================================================================================================
// SECTION: Configure debugging and related features.

  function register_debug_logging_controls( $inclusions, $exclusions = array() )
  {
    if( is_string($inclusions) )
    {
      if( trim($inclusions) == "*" )
      {
        Features::enable("debug_logging");
        $inclusions = array();
      }
      else
      {
        list($on, $off) = parse_switches_csv($inclusions);
        $inclusions = $on;
        $exclusions = array_merge($exclusions, $off);
      }
    }
  
    Script::concat("debug_logging_inclusions", $inclusions);
    Script::concat("debug_logging_exclusions", $exclusions);
  }

  function filter_debug_logging_enabled( $should )
  {
    if( !$should && ($inclusions = Script::get("debug_logging_inclusions")) )
    {
      $exclusions = array_diff((array)Script::get("debug_logging_exclusions"), $inclusions);

      $trace = null; try { throw new Exception(); } catch( Exception $e ) { $trace = $e->getTrace(); }
      foreach( $trace as $level )
      {      
        $function = $level["function"];
        array_key_exists("class", $level) and $function = $level["class"] . "::$function";

        foreach( $exclusions as $symbol )
        {
          if( strpos($function, $symbol) === 0 )
          {
            break 2;
          }
        }

        foreach( $inclusions as $symbol )
        {
          if( strpos($function, $symbol) === 0 )
          {
            return true;
          }
        }
      }
    }

    return $should;
  }


  // Configure debugging.

  Script::register_filter("debug_logging_enabled", "filter_debug_logging_enabled");
  Script::concat("debug_logging_exclusions", array("Script::signal"));  // Disable unwanted detours and noise

  if( Features::enabled("debugging") )
  {
    assert_options(ASSERT_ACTIVE, 1);

    if( $log_inside = Script::get_parameter("log_inside") )
    {
      register_debug_logging_controls($log_inside);
    }
    else
    {
      Features::disable("debug_logging");
    }
  
    // Log statistics to the error log, if debugging.

    Script::register_handler("beginning_teardown", array("ScriptReporter", "dump_script_report_to_error_log"));
  }
  else
  {
    assert_options(ASSERT_ACTIVE, 0);
  }




//=================================================================================================
// SECTION: Connect to data sources.

//   global $ds, $log_db;
//   $ds = new DataSource
//   (
//     cache_connect($statistics_collector = "Script"),
//     db_build_connector($statistics_collector = "Script", $series = "db"),
//     $for_writing = true,
//     $statistics_collector = "Script",
//     $on_abort = Callback::for_function("throw_exception", "service_failed")
//   );
//
//   if( Features::enabled("separate_log_db_connection") )
//   {
//     $log_db = db_connect($statistics_collector = null, $series = "db", $shared = false);   // As we have spare servers sitting about, we don't want persistent connections
//   }
//   else
//   {
//     $log_db = $ds->db;
//   }
//
//   Script::set("log_db", $log_db);
//   Script::set("ds"    , $ds    );
//
//   register_teardown_function(Callback::for_method($ds, "discard"));
//
//
//
//
// //=================================================================================================
// // SECTION: Configure database magic.
//
//   //
//   // Set up unpacking for any "extra" field in a query result. If the body is identifiably json or
//   // name-value pairs, they will be broken out and expanded into the row.
//
//   require_once "annotations.php"                ;
//   require_once "unpack_main_database_fields.php";
//   require_once "pack_main_database_fields.php"  ;
//   require_once "pack_sql_statement_fields.php"  ;
//   require_once "fill_in_log_record.php"         ;
//
//   Script::register_filter("query_result"        , "unpack_main_database_fields");
//   Script::register_filter("data_handler_fields" , "pack_main_database_fields"  );
//   Script::register_filter("sql_statement_fields", "pack_sql_statement_fields"  );
//   Script::register_filter("missing_log_field"   , "fill_in_log_record"         );
//
//
//
//
// //=================================================================================================
// // SECTION: Initialize logging.
//
//   require_once path("logging_environment.php", __FILE__);
//
//
//
//
// //=================================================================================================
// // SECTION: Start the GameEngine.
//
//   global $game;
//   $game = Script::set("game", new GameEngine($ds));
//
//
//   //
//   // Bail out for database-controlled system maintenance mode. You really should use Features
//   // instead.
//
//   if( Features::enabled("db_system_maintenance") && $game->get_parameter("SYSTEM_MAINTENANCE", false) )
//   {
//     $until = @strtotime($game->get_parameter("SYSTEM_MAINTENANCE_END"));
//     Script::respond_unavailable("System maintenance under way. Please try again later.\n", $until ? $until - time() : null);
//   }
//
//
//   //
//   // Hook the game into the event system.
//
//   $game->register_subsystem_event_handlers_and_filters();
//
//
//   //
//   // Replace the system-wide $ds with one age-limited on "everything".
//
//   global $ds;
//   $ds = Script::set("ds", $game->limit_data_source_age_by($ds, "everything"));
//
//
//   //
//   // Load the development environment before marking the system booted, in case it wants to
//   // customize anything.
//
//   if( Features::enabled("development_mode") )
//   {
//     require_once path("development_environment.php", __FILE__);
//   }
//
//   function postpone()
//   {
//     if( !Features::enabled("development_mode") )
//     {
//       $args = func_get_args();
//       call_user_func_array("abort", $args);
//     }
//   }
//
//
//
//   //
//   // Let the world know.
//
//   Script::signal("game_booted");
//
//
