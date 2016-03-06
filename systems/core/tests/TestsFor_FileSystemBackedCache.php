<?php
  
  class TestsFor_FileSystemBackedCache
  {
    protected $directory;
    protected $cache;
    
    function test_set_and_get( $tester )
    {
      $cache  = $this->cache;
      $output = array("a" => array(3, 4, 0), "b" => (object)array("x" => "y"), "c" => 10, "d" => "e");
      
      $tester->record("set"        , $cache->set("first", $output));
      $tester->record("file exists", file_exists(sprintf("%s/%s", $this->directory, "first")));
      $tester->record("get"        , $input = $cache->get("first"));
      $tester->record("get matches", $output == $input);
    }
    
    
    
  //===============================================================================================
  // SECTION: Configuration
  
  
    function configure( $configuration, $tester )
    {
      $directory = "/tmp/cache_test";
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
  }