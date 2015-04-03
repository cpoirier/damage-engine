<?php if (defined($inc = "SQL_DELETE_INCLUDED")) { return; } else { define($inc, true); }

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


  class SQLDelete
  {
    static function build_sql( $table, $criteria, $db = null, $no_empty_criteria = true )
    {
      $instance = new static($table, $criteria, $no_empty_criteria);
      return $instance->to_sql($db);
    }


    function __construct( $table, $criteria, $no_empty_criteria = true )
    {
      $this->table             = $table;
      $this->criteria          = $criteria;
      $this->no_empty_criteria = $no_empty_criteria;
    }


    //
    // Returns a SQL DELETE statement for your criteria. If you pass a $db, the parameters
    // will be expanded for you and you'll receive a string; otherwise, you'll receive an
    // array of string and parameters.

    function to_sql( $db = null )
    {
      $names      = array();
      $values     = array();
      $parameters = array();

      $criteria   = Script::filter(array("sql_delete_criteria_fields", "sql_criteria_fields", "sql_statement_fields"), $this->criteria, $this->table, $db);
      
      foreach( $criteria as $name => $value )
      {
        $comparisons[] = sprintf("`%s` = ?", $name);
        $parameters[]  = $value;
      }

      if( empty($comparisons)  )
      {
        $comparisons[] = $this->no_empty_criteria ? "1 = 0" : "1 = 1";
      }

      $statement = sprintf("DELETE FROM %s WHERE %s", $this->table, implode(" AND ", $comparisons));
      return $db ? $db->format($statement, $parameters) : array($statement, $parameters);
    }
  }




//=================================================================================================

  if( is_called_from_terminal(__FILE__) )
  {
    enter_text_mode();
    $test = new SimpleTest();

    $test->enter("Verifying SQL DELETE with data and criteria");
  //=============================================================================================
    $actual   = SQLDelete::build_sql("ATable", array("x" => "value", "y" => null));
    $expected = array("DELETE FROM ATable WHERE `x` = ? AND `y` = ?", array("value", null));
  //=============================================================================================
    $test->assert_equal($actual, $expected);


    $test->enter("Verifying SQL DELETE with intentionally empty criteria");
  //=============================================================================================
    $actual   = SQLDelete::build_sql("ATable", array(), null, false);
    $expected = array("DELETE FROM ATable WHERE 1 = 1", array());
  //=============================================================================================
    $test->assert_equal($actual, $expected);


    $test->enter("Verifying SQL UPDATE with accidentally empty criteria");
  //=============================================================================================
    $actual   = SQLDelete::build_sql("ATable", array());
    $expected = array("DELETE FROM ATable WHERE 1 = 0", array());
  //=============================================================================================
    $test->assert_equal($actual, $expected);

    $test->done();
  }
