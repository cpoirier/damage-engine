<?php if (defined($inc = "SQL_INSERT_INCLUDED")) { return; } else { define($inc, true); }

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


  class SQLInsert
  {
    static function build_sql( $table, $data, $db = null, $replace = false )
    {
      $instance = new static($table, $data, $replace);
      return $instance->to_sql($db);
    }


    function __construct( $table, $data, $replace = false )
    {
      $this->table   = $table;
      $this->data    = $data;
      $this->replace = $replace;
    }


    //
    // Returns a SQL INSERT statement for your data. If you pass a $db, the parameters
    // will be expanded for you and you'll receive a string; otherwise, you'll receive an
    // array of string and parameters.

    function to_sql( $db = null )
    {
      $names      = array();
      $values     = array();
      $parameters = array();

      $data       = Script::filter(array("sql_insert_data_fields", "sql_data_fields", "sql_statement_fields"), $this->data, $this->table, $db);

      foreach( $data as $name => $value )
      {
        $names[]      = sprintf("`%s`", $name);
        $values[]     = "?";
        $parameters[] = $value;
      }

      $command = ($this->replace ? "REPLACE" : ($this->replace === false ? "INSERT" : "INSERT IGNORE"));
      $statement = sprintf("$command INTO %s (%s) VALUES (%s)", $this->table, implode(", ", $names), implode(", ", $values));
      return $db ? $db->format($statement, $parameters) : array($statement, $parameters);
    }
  }



//=================================================================================================

  if( is_called_from_terminal(__FILE__) )
  {
    enter_text_mode();
    $test = new SimpleTest();

    $test->enter("Verifying SQL INSERT");
  //=============================================================================================
    $actual   = SQLInsert::build_sql("ATable", array("a" => 10, "b" => 20, "c" => null, "d" => "some text with spaces"));
    $expected = array("INSERT INTO ATable (`a`, `b`, `c`, `d`) VALUES (?, ?, ?, ?)", array(10, 20, null, "some text with spaces"));
  //=============================================================================================
    $test->assert_equal($actual, $expected);
    $test->done();
  }
