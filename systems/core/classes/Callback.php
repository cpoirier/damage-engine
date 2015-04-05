<?php if (defined($inc = "CORE_CALLBACK_INCLUDED")) { return; } else { define($inc, true); }

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


  class Callback    // Captures everything needed to issue a callback into a single, convenient package.
  {
    static function do_call( $callback )
    {
      $parameters = array_slice(func_get_args(), 1);
      return static::do_call_with_array($callback, $parameters);
    }
  
    static function do_call_with_array( $callback, $parameters = array() )
    {
      if( is_a($callback, "Callback") )
      {
        return $callback->call($parameters);
      }
      elseif( is_string($callback) and strpos(' ', $callback) )
      {
        return Callback::do_call_with_array(Callback::for_snippet($callback), $parameters);
      }
      else
      {
        return call_user_func_array($callback, $parameters);
      }
    }
  
    


  //===============================================================================================
  // SECTION: Generators for standard PHP stuff
  
    
    static function for_method( $object, $method_name )   // Returns a Callback on an instance method. Additional parameters are passed along.
    {
      return static::for_method_with_array($object, $method_name, array_slice(func_get_args(), 2));
    }

    static function for_method_with_dynamic_offset( $object, $method_name, $dynamic_offset )
    {
      return static::for_method_with_array($object, $method_name, array_slice(func_get_args(), 3), $dynamic_offset);
    }

    static function for_method_with_no_dynamic_parameters( $object, $method_name )
    {
      return static::for_method_with_array($object, $method_name, array_slice(func_get_args(), 2), $dynamic_offset = null);
    }




    static function for_method_of_property( $object, $property_name, $method_name )   // Returns a Callback on an instance method of a property of your object. Additional parameters are passed along.
    {
      return static::for_method_of_property_with_array($object, $property_name, $method_name, array_slice(func_get_args(), 3));
    }




    static function for_function( $function_name )    // Returns a Callback on a function. Additional parameters are passed along.
    {
      $args = func_get_args();
      return static::for_function_with_array($function_name, array_slice($args, 1));
    }




    static function for_constructor( $class_name )    // Returns a Callback on an object constructor. Additional parameters are passed along.
    {
      $args = func_get_args();
      return static::for_constructor_with_array($class_name, array_slice($args, 1));
    }




    static function for_method_with_array( $object, $method_name, $parameters, $dynamic_offset = -1 )    // Returns a Callback on an instance method. Pass parameters in an array.
    {
      return new static(array($object, $method_name), $parameters, $dynamic_offset);
    }


    static function for_function_with_array( $function_name, $parameters, $dynamic_offset = -1 )    // Returns a Callback on a function. Pass parameters in an array.
    {
      return new static($function_name, $parameters, $dynamic_offset);
    }


    static function for_constructor_with_array( $class_name, $parameters, $dynamic_offset = -1 )    // Returns a Callback on an object constructor. Pass parameters in an array.
    {
      return new static(array("new", $class_name), $parameters, $dynamic_offset);
    }


    static function for_method_of_property_with_array( $object, $property_name, $method_name, $parameters, $dynamic_offset = -1 )    // Returns a Callback on an instance method of a property of your object. Pass parameters in an array.
    {
      return new static(array("deref", $object, $property_name, $method_name), $parameters, $dynamic_offset);
    }




  //===============================================================================================
  // SECTION: Generators for snippets (ruby-closure-like code strings)
  
    
    static function for_snippet( $code )    // Callback::for_snippet('|$a, $b| $a * $b')   EQ   Callback::for_snippet('|$a, $b| return $a * $b;');
    {
      if( func_num_args() > 1 )
      {
        if( $binding = array_pair_slice(func_get_args(), 1) )
        {
          return static::for_snippet_with_binding($code, $binding);
        }
      }
    
      if( !array_key_exists($code, static::$compiled_snippets) )
      {
        list($parameters, $body) = static::parse_snippet($code);
        static::$compiled_snippets[$code] = create_function($parameters, $body);
      }

      return static::$compiled_snippets[$code];
    }
  
  
    static function for_snippet_with_binding( $code, $binding )   // Callback::for_snippet_with_binding('$a: $a * $c', 'c', $c);
    {
      if( empty($binding) )
      {
        return static::for_snippet($code);
      }
    
      if( !array_key_exists($code, static::$compiled_bound_snippets) )
      {
        list($parameters, $body) = static::parse_snippet($code);

        $parameters = empty($parameters) ? '$_binding' : sprintf('$_binding, %s', $parameters);
        $preamble   = 'foreach ($_binding as $_n => $_v) { $$_n = $_v; }';
        $body       = sprintf("%s\n\n%s", $preamble, $body);

        static::$compiled_bound_snippets[$code] = create_function($parameters, $body);
      }
    
      return static::for_function_with_array(static::$compiled_bound_snippets[$code], array($binding));
    }

  
    protected static function parse_snippet( $code )
    {
      $m = null;
      preg_match('/^\s*(|[^|]*|)?(.*)/', $code, $m);
      $parameters = substr((string)@$m[1], 1, -1);
      $body       = trim((string)@$m[2]);
      $last_char  = substr($expression, -1, 1);
    
      if( $last_char != ';' and $last_char != '}' )    // If not well-formed PHP, adjust it; only useful for simple expressions!
      {
        $body .= ';';
        preg_match('/(return|echo) ([^;}]+);$/', $body) or $body = sprintf("return %s", $body);
      }
    
      return array($parameters, $body);
    }
  
  
  
  
  
  //===============================================================================================
  // SECTION: Innards
    
    function __construct( $method_address, $parameters, $dynamic_offset = -1 )
    {
      $this->method_address = $method_address;
      $this->parameters     = $parameters;
      $this->dynamic_offset = $dynamic_offset;
    }

    function get_php_callback()
    {
      return array($this, "call_direct");
    }

    function call_direct()
    {
      return $this->call(func_get_args());
    }


    function call( $dynamic_parameters = array() )
    {
      $parameters = $this->parameters;
      if( is_null($this->dynamic_offset) )
      {
        // Discard the dynamic parameters
      }
      elseif( $this->dynamic_offset == -1 )
      {
        $parameters = array_merge($parameters, $dynamic_parameters);                    // Append them
      }
      elseif( $this->dynamic_offset < 0 )
      {
        array_splice($parameters, $this->dynamic_offset + 1, 0, $dynamic_parameters);   // Insert after the nth last element
      }
      else
      {
        array_splice($parameters, $this->dynamic_offset, 0, $dynamic_parameters);       // Insert them at the nth index
      }

      if( is_array($this->method_address) && $this->method_address[0] == "new" )
      {
        $class_name = $this->method_address[1];
        switch( $count = count($parameters) )
        {
          case 0:                                                     return new $class_name();
          case 1: list($a)                             = $parameters; return new $class_name($a);
          case 2: list($a, $b)                         = $parameters; return new $class_name($a, $b);
          case 3: list($a, $b, $c)                     = $parameters; return new $class_name($a, $b, $c);
          case 4: list($a, $b, $c, $d)                 = $parameters; return new $class_name($a, $b, $c, $d);
          case 5: list($a, $b, $c, $d, $e)             = $parameters; return new $class_name($a, $b, $c, $d, $e);
          case 6: list($a, $b, $c, $d, $e, $f)         = $parameters; return new $class_name($a, $b, $c, $d, $e, $f);
          case 7: list($a, $b, $c, $d, $e, $f, $g)     = $parameters; return new $class_name($a, $b, $c, $d, $e, $f, $g);
          case 8: list($a, $b, $c, $d, $e, $f, $g, $h) = $parameters; return new $class_name($a, $b, $c, $d, $e, $f, $g, $h);
          default:
            abort("NYI: support for constructors with $count parameters");
        }
      }
      elseif( is_array($this->method_address) && $this->method_address[0] == "deref" )
      {
        list($object, $property_name, $method_name) = array_slice($this->method_address, 1);
        foreach( (array)$property_name as $name )
        {
          $object = $object->$name;
        }

        return empty($parameters) ? $object->$method_name() : call_user_func_array(array($object, $method_name), $parameters);
      }
      else
      {
        return empty($parameters) ? call_user_func($this->method_address) : call_user_func_array($this->method_address, $parameters);
      }
    }

    static function describe( $callback )
    {
      if( is_a($callback, "Callback") )
      {
        $path = array();
        foreach( $callback->method_address as $piece )
        {
          if( is_object($piece) )
          {
            $path[] = get_class($piece);
          }
          else
          {
            $path[] = @(string)$piece;
          }
        }
      
        return implode(":", $path);
      }
      else
      {
        return $callback;
      }
    }
  }


  class Callback_Break extends Exception
  {
  }



//===============================================================================================
// SECTION: Give them all some sugar

  
  function m( $object, $method_name )
  {
    return Callback::for_method_with_array($object, $method_name, array_slice(func_get_args(), 2), $dynamic_offset = -1);
  }

  function n( $object, $method_name, $dynamic_offset )
  {
    return Callback::for_method_with_array($object, $method_name, array_slice(func_get_args(), 3), $dynamic_offset);
  }

  function f( $function_name )
  {
    return Callback::for_function_with_array($function_name, array_slice(func_get_args(), 1), $dynamic_offset = -1);
  }

  function g( $function_name, $dynamic_offset )
  {
    return Callback::for_function_with_array($function_name, array_slice(func_get_args(), 2), $dynamic_offset);
  }
  
  function s( $code )
  {
    return Callback::for_snippet_with_binding($code, array_pair_slice(func_get_args(), 1));
  }
  
  function call()
  {
    $args     = func_get_args();
    $callback = array_shift($args);
    
    return Callback::do_call($callback, $args);
  }
  
  function break_call()
  {
    throw new Callback_Break();
  }
  
  
  