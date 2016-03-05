<?php if (defined($inc = "MYSQLICONNECTOR_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class MysqliConnector
  {
    public    $masters;
    public    $slaves;
    public    $user;
    public    $database;
    protected $schema_name;
    private   $password;
    

    private static $connectors;


    // Creates a connector from a URL-encoded descriptor. The following fields are expected (you really
    // should supply at least one master):
    //   db      => database name
    //   user    => connection user name
    //   pass    => connection password
    //   master  => the name of a writeable master server
    //   masters => an array of master names, if there are more than one
    //   slave   => the name of a read-only slave server
    //   slaves  => an array of slave names, if there are more than one

    static function build( $descriptor )
    {
      empty(static::$connectors) and static::$connectors = array();

      if( !array_key_exists($descriptor, static::$connectors) )
      {
        $details = null;
        if( function_exists("mb_parse_str") )
        {
          mb_parse_str($descriptor, $details);
        }
        else
        {
          parse_str($descriptor, $details);
        }

        if( !empty($details) )
        {
          $user      = array_fetch_value($details, "user");
          $pass      = array_fetch_value($details, "pass");
          $db        = array_fetch_value($details, "db"  );
          $masters   = array_unique(array_filter(array_merge(array_fetch_value($details, "masters", array()), array(array_fetch_value($details, "master")))));
          $slaves    = array_unique(array_filter(array_merge(array_fetch_value($details, "slaves" , array()), array(array_fetch_value($details, "slave" )))));

          static::$connectors[$descriptor] = new static($masters, $slaves, $user, $pass, $db);
        }
      }

      return array_fetch_value(static::$connectors, $descriptor, null);
    }
    
    
    static function connect_now( $descriptor, $for_writing = true, $throw_on_failure = null )
    {
      if( $connector = static::build($descriptor) )
      {
        return $connector->connect(null, $for_writing, $throw_on_failure);
      }
      
      return null;
    }



    function __construct( $masters, $slaves, $user, $password, $database )
    {
      $this->masters     = $masters;
      $this->slaves      = $slaves;
      $this->user        = $user;
      $this->password    = $password;
      $this->database    = $database;
      $this->schema_name = sprintf("mysqli:%s:%s", implode(",", $this->masters), $this->database);
      $this->last_error  = null;
    }
    
    function get_schema_name()
    {
      return $this->schema_name;
    }
    
    function connect_for_writing( $statistics_collector = null, $throw_on_failure = null )
    {
      return $this->connect($statistics_collector, $for_writing = true, $throw_on_failure);
    }

    function connect_for_reading( $statistics_collector = null, $throw_on_failure = null )
    {
      return $this->connect($statistics_collector, $for_writing = false, $throw_on_failure);
    }

    function connect( $statistics_collector = null, $for_writing = true, $throw_on_failure = null )
    {
      $servers    = ($for_writing ? $this->masters : array_merge($this->masters, $this->slaves));
      $user       = $this->user;
      $pass       = $this->password;
      $db         = $this->database;
      $error      = null;
      $utc_offset = date('P');
      $utc_setter = sprintf("set time_zone = '%s'", $utc_offset);

      $this->last_error = null;
      if( !empty($servers) )
      {
        shuffle($servers);
        foreach( $servers as $server )
        {
          $host = $port = $socket = null;
          if( substr($server, 0, 1) == "/" )
          {
            $socket = $server;
          }
          else
          {
            @list($host, $port) = explode(":", $server, 2); 
            $port = (int)($port ?: 3306);
          }
          
          if( $handle = @mysqli_connect($host, $user, $pass, $db, (int)$port, (string)$socket) )
          {
            $okay = true;
            $okay and $okay = $handle->autocommit($for_writing);           // we use transactions for writing
            $okay and $okay = $handle->set_charset("utf8");                // utf8 seems a good choice
            $okay and $okay = ($this->get_database_name($handle) == $db);  // ensure we got the db we asked for
            $okay and $okay = $handle->real_query($utc_setter);            // ensure both ends of the connection have the same time zone

            
            if( $okay )
            {
              return new MysqliConnection($handle, $this, $this->schema_name);
            }
            else
            {
              @$handle->close();
              $handle = null;
            }
          }
          elseif( $errno = mysqli_connect_errno() )
          {
            $this->last_error = (object)array("errno" => $errno, "error" => @mysqli_connect_error());
          }
        }
      }
      else
      {
        $this->last_error = (object)array("errno" => -1, "error" => "no servers provided in configuration");
      }

      is_null($throw_on_failure) and $throw_on_failure = error_reporting() & E_USER_ERROR;
      if( $throw_on_failure and $this->last_error and $this->last_error->errno )
      {
        Script::throw_exception("mysqli_connection_error", "errno", $this->last_error->error, "description", $this->last_error->error);
      }

      return null;
    }
    
    
    function get_connection_error()
    {
      return $this->last_error;
    }
    
    
    
    
    
  //===============================================================================================
  // SECTION: Internals
  
    protected function get_database_name( $handle )
    {
      $name = null;
      if( $result = @$handle->query("SELECT DATABASE()") )
      {
        $row  = @$result->fetch_row();
        $name = @$row[0];
        @$result->close();
      }
      
      return $name;
    }
  }
