<?php if (defined($inc = "DATA_HANDLER_FOR_LIST_PROPERTY_INCLUDED")) { return; } else { define($inc, true); }

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


  class DataHandlerForListProperty extends DataHandlerForCollectionProperty
  {
    function __construct( $owner, $name, $source, $source_query, $column = "*", $namespace = null, $post_processor = null, $post_processor_error_handler = null )
    {
      parent::__construct($owner, $name, $source, $source_query, $namespace, $post_processor, $post_processor_error_handler);
      $this->column = $column;
    }

    function load()
    {
      $this->engage_post_processor();
      $value = $this->column == "*" ? $this->source->query_all($this->get_cache_key(), $this->source_query) : $this->source->query_column($this->get_cache_key(), $this->column, $this->source_query) or $value = array();
      $this->disengage_post_processor();
      $this->set_value($value);
    }

    function check_for_discrepancies( $db )
    {
      $change_records = array();

      if( $this->column == "*" )
      {
        $in_memory = $this->get_value($convert = true);
        $in_db     = $db->query_all($this->source_query);

        if( empty($in_memory) && empty($in_db) )
        {
          // no op
        }
        elseif( empty($in_memory) )
        {
          foreach( $in_db as $row )
          {
            $pairs = array_merge($this->owner->get_key_pairs(), (array)$row);
            $change_records[] = ChangeRecord::capture("delete", $pairs, $this->source_query, $db->schema);
          }
        }
        elseif( empty($in_db) )
        {
          foreach( $in_memory as $row )
          {
            $pairs = array_merge($this->owner->get_key_pairs(), (array)$row);
            $change_records[] = ChangeRecord::capture("insert", $pairs, $this->source_query, $db->schema);
          }
        }
        else
        {
          $sample = current($in_db);
          $map    = ChangeRecord::map($sample, $this->source_query, $db);
          if( count($map) != 1 )
          {
            throw new DataHandlerInsufficientStructureError($this->owner, $this->name, "cannot handle analyzing a multi-table list for discrepancies");
          }

          reset($map);
          $table_name = key($map);
          $table      = $db->schema->get_table($table_name);
          $pk_fields  = $table->get_pk_field_names();
          $key_pairs  = $this->owner->get_key_pairs();

          foreach( $in_memory as $object )
          {
            foreach( $key_pairs as $name => $value )
            {
              $object->$name = $value;
            }
          }

          foreach( $in_db as $object )
          {
            foreach( $key_pairs as $name => $value )
            {
              $object->$name = $value;
            }
          }

          $in_memory =& $this->build_map_from_list_of_objects($in_memory, $pk_fields);
          $in_db     =& $this->build_map_from_list_of_objects($in_db    , $pk_fields);

          $change_records = $this->build_change_set($in_memory, $in_db, $pk_fields, $key_pairs, $db);
          return $change_records;
        }
      }
      else
      {
        $in_memory = $this->get_value();
        $in_db     = $db->query_column($this->column, $this->source_query);

        sort($in_memory);
        sort($in_db    );

        foreach( array_diff($in_memory, $in_db) as $added )
        {
          $pairs = $this->owner->get_key_pairs();
          $pairs[$this->column] = $added;
          $change_records[] = ChangeRecord::capture("insert", $pairs, $this->source_query, $db->schema);
        }

        foreach( array_diff($in_db, $in_memory) as $removed )
        {
          $pairs = $this->owner->get_key_pairs();
          $pairs[$this->column] = $removed;
          $change_records[] = ChangeRecord::captue("delete", $pairs, $this->source_query, $db->schema);
        }
      }

      return $change_records;
    }



    // function replace()
    // {
    //   $args  = $this->extract(func_get_args());
    //   $pairs = array_merge($args[0], $this->owner->get_key_pairs());
    //
    //   //
    //   // Generate the change record and add it to our owner.
    //
    //   $this->changes[] = ChangeRecord::capture("insert", $pairs, $this->source_query, $this->source->schema);
    //
    //   //
    //   // Write the data into the list.
    //
    //   $value = (is_array($this->column) || $this->column == "*") ? $this->post_process((object)$pairs) : $pairs[$this->column];
    //   $set =& $this->get_value();
    //   $set << $value;
    // }


    //
    // Adds a new record. The only parameter is a singular value or a map of pairs.
    // Returns any auto-generated keys, one if singular, a map of field => id otherwise.

    function add()
    {
      $args  = $this->extract(func_get_args());
      $pairs = $this->owner->get_key_pairs();

      //
      // Add the named parameters to the pairs.

      if( !empty($args) )
      {
        $this->fill_pairs($pairs, $args[0], $this->column);
      }

      //
      // Generate the change record and apply it to our source.

      $results   = $this->apply_change($change_record = ChangeRecord::capture("insert", $pairs, $this->source_query, $this->source->schema), $return_results = true);
      $generated = array();
      foreach( $change_record->instructions as $instruction )
      {
        $table = $instruction->table;
        if( $table->id_is_autoincrement )
        {
          $generated[$table->id_field] = $results[$table->name];
        }
      }

      //
      // Write the data into the map.

      $value = (is_array($this->column) || $this->column == "*") ? $this->post_process((object)$pairs) : $pairs[$this->column];
      $set =& $this->get_value();
      $set or $set = array();

      $append = !(@$this->source_query->order_by_fields[1] == -1);
      $append ? array_push($set, $value) : array_unshift($set, $value);

      if( $this->source_query->limit )
      {
        while( count($set) > $this->source_query->limit )
        {
          $append ? array_shift($set) : array_pop($set);
        }
      }

      return count($generated) == 1 ? current($generated) : $generated;
    }




  }
