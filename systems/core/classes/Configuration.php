<?php if (defined($inc = "CORE_CONFIGURATION_INCLUDED")) { return; } else { define($inc, true); }

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


  class_exists("TypeConverter") or require_once __DIR__ . "/TypeConverter.php";


  class Configuration    // Manages configuration data and returns data defined in any of: run time constants, ini file data, $_SERVER, $_ENV, or defined faults.
  {
    public static $terms;       // term => description
    public static $links;       // term => parent term
    public static $defaults;    // term => default
    public static $overrides;   // term => value


    //
    // Returns the current or default value for the specified term. Search order is:
    //   1) specialized name
    //   2) name
    //   3) specialized parent name
    //   4) parent name
    //   5) specialized grandparent name
    //   6) grandparent name
    //   n) etc.
    //   m) stated default
    //
    // If you supply a numeric default, any result will be numeric.

    static function get( $name, $default = null, $specialized_by = "DB_ENVIRONMENT" )
    {
      static::initialize();
      $specializer = $specialized_by ? static::fetch($specialized_by) : null;

      $value = null;
      $queue = static::specialize_name($name, $specializer);
      while( is_null($value) && !empty($queue) )
      {
        $current = array_shift($queue);
        $value   = static::fetch($current);

        if( empty($queue) && array_key_exists($current, static::$links) )
        {
          $queue = static::specialize_name(static::$links[$current], $specializer);
        }
      }

      return is_null($value) ? $default : TypeConverter::coerce_type($value, $default);
    }


    //
    // Defines a free-standing term. Provide a default if you can.

    static function define_term( $name, $description, $default = null )
    {
      static::initialize();
      static::$terms[$name] = $description;

      if( !is_null($default) && !array_key_exists($name, static::$defaults) )
      {
        static::$defaults[$name] = $default;
      }
    }


    //
    // Defines a term that can get its value from another, if not explicitly set.
    // For convenience, the parent name doesn't have to be defined yet. However, if it isn't
    // defined when you

    static function define_subterm( $name, $to_name, $description )
    {
      static::initialize();
      static::$terms[$name] = $description;
      static::$links[$name] = $to_name;
    }


    //
    // Adds a set of defaults to the Conf state. It is safe to add specialized defaults this way.

    static function add_defaults( $defaults )
    {
      static::initialize();
      foreach( $defaults as $name => $value )
      {
        static::$defaults[$name] = $value;
      }
    }


    //
    // Adds a single default to the Conf state. It is safe to add specialized defaults this way.

    static function set_default( $term, $value )
    {
      static::$defaults[$term] = $value;
    }
  
  
    static function set( $name, $value )
    {
      if( strtolower($name) == $name and ini_set($name, $value) )   // all PHP ini names are lower case
      {
        return;
      }
    
      static::$overrides[$name] = $value;
    }



  //===============================================================================================
  // INTERNALS

    //
    // Returns true if the term or its base was defined with a numeric default.

    static function is_numeric_term( $name )
    {
      if( array_key_exists($name, static::$defaults) )
      {
        return is_numeric(static::$defaults[$name]);
      }
      elseif( array_key_exists($name, static::$links) )
      {
        return static::is_numeric_term(static::$links[$name]);
      }

      return false;
    }


    //
    // Retrieves the value for the named key (exactly), without additional problems.

    protected static function fetch( $name )
    {
      $value = null;

      do
      {
        if( array_key_exists($name, static::$overrides) )
        {
          $value = static::$overrides[$name];
        }
        
        if( strtolower($name) == $name )  // all PHP ini names are lower case
        {
          if( $ini = ini_get($name) )
          {
            $value = $ini;
            break;
          }
        }
        
        if( defined($name) )
        {
          $value = constant($name);
          break;
        }

        if( array_key_exists($name, $_SERVER) )
        {
          $value = $_SERVER[$name];
          break;
        }

        if( array_key_exists($name, $_ENV) )
        {
          $value = $_ENV[$name];
          break;
        }

        if( array_key_exists($name, static::$defaults) )
        {
          $value = static::$defaults[$name];
          break;
        }
      }
      while(false);

      return $value;
    }


    //
    // Returns a list of names, specialized version first (if applicable).

    protected static function specialize_name( $name, $specializer = null )
    {
      $names = array($name);

      if( $specializer )
      {
        array_unshift($names, "${name}_${specializer}");
      }

      return $names;
    }


    //
    // Initializes the static variables.

    protected static function initialize()
    {
      if( is_null(static::$terms) )
      {
        static::$terms     = array();
        static::$links     = array();
        static::$defaults  = array();
        static::$overrides = array();
      }
    }
  }
