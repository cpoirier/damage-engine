<?php if (defined($inc = "CORE_FEATURES_INCLUDED")) { return; } else { define($inc, true); }

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


  class Features
  {
    static $features = null;
    static $backups  = null;
  
    static function enabled( $name )
    {
      return array_key_exists($name, static::$features) && static::$features[$name];
    }
  
    static function disabled( $name )
    {
      return array_key_exists($name, static::$features) && !static::$features[$name];
    }
  
  

  
  //===============================================================================================
  // SECTION: Actions
  
    static function enable( $name )
    {
      static::$features[$name] = true;
      static::enabled("signal_feature_changes") and class_exists("Script", $autoload = false) and Script::signal("feature_enabled", $name);
      return true;
    }

    static function disable( $name )
    {
      static::$features[$name] = false;
      static::enabled("signal_feature_changes") and class_exists("Script", $autoload = false) and Script::signal("feature_disabled", $name);
      return true;
    }
  
    static function reset( $name )
    {
      unset(static::$features[$name]);
      static::enabled("signal_feature_changes") and class_exists("Script", $autoload = false) and Script::signal("feature_reset", $name);
      return true;
    }
    
    static function set( $name, $value )
    {
      if( is_null($value) )
      {
        static::reset($name);
      }
      elseif( $value )
      {
        static::enable($name);
      }
      else
      {
        static::disable($name);
      }
    }
  
  

  
  //===============================================================================================
  // SECTION: Temporary (stacked) actions
  
    static function temporarily_disable( $name, $return_restorer = false )
    {
      $current_value = @static::$features[$name];
      @static::$backups[$name][] = $current_value;
      
      static::disable($name);
      
      return $return_restorer ? new DeferredCall(Callback::for_method(__CLASS__, "restore", $name)) : null;
    }
    
    static function temporarily_enable( $name, $return_restorer = false )
    {
      $current_value = @static::$features[$name];
      @static::$backups[$name][] = $current_value;
      
      static::enable($name);
      
      return $return_restorer ? new DeferredCall(Callback::for_method(__CLASS__, "restore", $name)) : null;
    }
    
    static function restore( $name )
    {
      $old_value = @array_pop(static::$backups[$name]);
      static::set($name, $old_value);
    }



  //===============================================================================================
  // SECTION: Set up
    
    static function enable_all( $list )
    {
      foreach( $list as $name )
      {
        static::enable($name);
      }
    }
    
    static function disable_all( $list )
    {
      foreach( $list as $name )
      {
        static::disable($name);
      }
    }
  
    static function set_from_switches( $string, $signal = false )
    {
      $on  = array();
      $off = array();
      foreach( preg_split('/,\s*/', $string) as $command )
      {
        if( substr($command, 0, 1) == "!" )
        {
          $off[] = substr($command, 1);
        }
        else
        {
          $on[] = $command;
        }
      }
    
      $signal = 
      static::enable_all($on);
      static::disable_all($off);
    }
  



  //===============================================================================================
  // SECTION: Internals
  
    static function initialize()
    {
      static::$features = array();
      static::$backups  = array();
    }
  }

  Features::initialize();
  
  
  
  
