<?php if (defined($inc = "CORE_SQLRESULTSSET_INCLUDED")) { return; } else { define($inc, true); }

  // Damage Engine Copyright 2012-2015 Massive Damage, Inc.
  // Based on work Copyright 2011 1889 Labs
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

  
  // An abstract base class for things that return result records from a database.
  
  class SqlResultSet implements Iterator
  {
    function __construct()
    {
      $this->filters           = array("query_result");
      $this->structure_filters = array();
    }
    
    function __destruct()
    {
      $this->close();
    }


    
    
  //===============================================================================================
  // SECTION: What you'll need to override:
  
    
    function fetch()          // Fetches the next row from the results. Returns null if no more rows.
    {
      abort("override");
    }

    function rewind()
    {
      abort("override");
    }
  
    function current() 
    {
      abort("override");
    }

    function key()
    {
      abort("override");
    }
  
    function next()
    {
      $this->fetch();
    }
  
    function valid()
    {
      abort("override");
    }
  
    function close()
    {
      abort("override");
    }
    
    

    
    
  //===============================================================================================
  // SECTION: Filter management

    function add_filter( $filter )
    {
      $this->filters[] = $filter;
    }
  
    function add_structure_filter( $filter )
    {
      $this->structure_filters[] = $filter;
    }
  
  
  
  
  //===============================================================================================
  // SECTION: Converters
  
    function get_first()
    {
      $first = null;
      foreach( $this as $row )
      {
        $first = $row;
        break;
      }
      
      return $first;
    }
  
    function get_first_value( $field, $default = null )
    {
      $value = $default;
      foreach( $this as $row )
      {
        $value = TypeConverter::coerce_type($row->$field, $default);
        break;
      }

      return $value;
    }


    function has_results()
    {
      $this->rewind();
      return $this->valid();
    }

    
    
    function as_list( $fields = null )
    {
      $list = array();
      foreach( $this as $row )
      {
        $list[] = $this->project($row, $fields);
      }
      
      return $list;
    }
    
    
    function as_map( $key_fields, $value_fields = null )
    {
      $map        = array();
      $key_fields = (array)$key_fields;
      $key_depth  = count($key_fields);
      
      $key_depth > 10 and abort("to_map_max_key_depth_is_10");
      
      foreach( $this as $row )
      {
        $keys  = array_values(get_object_vars($this->project($row, $key_fields)));
        $value = $this->project($row, $value_fields);

        @list($k0, $k1, $k2, $k3, $k4, $k5, $k6, $k7, $k8, $k9) = $keys;
      
        switch( $key_depth )
        {
          case  1: @$map[$k0]                                              = $value; break;
          case  2: @$map[$k0][$k1]                                         = $value; break;
          case  3: @$map[$k0][$k1][$k2]                                    = $value; break;
          case  4: @$map[$k0][$k1][$k2][$k3]                               = $value; break;
          case  5: @$map[$k0][$k1][$k2][$k3][$k4]                          = $value; break;
          case  6: @$map[$k0][$k1][$k2][$k3][$k4][$k5]                     = $value; break; 
          case  7: @$map[$k0][$k1][$k2][$k3][$k4][$k5][$k6]                = $value; break;
          case  8: @$map[$k0][$k1][$k2][$k3][$k4][$k5][$k6][$k7]           = $value; break;
          case  9: @$map[$k0][$k1][$k2][$k3][$k4][$k5][$k6][$k7][$k8]      = $value; break;
          case 10: @$map[$k0][$k1][$k2][$k3][$k4][$k5][$k6][$k7][$k8][$k9] = $value; break;
        }
      }
      
      return $map;
    }
  
  

    // Builds a simple tree from a sorted, flat record set, based on a program you supply. 
    // Useful for when you join across a sequence of one-to-many relationships and want a
    // nested data structure back out.
    //
    // Example:
    //   $query = "
    //     SELECT a.id, a.name, b.id as room_id, b.room_name, b.size, c.id as item_id, c.item
    //     FROM a
    //     JOIN b on b.a_id = a.id
    //     JOIN c on c.b_id = b.id
    //     ORDER BY a.id, room_id, item_id
    //   ";
    //
    // Program structure 1:
    //   $program = array
    //   (
    //       0       => array("name", "id", "name", "age")      // The top-level map of name to record
    //     , "rooms" => array("room_name", "room_id", "size")   // An map of objects to appear in top->rooms
    //     , "items" => array(null, "item")                     // An array of strings to appear in top->room->items
    //   );
    //
    // Program structure 2:
    //   $program = array("name", "id", "name", "age", 
    //                            "rooms" => array("room_name", "room_id", "size", 
    //                                                          "items" => array(0, "item")));
    //
    //   $tree = $result->as_tree($program);

    function as_tree( $program )
    {
      // Flatten the program, if using the nested format.

      if( !is_array($program[0]) || (count($program) > 1 && isset($program[1])) )
      {
        $queue    = array($program);
        $program  = array();
        $this_key = 0;
        $next_key = null;

        while( !empty($queue) )
        {
          $level = array_shift($queue);
          $line  = array();
          foreach( $level as $key => $value )
          {
            if( is_array($value) )
            {
              $next_key = $key;
              array_unshift($queue, $value);
              break;
            }
            elseif( is_string($key) && is_string($value) )
            {
              $line[$key] = $value;
            }
            else
            {
              $line[] = $value;
            }
          }

          $program[$this_key] = $line;
          $this_key = $next_key;
          $next_key = null;
        }
      }


      // We are sort of knitting the flat record set back into a tree. $top is the real
      // result set to be returned, while the working edge of all (nested) levels is
      // maintained in $lasts.

      $top       = array();
      $lasts     = array();
      $previous  = null;
      $level_map = array_keys($program);
      foreach( $this->query_all($args) as $record )
      {
        // First, pop off anything that is finished.

        is_null($previous) and $previous = $record;
        foreach( $level_map as $level_index => $level_name )
        {
          if( count($lasts) > $level_index )
          {
            foreach( $program[$level_name] as $property => $field )
            {
              if( $field && substr($field, 0, 1) != "+" && $previous->$field != $record->$field )
              {
                while( count($lasts) > $level_index )
                {
                  array_pop($lasts);
                }

                break 2;
              }
            }
          }
        }

        $previous = $record;


        // Next, create objects along the current record.

        for( $level_index = count($lasts); $level_index < count($level_map); $level_index++ )
        {
          // Get the level instructions. The first name is the key, the rest fields.

          $level     = $program[$level_map[$level_index]];
          $key       = $level[0];
          $fields    = array_slice($level, 1);
          $null_test = $key ? $key : @$fields[0];

          // Create an object to hold this branches' data and fill it with data from this record.

          if( $null_test && is_null(@$record->$null_test) )
          {
            continue;
          }
          elseif( count($fields) == 0 )
          {
            $object = array();
          }
          elseif( count($fields) == 1 )
          {
            reset($fields);
            $field  = current($fields);
            $object = $record->$field;
          }
          else
          {
            $object = new stdClass;
            $key and $object->$key = $record->$key;

            if( $level_index + 1 < count($level_map) )
            {
              $child_container_name = $level_map[$level_index + 1];
              $object->$child_container_name = array();
            }

            foreach( $fields as $property => $field )
            {
              is_string($property) or $property = $field;

              if( substr($field, 0, 1) == "+" )
              {
                $name_field = substr($field, 1);
                if( isset($record->$name_field) && is_array($record->$name_field) )
                {
                  foreach( $record->$name_field as $field )
                  {
                    $object->$field = @$record->$field;
                  }
                }
              }
              else
              {
                $object->$property = @$record->$field;
              }
            }
          }

          // Add the object to its container and make sure it can be found for the next level and
          // future records.

          $container =& $top;
          if( $level_index )
          {
            $parent_container_name = $level_map[$level_index];
            if( isset($lasts[$level_index - 1]->$parent_container_name) )
            {
              $container =& $lasts[$level_index - 1]->$parent_container_name;
            }
            else
            {
              $container =& $lasts[$level_index - 1];
            }
          }

          if( is_string($key) )
          {
            $container[$record->$key] = $object;
            $lasts[$level_index] =& $container[$record->$key];
          }
          elseif( $object )
          {
            $container[] = $object;
            $lasts[$level_index] =& $container[count($container) - 1];
          }
        }
      }

      // Allow the world to adjust the finished products and return.
      
      $finished = array();
      foreach( $top as $key => $object )
      {
        $finished[$key] = Script::filter($this->structure_filters, $object, $key, $this);
      }

      return $finished;
    }
 



  //===============================================================================================
  // SECTION: Internals
  
    function project( $row, $fields = null )
    {
      if( is_null($fields) or $fields == "*" )
      {
        return $row;
      }
      elseif( is_scalar($fields) )
      {
        $field = $fields;
        return isset($row->$field) ? $row->$field : null;
      }
      else
      {
        $p = (object)null;
        foreach( $fields as $field )
        {
          $p->$field = isset($row->$field) ? $row->$field : null;
        }
      
        return $p;
      }
    }
    
  }