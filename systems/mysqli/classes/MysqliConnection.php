<?php if (defined($inc = "MYSQLICONNECTION_INCLUDED")) { return; } else { define($inc, true); }

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

  
  
  class MySqliConnection
  {
    protected $handle;
    
    function __construct( $handle, $connector, $name )
    {
      $this->handle     = $handle;
      $this->connector  = $connector;
      $this->name       = $name;
      $this->statements = array();
    }
        
    function __destruct()
    {
      $this->close();
    }

    
    

  //===============================================================================================
  // SECTION: Query and Statement Execution
  
    function query( $query /* parameters... */ )
    {
      return new MySqliResultsSet($this->get_statement());
    }
    
    function execute( $statement /* parameters... */ )
    {
      return 
    }
    



    
    
  //===============================================================================================
  // SECTION: Internals
    
    function get_statement( $query )
    {
      $statement = tree_fetch($this->statements, $query);
      if( !$statement and $statement = $this->handle->prepare($query) )
      {
        $this->statements[$query] = $statement;
      }
      
      $statement or $this->fail("prepare_failed", $query);
      
      return $statement;
    }
    
    
    function fail( $reason, $query = null )
    {
      $details = array();
      $details["reason"     ] = $reason;
      $details["errno"      ] = $this->handle->errno;
      $details["description"] = $this->handle->error;
      
      if( $query )
      {
        Script::throw_exception("mysqli_query_error", "query", $query, $details);
      }
      else
      {
        Script::throw_exception("mysqli_runtime_error", $details);
      }
    }
    
    
    
    
  //===============================================================================================
  // SECTION: Cleanup
    
    
    function close()
    {
      if( $this->handle )
      {
        try {$this->handle->close();} catch (Exception $e) { /* no op */ }
        $this->handle = null;
      }
    }
  }