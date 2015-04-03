<?php if (defined($inc = "CORE_SYMBOL_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class Symbol
  {
    protected $case;
    protected $original;
    protected $snake;
    protected $camel;
    protected $pascal;
    
    function __construct( $string, $case = "snake" )
    {
      $this->case     = $case;
      $this->original = $string;
      $this->snake    = null;     $case == "snake"  and $this->snake  = $string;
      $this->camel    = null;     $case == "camel"  and $this->camel  = $string;
      $this->pascal   = null;     $case == "pascal" and $this->pascal = $string;
    }
    
    
    function to_snake_case()
    {
      is_null($this->snake) and $this->snake = $this->convert_to("snake");
      return $this->snake;
    }
    
    function to_camel_case()
    {
      is_null($this->camel) and $this->camel = $this->convert_to("camel");
      return $this->camel;
    }
    
    function to_pascal_case()
    {
      is_null($this->pascal) and $this->pascal = $this->convert_to("pascal");
      return $this->pascal;
    }
    
    function convert_to( $to_case )
    {
      $method = sprintf("convert_%s_to_%s_case", $this->case, $to_case);
      return method_exists($this, $method) ? static::$method($this->original) : null;
    }
    
    function sprintf( $format, $to_case )
    {
      return sprintf($format, $this->convert_to($to_case));
    }
    
    
    
    
  //===============================================================================================
  // SECTION: Builders
  
    static function from( $string, $case = "snake" )
    {
      return new Symbol($string, $case);
    }

    static function from_identifier( $string )
    {
      return new Symbol($string, "snake");
    }
    
    static function from_class_name( $string )
    {
      return new Symbol($string, "pascal");
    }
  
  
    
    
    
  //===============================================================================================
  // SECTION: String converters

  
    static function convert_snake_to_pascal_case( $identifier )      // Given a string in snake_case, returns one in PascalCase.
    {
      $words = explode("_", $identifier);
      return implode("", array_map("ucfirst", $words));
    }


    static function convert_snake_to_camel_case( $identifier, $exceptions = null )   // Given a string in snake_case, returns one in camelCase.
    {
      if( strpos($identifier, "_") !== false && $identifier == strtolower($identifier) ) // Make sure it really is snake_case, to minimize accidental changes
      {
        $words = explode("_", $identifier);

        if( is_null($exceptions) )
        {
          global $camel_case_conversion_exceptions;
          if( !is_null($camel_case_conversion_exceptions) )
          {
            $exceptions =& $camel_case_conversion_exceptions;
          }
        }

        ob_start();
        print array_shift($words);
        foreach( $words as $word )
        {
          $converted = ucfirst($word);
          print (empty($exceptions) || !array_key_exists($converted, $exceptions)) ? $converted : $exceptions[$converted];
        }
        $converted = ob_get_clean();
        return (empty($exceptions) || !array_key_exists($converted, $exceptions)) ? $converted : $exceptions[$converted];
      }
      else
      {
        return $identifier;
      }
    }
    
    
    
    static function convert_pascal_to_snake_case( $identifier )   // Given a string in PascalCase, returns one in snake_case
    {
      $string = strtolower(preg_replace('/([A-Z])/', '_$1', $identifier));
      return substr($string, 0, 1) == "_" ? substr($string, 1) : $string;
    }
    
    
    static function convert_camel_to_snake_case( $identifier )    // Given a string in camelCase, returns one in snake_case
    {
      static $lookup = null;
      is_null($lookup) and $lookup = array();
    
      if( !isset($lookup[$identifier]) )
      {
        $words = preg_split("/(?=[A-Z])/", $identifier);
        $lookup[$identifier] = implode("_", array_map("strtolower", $words));
      }
    
      return $lookup[$identifier];
    }
    
    
    
    
  //===============================================================================================
  // SECTION: Tree converters
  
  
    static function convert_snake_object_to_camel_assoc( $datum )
    {
      if( is_object($datum) || (is_array($datum) && (reset($datum) || true) && is_string(key($datum))) )
      {
        $converted = array();
        foreach( $datum as $key => $value )
        {
          $converted[static::convert_snake_to_camel_case($key)] = static::convert_snake_object_to_camel_assoc($value);
        }

        return $converted;
      }
      elseif( is_array($datum) )
      {

        $converted = array();
        foreach( $datum as $key => $value )
        {
          $converted[$key] = static::convert_snake_object_to_camel_assoc($value);
        }

        return $converted;
      }
      else
      {
        return $datum;
      }
    }
    
  
  
    // Recursively converts an associative array with camelCase names to an object with snake_case
    // names. Handles arrays of, too -- though you will need to set $hash_levels if using container
    // associative arrays you don't want converted.

    function convert_camel_assoc_to_snake_object( $datum, $hash_levels = 0 )
    {
      if( is_array($datum) )
      {
        $converted = null;
        foreach( $datum as $key => $value )
        {
          if( is_null($converted) )
          {
            $converted = is_numeric($key) || $hash_levels > 0 ? array() : new stdClass;
          }

          if( is_array($converted) )
          {
            $converted[$key] = static::convert_camel_assoc_to_snake_object($value, $hash_levels - 1);
          }
          else
          {
            $key = static::convert_camel_to_snake_case($key);
            $converted->$key = static::convert_camel_assoc_to_snake_object($value, $hash_levels - 1);
          }
        }

        return $converted;
      }

      return $datum;
    }
  }
