<?php if (defined($inc = "MYSQLIRESULTSSET_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class MySqliResultsSet extends ResultsSet
  {
    protected $handle;
    
    function __construct( $handle )
    {
      parent::__construct();
      
      $this->handle   = $handle;
      $this->current  = null;
      $this->position = 0;
    }
    
    
    function fetch()          // Fetches the next row from the results. Returns null if no more rows.
    {
      $this->current = null;
      $row = null;
      
      while( $this->handle and is_null($row) )
      {
        if( $row = $this->handle->fetch_object() )
        {
          $row = Script::filter($this->filters, $row, $this->position, $this);
        }
        else
        {
          $this->close();
        }
      }
      
      $this->position += 1;
      $this->current   = $row;
      
      return $row;
    }


    function give_up_handle()    // Removes and returns the underlying myslqi result handle from this set
    {
      $handle = $this->handle;
      $this->handle = null;
      
      return $handle;
    }
    
    
    function close()
    {
      if( $this->handle )
      {
        try {$this->handle->free();} catch (Exception $e) { /* no op */ }
        $this->handle = null;
      }
    }
    

    function rewind()
    {
      if( $this->position > 0 )
      {
        $this->handle and $this->handle->data_seek(0);
        $this->position = 0;
      }
      
      $this->fetch();
    }
    
    function current() 
    {
      return $this->current;
    }
  
    function key()
    {
      return $this->position;
    }
    
    function next()
    {
      $this->fetch();
    }
    
    function valid()
    {
      return !is_null($this->current);
    }
    
  }