<?php
  
  class TestsFor_SqlParameterSet
  {
    function test_scalar_comparisons( $tester )
    {
      $parameters = array("string" => "value");
      
      $tests = array                       // we use irregular spacing to ensure the regex works
      (
              "field   =   {string}"
           => "field   =   'value'"
      ,    
              "field   !=  {string}"
           => "field   !=  'value'"    
      ,    
              "field   <>  {string}"
           => "field   <>  'value'"    
      ,    
              "field   <=  {string}"     
           => "field   <=  'value'"        
      ,        
              "field   >=  {string}"
           => "field   >=  'value'"        
      ,    
              "field   <   {string}"     
           => "field   <   'value'"        
      ,        
              "field   >   {string}"
           => "field   >   'value'"
             
      );
      
      $this->run_parameter_expansion_tests($tester, $tests, $parameters);
    }
    
        
    function test_null_comparisons( $tester )
    {
      $parameters = array("null" => null);
      
      $tests = array                       // we use irregular spacing to ensure the regex works
      (
              "field   =   {null}"
           => "field   is null"
      ,    
              "field   !=  {null}"
           => "field   is not null"    
      ,    
              "field   <>  {null}"
           => "field   is not null"    
      ,    
              "field   <=  {null}"     
           => "field   is null"        
      ,        
              "field   >=  {null}"
           => "field   is null"        
      ,    
              "field   <   {null}"     
           => "field   is not null"        
      ,        
              "field   >   {null}"
           => "field   is not null"        
             
      );

      $this->run_parameter_expansion_tests($tester, $tests, $parameters);
    }
    
    
    function test_vector_comparisons( $tester )
    {      
      $parameters = array("list" => array(1, 2, 3), "empty_list" => array(), "null" => null);
      
      $tests = array                       // we use irregular spacing to ensure the regex works
      (
              "field   in  {list}"
           => "field   in (1, 2, 3)"
      ,    
              "field   not in  {list}"
           => "field   not in (1, 2, 3)"
      ,    
              "field   =   {list}"     
           => "field   in (1, 2, 3)"        
      ,        
              "field   !=   {list}"     
           => "field   not in (1, 2, 3)"        
      ,        
              "field   in  {empty_list}"
           => "field   is null"    
      ,    
              "field   not in  {empty_list}"
           => "field   is not null"    
      ,    
              "field   =   {empty_list}"
           => "field   is null"        
      ,    
              "field   !=   {empty_list}"
           => "field   is not null"        
      ,    
              "field   in  {null}"
           => "field   is null"    
      ,  
              "field   not in  {null}"
           => "field   is not null"    
      );
        
      $this->run_parameter_expansion_tests($tester, $tests, $parameters);
    }
    
    
    function test_literal_expansion( $tester )
    {
      $parameters = array("limit" => 10, "from" => "Table");
      
      $tests = array
      (
              "LIMIT {limit:literal}"
           => "LIMIT 10"
      ,    
              "LIMIT {limit}"
           => "LIMIT 10"
      ,    
              "FROM {from:literal} t"
           => "FROM Table t"
      ,  
              "FROM {from} t"
           => "FROM 'Table' t"
      );
      
      $this->run_parameter_expansion_tests($tester, $tests, $parameters);
    }
     


    function test_type_conversions( $tester )
    {
      $parameters = array
      ( "integer"            => 10
      , "float"              => 3.141
      , "string"             => "This 'string' is \n full of \0 \win!"
      , "true"               => true
      , "false"              => false
      , "time"               => strtotime("2000-01-01 00:00:00")
      , "time_string"        => "2004-02-29 12:03:04"
      , "date"               => "2001-10-01"
      , "yesterday_midnight" => "yesterday midnight"
      , "null"               => null
      );
      
      $tests = array
      (
              "{integer}"
           => "10"
      ,    
              "{float}"
           => "3.141"
      ,    
              "{string}"
           => "'This \'string\' is \\n full of \\0 \\\\win!'"
      ,  
              "{true}"
           => "1"
      ,  
              "{false}"
           => "0"
      ,  
              "{null}"
           => "null"
      ,  
              "{integer:string}"
           => "'10'"
      ,  
              "{integer:boolean}"
           => "1"
      ,  
              "{time}"
           => sprintf("%d", $parameters["time"])
      ,  
              "{time:time}"
           => sprintf("'%s'", date("Y-m-d H:i:s", $parameters["time"]))
      ,
              "{time_string}"
           => sprintf("'%s'", $parameters["time_string"])
      ,  
              "{time_string:time}"
           => sprintf("'%s'", date("Y-m-d H:i:s", strtotime($parameters["time_string"])))
      ,
              "{date}"
           => "'2001-10-01'"
      ,  
              "{date:date}"
           => sprintf("'%s'", date("Y-m-d", strtotime($parameters["date"])))
      ,  
              "{date:time}"
           => sprintf("'%s'", date("Y-m-d H:i:s", strtotime($parameters["date"])))
      ,  
              "{yesterday_midnight}"
           => sprintf("'%s'", $parameters["yesterday_midnight"])
      ,  
              "{yesterday_midnight:time}"
           => sprintf("'%s'", date("Y-m-d H:i:s", strtotime($parameters["yesterday_midnight"])))
      );
      
      $this->run_parameter_expansion_tests($tester, $tests, $parameters);
    }
     
     
     
    function test_like_expansion( $tester )
    {
      $parameters = array("string" => 'Some 10%', "null" => null);
      
      $tests = array
      (
              "field lIke {string}"
           => "field like 'Some 10%'"
      ,    
              "field lIke {string:like}"
           => "field like 'Some 10\%'"
      ,    
              "field lIke {string:like:%s}"
           => "field like 'Some 10\%'"
      ,    
              "field lIke {string:like:KC%s%%}"
           => "field like 'KCSome 10\%%'"
      ,    
              "field lIke {string:like:%%%s}"
           => "field like '%Some 10\%'"
      ,    
              "field lIke {string:like:%%%s%%T}"
           => "field like '%Some 10\%%T'"
      ,    
              "field Not lIke {string}"
           => "field not like 'Some 10%'"
      ,    
              "field nOt lIke {string:like:%s%%}"
           => "field not like 'Some 10\%%'"
      ,
              "field lIke {null}"
           => "field is null"
      ,    
              "field lIke {null:like:%s%%}"
           => "field is null"
      ,    
              "field Not lIke {null}"
           => "field is not null"
      ,    
              "field nOt lIke {null:like:%s%%}"
           => "field is not null"
      );
      
      $this->run_parameter_expansion_tests($tester, $tests, $parameters);
    }
     

    
     
     
     
     
     
  //===============================================================================================
  // SECTION: Internals
  
     
    function run_parameter_expansion_tests( $tester, $tests, $parameters )
    {
      foreach( $tests as $given => $expect )
      {
        $expanded    = SqlParameterSet::expand_parameters($given, $parameters);
        $description = sprintf("%s BECOMES %s", $given, $expect);
        $tester->record($description, $expanded == $expect, $expanded);
      }
    } 
  }