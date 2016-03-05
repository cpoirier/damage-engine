<?php
//
// Copyright 2011 1889 Labs (contact: chris@courage-my-friend.org)
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.
//
// ============================================================================
//
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



  function make_query_string( $parameters, $marker = "?" )
  {
    $pairs = array();
    foreach( $parameters as $name => $value )
    {
      if( is_array($value) )
      {
        foreach( $value as $v )
        {
          $pairs[] = $name . "[]=" . rawurlencode($v);
        }
      }
      elseif( is_object($value) )
      {
        trigger_error("BUG: received an object in make_query_string()", E_USER_ERROR);
      }
      else
      {
        $pairs[] = "$name=" . rawurlencode($value);
      }
    }

    return empty($pairs) ? "" : "$marker" . implode("&", $pairs);
  }
