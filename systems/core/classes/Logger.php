<?php if (defined($inc = "CORE_LOGGER_INCLUDED")) { return; } else { define($inc, true); }

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


  class Logger
  {
    const level_none     = 0;
    const level_debug    = 5;
    const level_notice   = 10;
    const level_warning  = 25;
    const level_error    = 50;
    const level_critical = 100;
  
    static $level = self::level_debug;
  
  
    static function log( $level )
    {
      $args = func_get_args();
      count($args) == 1 && is_array($args[0]) and $args = $args[0];

      $level = array_shift($args);
      if( static::is_operating_at($level) )
      {
        if( Script::get_recursion_depth("Logger::log") == 0 )    // We do not ever recurse here
        {
          $monitor = Script::enter_recursion("Logger::log");
          $signals = array($level > static::level_debug || Features::enabled("signal_on_log_debug") ? "log" : "", "all");
          
          Script::signal_and_ignore_exceptions($signals, static::get_level_name($level), $level, $args);

          $monitor = null;    // Exit the recusion monitor
        }

        foreach( $args as $arg )
        {
          error_log(is_string($arg) ? $arg : capture_var_dump($arg));
        }
      }
    }

  
    static function log_with_args( $level, $args )
    {
      if( static::is_operating_at($level) )
      {
        array_unshift($args, $level);
        static::log($args);
      }
    }
  
  
    static function log_with_args_and_trace( $level, $args, $trace = null )
    {
      if( static::is_operating_at($level) )
      {
        array_unshift($args, $level);
        array_push($args, capture_trace());
        static::log($args);
      }
    }
  
      
    static function log_with_trace( $level )
    {
      if( static::is_operating_at($level) )
      {
        $args   = func_get_args();
        $args[] = capture_trace();
      
        static::log($args);
      }
    }


    static function is_operating_at( $level )
    {
      is_string($level) and abort("convert to level constants, please");
      return $level >= static::$level;
    }
  
  
    static function get_level_by_name( $name )
    {
      $name or $name = "error";
      $level = static::level_error;
    
      switch( $name )
      {
        case "critical": $level = static::level_critical; break;
        case "error"   : $level = static::level_error   ; break;
        case "warning" : $level = static::level_warning ; break;
        case "notice"  : $level = static::level_notice  ; break;
        case "debug"   : $level = static::level_debug   ; break;
        default:         $level = static::level_none    ; break;
      }
    
      return $level;
    }
  
    static function set_level_by_name( $name )
    {
      static::$level = static::get_level_by_name($name);
    }
  
    static function get_level_name( $level )
    {
      switch( $level )
      {
        case static::level_critical: return "critical";
        case static::level_error   : return "error";
        case static::level_warning : return "warning";
        case static::level_notice  : return "notice";
        case static::level_debug   : return "debug";
        case static::level_none    : return "none";
      
        default:
          abort("unrecognized Logger level [$level]");
      }
    }
  }
