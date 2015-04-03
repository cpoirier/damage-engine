<?php if (defined($inc = "CORE_DEFERRED_OBJECT_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class DeferredObject
  {
    private $_loader;
    private $_target;
    
    static function wrap( $loader )
    {
      return new static($loader);
    }
    
    function __construct( $loader )
    {
      $this->_loader = $loader;
      $this->_target = null;
    }
    
    function &__get( $name )
    {
      $this->_realize();
      
      $value =& $this->_target->$name;
      return $value;
    }
    
    function __set( $name, $value )
    {
      $this->_realize();
      $this->_target->$name = $value;
    }
    
    function __call( $name, $args )
    {
      $this->_realize();
      return call_user_func_array(array($this->_target, $name), $args);
    }
    
    
    private function _realize()
    {
      if( !$this->_target )
      {
        $this->_target = Callback::do_call($this->_loader);
      }
    }
  }