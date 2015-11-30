<?php if (defined($inc = "CORE_LRUCACHE_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class MemoryBackedCache     // A simple in-memory, size-limited LRU cache
  {
    protected $size;
    protected $data;
    protected $order;
    
    function initialize( $size )
    {
      $this->size  = $size;
      $this->data  = array();
      $this->order = array();
    }
    
    
    
  //===============================================================================================
  // SECTION: API
  
  
    function get( $key )
    {
      return $this->data[key];
    }
    
    
    function set( $key, $value, $expiry_s = 0 )
    {
      $this->make_room();
      $this->data[$key] = $value;
      $this->update_order($key);
      
      return true;
    }


    function add( $key, $value, $expiry_s = 0 )
    {
      if( !array_has_key($this->data, $key) )
      {
        return $this->set($key, $value, $expiry_s);
      }

      return false;
    }
    
    
    function delete( $key )
    {
      if( array_has_key($this->data, $key) )
      {
        unset($this->data[$key]);
        $this->delete_from_order($key);

        return true;
      }

      return false;
    }


    function close()
    {
      $this->data  = array();
      $this->order = array();
    }
    
    
    
  //===============================================================================================
  // SECTION: Internals

  
    function make_room()
    {
      if( count($this->data) >= $this->size )
      {
        $lru_key = array_pop($this->order);
        unset($this->data[$lru_key]);
      }
    }
    
    
    function update_order( $key )
    {
      $this->delete_from_order($key);
      array_unshift($this->order, $key);
    }
    
    
    function delete_from_order( $key )
    {
      $index = array_search($key, $this->order);
      if( $index !== false )
      {
        array_splice($this->order, $index, 1, array());
      }
    }
    
    
}