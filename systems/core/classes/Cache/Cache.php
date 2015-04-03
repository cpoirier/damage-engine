<?php if (defined($inc = "CORE_CACHE_INCLUDED")) { return; } else { define($inc, true); }

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


  require_once __DIR__ . "/../Symbol.php";
  
  
  class Cache
  {
    protected $connection;
    protected $namespace;
    protected $default_max_age;
    protected $claims;
    
    
    function __construct( $connection, $namespace )
    {
      $this->connection       = $connection;
      $this->namespace        = $namespace;
      $this->default_max_age  = ONE_YEAR;
      $this->claims           = array();
    }
    
    function __destruct()
    {
      if( $this->connection )
      {
        $this->release_all();
        $this->connection = null;
      }
    }
  
    function __sleep()
    {
      return array();
    }
    
    
    
    function get( $key, $max_age = null )
    {
      $value = null;
      $fqn   = $this->qualify_name($key);
      
      is_null($max_age) and $max_age = $this->default_max_age;
            
      if( $max_age )
      {
        Script::increment("cache_attempts");

        $oldest_acceptable = static::determine_age_limit(abs($max_age));

        debug("CACHE GET: $key ($fqn) with age limit $oldest_acceptable");
        Script::signal("getting_from_cache", $key, $fqn, $this);
        
        $start = microtime(true);
        $entry = false;

        try
        {
          $entry = $this->connection->get($fqn);
        }
        catch( Exception $e )
        {
          // likely class loading failed; discard the data and ignore it
        }
        
        Script::accumulate("cache_wait_time", microtime(true) - $start);
        
        if( $entry !== false and $entry->created >= $oldest_acceptable )
        {
          debug("CACHE GOT: $key");
          Script::increment("cache_hits");
          $value = $entry->value;
        }
      }

      is_null($value) and $value = Script::filter("cache_miss", $value, $key, $this);
      
      if( is_null($value) )
      {
        Script::increment("cache_misses");
      }
      else
      {
        Script::signal("got_from_cache", $value, $key, $fqn, $this);
      }
    
      return $value;
    }



    function set( $key, $value, $settings = null )
    {
      $settings = $this->parse_settings($settings);
      $expiry   = array_fetch_value($settings, "expiry", 0    );
      $method   = array_fetch_value($settings, "method", "set");
      $entry    = new Cache_Entry($key, $value);
      $fqn      = $this->qualify_name($key);

      debug("CACHE SET: $key");
      Script::increment("cache_writes");
      
      $start = microtime(true);
      if( !$this->connection->$method($fqn, $entry, $expiry) )
      {
        Script::accumulate("cache_wait_time", microtime(true) - $start);
        return false;
      }
      
      Script::accumulate("cache_wait_time", microtime(true) - $start);

      return true;
    }
    
    
    function add( $key, $value, $expiry = 0, $settings = null )
    {
      $settings = $this->parse_settings($settings);
      $settings["expiry"] = $expiry;
      $settings["method"] = "add";
      
      return $this->set($key, $value, $settings);
    }
    
    
    function delete( $key, $retrieve = false )
    {
      Script::increment("cache_deletes");

      $result = $retrieve ? $this->get($key) : null;

      $fqn = $this->qualify_name($key);
      $this->connection->delete($fqn);
      return $result;
    }
    
    
    
    
  //===============================================================================================
  // SECTION: Inter-process coordination
  
  
    function claim_and_get( $key, $max_age = null, $block = true, $timeout = 0 )   // Returns the named object only if claimed first
    {
      if( $this->claim($key, $block, $timeout) )
      {
        return $this->get($key, $max_age);
      }

      return null;
    }


    //
    // Attempts to claim the specified key. Any other cache user that attempts to claim the same
    // key will fail or block until you release() the key. Claimed keys are automatically released
    // for you (eventually), but you should probably release() them explicitly, to minimize
    // disruption to and failures for other users. You can safely nest claim()s for the same key,
    // as long as you release() the same number of times.
    //
    // NOTE: $timeout is in seconds.

    function claim( $key, $block = true, $timeout_ms = 0, $expiry_s = null )     // Attempts to claim the specified key in the (shared) cache, giving you some amount of time before someone else can claim it. NB: Doesn't actually prevent writing to the underlying object!
    {      
      // Bypass the hard work if we have already claimed the key.

      if( array_fetch_value($this->claims, $key, 0) > 0 )
      {
        $this->claims[$key] += 1;
        return $this->claims[$key];                        //<<<<<<<<<< FLOW CONTROL <<<<<<<<<<<
      }


      // If we are still here, we're doing it the hard way.

      $timeout_ms or $timeout_ms = Configuration::get("CACHE_DEFAULT_CLAIM_TIMEOUT_MS", Configuration::get("CACHE_DEFAULT_CLAIM_TIMEOUT_S", 8) * 1000);
      is_null($expiry_s) and $expiry_s = max(ceil($timeout_ms / 1000) * 2, Configuration::get("CACHE_DEFAULT_CLAIM_EXPIRY_S", 30));     // This used to be calculated off max_execution_time, but random values are dangerous. This is a reasonable limit.

      $cache_key   = "$key::claim";
      $fqn         = $this->qualify_name($cache_key);
      $start       = microtime(true);
      $claimed     = false;
      $pid         = Script::get_id();
      $min_wait_us = greatest_of(ceil($timeout_ms * 1000 / 101), 200);
      $max_wait_us = greatest_of(ceil($timeout_ms * 1000 /   5), 500);
      $timeout_s   = $timeout_ms / 1000.0; 


      // This bit of processing can get very complex to debug, as it is hard to duplicate the
      // problems in a controlled environment. So, we'll take extra steps to track who is
      // blocking us, so that if we fail, we can provide useful logging.

      $blocker_log     = array();
      $blocker_summary = array();
      $debug_claims    = Features::enabled("debug_cache_claims");

      do
      {
        $attempt = microtime(true);
        $claimed = $this->connection->add($fqn, new Cache_Entry($cache_key, $pid), $expiry_s);

        if( !$claimed and $debug_claims )
        {
          $blocker = $this->connection->get($fqn);
          $record  = new Cache_BlockedClaimRecord($attempt, $blocker ? $blocker->value : "removed");

          $blocker_log[] = $record;
          @$blocker_summary[$record->by] += 1;
        }
      }
      while( !$claimed && $block && microtime(true) - $start < $timeout_s && is_null(usleep(mt_rand($min_wait_us, $max_wait_us))) );

      $completed = microtime(true);
      $wait_time = $completed - $start;

      Script::accumulate("cache_claim_time", $wait_time);
      Script::signal("cache_claim_processed", $key, $completed, $claimed, $wait_time, $blocker_log, $blocker_summary, $this);

      if( $claimed )
      {
        debug("CACHE CLAIMED: $cache_key");
        $this->claims[$key] = 1;
        return 1;
      }

      debug("CACHE CLAIM FAILED: $cache_key");
      return 0;
    }


    function release( $key )   // Releases the claimed key. You really, really shouldn't call this unless you successfully claim()ed first.
    {
      $key       = $this->key_for($key);
      $released  = false;
      $pid       = Script::get_id();

      if( isset($this->claims[$key]) )
      {
        if( $this->claims[$key] > 1 )
        {
          $this->claims[$key] -= 1;
          $released = true;
        }
        elseif( $this->claims[$key] == 1 )
        {
          $cache_key = "$key::claim";
          $fqn       = $this->qualify_name($cache_key);
          
          if( $existing = $this->connection->get($fqn) )
          {
            if( !is_a($existing, "Cache_Entry") || $existing->value == $pid )
            {
              if( $this->connection->delete($fqn) )
              {
                Script::increment("cache_claims_released");
                unset($this->claims[$key]);
                $released = true;

                Script::signal("cache_claim_released", $key, $this);
                debug("CACHE CLAIM RELEASED: $key");
              }
            }
          }
        }
      }

      return $released;
    }


    function release_all()      // Releases all claims.
    {
      foreach( array_keys($this->claims) as $key )
      {
        if( $this->claims[$key] > 0 )
        {
          $this->claims[$key] = 1;
        }

        $this->release($key);
      }
    }


    function has_claimed( $key )    // Returns true if you hold a claim on the specified key.
    {
      return array_fetch_value($this->claims, $key, 0) > 0;
    }


    // Attempts to claim several keys, all within a single overall time limit. Returns a map
    // of claim key to claim count if all claims were required. Any acquired keys will be
    // released if the routine fails.

    function claim_several( $keys, $block = true, $overall_timeout_ms = 0 )
    {
      $overall_timeout_ms = greatest_of($overall_timeout_ms, 0);
      $has_timeout        = ($overall_timeout_ms > 0);
      $remaining_ms       = $overall_timeout_ms;
      $start              = microtime(true);


      // Attempt the claims in a consistent order, so there's less chance of a deadlock

      sort($keys);
      $claim_counts = array();
      foreach( $keys as $key )
      {
        if( $claim_count = $this->claim($key, $block, $remaining_ms) )
        {
          $claim_counts[$key] = $claim_count;
          if( $has_timeout )
          {
            $elapsed_s    = microtime(true) - $start;
            $remaining_ms = ceil($overall_timeout_ms - ($elapsed_s * 1000));
            
            if( $remaining_ms <= 0 )
            {
              break;
            }
          }
        }
        else
        {
          break;
        }
      }


      // This is an all or nothing operation. Clean up if we failed.

      if( count($claimed) < count($keys) )
      {
        foreach( array_reverse(array_keys($claimed)) as $key )
        {
          $this->release($key);
        }

        return false;
      }

      return $claim_counts;
    }


    function forget_claims()        // For testing purposes only, forgets all current claims, preventing them from being released (before automatic expiry).
    {
      $this->claims = array();
    }
  



  //===============================================================================================
  // SECTION: Connection sugar
  
    static function connect( $namespace, $type /* type parameters... */ )
    {                                                                                                                            $_ = TRACE_ENTRY(__METHOD__);
      $args  = array_slice(func_get_args(), 2);
      $class = Symbol::convert_snake_to_pascal_case($type) . "Connection";                                                            TRACE(__METHOD__, "using $class");
      class_exists($class, $autoload = false) or Script::safe_require_once(__DIR__ . "/$class.php");                                  TRACE(__METHOD__, "$class loaded");
      if( $connection = call_user_func_array(array($class, "connect"), $args) )
      {                                                                                                                               TRACE(__METHOD__, "connected");
        return new Cache($connection, $namespace);
      }
                                                                                                                                      TRACE(__METHOD__, "exiting null");
      return null;
    }

    static function connect_from_configuration( $namespace, $type, $prefix )
    {                                                                                                                            $_ = TRACE_ENTRY(__METHOD__);
      $class = Symbol::convert_snake_to_pascal_case($type) . "Connection";                                                            TRACE(__METHOD__, "using $class");
      class_exists($class, $autoload = false) or Script::safe_require_once(__DIR__ . "/$class.php");                                  TRACE(__METHOD__, "$class loaded");
      if( $connection = call_user_func_array(array($class, "connect_from_configuration"), array($prefix)) )
      {                                                                                                                               TRACE(__METHOD__, "connected");
        return new Cache($connection, $namespace);
      }
                                                                                                                                      TRACE(__METHOD__, "exiting null");
      return null;
    }
    
    
    static function preload_from_configuration( $type )
    {
      $class = Symbol::convert_snake_to_pascal_case($type) . "Connection";
      class_exists($class, $autoload = false) or Script::safe_require_once(__DIR__ . "/$class.php");
    }
  



  //===============================================================================================
  // SECTION: Support routines
    
    function qualify_name( $name )
    {
      return sprintf("%s::%s", $this->namespace, $name);
    }
        

    function parse_settings( $settings )   // Parses a settings array/string into an array of key/value pairs.
    {
      if( is_string($settings) )
      {
        $unparsed = $settings;
        $settings = array();
        parse_str($unparsed, $settings);
      }
      elseif( !is_array($settings) )
      {
        $settings = array();
      }

      return $settings;
    }
    
  
    static function determine_age_limit()
    {
      $age_limit = 0;
      foreach( func_get_args() as $limit )
      {
        if( $limit )
        {
          $limit = static::canonicalize_time($limit);
          $age_limit >= $limit or $age_limit = $limit;
        }
      }

      return $age_limit;
    }
    
    static function canonicalize_time( $value )
    {
      return $value > ONE_YEAR ? $value : (time() - $value);
    }
    
  }
  
  
  
  
//===============================================================================================
// SECTION: Support classes
  
  class Cache_Entry
  {
    function __construct( $key, $value, $created = 0 )
    {
      $this->key     = $key;
      $this->value   = $value;
      $this->created = $created ? $created : time();
    }
  }
  
  class Cache_BlockedClaimRecord
  {
    function __construct( $at, $by )
    {
      $this->at = $at;
      $this->by = $by;
    }
  }
  
