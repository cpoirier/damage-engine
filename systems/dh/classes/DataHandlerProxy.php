<?php if (defined($inc = "DATA_HANDLER_PROXY_INCLUDED")) { return; } else { define($inc, true); }

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



  class DataHandlerProxy
  {
    protected $_key_pairs;
    protected $_handlers;


    //
    // Gets or creates a DHP for the specified key pairs. For clients that want to share data
    // across objects. $key must uniquely identify a DHP across the system.

    static function find( $key, $key_pairs )
    {
      $proxy = null;

      is_array(static::$_existing_proxies) or static::$_existing_proxies = array();
      if( array_key_exists($key, static::$_existing_proxies) )
      {
        $proxy = static::$_existing_proxies[$key];
      }
      else
      {
        $proxy = new static($key_pairs);
        static::$_existing_proxies[$key] = $proxy;
      }

      return $proxy;
    }

    static protected $_existing_proxies;






    function __construct( $key_pairs )
    {
      $this->_key_pairs = $key_pairs;
      $this->_handlers  = array();
    }

    function __sleep()
    {
      return array();
    }

    function get_key_pairs()
    {
      return $this->_key_pairs;
    }

    function has_handler( $name )
    {
      return array_key_exists($name, $this->_handlers);
    }
    
    function drop_handler( $name )
    {
      unset($this->_handlers[$name]);
    }

    function get_handler( $name, $remove = false )
    {
      return array_fetch_value($this->_handlers, $name, null, $remove);
    }

    function add_handler( $handler )
    {
      $this->_handlers[$handler->name] = $handler;
    }

    function get_handler_names()
    {
      return array_keys($this->_handlers);
    }

    function load( $handler_names = array() )
    {
      $handler_names = empty($handler_names) ? array_keys($this->_handlers) : (array)$handler_names;
      foreach( $handler_names as $name )
      {
        $handler = $this->_handlers[$name];
        $handler->load();
      }

      return true;
    }

    function add_handler_and_load( $handler )
    {
      $this->add_handler($handler);
      return $this->load($handler->name);
    }

    function invalidate( $handler )
    {
      $handler->invalidate_cache();
      $handler->disengage();
      unset($this->_handlers[$handler->name]);
    }




  //=================================================================================================
  // SECTION: Saving


    //
    // Saves any changes back to the cache and database. If you pass $cache, you are asking that
    // changed handler data sets be put back into the cache individually. Don't pass one if you are
    // caching the container object!

    function save()
    {
      if( $this->has_changed() )
      {
        if( $this->prep_changes() )
        {
          if( $this->validate_changes($skip_prep = true) )
          {
            return $this->write_changes($skip_checks = true);
          }
        }

        return false;
      }
      
      return true;
    }



    //
    // Returns true if any changes have been made to the underlying data.

    function has_changed()
    {
      foreach( $this->_handlers as $name => $handler )
      {
        if( $handler->has_changed() )
        {
          return true;
        }
      }

      return false;
    }


    //
    // Does any prepatory work necessary for saving data back to database and cache.

    function prep_changes()
    {
      foreach( $this->_handlers as $name => $handler )
      {
        if( !$handler->prep_changes() )
        {
          return false;
        }
      }

      return true;
    }


    //
    // Validates the change data to ensure it can be written out. Throws exceptions if it finds
    // something it doesn't like.

    function validate_changes( $skip_prep = false )
    {
      foreach( $this->_handlers as $name => $handler )
      {
        if( !$handler->validate_changes($skip_prep) )
        {
          return false;
        }
      }

      return true;
    }


    //
    // Writes the change data back to the cache and database.

    function write_changes( $skip_checks = false )
    {
      foreach( $this->_handlers as $name => $handler )
      {
        if( !$handler->write_changes($skip_checks) )
        {
          return false;
        }
      }

      return true;
    }


    function save_all_to_cache()
    {
      foreach( $this->_handlers as $name => $handler )
      {
        $handler->write_to_cache();
      }

      return true;
    }

  }
