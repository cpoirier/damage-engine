<?php

  Features::disabled("security") or Script::send_forbidden_and_exit();


  // Add the tests directory for all system components to the ClassManager
  
  foreach( Script::get_system_component_paths() as $path )
  {
    ClassManager::add_directory("$path/tests");
  }


  // Run one or all sets of tests.

  $for           = Script::get_parameter("for"            ,    "");    // If blank, all tests will be run.
  $flatten       = Script::get_parameter("flatten_results", false);    // If true, flattens the result set into one simple array
  $failures_only = Script::get_parameter("failures_only"  , false);    // If true, only failures will be returned

  $test_record = new TestRecord("all");
  $test_record->failures_only = $failures_only;
  
  if( $for )
  {
    if( ClassLoader::is_loadable($class = "TestsFor_$for") )
    {
      $test_record->run_class_tests($class);
    }
    else
    {
      $test_record->record($class, false);
    }
  }
  else
  {
    $test_record->run_all_tests();
  }
  
  Script::set_response($test_record->to_string($flatten), "application/json");
  Script::send_response_and_exit();
  