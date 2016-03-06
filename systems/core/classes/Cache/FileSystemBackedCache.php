<?php if (defined($inc = "CORE_FILESYSTEMBACKEDCACHE_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class FileSystemBackedCache     // A simple, filesystem-based cache
  {
    protected $directory;
    
    function __construct( $directory = "/tmp" )
    {
      $this->directory = $directory;
    }
    
    
    
  //===============================================================================================
  // SECTION: API
  
  
    function get( $key )
    {
      $path = $this->make_path($key);
      if( file_exists($path) )
      {
        if( $contents = file_get_contents($path) )
        {
          $entry = unserialize($contents);
          if( is_a($entry, "FileSystemBackedCache_Entry") )
          {
            if( $entry->expires_at <= 0 or $entry->expires_at > time() )
            {
              return $entry->value;
            }
            else
            {
              $this->delete($key);    // It's expired
            }
          }
        }
      }
      
      return null;
    }
    
    
    function set( $key, $value, $expiry_s = 0 )
    {
      $path       = $this->make_path($key);
      $entry      = new FileSystemBackedCache_Entry($value, $expiry_s);
      $serialized = serialize($entry);

      file_put_contents($path, $serialized);
      return true;
    }


    function add( $key, $value, $expiry_s = 0 )
    {
      warn("FileSystemBackedCache does not support add(), due to intolerable race conditions");
      return false;   // There is no way to do add() on the filesystem without intolerable race conditions, so we just refuse.
    }
    
    
    function delete( $key )
    {
      $path = $this->make_path($key);
      if( file_exists($path) )
      {
        return @unlink($path);
      }

      return false;
    }


    function close()
    {
    }
    
    
    
  //===============================================================================================
  // SECTION: Internals

  
    function make_path( $key )
    {
      $key != "." and $key != ".."        or Script::fail("cache_key_invalid_for_file_system", array("key" => $key));
      preg_match("/^[\d\w\.\-]+$/", $key) or Script::fail("cache_key_invalid_for_file_system", array("key" => $key));
      strlen($key) < 250                  or Script::fail("file_key_length_too_long", array("key" => $key, "limit" => 250, "actual" => strlen($key)));
      
      return sprintf("%s/%s", $this->directory, $key);
    }
  }



  class FileSystemBackedCache_Entry
  {
    function __construct( $value, $expiry )
    {
      $this->value      = $value;
      $this->expires_at = $expiry != 0 ? time() + $expiry : 0;     // Using $expiry != 0 to allow testing to supply negative expiries
    }
  }
  

