<?php if (defined($inc = "CORE_CLASSMANAGER_INCLUDED")) { return; } else { define($inc, true); }

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


  if( !defined("DOCUMENT_ROOT_MADE_REAL") )
  {
    $_SERVER["DOCUMENT_ROOT"] = realpath($_SERVER["DOCUMENT_ROOT"]);
    define("DOCUMENT_ROOT_MADE_REAL", true);
  }


  class_exists("Features", $autoload = false) or require_once __DIR__ . "/Features.php";
  class_exists("Callback", $autoload = false) or require_once __DIR__ . "/Callback.php";

  class ClassManager
  {
    static function add_directory( $path, $append = true )
    {
      $path or abort("add_directory_path_empty");
      $append ? array_push(static::$roots, $path) : array_unshift(static::$roots, $path);
    
      static::$roots_signature = hash("md5", implode("\n", static::$roots));
      static::$index           = null;
      static::$identifier      = null;
    }
    
    static function add_epoch_source( $path )
    {
      static::$epoch_sources[$path] = $path;
      static::$epoch_signature      = hash("md5", implode("\n", array_values(static::$epoch_sources)));
      static::$identifier           = null;
    }
    
    
    static function get_code_epoch()
    {                                                                                                                            $_ = TRACE_ENTRY(__METHOD__);      
      if( empty(static::$epoch) )
      {
        static::$epoch = 0;
        foreach( static::$epoch_sources as $source )
        {
          file_exists($source) and static::$epoch = latest_of(static::$epoch, filectime($source));
        }
        
        if( Features::enabled("development_mode") or static::$epoch == 0 )
        {                                                                                                                             DISABLE_TRACING();
          static::walk_roots(Callback::for_method(__CLASS__, "add_to_epoch"));                                                        RESTORE_TRACING(); TRACE(__METHOD__, "epoch is: " . date("Y-m-d H:i:s", static::$epoch));
        }
      
        if( Features::enabled("log_class_loader_epoch") or Features::enabled("log_class_loader_epoch_occassionally") and time() % 300 == 0 and mt_rand(0, 10) == 37 )
        {
          TRACE(__METHOD__, "epoch", date("Y-m-d H:i:s", static::$epoch));
        }
      }
    
      return static::$epoch;
    }
  
    static function get_code_id()
    {
      if( empty(static::$identifier) )
      {
        static::$identifier = sprintf("%s.%d", static::$roots_signature, static::$epoch);
      }
      
      return static::$identifier;
    }
    
  
  
  //===============================================================================================
  // SECTION: Class information
  
  
    static function is_loadable( $class_name )
    {
      static::build_index();
      return static::$index->is_class_in_index($class_name);
    }
    
    
    static function is_loaded( $class_name )
    {
      return class_exists($class_name, $autoload = false);
    }
    
    
    static function get_classes_matching( $pattern )
    {
      $callback = Callback::for_function("preg_match", $pattern);
      $matches  = static::$index->select_matching($callback);
      
      return array_keys($matches);
    }
    
    
    static function pick_class()
    {
      $args = func_get_args();
      foreach( array_flatten($args) as $name )
      {
        if( static::is_loadable($name) )
        {
          return $name;
        }
      }

      return null;
    }
  
  
    static function pick_class_or_abort()
    {
      foreach( func_get_args() as $name )
      {
        if( static::is_loadable($name) )
        {
          return $name;
        }
      }

      abort("couldn't find class");
    }


    static function get_pedigree( $class_name )
    {
      is_object($class_name) and $class_name = get_class($class_name);

      if( !isset(static::$pedigrees[$class_name]) and static::is_loadable($class_name) )
      {
        static::$pedigrees[$class_name] = array();
      
        $current = $class_name;
        do
        {
          static::$pedigrees[$class_name][] = $current;
        } while( $current = get_parent_class($current) );
      }
    
      return array_fetch_value(static::$pedigrees, $class_name);
    }
  
  
    static function get_snake_case_pedigree( $class_name )
    {
      is_object($class_name) and $class_name = get_class($class_name);
      if( !isset(static::$snake_pedigrees[$class_name]) and static::is_loadable($class_name) )
      {
        if( $pedigree = static::get_pedigree($class_name) )
        {
          static::$snake_pedigrees[$class_name] = array_map('convert_pascal_to_snake_case', $pedigree);
        }
      }

      return array_fetch_value(static::$snake_pedigrees, $class_name);
    }
  
  
    static function sprintf_with_pedigree( $format, $class_name, $snake_case = true, $be_tolerant = false )
    {
      $names = array();
      $method = $snake_case ? "get_snake_case_pedigree" : "get_pedigree";
      if( $class_names = static::$method($class_name) )
      {
        foreach( $class_names as $string )
        {
          foreach( (array)$format as $f )
          {
            $names[] = sprintf($f, $string);
          }
        }
      }
      elseif( $be_tolerant )
      {
        foreach( (array)$format as $f )
        {
          $names[] = sprintf($f, $snake_case ? convert_pascal_to_snake_case($class_name) : $class_name);
        }
      }

      return $names;
    }



  //===============================================================================================
  // SECTION: Class loader
  
    static function load_class( $class_name )
    {
      $full_class_name = $class_name;
      if( strpos($class_name, "\\") )
      {
        $pieces = explode("\\", $class_name);
        $class_name = array_pop($pieces);
      }

      static::build_index($class_name);
      if( static::is_reasonable_filename($class_name) )
      {
        // We support to class name => file name mappings:
        //   ClassName => ClassName.php
        //   ClassName_Specialiation => ClassName.php

        $names = array($class_name);
        strpos($class_name, "_") and $pieces = explode("_", $class_name, 2) and $names[] = array_shift($pieces);

        // Search the index.

        foreach( $names as $name )
        {
          if( static::is_loadable($name) )
          {
            Script::safe_require_once(static::$index->get_path_to_class($name));
            static::is_loaded($full_class_name) or Script::fail("class_not_present_in_class_file", array("class_name" => $full_class_name));
            return true;
          }
        }
    
        // In general, we aren't asked to load stuff that doesn't exist. If it isn't in the
        // index, the index might be bad. Check.
      
        static::$matching_paths = array();
        $searcher = Callback::for_method("ClassManager", "capture_matching_paths", $names);
        static::walk_roots($searcher);
      
        foreach( static::$matching_paths as $matching_path )
        {
          Script::safe_require_once($matching_path);
          if( static::is_loaded($full_class_name) )
          {
            static::$index->start_build();
            static::$index->add_to_index($matching_path, $replace = true);
            static::$index->commit_build();
          
            return true;
          }
        }
      }

      return false;
    }

  

  //=================================================================================================
  // SECTION: Internals

    protected static $roots;
    protected static $roots_signature; 
    protected static $epoch_sources;
    protected static $epoch_signature;
    protected static $epoch;
    protected static $identifier;
    protected static $index;
    protected static $matching_paths;
  
    protected static $pedigrees;
    protected static $snake_pedigrees;


    static function initialize()
    {
      if( is_null(static::$roots) )
      {
        static::$roots           = array();
        static::$roots_signature = ""; 
        static::$epoch_sources   = array();
        static::$epoch_signature = "";
        static::$epoch           = 0;
        static::$identifier      = null;
        static::$index           = null;
        static::$matching_paths  = null;

        static::$pedigrees       = array();
        static::$snake_pedigrees = array();
      }
    }
    
    static function build_index()
    {                                                                                                                            $_ = TRACE_ENTRY(__METHOD__);
      if( !static::$index or static::$index->identifier != static::get_code_id() )
      {                                                                                                                               TRACE(__METHOD__, "building index");
        $class_name = sprintf("ClassManager_%sIndex", Features::enabled("trusted_tmpdir") ? "Symlink" : "Memory");                    TRACE(__METHOD__, "using $class_name for index");
        static::$index = new $class_name(static::$roots_signature, static::get_code_epoch(), static::get_code_id());                  TRACE(__METHOD__, "index object created");

        if( !static::$index->is_built() )
        {                                                                                                                             TRACE(__METHOD__, "building index");
          static::$index->start_build();
          $builder = Callback::for_method(static::$index, "add_to_index");
          static::walk_roots($builder);                                                                                               TRACE(__METHOD__, "roots walked", static::$index);
          static::$index->commit_build();                                                                                             TRACE(__METHOD__, "build committed");
        }                                                                                                                             TRACE(__METHOD__, "index object ready");
      }
    }
    
    static function add_to_epoch( $path )
    {
      static::$epoch = max(static::$epoch, filectime($path));
    }


    static function walk_roots( $callback )
    {                                                                                                                            $_ = TRACE_ENTRY(__METHOD__, static::$roots, $callback);
      foreach( static::$roots as $root )
      {
        static::walk_directory($root, $callback);                                                                                     TRACE(__METHOD__, "walked root [$root]");
      }
    }

    static function walk_directory( $directory, $file_processor )
    {                                                                                                                         // $_ = TRACE_ENTRY(__METHOD__);
      if( file_exists($directory) && is_dir($directory) && ($handle = @opendir($directory)) )
      {                                                                                                                            // TRACE(__METHOD__, "[$directory] opened");
        while( $name = readdir($handle) )
        {
          $path = sprintf("%s/%s", $directory, $name);
          if( static::is_reasonable_filename($name) )
          {
            if( is_dir($path) )
            {                                                                                                                      // TRACE(__METHOD__, "[$path] is a directory; recursing");
              static::walk_directory($path, $file_processor);
            }
            elseif( preg_match('/([A-Z][a-z0-9]*)+\.php/', $name) )
            {                                                                                                                      // TRACE(__METHOD__, "[$path] is a class file; processing");
              $file_processor->call(array($path));
            }
            else
            {                                                                                                                      // TRACE(__METHOD__, "[$path] ignored");
            }
          }
          else
          {                                                                                                                        // TRACE(__METHOD__, "[$path] not a file name");
          }
        }

        closedir($handle);                                                                                                         // TRACE(__METHOD__, "[$directory] closed");
      }
    }
    
    static function is_reasonable_filename( $string )
    {
      return $string != "." && $string != ".." && preg_match("/^[\d\w\.\-]+$/", $string);
    }
    
    static function capture_matching_paths( $class_names, $test_path )
    {
      foreach( $class_names as $class_name )
      {
        if( $class_name == basename($test_path, ".php") )
        {
          static::$matching_paths[] = $test_path;
        }
      }
    }
  }

  ClassManager::initialize();




//===============================================================================================
// SECTION: Support classes

  class ClassManager_Index
  {
    public $signature;
    public $epoch;
    public $identifier;
    
    function __construct( $signature, $epoch, $identifier )
    {
      $this->signature  = $signature;
      $this->epoch      = $epoch;    
      $this->identifier = $identifier;
    }
    
    function add_to_index( $path, $replace = false )   // Adds the class file to the index; returns false if the name was already in use
    {
      abort("override");
    }
    
    function is_class_in_index( $class_name )          // Returns true if the named class is in the index
    {
      return (bool)$this->get_path_to_class($class_name);
    }
    
    function get_path_to_class( $class_name )          // Returns the path to the named class
    {
      abort("override");
    }
    
    function select_matching( $callback )              // Returns a map of class name to file path for all entries that match your callback criteria
    {
      abort("override");
    }
    
    function start_build()
    {
    }
    
    function commit_build()
    {
    }
    
    function is_built()
    {
      abort("override");
    }
  }



  class ClassManager_SymlinkIndex extends ClassManager_Index
  {
    protected $index_path;
    
    function __construct( $signature, $epoch, $identifier, $clean = false )
    {
      parent::__construct($signature, $epoch, $identifier);
      $this->index_path = sprintf("/tmp/%s.class_index/%s", Script::get_system_name(), $identifier);
    }
    
    function add_to_index( $path, $replace = false )
    {
      $class_name = basename($path, ".php");
      $link_path  = $this->index_path . "/" . $class_name;
      
      if( !file_exists($link_path) )
      {
        @symlink($path, $link_path);
        return true;
      }
      elseif( $replace )
      {
        $trash_path = $link_path . "." . getmypid();
        
        @rename($link_path, $trash_path);
        @symlink($path, $link_path);
        @unlink($trash_path);
        return true;
      }
      
      return false;
    }
    
    function is_class_in_index( $class_name )
    {
      $path = $this->index_path . "/" . $class_name;
      return file_exists($path);
    }
    
    function get_path_to_class( $class_name )
    {
      $link_path = $this->index_path . "/" . $class_name;
      return file_exists($link_path) ? readlink($link_path) : null;
    }
    
    function select_matching( $callback )
    {
      $matches = array();
      if( $handle = @opendir($this->index_path) )
      {
        while( $name = readdir($handle) )
        {
          if( ClassManager::is_reasonable_filename($name) && Callback::do_call($callback, $name) )
          {
            $matches[$name] = readlink(static::$index_path . "/" . $name);
          }
        }

        closedir($handle);
      }
  
      return $matches;
    }
    
    function start_build()
    {
      @mkdir($this->index_path, $mode = 0755, $recursive = true);
    }
    
    function commit_build()
    {
      touch($this->index_path . "/.done");
    }
    
    function is_built()
    {
      return file_exists($this->index_path . "/.done");
    }
  }
  
  
  
  class ClassManager_MemoryIndex extends ClassManager_Index
  {
    protected $cache_key;
    protected $index;
    
    function __construct( $signature, $epoch, $identifier, $clean = false )
    {                                                                                                                            $_ = TRACE_ENTRY(__METHOD__);                                                                                                                                 $_ = TRACE_ENTRY(__METHOD__);
      parent::__construct($signature, $epoch, $identifier);
      $this->cache_key = sprintf("ClassManager::index.%s", $this->identifier);
      $this->index = array();                                                                                                         TRACE(__METHOD__, $clean ? "building new index" : "using existing index");

      if( !$clean )
      {
        $this->index = Script::get_from_system_cache($this->cache_key, $epoch) or $this->index = array();                             TRACE(__METHOD__, "loaded index", $this->index);
      }
    }
    
    function add_to_index( $path, $replace = false )
    {                                                                                                                         // $_ = TRACE_ENTRY(__METHOD__);
      $class_name = basename($path, ".php");                                                                                       // TRACE(__METHOD__, "attempting to add [$path] to index");
      if( !$replace and tree_has($this->index, $class_name) )
      {                                                                                                                            // TRACE(__METHOD__, "already present; skipping");
        return false;
      }
      else
      {                                                                                                                            // TRACE(__METHOD__, "adding or replacing");
        $this->index[$class_name] = $path;
        return true;
      }
    }
    
    function get_path_to_class( $class_name )
    {
      return tree_fetch($this->index, $class_name);
    }
    
    function select_matching( $callback )
    {
      $matches = array();
      foreach( $this->index as $name => $path )
      {
        if( Callback::do_call($callback, $name) )
        {
          $matches[$name] = $path;
        }
      }
      
      return $matches;
    }


    function start_build()
    {
      $this->index = array();
    }
    
    
    function commit_build()
    {
      Script::set_to_system_cache($this->cache_key, $this->index);  
    }
    
    
    function is_built()
    {
      return !empty($this->index);
    }
    
  }



