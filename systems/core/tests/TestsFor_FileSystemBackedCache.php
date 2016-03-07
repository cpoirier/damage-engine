<?php
  
  class TestsFor_FileSystemBackedCache
  {
    protected $directory;
    
    function test_connect( $tester )
    {
      $directory = $this->make_test_directory_path($tester);
      if( $cache = $this->connect($tester, $directory) )
      {
        $key       = "a_key";
        $output    = "lorem ipsum dolor sit amet";
      
        $tester->record("set"            , $cache->set($key, $output));
        $tester->record("get matches set", $cache->get($key) == $output);
      
        $cache = FileSystemBackedCache::connect($directory, 0777);
        if( $tester->record("reconnected", $cache != null) )
        {
          $tester->record("get is stable", $cache->get($key) == $output);
        }
      }
      
      $this->clean_up($directory);
    }


    function test_set_and_get( $tester )
    {
      $directory = $this->make_test_directory_path($tester);
      if( $cache = $this->connect($tester, $directory) )
      {
         $output = array("a" => array(3, 4, 0), "b" => (object)array("x" => "y"), "c" => 10, "d" => "e");
         $key    = "b_key";
         $path   = $this->make_key_path($key, $directory);
    
         $tester->record("set"        , $cache->set($key, $output));
         $tester->record("file exists", file_exists($path));
         $tester->record("get"        , ($input = $cache->get($key)) != null);
         $tester->record("get matches", $output == $input);
         $tester->record("delete"     , $cache->delete($key));
         $tester->record("file gone"  , !file_exists($path));
      }
      
      $this->clean_up($directory);
    }
    
    
    function test_expiry( $tester )
    {
      $directory = $this->make_test_directory_path($tester);
      if( $cache = $this->connect($tester, $directory) )
      {
        $key   = "c_key";
        $path  = $this->make_key_path($key, $directory);
      
        $tester->record("set with expiry = -1", $cache->set($key, "value", -1));
        $tester->record("file exists"         , file_exists($path));
        $tester->record("get returns null"    , $cache->get($key) == null);
      }
      
      $this->clean_up($directory);
    }


    
    
  //===============================================================================================
  // SECTION: Configuration
  
  
    function configure( $configuration, $tester )
    {
      $this->directory = "/tmp/cache_test_" . time();
      return true;
    }
    
    function connect( $tester, $directory, $create_mode = 0777 )
    {
      $cache = FileSystemBackedCache::connect($directory, $create_mode);
      if( $tester->record("connected", $cache != null) )
      {
        if( $tester->record("directory exists", is_dir($directory)) )
        {
          return $cache;
        }
      }
      
      return null;
    }
    
    function clean_up( $directory )
    {
      if( is_dir($directory) )
      {
        if( $dh = opendir($directory) )
        {
          while( $name = readdir($dh) )
          {
            $path = sprintf("%s/%s", $directory, $name);
            if( is_file($path) )
            {
              @unlink($path);
            }
          }
        }

        @rmdir($directory);
      }
    }
    
    function make_test_directory_path( $tester )
    {
      return sprintf("%s_%s", $this->directory, $tester->name);
    }
    
    function make_key_path( $key, $directory )
    {
      return sprintf("%s/%s", $directory, $key);
    }
  }