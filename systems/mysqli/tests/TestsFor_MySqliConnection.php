<?php
  
  class TestsFor_MySqliConnection
  {
    protected $connection;
    
    
    function test_connection( $tester )
    {
      $tester->record("connected", $this->connection, $this->connection);
      
    
    }
    
    
    
    
    
    
  //===============================================================================================
  // SECTION: Configuration
    
    function configure( $configuration, $tester )
    {
      if( $descriptor = tree_fetch($configuration, "MySqliConnection") )
      {
        if( $this->connection = MySqliConnector::connect_now($descriptor) )
        {
          return true;
        }
        else
        {
          $tester->skip("configuration failed: MySqliConnection=master=&user=&pass=&db=");
        }
      }
      else
      {
        $tester->skip("configuration missing: MySqliConnection=master=&user=&pass=&db=");
      }
      
      return false;
    }
    
    
  }