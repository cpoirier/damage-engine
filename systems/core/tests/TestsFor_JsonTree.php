<?php
  
  class TestsFor_JsonTree
  {
    function test_simple_set( $tester )
    {
      $tree = new JsonTree();
      $tree->set("x", 10);
      
      return $tree->get("x") == 10;
    }
    
    function test_deep_set( $tester )
    {
      $tree = new JsonTree();
      $tree->set("x/y/z", 20);
      
      $tester->record("x is an array"  , is_array($tree->get("x")));
      $tester->record("x/y is an array", is_array($tree->get("x/y")));
      $tester->record("x/y/z is 20"    , $tree->get("x/y/z") == 20);
    }
    
    
    function test_altered_root( $tester )
    {
      $tree = new JsonTree();
      $tree->push_root("subtree");
      $tree->set("x/value", "something");

      $tester->record("x/value is correct"         , $tree->get("x/value", "")          == "something");
      $tester->record("/x/value is null"           , $tree->get("/x/value")             == null       );
      $tester->record("/subtree/x/value is correct", $tree->get("/subtree/x/value", "") == "something");      
    }
    
    
    function test_root_scope_exit( $tester )
    {
      $tree = new JsonTree();
      $tree->set("x", 10);
      $this->do_enter_root_scope($tester, $tree);
      $tree->set("z", 11);
      
      $tester->record("x is at real root"           , $tree->get("/x"        , 0) == 10);
      $tester->record("y was set in subtree"        , $tree->get("/subtree/y", 0) == 99);
      $tester->record("z was set outside of subtree", $tree->get("/z"        , 0) == 11);
    }
    
    function do_enter_root_scope( $tester, $tree )
    {
      $scope = $tree->with_root("subtree");
      $tree->set("y", 99);
    }
    
    
    
    function test_array_access( $tester )
    {
      $tree = new JsonTree();
      $tree->set("x/11/y", 19);
      
      $tester->record("x is an array"    , is_array($tree->get("/x")));
      $tester->record("x/11 is an array" , is_array($tree->get("/x/11")));
      $tester->record("x/11/y is correct", $tree->get("/x/11/y", 0) == 19);
    }
    

    function test_relative_path( $tester )
    {
      $tree = new JsonTree();
      $tree->set("../../x", 10);
      $tree->set("a/b/c/../../y", 11);
      
      $scope = $tree->with_root("a/b");
      $tree->set("../z", 12);
      $tree->set("/d", 13);
      $scope = null;
      
      $tester->record("extraneous ../ are ignored"   , $tree->get("/x"  , 0) == 10, $tree);
      $tester->record("relative path is correct"     , $tree->get("/a/y", 0) == 11, $tree);
      $tester->record("root-relative path is correct", $tree->get("/a/z", 0) == 12, $tree);
      $tester->record("absolute path is correct"     , $tree->get("/d"  , 0) == 13, $tree);
    }
  }