<?php
  
  class TestsFor_TypeConverter
  {
    function __construct()
    {
      $this->exemplars    = array("int" => 10, "boolean" => true, "float" => 9.73, "int string" => "10", "text string" => "a", "object" => (object)null, "array" => array());
      $this->skip_targets = array("int string");
    }

    function test_conversion( $tester )
    {
      foreach( $this->exemplars as $to_type => $to_exemplar )
      {
        if (in_array($to_type, $this->skip_targets)) continue;
        foreach( $this->exemplars as $from_type => $from_value )
        {
          $result = TypeConverter::coerce_type($from_value, $to_exemplar);
          $tester->record("from ${from_type} to {$to_type}", gettype($result) == gettype($to_exemplar), $result);
        }
      }
    }
    
    function test_specific_conversions( $tester )
    {
      $tester->record("from 'abc' to int == 0"          , TypeConverter::coerce_type("abc"   ,     0) ===     0);
      $tester->record("from '938.29' to int == 938"     , TypeConverter::coerce_type("938.29",     0) ===   938);
      $tester->record("from 'true' to boolean == true"  , TypeConverter::coerce_type("true"  , false) ===  true);
      $tester->record("from 'false' to boolean == false", TypeConverter::coerce_type("false" , true ) === false);
    }
  }