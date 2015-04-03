<?php if (defined($inc = "CORE_SORTERS_INCLUDED")) { return; } else { define($inc, true); }

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


  function order_ascending( $a, $b )
  {
    return ($a == $b) ? 0 : ($a < $b ? -1 : 1);
  }

  function order_descending( $a, $b )
  {
    return ($a == $b) ? 0 : ($a < $b ? 1 : -1);
  }


  function order_by_value( $a, $b )
  {
    if( $a < $b )
    {
      return -1;
    }
    elseif( $a > $b )
    {
      return 1;
    }
    else
    {
      if( func_num_args() > 2 )
      {
        $args  = func_get_args();
        $chain = array_slice($args, 2);
        
        return call_user_func_array("order_by_value", $chain);
      }
      else
      {
        return 0;
      }
    }
  }
  
  
  function order_by_property( $a, $b, $property )
  {
    $sign = 1;
    is_array($property) and list($property, $sign) = $property;

    if( $a->$property < $b->$property )
    {
      return $sign * -1;
    }
    elseif( $a->$property > $b->$property )
    {
      return $sign * 1;
    }
    else
    {
      if( func_num_args() > 3 )
      {
        $args  = func_get_args();
        $chain = array_merge(array($a, $b), array_slice($args, 3));
        
        return call_user_func_array("sort_compare_by_property", $chain);
      }
      else
      {
        return 0;
      }
    }
  }
  
  
  
  function order_by_count_asc( $a, $b )
  {
    return sort_compare(count($a), count($b));
  }
  
  
  function order_by_count_desc( $a, $b )
  {
    return -1 * order_by_count_asc($a, $b);
  }
  