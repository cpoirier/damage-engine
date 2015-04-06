<?php if (defined($inc = "CORE_TESTER_INCLUDED")) { return; } else { define($inc, true); }

  // Damage Engine Copyright 2012-2015 Massive Damage, Inc.
  //
  // Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except 
  // in compliance with the License. You may obtain a copy of the License at
  //
  //     http://www.apache.org/licenses/LICENSE-2.0
  //
  // Unless required by applicable law or agreed to in writing, software distributed under the License 
  // is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express 
  // or implied. See the License for the specific language governing permissions and limitations under 
  // the License.


  class TestRecord
  {
    function __construct( $name, $passed = true, $data = null, $failures_only = false )
    {
      $this->name          = $name;
      $this->passed        = $passed;
      $this->data          = $data;
      $this->subtests      = array();
      $this->failures_only = $failures_only;
    }
    
    
    
    function record( $name, $passed )         // records a sub test and percolates its result up
    {
      $subtest = is_a($passed, "TestRecord") ? $passed : new static($name, $passed);
      $subtest->failures_only = $this->failures_only;
      
      if( !$this->failures_only or !$subtest->passed )
      {
        $this->subtests[] = $subtest;
      }
      
      if( !$subtest->passed )
      {
        if( func_num_args() > 2 )
        {
          $args = func_get_args();
          $subtest->data = array_slice($args, 2);
        }
        
        $this->fail();
      }
      
      return $passed;
    }
    
    
    function fail()
    {
      $this->passed = false;
    }
    
    
    
    function to_result( $flatten = false, $top = true )
    {
      $result = (object)null;
      $result->path     = array($this->name);
      $result->name     = $this->name;
      $result->passed   = $this->passed;
      $result->subtests = array();
      $result->data     = null;
      

      // Write in any data.
      
      if( $this->data )
      {
        $result->data = array();
        foreach( $this->data as $datum )
        {
          $result->data[] = ((@strval($datum)) ?: capture_var_dump($datum));
        }
      }


      // Write in any subtests. If flattening, roll up the subtests' subtests.
      
      foreach( $this->subtests as $name => $subtest )
      {
        $subtest_result = $subtest->to_result($flatten, false);
        if( $flatten and $subtest_result->subtests )
        {
          $first = true;
          foreach( $subtest_result->subtests as $subsub_result )
          {
            $subsub_result->path = array_merge($subtest_result->path, $subsub_result->path);
            $result->subtests[] = $subsub_result;
          }
        }
        else
        {
          $result->subtests[] = $subtest_result;
        }
      }
      
      return $this->minimalize_result($result, $flatten, $top);
    }
    
    
    function to_string( $flatten = false )
    {
      return json_encode($this->to_result($flatten));
    }
    
    function __toString()
    {
      return $this->to_string();
    }
    
    
    
    
  //===============================================================================================
  // SECTION: Test execution
  
    function run_all_tests()
    {
      foreach( ClassManager::get_classes_matching("/^TestsFor_/") as $class_name )
      {
        $this->run_class_tests($class_name);
      }
    }
  
    function run_class_tests( $class )
    {
      if( class_exists($class) )
      {
        $class_tester = new static($class);  $class_tester->failures_only = $this->failures_only;
        $class_object = new ReflectionClass($class);
        $instance     = new $class();
        
        foreach( $class_object->getMethods(ReflectionMethod::IS_PUBLIC) as $method_object )
        {
          $method_name = $method_object->name;
          if( substr($method_name, 0, 5) == "test_" and $method_object->getNumberOfRequiredParameters() == 1 )
          {
            $method_tester = new static($method_name); $method_tester->failures_only = $this->failures_only;

            try
            {
              $result = $method_object->invoke($instance, $method_tester);
              is_null($result) or $result or $method_tester->fail();
            }
            catch( Exception $e )
            {
              $method_tester->fail();
            }

            $class_tester->record($method_name, $method_tester);
          }
        }

        $this->record($class, $class_tester);
      }
      else
      {
        $this->record($class, false);
      }
    }
    
    
    
  //===============================================================================================
  // SECTION: Internals
  
  
  
    function minimalize_result( $result, $flatten, $top )   // Cleans up the result data to include only necessary fields
    {
      $no_data      = true;
      $no_subtests  = true;
      $simple_paths = !$flatten || $top;
      
      foreach( $result->subtests as $subtest_result )
      {
        empty($subtest_result->data    )  or $no_data      = false;
        empty($subtest_result->subtests)  or $no_subtests  = false;
        count($subtest_result->path) == 1 or $simple_paths = false;
      }
      
      foreach( $result->subtests as $subtest_result )
      {
        if( $no_data )
        {
          unset($subtest_result->data);
        }
        elseif( empty($subtest_result->data) ) 
        {
          $subtest_result->data = null; 
        }
        
        if( $no_subtests )
        {
          unset($subtest_result->subtests);
        }
        elseif( empty($subtest_result->subtests) ) 
        {
          $subtest_result->subtests = array(); 
        }
        
        if( $simple_paths )
        {
          unset($subtest_result->path);
        }
      }
      
      return $result;
    }
  
  }