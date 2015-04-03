<?php if (defined($inc = "DATA_HANDLER_FOR_STRUCTURED_PROPERTY_INCLUDED")) { return; } else { define($inc, true); }

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



  class DataHandlerForStructuredProperty extends DataHandlerForCollectionProperty
  {
    function __construct( $owner, $name, $source, $source_query, $descriptor, $namespace = null )
    {
      parent::__construct($owner, $name, $source, $source_query, $namespace);
      $this->descriptor = $descriptor;
    }


    function load()
    {
      $value = $this->source->query_structure($this->get_cache_key(), $this->descriptor, $this->source_query);
      $this->set_value($value);
    }


    function check_for_discrepancies( $db )
    {
      $change_records = array();
      $in_memory = $this->get_value($convert = true);
      $in_db     = $db->query_structure($this->descriptor, $this->source_query);

      $key_fields = array();
      $key_pairs  = $this->owner->get_key_pairs();
      $vector     = is_array($this->descriptor[0]) ? $this->descriptor[0] : array($this->descriptor[0]);
      return $this->build_change_set($in_memory, $in_db, $vector, $key_pairs, $db);
    }


    protected function build_vector_for_substructure( $vector, $substructure )
    {
      $context_key_pairs = $this->owner->get_key_pairs();
      $vector = array_slice($vector, count($context_key_pairs));

      $descriptor =& $this->descriptor;
      while( true )
      {
        $key_fields = is_array($descriptor[0]) ? $descriptor[0] : array($descriptor[0]);
        $vector = array_slice($vector, count($key_fields));

        if( empty($vector) ) // We are on the right level
        {
          if( array_key_exists($substructure, $descriptor) )
          {
            $descriptor =& $descriptor[$substructure];
            return is_array($descriptor[0]) ? $descriptor[0] : array($descriptor[0]);
          }
          else
          {
            throw new DataHandlerIncompatibleStructuresError($this->owner, $this->name, "cannot find substructure $structure");
          }
        }
        else
        {
          $found = false;
          foreach( $descriptor as $name => &$value )
          {
            if( is_array($value) )
            {
              $descriptor =& $value;
              $found = true;
              break;
            }
          }

          if( !$found )
          {
            throw new DataHandlerIncompatibleStructuresError($this->owner, $this->name, "cannot find appropriate substructure to continue digging");
          }
        }
      }

      return null;
    }

  }
