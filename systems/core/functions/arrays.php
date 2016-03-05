<?php if (defined($inc = "ARRAYS_INCLUDED")) { return; } else { define($inc, true); }

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

  require_once __DIR__ . "/trees.php";

  function array_flatten( &$array )
  {
    $container = new ArrayFlattenHelper();
    $callback  = array($container, "append");
    array_walk_recursive($array, $callback);

    return $container->flattened;
  }

  class ArrayFlattenHelper
  {
    function __construct()
    {
      $this->flattened = array();
    }

    function append( $element, $ignored )
    {
      $this->flattened[] = $element;
    }
  }
  
  function array_fetch_value( $array, $key, $default = null )
  {
    $value = $default;
  
    if( is_array($array) )
    {
      if( @array_key_exists($key, $array) )
      {
        $value = TypeConverter::coerce_type($array[$key], $default);
      }
    }
    else
    {
      $value = tree_fetch($array, $key, $default);
    }

    return $value;
  }


  function array_has_member( $array, $key )
  {
    if( is_array($array) )
    {
      return array_key_exists($key, $array);
    }
    else
    {
      return tree_has($array, $key);
    }
  }
  
  
  function array_fetch_first_key( $array )
  {
    $key = null;
    
    if( is_array($array) and !empty($array) )
    {
      reset($array);
      $key = key($array);
    }
    
    return $key;
  }
  

  function array_fetch_first( $array, $default = null )    // Returns the first value of the array (even in assoc arrays)
  {
    $first = $default;
    $key   = array_fetch_first_key($array);
    
    if( $key != null )
    {
      $first = $array[$key];
    }
    
    return TypeConverter::coerce_type($first, $default);
  }
  
  
  function array_fetch_property( $array, $property, $keep_keys = true )   // Given an array of objects, returns the values of a property on each
  {
    $results = array();
    foreach( $array as $key => $item )
    {
      if( is_array($item) ) 
      { 
        $value = array_fetch_value($item, $property); 
      }
      else 
      { 
        $value = $item->$property; 
      }
      
      if( $keep_keys )
      {
        $results[$key] = $value;
      }
      else
      {
        $results[] = $value;
      }
    }
    
    return $results;
  }
  
  

  function array_fetch_random_value( $array, $default = null )    // Returns a random value from an array
  {
    if( $array )
    {
      $index = mt_rand(0, count($array)-1);
      return array_fetch_value($array, $index, $default);
    }
  
    return $default;
  }
  
  
  
  function array_fetch_random_values( &$array, $count )    // Returns some number of random values an array
  {
    $values = array();
    if( !empty($array) )
    {
      if( $keys = (array) array_rand($array, min($count, count($array))) )
      {
        foreach( $keys as $key )
        {
          $values[] = $array[$key];
        }
      }
    }

    return $values;
  }


  function array_merge_keys( $first, $second )
  {
    $result = array();
    foreach( $first as $key => $value )
    {
      $result[$key] = $value;
    }
    foreach( $second as $key => $value )
    {
      $result[$key] = $value;
    }
    return $result;
  }

  
  function array_pair( $array )   // Returns a map produced by using alternating elements for key and value. If the last (unpaired) value in the array is a map or object, it will be used as defaults for any pair not already set.
  {
    is_scalar($array) and $array = func_get_args();
  
    $pairs = array();
    while( !empty($array) )
    {
      if( count($array) == 1 and array_key_exists(0, $array) and (is_null($array[0]) or is_array($array[0]) or is_object($array[0])) )
      {                                                                                            // handle array(array("a" => "b"))
        $tail  = array_shift($array);
        $pairs = array_merge(is_object($tail) ? get_object_vars($tail) : (array)$tail, $pairs);
      }
      else if( is_string(array_fetch_first_key($array)) )                                          // handle array("a" => "b")
      {
        $pairs = array_merge($array, $pairs);
        $array = array();
      }
      else                                                                                         // handle array("a", "b")
      {
        $key   = array_shift($array);
        $value = @array_shift($array);
        
        $pairs[(string)$key] = $value;
      }
    }

    return $pairs;
  }


  function array_pair_slice( $args, $from, $length = null )   // Pairs a slice of the passed array
  {
    return array_pair(array_slice($args, $from, $length));
  }
  
  
  function array_unpack( $array )
  {
    if( count($array) == 1 and array_key_exists(0, $array) )
    {
      return array_unpack($array[0]);
    }
    
    return $array;
  }
  
  
  