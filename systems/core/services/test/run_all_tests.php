<?php

  Features::disabled("security") or Script::send_forbidden_and_exit();


  // Add the tests directory for all system components to the ClassManager
  
  foreach( Script::get_system_component_paths() as $path )
  {
    ClassManager::add_directory("$path/tests");
  }


  // Run one or all sets of tests.

  $for               = Script::get_parameter("for"              , ""         );    // If blank, all tests will be run.
  $flatten           = Script::get_parameter("flatten_results"  , false      );    // If true, flattens the result set into one simple array
  $failures_only     = Script::get_parameter("failures_only"    , empty($for));    // If true, only failures will be returned; defaults to true if no specific tests are specified
  $configuration_nvp = Script::get_parameter("configuration_nvp", ""         );    // Configuration name-value pairs for tests that require it

  $test_record = new TestRecord("all");
  $test_record->failures_only = $failures_only;
  
  $configuration = TypeConverter::decode($configuration_nvp, "nvp");
  
  if( $for )
  {
    if( ClassManager::is_loadable($class = "TestsFor_$for") )
    {
      $test_record->run_class_tests($class, $configuration);
    }
    else
    {
      $test_record->record($class, false);
    }
  }
  else
  {
    $test_record->run_all_tests($configuration);
  }
  
  Script::set_response($test_record->to_string($flatten), "application/json");
  Script::send_response_and_exit();
  