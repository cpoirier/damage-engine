<?php if (defined($inc = "SQL_QUERY_INCLUDED")) { return; } else { define($inc, true); }

// =============================================================================================
// Schemaform
// A high-level database construction and programming layer.
//
// [Website]   http://schemaform.org
// [Copyright] Copyright 2004-2012 Chris Poirier
// [License]   Licensed under the Apache License, Version 2.0 (the "License");
//             you may not use this file except in compliance with the License.
//             You may obtain a copy of the License at
//
//                 http://www.apache.org/licenses/LICENSE-2.0
//
//             Unless required by applicable law or agreed to in writing, software
//             distributed under the License is distributed on an "AS IS" BASIS,
//             WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//             See the License for the specific language governing permissions and
//             limitations under the License.
// =============================================================================================



  class SQLQuery
  {
    function __construct( $table )
    {
      $this->fields           = array();
      $this->sources          = array();        // alias => SQLQuerySource
      $this->parameters       = array();
      $this->where_condition  = null;
      $this->group_by_fields  = null;
      $this->having_condition = null;
      $this->order_by_fields  = null;
      $this->offset           = 0;
      $this->limit            = 0;
      $this->has_aggregates   = false;
      $this->sql_string       = null;

      list($source_mappings, $source_discards, $write_discards) = static::make_source_mappings($table);
      $this->source_mappings = $source_mappings;
      $this->source_discards = $source_discards;
      $this->write_discards  = $write_discards;

      $this->add_source(new SQLQuerySource($table));
    }

    function get_field_names()
    {
      return array_keys($this->fields);
    }

    //
    // Projects some subset of fields from the underlying source(s). You can pass a list of names,
    // or an associative array of mappings.

    function project( $commands )
    {
      $result = $this->nest();

      if( !is_array($commands) )
      {
        $commands = func_get_args();
      }

      //
      // First rename the fields.

      $fields   = array();
      $mappings = array();
      foreach( $commands as $from => $to )
      {
        if( is_numeric($from) )
        {
          $from = $to;
        }

        $mappings[$from] = $to;
        $fields[$to] = $this->fields[$from];
      }

      //
      // Then patch up the mappings.

      if( !is_null($result->source_mappings) )
      {
        $discards = array_diff(array_keys($this->fields), array_keys($mappings));
        foreach( $discards as $discard )
        {
          $result->source_discards[$discard] = $discard;
        }
        
        $source_mappings = $result->source_mappings;
        foreach( $mappings as $from => $to )
        {
          $result->source_mappings[$to] = $source_mappings[$from];
          unset($result->source_discards[$to]);
        }
      }

      //
      // Move the new fields in place.

      $result->fields = $fields;
      return $result;
    }


    //
    // An alias for project().

    function select( $mappings )
    {
      if( !is_array($mappings) )
      {
        $mappings = func_get_args();
      }

      return $this->project($mappings);
    }


    //
    // Removes some subset of fields from the relation. You can pass a list of names or an array.

    function discard( $victims )
    {
      if( !is_array($victims) )
      {
        $victims = func_get_args();
      }

      return $this->project(array_diff(array_keys($this->fields), $victims));
    }


    //
    // Renames one or more fields. You can pass an associative array of mappings (from => to) or
    // a single pair of from and to. Original field order will be respected.

    function rename( $mappings )
    {
      if( !is_array($mappings) )
      {
        $args = func_get_args();
        $mappings = array($args[0] => $args[1]);
      }

      $ordered = array();
      foreach( $this->fields as $name => $ignored )
      {
        $ordered[$name] = array_key_exists($name, $mappings) ? $mappings[$name] : $name;
      }

      return $this->project($ordered);
    }


    //
    // Adds a prefix to the named fields. If you don't pass any fields, the prefix is added
    // to all fields.

    function prefix( $prefix )
    {
      $names = func_get_args(); array_shift($names);
      $ordered = array();
      foreach( $this->fields as $name => $ignored )
      {
        $ordered[$name] = (empty($names) || in_array($name, $names) ? sprintf("%s%s", $prefix, $name) : $name);
      }

      return $this->project($ordered);
    }


    //
    // Returns a query with restricted results.

    function where( $expression )
    {
      $args = func_get_args(); array_shift($args);

      $copy = $this->nest();
      return $copy->and_where($this->resolve_expression($expression), $args);
    }


    //
    // Similar to where(), but generates the criteria expression from a map of name => value.

    function where_equals( $pairs )
    {
      $comparisons = array();
      $parameters  = array();
      foreach( $pairs as $name => $value )
      {
        $comparisons[] = "$name = ?";
        $parameters[]  = $value;
      }

      $copy = $this->nest();
      return $copy->and_where($this->resolve_expression(implode(" and ", $comparisons)), $parameters);
    }


    //
    // Produces the natural join of this and the RHS Table or SQLQuery (ie. they will be joined on
    // all common fields). If you need a left join, pass "left" as the first parameter.

    function natural_join( $rhs )
    {
      return call_user_func_array(array($this, "join_on"), func_get_args());
    }

    function natural_left_join( $rhs )
    {
      $args = func_get_args();
      array_unshift($args, "left");
      return call_user_func_array(array($this, "natural_join"), $args);
    }

    //
    // Joins on the supplied list of commonly-named fields. If you supply no names, provides the
    // natural join instead.

    function join_on( $rhs )
    {
      $args = array_slice(func_get_args(), 1);
      $type = "";
      if( is_string($rhs) )
      {
        $type = $rhs;
        $rhs  = array_shift($args);
      }

      if( is_a($rhs, "Table") )
      {
        $rhs = new static($rhs);
      }

      if( empty($args) )
      {
        $args = array_intersect(array_keys($this->fields), array_keys($rhs->fields));
      }

      $renames    = array();
      $conditions = array();
      $discards   = array();
      foreach( $args as $name )
      {
        $rename         = "${name}__rhs";
        $renames[$name] = $rename;
        $conditions[]   = "$name = ${rename}";
        $discards[]     = $rename;
      }

      return $this->join($rhs->rename($renames), implode(" and ", $conditions), $type)->discard($discards);
    }

    function left_join_on( $rhs )
    {
      $args = func_get_args();
      array_unshift($args, "left");
      return call_user_func_array(array($this, "join_on"), $args);
    }


    //
    // Joins on the supplied expression.

    function join( $rhs, $condition = null, $type = "" )
    {
      if( !is_a($rhs, "SQLQuery") )
      {
        $rhs = new SQLQuery($rhs);
      }

      $result = $this->nest($for_group_by = !empty($this->group_by_fields));
      if( count($rhs->sources) == 1 && empty($rhs->group_by_fields) && empty($rhs->offset) && empty($rhs->limit) && !$rhs->has_aggregates )
      {
        //
        // The RHS query is simple, and so its source can be merged into this one directly, saving
        // a potentially very-expensive (ie. orders of magnitude longer) subtable in the database.
        // We'll have to rename the table alias when importing the fields and any where condition.
        // We'll also have to hand merge the source mappings.

        $table = $rhs->sources["t1"]->table;
        $join  = new SQLQueryJoin($type, $table, $condition);
        $alias = $result->add_source($join, false, false);
        $remap = array("t1" => $alias);

        foreach( $rhs->fields as $name => $definition )
        {
          if( !array_key_exists($name, $result->fields) )
          {
            $copy = clone $definition;
            $copy->remap_expressions($result, $remap);
            $result->fields[$name] = $copy;
          }
        }

        $result->import_source_mappings(new SQLQueryJoin($type, $rhs, $condition));
        $join->resolve_expressions($result);

        if( !empty($rhs->where_condition) )
        {
          $result->and_where($result->remap_expression($rhs->where_condition, array("t1" => $alias)));
        }

      }
      else
      {
        $result->add_source(new SQLQueryJoin($type, $rhs, $condition));
      }

      return $result;
    }


    function left_join( $rhs, $condition = null )
    {
      return $this->join($rhs, $condition, "left");
    }


    function make_read_only()  // BUG: This routine is currently VERY naive. It specifically will not behave as expected if you include the same table twice in your query.
    {
      $result = $this->nest();
      $result->write_discards = array_unique(array_merge($result->write_discards, array_values(array_flatten($this->source_mappings))));
      
      return $result;
    }


    //
    // Adds a calculated field to the query.

    function define( $name, $expression )
    {
      $result = $this->nest();
      $result->fields[$name] = new SQLQueryDefinedField($this->resolve_expression($expression));

      if( !is_null($result->source_mappings) )
      {
        $result->source_mappings[$name] = array();
        unset($result->source_discards[$name]);
      }

      return $result;
    }


    //
    // Groups the results over the specified fields.

    function group_by()
    {
      $result = $this->nest($for_group_by = true);
      $result->source_mappings = null;
      $result->group_by_fields = array();
      foreach( func_get_args() as $name )
      {
        $result->group_by_fields[] = $result->fields[$name];
      }

      return $result;
    }


    //
    // Summarizes one column of the query over the others. The first parameter can contain contain
    // the full text of the expression, in which case the second paramter should be skipped.
    //   $query->summarize("MAX", "fieldname", "max_fieldname")
    //   $query->summarize("MAX(fieldname)", "max_fieldname")
    //   $query->summarize("MAX", "fieldname")
    //
    // Note: only supports one summary expression per query. If you need more, you'll have to use
    // define() and group_by() directly.

    function summarize( $op, $field = null, $as = null )
    {
      $result = $this->nest($for_group_by = true);

      $expression = null;
      $fields     = array();
      if( preg_match('/^\w+\(\s*(?:distinct\s*)?(\w+(?:\s*,\s*\w+)*)/', $op, $m) )
      {
        $fields = preg_split('/\s*,\s*/', $m[1]);
        $expression = $op;
        $as = $field or $as = $fields[0];
      }
      else
      {
        $xpression = strtoupper($op) . "($field)";
        $fields[] = $field;
        $as or $as = $field;
      }

      $result->fields[$as] = new SQLQueryDefinedField($this->resolve_expression($expression));
      $result->has_aggregates  = true;
      $result->source_mappings = null;

      $group_by_fields = array_diff(array_keys($this->fields), $fields);
      $result->group_by_fields = array();
      foreach( $group_by_fields as $name )
      {
        $result->group_by_fields[] = $result->fields[$name];
      }

      return $result;
    }


    //
    // Orders the results by the specified fields. Pass -1 after any field name to reverse the
    // sort order of the preceding field.

    function order_by()
    {
      $result = $this->nest();
      $args   = func_get_args();
      $result->order_by_fields = $fields = array_flatten($args);
      
      if( count($fields) == 1 && strpos($fields[0], ",") )
      {
        $result->order_by_fields = preg_split('/,\s*/', $fields[0]);
      } 
      
      return $result;
    }


    //
    // Sets a limit on the number of results produced.

    function limit( $limit, $offset = 0 )
    {
      $result = $this->nest();
      $result->offset = $offset;
      $result->limit  = $limit;

      return $result;
    }


    //
    // Allows you to offset into the results. Returns 1 row, unless you ask for more.

    function offset( $offset, $limit = 1 )
    {
      $result = $this->nest();
      $result->offset = $offset;
      $result->limit  = $limit;

      return $result;
    }


    //
    // Creates a UNION query from this and another query.

    function union( $rhs )
    {
      return new SQLUnion($this, $rhs);
    }




  //===============================================================================================
  // SECTION: Conversion routines.


    function to_query()
    {
      return $this;
    }

    function to_string( $indent = "" )
    {
      if( !$this->sql_string )
      {
        ob_start();
        $this->generate_sql($indent);
        $this->sql_string = ob_get_clean();
      }

      return $this->sql_string;
    }

    function to_sql( $db, $indent = "" )
    {
      return $db->format($this->to_string($indent), $this->parameters);
    }


    function simplify()
    {
      return new SQLQuerySimplified($this);
    }




  //===============================================================================================
  // SECTION: Internals

    function generate_sql( $indent = "" )
    {
      print $indent;
      print "SELECT ";

      $first = true;
      foreach( $this->fields as $name => $definition )
      {
        if( $first ) { $first = false; } else { print ", "; }
        $definition->generate_sql($indent);
        if( !is_a($definition, "SQLQueryFieldReference") || $definition->field_name != $name )
        {
          print " as `";
          print $name;
          print "`";
        }
      }

      $first = true;
      foreach( $this->sources as $alias => $source )
      {
        print "\n$indent";
        $source->generate_sql($indent, $alias);
      }

      if( $this->where_condition )
      {
        print "\n${indent}WHERE $this->where_condition";
      }

      if( !empty($this->group_by_fields) )
      {
        print "\n${indent}GROUP BY ";
        $first = true;
        foreach( $this->group_by_fields as $field )
        {
          if( $first ) { $first = false; } else { print ", "; }
          $field->generate_sql($indent);
        }
      }

      if( !empty($this->order_by_fields) )
      {
        print "\n${indent}ORDER BY ";
        $first = true;
        foreach( $this->order_by_fields as $name )
        {
          if( is_numeric($name) )
          {
            if( $name == -1 )
            {
              print " desc";
            }
          }
          else
          {
            if( $first ) { $first = false; } else { print ", "; }
            print "$name";
          }
        }
      }

      if( !empty($this->limit) )
      {
        if( !empty($this->offset) )
        {
          printf("\n${indent}LIMIT %d, %d", $this->offset, $this->limit);
        }
        else
        {
          printf("\n${indent}LIMIT %d", $this->limit);
        }
      }

    }


    //
    // Ensures we cache a string representation of the query when serializing, for faster results
    // on wakeup.

    function __sleep()
    {
      $this->to_string();
      return array_keys((array)$this);
    }

    //
    // Adds a SQLQuery or Table source to the state.

    protected function add_source( $source, $import_fields = true, $resolve_expressions = true )
    {
      $this->sql_string = null;

      $source->alias = $alias = $this->next_alias();
      $this->sources[$alias] = $source;

      if( $import_fields )
      {
        $this->import_fields($alias);
        $this->import_source_mappings($source);
      }

      if( $resolve_expressions )
      {
        $source->resolve_expressions($this);
      }

      return $alias;
    }


    protected function import_fields( $alias )
    {
      foreach( $this->sources[$alias]->field_names() as $name )
      {
        if( !array_key_exists($name, $this->fields) )
        {
          $this->fields[$name] = new SQLQueryFieldReference($alias, $name);
        }
      }

      return $alias;
    }

    protected function import_source_mappings( $source )
    {
      if( $this->source_mappings )
      {
        list($new_mappings, $new_discards, $new_write_discards) = static::make_source_mappings($source->table);
        if( $new_mappings )
        {
          foreach( $new_mappings as $name => $values )
          {
            if( !array_key_exists($name, $this->source_mappings) || array_key_exists($name, $this->source_discards) )
            {
              $this->source_mappings[$name] = $values;
              unset($this->source_discards[$name]);

              if( array_key_exists($name, $new_discards) )
              {
                $this->source_discards[$name] = $name;
              }
            }
          }
          
          $this->write_discards = array_unique(array_merge($this->write_discards, $new_write_discards));
        }

        //
        // Merge the mappings on any equijoined fields.

        $condition = @$source->condition;
        if( !is_null($this->source_mappings) && $condition )
        {
          $tokens =& SQLExpressionTokenizer::tokenize($condition, $strip_whitespace = true);
          $count  = count($tokens);

          for( $i = 0; $i < $count; $i++ )
          {
            $token = $tokens[$i];
            if( $token->type == SQLExpressionTokenizer::DOT )
            {
              abort("why is there a dotted expression in the join condition?");
            }
            elseif( $token->type == SQLExpressionTokenizer::EQUALS )
            {
              if( $i > 0 && $i + 1 < $count )
              {
                $lhs_token = $tokens[$i-1];
                $rhs_token = $tokens[$i+1];

                $lhs_name  = $lhs_token->string;
                $rhs_name  = $rhs_token->string;

                $lhs = @$this->source_mappings[$lhs_name];
                $rhs = @$this->source_mappings[$rhs_name];
                if( $lhs && $rhs )
                {
                  $combined = array_unique(array_merge($lhs, $rhs));
                  $this->source_mappings[$lhs_name] = $combined;
                  $this->source_mappings[$rhs_name] = $combined;

                  $i++; // skip the rhs
                }
              }
              else
              {
                abort("there seems to be a dangling equals sign in the join condition");
              }
            }
          }
        }
      }
    }


    //
    // Given an expression full of external names, returns one full of fully-qualified internal
    // names instead.

    function resolve_expression( $expression )
    {
      $last_token = null;
      $resolved   = array();
      $tokens     = SQLExpressionTokenizer::tokenize($expression);
      foreach( $tokens as $token )
      {
        if( $token->type == SQLExpressionTokenizer::IDENTIFIER )
        {
          if( array_key_exists($token->string, $this->fields) )
          {
            $resolved[] = $this->fields[$token->string];
          }
          else
          {
            trigger_error("unrecognized field [$token->string] in expression [$expression]", E_USER_ERROR);
          }
        }
        elseif( $token->type == SQLExpressionTokenizer::WORD && array_key_exists($token->string, $this->fields) )
        {
          if( $last_token && $last_token->type == SQLExpressionTokenizer::DOT )
          {
            $resolved[] = $token;
          }
          else
          {
            $resolved[] = $this->fields[$token->string];
          }
        }
        else
        {
          $resolved[] = $token;
        }

        $last_token = $token;
      }

      $expression = implode("", $resolved);
      return $expression;
    }


    function remap_expression( $expression, $table_alias_mappings )
    {
      $remapped = array();
      foreach( SQLExpressionTokenizer::tokenize($expression) as $token )
      {
        if( $token->type == SQLExpressionTokenizer::DOT )
        {
          $old_alias = array_pop($remapped);
          if( $old_alias->type == SQLExpressionTokenizer::WORD && array_key_exists($old_alias->string, $table_alias_mappings) )
          {
            array_push($remapped, $table_alias_mappings[$old_alias->string]);
          }
          else
          {
            array_push($remapped, $old_alias);
          }
        }

        $remapped[] = $token;
      }

      return implode("", $remapped);
    }



    //
    // Returns the next alias for a table.

    protected function next_alias()
    {
      $number = count($this->sources) + 1;
      return "t$number";
    }


    //
    // Appends or sets an already-resolve WHERE expression into the state.

    protected function and_where( $expression, $parameters = array() )
    {
      $this->sql_string = null;

      if( $this->where_condition )
      {
        $this->where_condition = sprintf("(%s) AND (%s)", $this->where_condition, $expression);
      }
      else
      {
        $this->where_condition = $expression;
      }

      if( !empty($parameters) )
      {
        //
        // Workaround for a PHP issue. For some reason, in the staging environment,
        // $this->parameters seems unset on this line. No explanation, as it is clearly set
        // in the constructor. In any event, this fixes it.

        if( !isset($this->parameters) )
        {
          $this->parameters = array();
        }

        $this->parameters = array_merge($this->parameters, $parameters);
      }

      return $this;
    }

    function __toString()
    {
      return $this->to_string();
    }


    protected function nest( $for_group_by = false )
    {
      if( $this->limit || $this->offset || ($for_group_by && !empty($this->group_by_fields)) )
      {
        return new static($this);
      }
      else
      {
        $copy = clone $this;
        $copy->sql_string = null;
        return $copy;
      }
    }

    static function make_source_mappings( $table )
    {
      if( is_a($table, "Table") )
      {
        $mappings = array();
        foreach( $table->get_field_names() as $field_name )
        {
          $mappings[$field_name] = array(sprintf("%s.%s", $table->name, $field_name));
        }

        return array($mappings, array(), array());
      }
      else
      {
        return array($table->source_mappings, $table->source_discards, $table->write_discards);
      }
    }
    
    
    function &get_write_mappings()
    {
      $write_mappings = array();
      if( !is_null($this->source_mappings) )
      {
        foreach( $this->source_mappings as $from => $to )
        {
          $write_mappings[$from] = array_diff($to, $this->write_discards);
        }
      }
      
      return $write_mappings;
    }
  }



  class SQLQueryHelper
  {
    function resolve_expressions( $against )
    {
    }

    function remap_expressions( $against, $mappings )
    {
    }

    function __toString()
    {
      ob_start();
      $this->generate_sql("");
      return ob_get_clean();
    }
  }



  class SQLQuerySource extends SQLQueryHelper
  {
    function __construct( $table, $op = "FROM" )
    {
      $this->table = $table;
      $this->alias = null;
      $this->op    = $op;
    }

    function field_names()
    {
      if( is_a($this->table, "Table") )
      {
        return array_keys($this->table->types);
      }
      else
      {
        return array_keys($this->table->fields);
      }
    }

    function generate_sql( $indent )
    {
      print "$this->op ";

      if( is_a($this->table, "Table") )
      {
        print $this->table->name;
      }
      else
      {
        print "(\n";
        $this->table->generate_sql("$indent  ");
        print "\n$indent)";
      }

      if( $this->alias )
      {
        print " ";
        print $this->alias;
      }
    }

  }


  class SQLQueryJoin extends SQLQuerySource
  {
    function __construct( $type, $table, $condition )
    {
      parent::__construct($table, ($type ? strtoupper($type) . " " : "") . "JOIN");
      $this->condition = $condition;
    }

    function resolve_expressions( $against )
    {
      $this->condition = $against->resolve_expression($this->condition);
    }

    function generate_sql( $indent )
    {
      parent::generate_sql($indent, false);
      if( !empty($this->condition) )
      {
        print " ON ";
        print $this->condition;
      }
    }
  }



  class SQLQueryFieldReference extends SQLQueryHelper
  {
    function __construct( $table_alias, $field_name )
    {
      $this->table_alias = $table_alias;
      $this->field_name  = $field_name;
    }

    function generate_sql( $indent )
    {
      printf("%s.%s", $this->table_alias, $this->field_name);
    }

    function remap_expressions( $against, $mappings )
    {
      if( array_key_exists($this->table_alias, $mappings) )
      {
        $this->table_alias = $mappings[$this->table_alias];
      }
    }
  }


  class SQLQueryDefinedField extends SQLQueryHelper
  {
    function __construct( $expression )
    {
      $this->expression = $expression;
    }

    function generate_sql( $indent )
    {
      print $this->expression;
    }

    function resolve_expressions( $against )
    {
      $this->expression = $against->resolve_expression($this->expression);
    }

    function remap_expressions( $against, $mappings )
    {
      $this->expression = $against->remap_expression($this->expression, $mappings);
    }
  }
