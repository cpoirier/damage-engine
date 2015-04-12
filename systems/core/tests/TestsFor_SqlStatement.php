<?php
  
  class TestsFor_SqlStatement
  {
    function test_parameter_override( $tester )
    {
      $query         = "SELECT x, y, z FROM Table t WHERE t.a = {parameter} LIMIT {limit:literal}";
      $defaults      = array("parameter" => "value", "limit" => 10);
      $statement     = new SqlStatement($query, $defaults);
      $default_query = $statement->to_string(           );
      $parameters    = array("parameter" => "alternate", "limit" => 20);
      $custom_query  = $statement->to_string($parameters);
      
      $tester->record("default parameters used when not overriden", $default_query == "SELECT x, y, z FROM Table t WHERE t.a = 'value' LIMIT 10"    , $default_query);
      $tester->record("custom parameters used when supplied"      , $custom_query  == "SELECT x, y, z FROM Table t WHERE t.a = 'alternate' LIMIT 20", $custom_query );
    }
    
    
    function test_compile( $tester )
    {
      $query      = "SELECT x, y, z FROM Table t WHERE t.a = {parameter} LIMIT {limit:literal}";
      $parameters = array("parameter" => "value", "limit" => 10);
      $result     = SqlStatement::compile($query, $parameters);
      
      $tester->record("compilation succeeded", $result == "SELECT x, y, z FROM Table t WHERE t.a = 'value' LIMIT 10", $result);
    }
  }