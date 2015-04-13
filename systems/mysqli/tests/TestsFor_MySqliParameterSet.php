<?php
  
  class TestsFor_MySqliParameterSet
  {
    function test_simple_sequentialization( $tester )
    {
      $query      = "SELECT x, y, z FROM Table t WHERE x = {x} and y >= {y} and z = {z}";
      $parameters = array();

      $expected = (object)null;
      $expected->query                = "SELECT x, y, z FROM Table t WHERE x = ? and y >= ? and z = ?";
      $expected->parameter_order      = array("x", "y", "z");
      $expected->parameter_converters = array(null, null, null);
      $expected->parameter_types      = array("s", "s", "s");
      $expected->has_literals         = false;

      $plan = MySqliParameterSet::sequentialize_parameters($query, $parameters);

      foreach( $expected as $name => $value )
      {
        $tester->record($name, @$plan->$name == $value, $plan);
      }
      
    }
    
    
    function test_complex_sequentialization( $tester )
    {
      $query      = "SELECT x, y, z FROM {table:literal} t WHERE x = {x:int} and y >= {y:string} and z = {x:time}";
      $parameters = array("table" => "Table");

      $expected = (object)null;
      $expected->query                = "SELECT x, y, z FROM Table t WHERE x = ? and y >= ? and z = ?";
      $expected->parameter_order      = array("x", "y", "x");
      $expected->parameter_converters = array(null, null, "format_time");
      $expected->parameter_types      = array("i", "s", "s");
      $expected->has_literals         = true;
      
      $plan = MySqliParameterSet::sequentialize_parameters($query, $parameters);

      foreach( $expected as $name => $value )
      {
        $tester->record($name, @$plan->$name == $value, $plan);
      }
    }
    
    
  }