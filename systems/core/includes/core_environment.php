<?php if (defined($inc = "SYSTEM_ENVIRONMENT_INCLUDED")) { return; } else { define($inc, true); }

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

  
   isset($_SERVER["REDIRECT_URL"]) or $_SERVER["REDIRECT_URL"] = $_SERVER["SCRIPT_NAME"];
 
   if( !defined("DOCUMENT_ROOT_MADE_REAL") )
   {
     $_SERVER["DOCUMENT_ROOT"] = realpath($_SERVER["DOCUMENT_ROOT"]);
     define("DOCUMENT_ROOT_MADE_REAL", true);
   }




//=================================================================================================
// SECTION: Boot the system

  define('ONE_MILLION',          1000000);
  define('ONE_BILLION',       1000000000);
  
  define('ONE_SECOND' ,   1             );
  define('ONE_MINUTE' ,  60             );
  define('ONE_HOUR'   ,  60 * ONE_MINUTE);
  define('ONE_DAY'    ,  24 * ONE_HOUR  );
  define('ONE_WEEK'   ,   7 * ONE_DAY   );
  define('ONE_MONTH'  ,  30 * ONE_DAY   );
  define('ONE_YEAR'   , 365 * ONE_DAY   );
  

  function deep_copy( $original )
  {
    return unserialize(serialize($original));
  }
  

  // Load the basics.

  require_once __DIR__ . "/../functions/debugging.php"  ;
  require_once __DIR__ . "/../functions/arrays.php"     ;
  require_once __DIR__ . "/../functions/trees.php"      ;

  require_once __DIR__ . "/../classes/DeferredCall.php" ;
  require_once __DIR__ . "/../classes/Features.php"     ;
  require_once __DIR__ . "/../classes/TypeConverter.php";
  require_once __DIR__ . "/../classes/Configuration.php";
  require_once __DIR__ . "/../classes/Script.php"       ;
  require_once __DIR__ . "/../classes/PathManager.php"  ;
  require_once __DIR__ . "/../classes/Cache/Cache.php"  ;
  require_once __DIR__ . "/../classes/Logger.php"       ;

  require_once __DIR__ . "/../functions/encodings.php"  ;
  

  // Configure features.

  if( $string = Configuration::get("FEATURES", "production_mode, security, !debugging") )
  {
    Features::set_from_switches($string);
    Features::enabled("development_mode") and !Features::disabled("tracing") and Features::enable("tracing");
  }


  // Bail out if system_maintenance mode is enabled.

  if( Features::enabled("system_maintenance") )
  {
    $until = @strtotime(Configuration::get("SYSTEM_MAINTENANCE_END"));
    if( Script::filter("should_exit_for_system_maintenance", true) )
    {
      Script::signal("exiting_for_system_maintenance", $until);
      Script::respond_unavailable("System maintenance under way. Please try again later.\n", $until ? $until - time() : null);
    }
  }


  // Load any application-defined configuration file.

  if( !Features::disabled("load_configuration_at_boot") and file_exists($configuration_path = $_SERVER["DOCUMENT_ROOT"] . "/configuration.php") )
  {
    require_once $configuration_path;
  }


  // Load various globally useful functions. Now that the path is set, we allow it to find the files.





//=================================================================================================
// SECTION: ERROR HANDLING ROUTINES


  // Dumps an exception to the error log and standard output and exits. Sends an HTTP 500 instead
  // if not debug mode.
  //
  // Signals:
  //   signal("dying_from_uncaught_exception", $exception)       -- exit to prevent any uncaught exception processing
  //   signal("killed_by_uncaught_exception", $exception, $dump) -- exit to prevent the default response processing

  function report_uncaught_exception_and_exit( $exception )
  {
    Script::signal("dying_from_uncaught_exception", $exception);
    Script::signal("killed_by_uncaught_exception", $exception, $dump = format_exception_data($exception, sprintf("ERROR in %s: ", Script::get_script_name())));
    
    error_log(Script::get_script_name() . " DIED FROM UNCAUGHT EXCEPTION: " . $exception->getMessage() . "\n" . capture_var_dump($exception) . "\n" . capture_trace());
    exit;
  }


  function report_uncaught_exception_to_client( $exception, $dump )
  {
    if( Script::get("response_size", 0) == 0 )
    {
      if( Features::enabled("debugging") )
      {      
        @header("Content-type: text/plain");
        if( Script::flush_output_buffers() )
        {
          print "\n\n";
          print str_repeat("-", 80);
          print "\n\n";
        }

        print $dump;
      }
      else
      {
        Script::discard_output_buffers();
        @header("HTTP/1.0 500 Service failed");
      }
    }
  }

  Script::register_event_handler("killed_by_uncaught_exception", "report_uncaught_exception_to_client", -1);


  // Throws an ErrorException for the supplied PHP trigger_error() data.

  function convert_errors_to_exceptions( $errno, $errstr, $errfile = null, $errline = null, $errcontext = null )
  {
    if( $level = error_reporting() )
    {
      if( $level & $errno and strpos($errstr, "Indirect modification") === false )    // We are expressly ignoring this one because it's spurious
      {
        error_log("THROWING ErrorException: $errstr");
        throw new ErrorException($errstr, $code = 0, $severity = $errno, $errfile, $errline);
      }
    }
    elseif( $errno & (E_ERROR | E_WARNING | E_USER_ERROR | E_USER_WARNING) )  // because, for whatever reason, error_get_last() doesn't seem to work reliably
    {
      $GLOBALS["last_error"] = array("type" => $errno, "message" => $errstr, "file" => $errfile, "line" => $errline);
    }
  }

  function get_last_error()
  {
    return @$GLOBALS["last_error"];
  }




//=================================================================================================
// SECTION: COMPLETE THE RUNTIME ENVIRONMENT


  // Set up error handling.

  set_error_handler("convert_errors_to_exceptions", E_ALL | E_STRICT);
  set_exception_handler('report_uncaught_exception_and_exit');


  // Set up the ClassManager.

  if( !function_exists("__autoload") )
  {
    class_exists("ClassManager", $autoload = false) or require_once __DIR__ . "/../classes/ClassManager.php";

    function __autoload( $class_name )
    {                                                                                                                            $_ = TRACE_ENTRY(__METHOD__);
      if( ClassManager::load_class($class_name) )
      {
        return true;
      }                                                                                                                               TRACE(__METHOD__, "[$class_name] not found in class index!");
      
      if( error_reporting() )
      {
        Script::fail("class_not_found:$class_name", array("class_name", $class_name));
      } 

      return false;
    }
  }
  
  
  // Set up the system component paths, if available.
  
  if( $current = Configuration::get("SYSTEM_COMPONENTS_CSV") )
  {
    Script::set_system_component_names(str_getcsv($current));
  }

  if( $paths = Script::get_system_component_paths() or $paths = array(__DIR__ . "/..") )
  {
    $class_subdirectories = str_getcsv(Configuration::get("CLASS_PATH_SUBDIRECTORIES_CSV", "classes,exceptions"));
    foreach( $paths as $path )
    {
      PathManager::add_directory($path);

      $class_subdirectory_found = false;
      foreach( $class_subdirectories as $class_subdirectory )
      {
        if( file_exists($current = "$path/$class_subdirectory") )
        {
          ClassManager::add_directory($current);
          $class_subdirectory_found = true;
        }
      }
      
      if( !$class_subdirectory_found )
      {
        ClassManager::add_directory($path);
      }
    }      
  }
  

  // Set up the logging environment.

  Configuration::define_term("LOGGING_LEVEL", "error, warn, info, or debug", "error");
  Logger::$level = Features::enabled("debugging") ? Logger::level_debug : Logger::set_level_by_name(Configuration::get("LOGGING_LEVEL"));


  // Enable garbage collection.

  gc_enable();


  // Ensure all scripts run to completion, regardless of user behaviour.

  ignore_user_abort(true);


  // Configure error reporting levels.

  if( Features::enabled("debugging") )
  {
    ini_set("display_errors", "1");
  }

  enable_notices();

  
  // Ensure we have a timezone.

  ini_get("date.timezone") or date_default_timezone_set(Configuration::get("TZ", "America/Toronto"));


  // Set a sane caching policy

  if( !Script::was_called_as_get() )
  {
    Script::$response->set_header("Cache-Control", "no-cache");
  }


  // Define some useful constants.

  define('FAR_FUTURE'       , '2037-01-01 00:00:00');
  define('DISTANT_PAST'     , '1970-01-01 00:00:00');
  define('TIMESTAMP_PATTERN', '/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d$/');
    

  // And, finally, a new value "keyword", for E_STRICT nanny purposes when adding required parameters 
  // to a subclassed method. Yes, sometimes there are legitimate reasons for it.

  define('required', null);

