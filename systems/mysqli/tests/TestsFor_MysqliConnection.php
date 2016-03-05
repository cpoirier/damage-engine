<?php
  
  class TestsFor_MysqliConnection
  {
    protected $connector;
    protected $connection;
    
    
    function test_connection( $tester )
    {
      $tester->record("connected", $this->connection != null, $this->connector->get_connection_error());
    }
    
    
    
    
    
    
  //===============================================================================================
  // SECTION: Configuration
    
    function configure( $configuration, $tester )
    {
      if( $descriptor = tree_fetch($configuration, "MysqliConnection") )
      {
        if( $this->connector = MysqliConnector::build($descriptor) )
        {
          $this->connection = @$this->connector->connect_for_writing();
          return true;
        }
        else
        {
          $tester->skip("configuration failed to build: MysqliConnection=master=&user=&pass=&db=");
        }
      }
      else
      {
        $tester->skip("configuration missing: MysqliConnection=master=&user=&pass=&db=");
      }
      
      return false;
    }
    
    
  }