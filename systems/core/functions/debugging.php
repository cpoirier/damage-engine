<?php if (defined($inc = "CORE_DEBUGGING_INCLUDED")) { return; } else { define($inc, true); }

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

  
  
  function abort()    // aborts the script and var_dumps() its parameters into the message
  {
    $args   = func_get_args();
    $reason = (count($args) > 0 && (is_string($args[0]) || is_null($args[0]))) ? array_shift($args) : null;
    $data   = $args;

    @header("Content-Type: text/plain");
    try
    {
      throw new Exception("ABORTED" . ($reason ? ": $reason" : ""));
    }
    catch( Exception $e )
    {
      for( $i = 0; $i < count($data); $i++ )
      {
        $name = "p$i";
        @$e->$name = $data[$i];
      }

      report_uncaught_exception_and_exit($e);
    }
  }


  function enable_notices()
  {
    error_reporting(E_ALL | E_STRICT);
  }

  function disable_notices()
  {
    $current = error_reporting();    
    $current & E_NOTICE      and $current = $current ^ E_NOTICE     ;
    $current & E_USER_NOTICE and $current = $current ^ E_USER_NOTICE;
    $current & E_STRICT      and $current = $current ^ E_STRICT     ;
  
    error_reporting($current);
  }

  function disable_strict()
  {
    $current = error_reporting();
    $current & E_STRICT and $current = $current ^ E_STRICT;
  
    error_reporting($current);
  }



  // As this routine has the potential to crash the process with excessive memory use, we try to at 
  // least mitigate that risk by not blindly copying. If the output hits $limit, you'll receive your
  // $discarded message instead.

  function capture_var_dump( $value, $limit = 5000000, $discarded = "TOO LARGE TO VAR_DUMP" )   // Return var_dump() or $object->to_dump() in a string.
  {
    if( is_object($value) && is_a($value, "Exception") )
    {
      return format_exception_data($value);
    }
    elseif( is_object($value) and method_exists($value, "to_dump") )
    {
      return $value->to_dump();
    }
    else
    {
      ob_start();
      var_dump($value);

      if( $limit and ob_get_length() > $limit )
      {
        ob_end_clean();    // Sadly, there is no way to get a part of the buffer, so we just have to discard it.
        print $discarded;
      }
    
      return ob_get_clean();
    }
  }



  function capture_trace()   // Returns a formatted stack trace at the current execution point.
  {
    try
    {
      throw new Exception();
    }
    catch( Exception $e )
    {
      return format_exception_trace($e);
    }
  }



  function capture_trace_signature( $exception = null )    // Returns a SHA1 hash (hopefully-)unique to this point in the call chain.
  {
    if( !$exception )
    {
      try { throw new Exception(); } catch( Exception $e ) { $exception = $e; }
    }
  
    $trace  = $exception->getTrace();
    $levels = array();
    $leader = "";

    if( $pos = strripos(__FILE__, "/system/system_environment.php") )
    {
      $leader = substr(__FILE__, 0, $pos);
    }
  
    foreach( $trace as $level )
    {
      $function = @$level["function"];
      array_key_exists("class", $level) and $function = $level["class"] . "::" . $function;
    
      $file = @$level["file"] and strpos($file, ".php") and $file = @realpath($file);
      $leader and $file = str_ireplace($leader, "", $file);
    
      $levels[] = sprintf("%s() at %s(%d)", $function, $file, @$level["line"]);
    }
  
    return hash("sha1", implode("\n", $levels));
  }
  
  
  function capture_trace_depth( $offset = 0, $exception = null )
  {
    if( !$exception )
    {
      try { throw new Exception(); } catch (Exception $e) { $exception = $e; }
    }
    
    return count($exception->getTrace()) - ($offset + 1);
  }



  function format_exception_trace( $exception )   // Formats an exception trace for display.
  {
    $trace = $exception->getTraceAsString();

    //
    // Strip out any unnecessary path information.

    if( $leader = strripos(realpath(__FILE__), "/systems/") )   
    {
      $leader = substr(realpath(__FILE__), 0, $leader);
      $trace  = str_ireplace($leader, "", $trace);
    }

    //
    // For the sake of readability, break up the trace lines.

    $lines        = explode("\n", $trace);
    $locations    = array();
    $descriptions = array();

    if( !Features::disabled("truncate_exception_trace") and count($lines) > 50 ) 
    {
      $lines = array_slice($lines, 0, 50);
      $lines[] = "...: [truncated]";
    }

    foreach( $lines as $line )
    {
      @list($location, $description) = explode(": ", $line    , 2);
      @list($number  , $location   ) = explode(" " , $location, 2);

      if( empty($description) || trim($description) == "" )
      {
        break;
      }

      $locations[]    = $location;
      $descriptions[] = $description;
    
    }
  
    $width = max(array_map("strlen", $locations));

    ob_start();
    while( !empty($locations) )
    {
      $location    = array_shift($locations);
      $description = array_shift($descriptions);

      printf(" %-${width}s: %s\n", $location, $description);
    }

    return ob_get_clean();
  }


  function format_exception_data( $exception, $preamble = "Error: " )
  {
    static $depth = 0; $depth += 1;
    if( $depth > 5 )
    {
      $depth -= 1;
      return "RECURSION DETECTED: format_exception_data failed\n";
    }
      
    ob_start();
    print $preamble;
    print $exception->getMessage();
    print "\n\n";
    print "Trace:\n";
    print format_exception_trace($exception);

    $first = true;
    foreach( $exception as $property => $value )
    {
      if( $first )
      {
        print "\n";
        $first = false;
      }

      print "\n$property:\n";
      if( is_scalar($value) )
      {
        print $value;
        print "\n";
      }
      elseif( is_object($value) && is_a($value, "Exception") )
      {
        if( spl_object_hash($exception) == spl_object_hash($value) )
        {
          print "==RECURSION==\n";
        }
        else
        {
          print format_exception_data($value);
        }
      }
      else
      {
        print capture_var_dump($value, $limit = 1000000, $discarded = "==TOO LARGE TO VAR_DUMP==\n");
      }
    }

    $depth -= 1;
    return ob_get_clean();
  }
  
  
  
//===============================================================================================
// SECTION: Log sugar

  
  function alert() { Logger::log_with_args(Logger::level_critical, func_get_args()); }  
  function warn()  { Logger::log_with_args(Logger::level_warning , func_get_args()); }
  function note()  { Logger::log_with_args(Logger::level_debug   , func_get_args()); }
  function dump()  { Logger::log_with_args(Logger::level_debug   , func_get_args()); }

  function alert_with_trace() { Logger::log_with_args_and_trace(Logger::level_critical, func_get_args()); }
  function error_with_trace() { Logger::log_with_args_and_trace(Logger::level_error   , func_get_args()); }
  function warn_with_trace()  { Logger::log_with_args_and_trace(Logger::level_warning , func_get_args()); }
  function note_with_trace()  { Logger::log_with_args_and_trace(Logger::level_debug   , func_get_args()); }
  function dump_with_trace()  { Logger::log_with_args_and_trace(Logger::level_debug   , func_get_args()); }

  function abort_if_in_development_mode_else_alert( $message )
  {
    Logger::log_with_args_and_trace(Logger::level_error, func_get_args());
    if( Features::enabled("development_mode") )
    {
      abort($message);
    }
  }




//===============================================================================================
// SECTION: Debug logging
  
  function debug_logging_is_enabled()    // Returns true if debug mode is enabled at the current position in the call stack
  {
    $enabled = Features::enabled("debugging") || Features::enabled("debug_logging");
    return ($enabled && !Features::disabled("debug_logging") && Script::filter("debug_logging_enabled", Features::enabled("debug_logging")));
  }

  function debug()                       // Logs a debug message if debug_logging_is_enabled()
  {
    if( debug_logging_is_enabled() )
    {
      $restore = Features::enabled("debugging") || Features::enabled("debug_logging");

      Features::disable("debugging");
      try { Logger::log_with_args(Logger::level_debug, func_get_args()); } catch (Exception $e) {}
      $restore and Features::enable("debugging");
    }
  }

  function debug_with_trace()            // Logs a debug message with trace if debug_logging_is_enabled()
  {
    if( debug_logging_is_enabled() )
    {
      $restore = Features::enabled("debugging") || Features::enabled("debug_logging");

      Features::disable("debugging");
      try { Logger::log_with_args_and_trace(Logger::level_debug, func_get_args()); } catch (Exception $e) {}
      $restore and Features::enable("debugging");
    }
  }
  
  
  
  
//===============================================================================================
// SECTION: Tracing


  function tracing_is_enabled( $function )
  {
    if( Features::enabled("tracing") )
    {
      $pos   = strpos($function, "::");
      $class = $pos ? substr($function, 0, $pos) : null;
      
      if( Features::enabled("trace_all") )
      {
        if( $class and Features::disabled("trace:$class") )
        {
          return Features::enabled("trace:$function");
        }
        
        return !Features::disabled("trace:$function");
      }
      else
      {
        if( $class and Features::enabled("trace:$class") )
        {
          return !Features::disabled("trace:$function");
        }
      
        return Features::enabled("trace:$function");
      }
    }
    
    return false;
  }


  function trace( $function, $trace /* ... */ )
  {
    if( tracing_is_enabled($function) )
    {
      $error_log = !Features::disabled("log_tracing_to_error_log");
      $do_work   = $error_log; 
      
      if( $do_work )
      {
        // To provide consistent processing, ensure { and } match
        
        static $traced = null;
        is_null($traced) and $traced = array();
        
        if( $trace == "{" )
        { 
          @$traced[$function] += 1; 
        } 
        elseif( $trace == "{" )
        {
          @$traced[$function] -= 1;
          $traced[$function] < 0 and abort("trace_exit() calls don't match trace_enter() calls; did you forget to hold the cleanup object in a variable?");
        }
        elseif( !array_key_exists($function, $traced) )
        { 
          abort("trace_entry() not called for $function");
        }
        
        
        // Generate the message.
        
        static $widest = null;
        $width   = strlen($function);
        $widest  = is_null($widest) ? $width : max($width, $widest);
        $depth   = trace_depth();
        $format  = "%s % -{$widest}s" . (($trace == "{" or $trace == "}") ? " " : " | ") . "%s";
        $message = sprintf($format, str_repeat("-", $depth), $function, $trace);
        
        if( $args = array_slice(func_get_args(), 2) )
        {
          $strings = array();
          foreach( $args as $arg )
          {
            $strings[] = is_scalar($arg) ? (string)$arg : capture_var_dump($arg);
          }
          
          $message .= "\n" . implode("\n", $strings);
        }

        
        // Log it as appropriate.
        
        $error_log and error_log($message);
      }
    }
  }

  
  function trace_entry( $function )
  {
    if( tracing_is_enabled($function) )
    {
      trace_depth(1);
      if( func_num_args() > 1 )
      {
        $args = array_merge(array($function, "{"), array_slice(func_get_args(), 1));
        call_user_func_array("trace", $args);
      }
      else
      {
        trace($function, "{");
      }
      return new DeferredCall(Callback::for_function("trace_exit", $function));
    }
  }
  
  
  function trace_exit( $function )
  {
    if( tracing_is_enabled($function) )
    {
      trace($function, "}");
      trace_depth(-1);
    }
  }
  
  
  function trace_depth( $delta = 0 )
  {
    static $depth = 0;
    $depth += $delta;
    return $depth;
  }


  function restore_tracing()
  {
    Features::restore("tracing");
  }

  function enable_tracing()
  {
    Features::temporarily_enable("tracing");
  }

  function disable_tracing()
  {
    Features::temporarily_disable("tracing");
  }


  
