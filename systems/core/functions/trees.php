<?php if (defined($inc = "TREES_INCLUDED")) { return; } else { define($inc, true); }

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


  // $a = array("m" => array("b" => (object)array("x" => 10, "y" => 20), "c" => (object)array("x" => 30, "y" => 40)));
  //
  // tree_fetch($a, array("m", "c", "x"),     100);   // 30
  // tree_fetch($a, array("m", "d", "x"),     100);   // 100
  // tree_fetch($a, array("m", "c")     , array());   // array("x" => 30, "y" => 40)
  // tree_fetch($a, array("m", "d")     , array());   // array()
  // tree_fetch($a, array("m", "c")     ,    null);   // (object)array("x" => 30, "y" => 40)
  // tree_fetch($a, array("m", "d")     ,    null);   // null
  // tree_fetch($a, "m"                 ,    null);   // array("b" => (object)array("x" => 10, "y" => 20), "c" => (object)array("x" => 30, "y" => 40))

  function tree_fetch( $tree, $path, $default = null )  // Given a tree, a default, and a list of offsets, returns the value from that coordinate within the tree, coerced to the same type as the default, or the default.
  {
    $path    = (array)$path;
    $found   = !empty($path);
    $current = $tree;
  
    foreach( $path as $key )
    {
      if( is_null($key) )                     // null keys are no-ops
      {
        continue;    
      }
      elseif( is_array($current) )
      {
        if( @array_key_exists($key, $current) )
        {
          $current =& $current[$key];
        }
        else
        {
          $found = false;
          break;
        }
      }
      elseif( is_object($current) )
      {
        if( isset($current->$key) )
        {
          $current = $current->$key;
        }
        else
        {
          $found = false;
          break;
        }
      }
      else
      {
        $found = false;
        break;
      }
    }
  
    return $found ? TypeConverter::coerce_type($current, $default) : $default;
  }


  // $a = array("m" => array("b" => (object)array("x" => 10, "y" => 20), "c" => (object)array("x" => 30, "y" => 40)));
  // 
  // tree_has($a, array("m", "c", "x"));   // true
  // tree_has($a, array("m", "d", "x"));   // false
  // tree_has($a, array("m", "c")     );   // true
  // tree_has($a, array("m", "d")     );   // false
  // tree_has($a, "m"                 );   // true
  
  function tree_has( $tree, $path )   // Given a tree and a list of offsets, returns true if that coordinate exists within the tree.
  {
    $path    = (array)$path;
    $found   = !empty($path);
    $current = $tree;
    
    foreach( $path as $key )
    {
      if( is_array($current) )
      {
        if( array_key_exists($key, $current) )
        {
          $current = $current[$key];
        }
        else
        {
          $found = false;
          break;
        }
      }
      elseif( is_object($current) )
      {
        if( isset($current->$key) )
        {
          $current = $current->$key;
        }
        else
        {
          $found = false;
          break;
        }
      }
      else
      {
        $found = false;
        break;
      }
    }
    
    return $found;
  }
  
  