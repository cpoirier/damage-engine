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
             "field   =   {list}"     
          => "field   in (1, 2, 3)"        
      ,       
             "field   !=   {list}"     
          => "field   not in (1, 2, 3)"        
      ,       
             "field   in  {empty_list}"
          => "field   is null"    
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
             "field   =   {null}"     
          => "field   is null"        
      ,  
             "field   !=   {null}"     
          => "field   is not null"        
      );
        
      $this->run_parameter_expansion_tests($tester, $tests, $parameters);
    }
     
     
     
     
     
     
     
     
     
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