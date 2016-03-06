<?php
  
  class TestsFor_FileSystemBackedCache
  {
    protected $directory;
    protected $cache;
    
    function test_set_and_get( $tester )
    {
      $cache  = $this->cache;
      $output = array("a" => array(3, 4, 0), "b" => (object)array("x" => "y"), "c" => 10, "d" => "e");
      $key    = "first";
      $path   = $this->make_path($key);
      
      $tester->record("set"        , $cache->set($key, $output));
      $tester->record("file exists", file_exists($path));
      $tester->record("get"        , $input = $cache->get($key));
      $tester->record("get matches", $output == $input);
      $tester->record("delete"     , $cache->delete($key));
      $tester->record("file gone"  , !file_exists($path));
    }
    
    
    function test_expiry( $tester )
    {
      $cache = $this->cache;
      $key   = "third";
      $path  = $this->make_path($key);
      
      $tester->record("set with expiry = -1", $cache->set($key, "value", -1));
      $tester->record("file exists"         , file_exists($path));
      $tester->record("get returns null"    , $cache->get($key) == null);
    }


    
    
  //===============================================================================================
  // SECTION: Configuration
  
  
    function configure( $configuration, $tester )
    {
      $directory = "/tmp/cache_test_" . time();
      if( is_dir($directory) or @mkdir($directory, 0777, $recursive = true) )
      {
        $this->directory = $directory;
        $this->cache     = new FileSystemBackedCache($directory);
        
        return true;
      }
      else
      {
        $tester->skip("unable to find/create cache directory: " . $directory);
      }
    }
    
    function make_path( $key )
    {
      return sprintf("%s/%s", $this->directory, $key);
    }
  }