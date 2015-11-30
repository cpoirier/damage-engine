<?php
  
  class TestsFor_MysqliParameterizedStatement
  {
    function test_simple_sequentialization( $tester )
    {
      $sql_in         = "SELECT x, y, z FROM Table t WHERE x = {x} and y >= {y:bool} and z = {z}";
      $parameters_in  = array("x" => 10, "y" => true, "z" => "something 'else'");

      $sql_out        = "SELECT x, y, z FROM Table t WHERE x = ? and y >= ? and z = ?";
      $parameters_out = array(10, 1, "something 'else'");
      $bind_types_out = array("i", "i", "s");

      $this->run_parameter_expansion_test($tester, $sql_in, $parameters_in, $sql_out, $parameters_out, $bind_types_out);
    }
    
    
    function test_complex_sequentialization( $tester )
    {
      $now_s          = time();
      $now_ymd        = date("Y-m-d H:i:s", $now_s);
      
      $sql_in         = "SELECT x, y, z FROM {table:literal} t WHERE x = {x:int} and y >= {y:string} and z = {x:time}";
      $parameters_in  = array("table" => "Table", "x" => (string)$now_s, "y" => 10);
      
      $sql_out        = "SELECT x, y, z FROM Table t WHERE x = ? and y >= ? and z = ?";
      $parameters_out = array($now_s, "10", $now_ymd);
      $bind_types_out = array("i", "s", "s");

      $this->run_parameter_expansion_test($tester, $sql_in, $parameters_in, $sql_out, $parameters_out, $bind_types_out);
    }
   
   
   
   
  //===============================================================================================
  // SECTION: Internals
  
    function run_parameter_expansion_test( $tester, $sql_in, $parameters_in, $sql_out, $parameters_out, $bind_types_out )
    {
      $connection    = new SqlDatabaseConnection(null);
      $parameter_set = SqlParameterSet::build($parameters_in, $connection);
      $result        = MysqliParameterizedStatement::build($sql_in, $parameter_set, $connection);
      
      $tester->record("sql"            , @$result->sql               == $sql_out              , $result);
      $tester->record("parameter count", count(@$result->parameters) == count($parameters_out), $result);
      $tester->record("bind type count", count(@$result->bind_types) == count($bind_types_out), $result);
      
      foreach( $parameters_out as $k => $v )
      {
        $tester->record("parameter $k", $v === @$result->parameters[$k]               , "found:", $result->parameters[$k], "expected:", $v);
        $tester->record("bind type $k", $result->bind_types[$k] == $bind_types_out[$k], "found:", $result->bind_types[$k], "expected:", $bind_types_out[$k]);
      }
    } 
    
    
  }