<?php if (defined($inc = "CORE_PATHMANAGER_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class PathManager
  {
    static $include_paths     = null;
    static $class_directories = null;
    
    static function add_directory( $path, $append = true )
    {
      if( file_exists($path) )
      {
        if( !in_array($path, $include_paths) )
        {
          file_exists($functions_path = "$path/functions") or $functions_path = $path;
          $append ? array_push(static::$include_paths, $functions_path) : array_unshift(static::$include_paths, $functions_path);
    
          set_include_path(implode(PATH_SEPARATOR, static::$include_paths));
        }
      }
    }
    
    static function initialize()
    {
      static::$include_paths = array_filter(explode(PATH_SEPARATOR, get_include_path()));
      static::$class_directories = array("classes", "exceptions", "templates", "projectors");
    }
  }
  
  PathManager::initialize();