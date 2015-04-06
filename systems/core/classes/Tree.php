<?php

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


  // A tree of stuff. Provides a filesystem-path-like interface for traversing the tree.
  //
  // If you have a general structure you want to use as the initial state, supply an initializer
  // callback and it will be called for you every time the tree is reset().
  //
  // Paths can take two forms:
  //   1) a filesystem-like a/b/c path (/a/b/c, ../b/c, a/b/./c, etc.) in a string
  //   2) an array of specific path tokens (first element null makes it an absolute path)
  //
  // The tree is automatically filled in for you on use, so you can safely access non-existant 
  // paths. Be sure to use appropriate type exemplars to ensure you get what you are expecting.
  //
  // You will generally use relative paths when accessing elements. With enter_root() and 
  // related routines, you can alter the current root object in the tree, against which those
  // paths will be interpreted.

  class Tree
  {
    protected $data;
    protected $initializer;
    protected $contexts;
    
    function __construct( $initializer = null )
    {
      $this->initializer = $initializer;
      $this->reset();
    }


    //
    // Resets the response to its empty state.

    function reset()
    {
      $this->contexts = array(array(null));
      $this->data     = array();
      
      if( $this->initializer )
      {
        Callback::do_call($this->initializer, $this);
      }
    }




  //===============================================================================================
  // SECTION: Tree management.


    function get( $path, $default = null )
    {
      $steps = $this->canonicalize_path($path);
      return tree_fetch($this->data, $steps, $default);
    }
    

    function set( $path, $value )
    {
      list($container_path, $key) = $this->split_path($path);
      is_object($value) and $value = get_object_vars($value);

      $container =& $this->get_container($container_path);
      if( is_object($container) )
      {
        $container->$key = $value;
      }
      else
      {
        $container[$key] = $value;
      }
    }


    function delete( $path )
    {
      list($container_path, $key) = $this->split_path($path);

      $container =& $this->get_container($container_path);
      if( is_object($container) )
      {
        unset($container->$key);
      }
      else
      {
        unset($container[$path]);
      }
    }


    function append( $path, $value )         // Appends $value to an array at $path. [] is added to $path if not present.
    {
      $container =& $this->get_container($path);
      $container[] = $value;
    }


    function merge( $data, $path = null )     // Merges the data from the supplied object into the container at $path.
    {
      $scope = $path ? $this->with_root($path) : null;
      foreach( $data as $name => $value )
      {
        $this->set($name, $value);
      }
    }


    function increment( $path, $value = 1 )  // Increments $path by $value (can be negative).
    {
      list($container_path, $key) = $this->split_path($path);
      $container =& $this->get_container($container_path);
      
      if( is_object($container) )
      {
        if( isset($container->$key) and is_numeric($container->$key) )
        {
          $container->$key += $value;
        }
        else
        {
          $container->$key = $value;
        }
      }
      else
      {
        if( isset($container[$key]) and is_numeric($container[$key]) )
        {
          $container[$key] += $value;
        }
        else
        {
          $container[$key] = $value;
        }
      }
    }






  //===============================================================================================
  // SECTION: Root management.
  
  
    function with_root( $path )        // Equivalent to push_root(), but returns an object that calls pop_root() for you when it passes out of scope.
    {
      $steps = $this->canonicalize_path($path);
      return new Tree_RootScope($this, $steps);
    }
    

    function push_root( $path )        // Pushes a new root onto the root stack. Relative paths are relative the current root.
    {
      $this->contexts[] = $this->canonicalize_path($path);
    }


    function pop_root( $path = null )  // Pops the top root off the root stack.
    {
      array_pop($this->contexts);
    }








  //===============================================================================================
  // SECTION: Internals.


    function canonicalize_path( $path )
    {
      $steps = count($this->contexts) > 0 ? $this->contexts[count($this->contexts) - 1] : array(null);
      
      if( is_string($path) )                             // strings get parsed like a filesystem path
      {
        $path = preg_replace('/\/{2,}/', '/', $path);    // eliminate double slashes

        if( substr($path, 0, 1) == "/" )                 // it's an absolute path
        {
          $steps = array(null);                          // start at the root
          $path  = substr($path, 1);                     // eat the leading /
        }
        
        if( $path )                                      // it's (now) a relative path
        {
          $pieces = explode("/", $path);
          foreach( $pieces as $piece )
          {
            if( $piece == "." or $piece == "" )
            {
              /* no op */
            }
            elseif( $piece == ".." )
            {
              $count = count($steps);
              $count > 0 and array_pop($steps);
            }
            else
            {
              $steps[] = $piece;
            }
          }
        }
      }
      else                                               // arrays are used verbatim
      {
        if( count($path) > 0 and $path[0] == null )      // it's an absolute path
        {
          $steps = $path;
        }
        else                                             // otherwise it's relative
        {
          $steps = array_merge($steps, array_values($path));
        }
      }

      return $steps;
    }
    
    
    function split_path( $path )
    {
      $steps = $this->canonicalize_path($path);
      $key   = array_pop($steps);
      
      return array($steps, $key);
    }
    
    
    function &get_container( $path )    // returns an array or an object; fills in the tree as necessary
    {
      $container =& $this->data;
      $steps = $this->canonicalize_path($path);   
      
      foreach( array_slice($steps, 1) as $step )   // first element is always null
      {
        if( is_object($container) )
        {
          if( !isset($container->$step) or is_scalar($container->$step) )
          {
            $container->$step = array();
          }
          
          $container = $container->$step;
        }
        else
        {
          if( !isset($container[$step]) or is_scalar($container[$step]) )
          {
            $container[$step] = array();
          }

          $container =& $container[$step];
        }
      }
      
      return $container;
    }
  }




  class Tree_RootScope
  {
    function __construct( $tree, $path )
    {
      $this->path = $path;
      $this->tree = $tree;

      $this->tree->push_root($path, $return_scope = false);
    }

    function __destruct()
    {
      $this->tree->pop_root($this->path);
    }
  }
  
  
