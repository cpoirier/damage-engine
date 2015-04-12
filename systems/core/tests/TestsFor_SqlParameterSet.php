<?php
  
  class TestsFor_SqlParameterSet
  {
    function test_scalar_comparisons( $tester )
    {
      // we use irregular spacing to ensure the regex works
      
      $parameters = array("string" => "value");
      
      $tests = array
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
      
      foreach( $tests as $given => $expect )
      {
        $expanded    = SqlParameterSet::expand_parameters($given, $parameters);
        $description = sprintf("%s BECOMES %s", $given, $expect);
        $tester->record($description, $expanded == $expect, $expanded);
      }
    }
    
        
    function test_null_comparisons( $tester )
    {
      // we use irregular spacing to ensure the regex works

      $parameters = array("null" => null);
      
      $tests = array
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


      foreach( $tests as $given => $expect )
      {
        $expanded    = SqlParameterSet::expand_parameters($given, $parameters);
        $description = sprintf("%s BECOMES %s", $given, $expect);
        $tester->record($description, $expanded == $expect, $expanded);
      }
    }
    
    
    function test_vector_comparisons( $tester )
    {
      // we use irregular spacing to ensure the regex works
      
      $parameters = array("list" => array(1, 2, 3), "empty_list" => array(), "null" => null);
      
      $tests = array
      (
             "field   in  {list}"
          => "field   in (1, 2, 3)"
      ,   
             "field   in  {empty_list}"
          => "field   is null"    
      ,   
             "field   in  {null}"
          => "field   is null"    
      ,   
             "field   =   {list}"     
          => "field   in (1, 2, 3)"        
      ,       
             "field   =   {empty_list}"
          => "field   is null"        
      ,   
             "field   =   {null}"     
          => "field   is null"        
      ,  
             "field   !=   {list}"     
          => "field   not in (1, 2, 3)"        
      ,       
             "field   !=   {empty_list}"
          => "field   is not null"        
      ,   
             "field   !=   {null}"     
          => "field   is not null"        
      );
      
      
      foreach( $tests as $given => $expect )
      {
        $expanded    = SqlParameterSet::expand_parameters($given, $parameters);
        $description = sprintf("%s BECOMES %s", $given, $expect);
        $tester->record($description, $expanded == $expect, $expanded);
      }
    }
      
  }