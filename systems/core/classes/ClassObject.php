<?php if (defined($inc = "CORE_CLASSOBJECT_INCLUDED")) { return; } else { define($inc, true); }

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


  class ClassObject
  {
    public $class;
    
    static function objectify( $class )
    {
      return is_string($class) ? new static($class) : $class;
    }

    function __construct( $class )
    {
      $this->class = $class;
    }
    
    function instantiate()
    {
      $args  = func_get_args();
      $count = count($args);
      $class = $this->class;
      switch($count)
      {
        case  0: return new $class();
        case  1: return new $class($args[0]);
        case  2: return new $class($args[0], $args[1]);
        case  3: return new $class($args[0], $args[1], $args[2]);
        case  4: return new $class($args[0], $args[1], $args[2], $args[3]);
        case  5: return new $class($args[0], $args[1], $args[2], $args[3], $args[4]);
        case  6: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);
        case  7: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6]);
        case  8: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7]);
        case  9: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8]);
        case 10: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9]);
        case 11: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9], $args[10]);
        case 12: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9], $args[10], $args[11]);
        case 13: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9], $args[10], $args[11], $args[12]);
        case 14: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9], $args[10], $args[11], $args[12], $args[13]);
        case 15: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9], $args[10], $args[11], $args[12], $args[13], $args[14]);
        case 16: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9], $args[10], $args[11], $args[12], $args[13], $args[14], $args[15]);
        case 17: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9], $args[10], $args[11], $args[12], $args[13], $args[14], $args[15], $args[16]);
        case 18: return new $class($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9], $args[10], $args[11], $args[12], $args[13], $args[14], $args[15], $args[16], $args[17]);
        default: 
          abort("add support for $count parameters");
      }
    }

    function __call( $name, $args )
    {
      return call_user_func_array(array($this->class, $name), $args);
    }

    function __get( $name )
    {
      $class = $this->class;
      return $class::$$name;
    }

    function __set( $name, $value )
    {
      $class = $this->class;
      $class::$$name = $value;
    }
  }
