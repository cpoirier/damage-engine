<?php if (defined($inc = "CORE_MEMCACHECONNECTION_INCLUDED")) { return; } else { define($inc, true); }

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

  defined('MEMCACHE_COMPRESSED') or define('MEMCACHE_COMPRESSED', 2);


  class MemcacheConnection
  {
    static $connections = null;
    static function connect( $servers_csv, $timeout = null, $retries = null, $retry_delay = null )
    {
      is_null(static::$connections) and static::$connections = array();
      is_null($timeout    ) and $timeout     = 1;
      is_null($retries    ) and $retries     = 3;
      is_null($retry_delay) and $retry_delay = 1;

      $connection = null;
      $servers    = is_array($servers_csv) ? $servers_csv : array_filter(array_map('ltrim', explode(", ", $servers_csv)));

      if( empty($servers_csv) )
      {
        Script::fail("memcache_connection_no_servers_provided");
      }
      elseif( count($servers) > 1 or is_array($servers_csv) )
      {
        sort($servers);
        $servers_csv = csv_encode($servers);    // We'll use this as a key into $servers_csv, so ensure it has a consistent order
      }
      
      if( array_has_member(static::$connections, $servers_csv) )
      {
        $connection = static::$connections[$servers_csv];
      }
      elseif( class_exists("Memcache") )
      {
        shuffle($servers);

        $memcache = new Memcache;
        $failure  = null;
        
        for( ; $retries > 0; $retries-- and $retry_delay and sleep($retry_delay) )
        {
          foreach( $servers as $server )
          {
            $server = trim($server);
            if( !empty($server) )
            {
              @list($host, $port) = explode(":", $server);
              $port = $port ? 0 + $port : 11211;
              if( @$memcache->connect($host, $port, $timeout) )
              {
                debug("Connected cache on $host:$port");
                static::$connections[$servers_csv] = $connection = new MemcacheConnection($memcache);
                break 2;                                           //<<<<<<<<<< FLOW CONTROL <<<<<<<<<
              }
            }
          }
        }
      }
      
      return $connection;
    }
    
    
    static function connect_from_configuration( $prefix )
    {
      $servers_csv = Configuration::get("{$prefix}_SERVERS");
      $timeout     = Configuration::get("{$prefix}_TIMEOUT");
      $retries     = Configuration::get("{$prefix}_RETRIES");
      $delay       = Configuration::get("{$prefix}_RETRY_DELAY");
      
      return static::connect($servers_csv, $timeout, $retries, $delay);
    }




  //===============================================================================================
  // SECTION: API
  
    
    function get( $key )
    {
      $value = null;
      if( $this->handle )
      {
        $value = $this->handle->get($key);
      }

      return $value;
    }
    
    
    function set( $key, $value, $expiry_s = 0 )
    {
      strlen($key) < 250 or Script::fail("memcache_key_length_too_long", array("key" => $key, "limit" => 250, "actual" => strlen($key)));

      if( $this->handle )
      {
        return $this->handle->set($key, $value, MEMCACHE_COMPRESSED, (int)$expiry_s);
      }
      
      return false;
    }


    function add( $key, $value, $expiry_s = 0 )
    {
      strlen($key) < 250 or Script::fail("memcache_key_length_too_long", array("key" => $key, "limit" => 250, "actual" => strlen($key)));

      if( $this->handle )
      {
        return $this->handle->add($key, $value, MEMCACHE_COMPRESSED, (int)$expiry);
      }
      
      return false;
    }
    
    
    function delete( $key )
    {
      if( $this->handle )
      {
        return $this->handle->delete($key);
      }
      
      return false;
    }


    function close()
    {
      if( $this->handle )
      {
        @memcache_close($this->handle);
        $this->handle = null;
      }
    }


    
    
  //===============================================================================================
  // SECTION: Internals
  
    protected $handle;
  
    function __construct( $handle )
    {
      $this->handle = $handle;
    }
  
    function __destruct()
    {
      $this->close();
    }
  }