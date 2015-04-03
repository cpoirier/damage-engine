<?php if (defined($inc = "ENGINE_SIMPLIFY_SCRIPT_NAME_INCLUDED")) { return; } else { define($inc, true); }

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


  function simplify_script_name( $name = null, $limit = 50 )
  {
    $name or $name = substr($_SERVER["REDIRECT_URL"], 1);


    // index.php is not often helpful. Discard it unless that's all we've got.

    if( $name != "/index.php" && substr($name, -10) == "/index.php" )
    {
      $name = substr($name, 0, -10);
    }


    // Trim off leading directories until we can fit it in $limit characters.

    while( strlen($name) > $limit && is_numeric(strpos($name, "/")) )
    {
      list($discard, $name) = explode("/", $name, 2);
    }


    // And just plain truncate it to $limit characters, if still over.

    if( strlen($name) > $limit )
    {
      $name = substr($name, 0, $limit);
    }

    return $name;
  }
