<?php if (defined($inc = "CORE_SCRIPT_INCLUDED")) { return; } else { define($inc, true); }

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


  if( !defined("DOCUMENT_ROOT_MADE_REAL") )
  {
    $_SERVER["DOCUMENT_ROOT"] = realpath($_SERVER["DOCUMENT_ROOT"]);
    define("DOCUMENT_ROOT_MADE_REAL", true);
  }

  require_once __DIR__ . "/Callback.php";
  require_once __DIR__ . "/ClassObject.php";
  require_once __DIR__ . "/TypeConverter.php";
  require_once __DIR__ . "/Cache/Cache.php";

  


  class Script    // Core registry and arbiter of useful things; interface with the client
  {

    
//=================================================================================================
// COORDINATES AND GENERAL
//=================================================================================================
  
    public static $class;              // A ClassObject on Script, for your convenience
    public static $start_microtime;    // Seconds.micros in floating point
    public static $start_time;         // Seconds since epoch at the start of the script
    public static $start_micros;       // Micros since $start_time at the start of the script


    static function get_script_name()
    {
      return static::$script_name;
    }

    static function set_script_name( $name = null )
    {
      $name or $name = static::$script_name;
      static::$script_name = static::filter("script_name", $name);
    }
    

    static function get_id()
    {
      $id = static::get("script_id") or $id = sprintf("%s.%d.%d", $_SERVER["SERVER_ADDR"], getmypid(), static::$start_microtime);
      return $id;
    }

    static function fail( $message = null, $parameters = array() )
    {
      is_string($message) or $message = "service_failed";
      static::signal("script_failing", $message, $parameters);

      header("HTTP/1.0 500 Service failed");
      abort($message);
    }



  //===============================================================================================
  // SECTION: System components (controls search path)
  
    static function get_system_name( $default = "app" )   // calculates it on first use; stable thereafter; be careful when you first call it!
    {
      if( is_null(static::$system_name) )
      {
        $implied_name = array_fetch_value(static::$system_component_names, 0, $default);
        static::$system_name = Configuration::get("SYSTEM_NAME", (string)$implied_name);
      }
      
      return static::$system_name;
    }


    static function set_system_component_names( $names )
    {
      static::$system_component_names = array();
      static::$system_component_paths = array();
      
      foreach( $names as $name )
      {
        static::$system_component_names[] = basename($name);
      }

      $base_path = dirname($_SERVER["DOCUMENT_ROOT"]);
      foreach( $names as $name )
      {
        $full_path = (substr($name, 0, 1) == "/" ? "" : $base_path) . "/" . $name;                                                    // TRACE(__METHOD__, "processing system component [$full_path]");
        $real_path = realpath($full_path) or abort("system_component_name_path_invalid", $name, $base_path, $real_path);              // TRACE(__METHOD__, "real path is [$real_path]");

        static::$system_component_paths[] = $real_path;
      }
    }
    
    
    static function get_system_component_paths()
    {
      return static::$system_component_paths;
    }
  
  
    static function find_system_component( $relative_path )
    {
      substr($relative_path, 0, 1) == "/" or $relative_path = "/$relative_path";
      foreach( static::$system_component_paths as $base_path )
      {
        $path = $base_path . $relative_path;
        if( file_exists($path) )
        {
          return $path;
        }
      }
    
      return null;
    }
  
  
    static function find_system_components_matching( $relative_glob )
    {
      $components = array();
    
      foreach( static::$system_component_paths as $base_directory )
      {
        foreach( glob("$base_directory/$relative_glob") as $path )
        {
          $filename = basename($path);
          list($component, $extension) = @explode(".", $filename, 2);
        
          array_has_member($components, $component) or $components[$component] = $path;
        }
      }
    
      return $components;
    }
  


  //===============================================================================================
  // SECTION: Internals
  
    protected static $script_name;
    protected static $system_name;
    protected static $system_component_names;
    protected static $system_component_paths;

    static function initialize_coordinates()
    {
      list($fraction, $seconds) = explode(" ", microtime());
      
      static::$class           = new ClassObject(__CLASS__);
      static::$start_microtime = microtime(true);              
      static::$start_time      = (int)$seconds;
      static::$start_micros    = substr($fraction, 2);

      static::$script_name     = $_SERVER["SCRIPT_FILENAME"];
      static::$system_name     = null;

      static::$system_component_names = array();
      static::$system_component_paths = array();
    }



        
    
//=================================================================================================
// REQUEST
//=================================================================================================
    
  //===============================================================================================
  // SECTION: Parameter access
  
    static function has_parameter( $name, $allow_empty = true, $outer = true )
    {
      $key   = $name;
      $camel = false;

    retry:
      $raw = static::get_parameter_from_source($key);
      if( !is_null($raw) and ($allow_empty or is_string($raw) and strlen($raw) > 0 or is_array($raw) and count($raw) > 0) )
      {
        return true;
      }
      elseif( Features::enabled("camel_case_parameters") && !$camel )
      {
        $key   = static::convert_snake_to_camel_case($name);
        $camel = true;
        goto retry;         //<<<<<<<<<<< FLOW CONTROL <<<<<<<<<<<<<
      }
      
      return false;
    }


    // Gets a parameter from the $_REQUEST. If you supply a default, the result will be coerced
    // to match. If Features::enabled("camel_case_parameters"), tries a (naive) camel case version
    // of the name if the name isn't present.

    static function get_parameter( $name, $default = null, $fail_if_missing = false, $fail_parameters = array() )
    {
      $value = $default;
      $key   = $name;
      $camel = false;

    retry:
      $raw = static::get_parameter_from_source($key);
      if( !is_null($raw) and (is_array($raw) or strlen($raw) > 0) )
      {
        $value = $raw;
      }
      elseif( Features::enabled("camel_case_parameters") && !$camel )
      {
        $key   = static::convert_snake_to_camel_case($name);
        $camel = true;
        goto retry;         //<<<<<<<<<<< FLOW CONTROL <<<<<<<<<<<<<
      }
      elseif( $fail_if_missing )
      {
        static::fail($fail_if_missing, $fail_parameters);
      }

      return TypeConverter::coerce_type($value, $default);
    }


    static function get_parameter_or_fail( $name, $default = null, $message = null )
    {
      return static::get_parameter($name, $default, $fail_message = "missing_required_parameter", array("parameter" => $name));
    }



    static function filter_parameter( $name, $pattern, $default = null, $fail_if_invalid = false, $fail_parameters = array() )   // A version of get_parameter() for strings that must match a pattern.
    {
      $value = static::get_parameter($name, $default, $fail_if_invalid, $fail_parameters);
      if( empty($value) || !is_string($value) || !(substr($pattern, 0, 1) == "/" ? preg_match($pattern, $value) : $pattern == $value) )
      {
        if( $fail_if_invalid )
        {
          static::fail($fail_if_invalid, $fail_parameters);
        }
        else
        {
          $value = $default;
        }
      }

      return $value;
    }


    static function filter_parameter_or_fail( $name, $pattern, $default = null, $message = null, $fail_parameters = array() )
    {
      if( !$message )
      {
        $message         = "parameter_format_mismatch";
        $fail_parameters = array("parameter" => $name, "expected" => $pattern);
      }

      return static::filter_parameter($name, $pattern, $default, $message, $fail_parameters);
    }



    static function parse_comma_delimited_parameter( $name, $type_exemplar = "" )   // Returns a list of type-coerced data from a comma-delimited parameter
    {
      if( $data = static::get_parameter($name, "") )
      {
        $cleaner = Callback::for_method_with_dynamic_offset("TypeConverter", "coerce_type", $dynamic_offset = 0, $default = $type_exemplar)->get_php_callback();
        return array_map($cleaner, array_map('ltrim', explode(',', $data)));
      }

      return array();
    }


    static function unset_parameter( $name )
    {
      static::set_parameter($name, null);
    }
    
    
    static function set_parameter( $name, $value )
    {
      static::$overrides[$name] = $value;
    }




  //===============================================================================================
  // SECTION: Call method
  
    static function get_call_method()
    {
      return strtolower($_SERVER['REQUEST_METHOD']);
    }
  
    static function was_called_as( $method )
    {
      return strtolower($_SERVER["REQUEST_METHOD"]) == strtolower($method);
    }
  
    static function was_called_as_post()
    {
      return static::was_called_as("post");
    }
  
    static function was_called_as_get()
    {
      return static::was_called_as("get");
    }
  
    static function was_called_as_put()
    {
      return static::was_called_as("put");
    }
  
    static function was_called_as_delete()
    {
      return static::was_called_as("delete");
    }
    
    static function set_parameter_source_order( $order )
    {
      is_array($order) or $order = explode(",", $order);

      static::$source_order = array();
      foreach( $order as $source )
      {
        $pairs = null;
        switch( strtolower($source) )
        {
          case "g": case "get" :                   static::$source_order[] = "get"   ; break;
          case "p": case "post":                   static::$source_order[] = "post"  ; break;
          case "c": case "cookie": case "cookies": static::$source_order[] = "cookie"; break;
        }
      }
    }

    
    
    
  //===============================================================================================
  // SECTION: Request details
  
    static function get_accepted_languages( $default = "en", $string = null )
    {
      if( !array_has_member(static::$accepted_languages, $default) )
      {
        $parser = "/([[:alpha:]]{1,8})(-([[:alpha:]|-]{1,8}))?(\s*;\s*q\s*=\s*(1(\.0{0,3})?|0(\.\d{0,3})?))?\s*(,|$)/i";
        $string or $string = array_fetch_value($_SERVER, 'HTTP_ACCEPT_LANGUAGE', '');

        $accepted = array(strtolower($default) => 0.001);
        if( preg_match_all($parser, strtolower($string), $hits, PREG_SET_ORDER) )
        {
          foreach( $hits as $hit )
          {
            @list($ignored, $major, $ignored, $minor, $ignored, $value) = $hit;
            if( $major )
            {
              $value = $value ? (float)$value : 1.000;
              if( $minor )
              {
                $accepted[$major . "-" . $minor] = $value;
                $accepted[$major] = max($value * .9, array_fetch_value($accepted, $major, 0));
              }
              else
              {
                $accepted[$major] = max($value, array_fetch_value($accepted, $major, 0));
              }
            }
          }

          arsort($accepted);
        }
        
        static::$accepted_languages[$default] = array_keys($accepted);
      }

      return array_fetch_value(static::$accepted_languages, $default, array());
    }
  


    
  //===============================================================================================
  // SECTION: Internals
  
    protected static $source_order       = null;
    protected static $overrides          = null;
    protected static $accepted_languages = null;
  
    static function initialize_request()
    {
      static::$source_order       = static::was_called_as_post() ? array("get", "post", "cookie") : array("get", "cookie");
      static::$overrides          = array();
      static::$accepted_languages = array();
    }
    
    
    static function get_parameter_from_source( $key, $source_order = null )
    {
      if( array_has_member(static::$overrides, $key) )
      {
        return static::$overrides[$key];
      }

      is_null($source_order) and $source_order = static::$source_order;
      foreach( $source_order as $source )
      {
        switch( strtolower($source) )
        {
          case "g": case "get":                    return array_fetch_value($_GET   , $key);
          case "p": case "post":                   return array_fetch_value($_POST  , $key);
          case "c": case "cookie": case "cookies": return array_fetch_value($_COOKIE, $key);
        }
      }
      
      return null;
    }
    
    
    static function has_parameter_from_source( $key, $source_order = null )
    {
      if( array_has_member(static::$overrides, $key) and !is_null(static::$overrides[$key]) )
      {
        return true;
      }

      is_null($source_order) and $source_order = static::$source_order;
      foreach( $source_order as $source )
      {
        switch( strtolower($source) )
        {
          case "g": case "get":                    return isset(   $_GET[$key]);
          case "p": case "post":                   return isset(  $_POST[$key]);
          case "c": case "cookie": case "cookies": return isset($_COOKIE[$key]);
        }
      }
      
      return false;
    }
  
  
  
  
  
//=================================================================================================
// RESPONSE
//=================================================================================================
    
    public static $response = "";            // The body of the response to send; anything that can be cast to a string, really; set with set_response() for best results

    
  //===============================================================================================
  // SECTION: Setting the response object

    static function set_response_code( $status, $clear_response = false )
    {
      static::$status = is_numeric($status) ? sprintf("%d %s", $status, static::get_http_response_code_description($status)) : $status; 
      $clear_response and static::$response = "";
    }
    
    static function set_response( /* [response code], */ $response, $content_type = null )    // $response can be anything that can be cast to a string; if it's an object and answers get_content_type(), you don't need to provide one explicitly
    {
      if( is_numeric($response) and $response >= 200 and $response <= 600 and func_num_args() > 1 )
      {
        @list($response_code, $response, $content_type) = func_get_args();
        static::set_response_code($response_code);
      }
      
      if( is_null($content_type) )
      {
        $content_type = "text/html";   // a reasonable default
        if( is_object($response) )
        {
          method_exists($response, "get_content_type") and $content_type = $response->get_content_type();
        }
        elseif( is_string($response) )
        {
          if( substr($response, 0, 15) != "<!DOCTYPE html>" )
          {
            $content_type = "text/plain";
          }
        }
      }
  
      static::set_response_content_type($content_type);
      static::$response = $response;
    }


    static function set_response_content_type( $content_type )   // you shouldn't have to call this yourself very often
    {
      static::$content_type = $content_type;
    }



  //===============================================================================================
  // SECTION: Response headers
  

    static function add_response_header( $name, $content )
    {
      static::$headers[] = sprintf("%s: %s", $name, $content);
    }
    
    static function discard_response_headers( $content_type = null, $response_code = null )
    {
      static::$headers = array();
      is_null($content_type ) or static::set_response_content_type($content_type);
      is_null($response_code) or static::set_response_code($response_code);
    }
    
    static function reset_response( $content_type = "text/html", $response_code = 200 )
    {
      static::discard_response_headers($content_type, $response_code);
      static::$response = "";
    }



  //===============================================================================================
  // SECTION: Response status
  
    static function are_headers_sent()
    {
      return headers_sent();
    }

    static function is_response_sent()
    {
      return static::$is_response_sent;
    }



  //===============================================================================================
  // SECTION: Sending the response
  
    static function send_response()
    {
      $response_text = (string)static::$response;

      if( !static::are_headers_sent() )
      {
        static::add_response_header("Content-Length", strlen($response_text));    // byte length, not character length
        static::send_headers();
      }
    
      static::send_text($response_text);
      static::$is_response_sent = true;
    }
    

    static function send_file_contents( $path, $content_type )   // sends file contents (*instead of* the response object!); writes the contents directly to the client, bypassesing most response processing!
    {
      static::reset_response($content_type, 200);
      
      static::send_headers();
      readfile($path);
    }
    
    
    static function send_redirect( $url, $permanent = false )
    {
      substr($url, 0, 1) == "/" and $url = sprintf("%s%s", Configuration::get("APPLICATION_URL"), $url);

      static::set_response_code($permanent ? "302" : "301", $clear_response = true);
      static::add_response_header("Location", $url);
      static::send_headers();
      static::send_text("");
    }
    

    static function send_unavailable( $message = "", $until = null, $content_type = "text/plain" )   // sets a 503 and sends the message (*instead of* the response object!); $until is the number of seconds until return
    {
      static::reset_response($content_type, 503);
      $until and static::add_response_header("Retry-After", $until);

      static::send_headers();
      static::send_text((string)$message);
    }
  
  
    static function send_internal_server_error( $message = "", $until = null, $content_type = "text/plain" )   // sets a 500 and sends the message (*instead of* the response object!)
    {
      static::reset_response($content_type, 500);

      static::send_headers();
      static::send_text((string)$message);
    }
  
  
    static function send_forbidden( $message = "", $content_type = "text/plain" )    // sets a 403 and sends the message (*instead of* the response object!)
    {
      static::reset_response($content_type, 403);
      
      static::send_headers();
      static::send_text((string)$message);
    }
  
  
    static function send_not_found( $message = "Not Found", $content_type = "text/plain" )   // sets a 404 and sends the message (*instead of* the response object!)
    {
      static::reset_response($content_type, 404);
      
      static::send_headers();
      static::send_text((string)$message);
    }
  
  
    static function send_headers()
    {
      header("HTTP/1.0 " . static::$status);
      header("Content-Type: " . static::$content_type);
      foreach( static::$headers as $header )
      {
        header($header);
      }
    }
    
    

  //===============================================================================================
  // SECTION: Output buffer management
  
    static function flush_output_buffers()
    {
      $length = 0;
      while( ($current = ob_get_length()) !== false )
      {
        if( Features::enabled("debugging") )
        {
          $length += $current;
          @ob_end_flush();
        }
        else
        {
          @ob_end_clean();
        }
      }

      return $length;
    }

    static function discard_output_buffers()
    {
      while (@ob_end_clean()) /* no op */ ;
    }
  
    
  
  //===============================================================================================
  // SECTION: Internals

    static function send_text( $text )   // Sends raw text to the client. Headers are not sent. Keeps track of the amount of data sent, for reporting purposes.
    {
      $text = static::filter("response_text", $text);
      static::flush_output_buffers();

      debug($text);

      static::accumulate("response_size", strlen($text));    // byte length, not character length
      print $text;
      flush();
    }

    protected static $status           = "200 OK";           
    protected static $headers          = null;
    protected static $content_type     = "text/html";
    protected static $is_response_sent = false;

    static function initialize_response()
    {
      static::reset_response();
    }

    static function get_http_response_code_description( $code )
    {
      switch( $code )
      {
        case 200: return "OK";
        case 301: return "Moved Permanently";
        case 302: return "Found";
        case 400: return "Bad Request";
        case 401: return "Unauthorized";
        case 403: return "Forbidden";
        case 404: return "Not Found";
        case 410: return "Gone";
        case 418: return "I'm a teapot";
        case 429: return "Too Many Requests";
        case 500: return "Internal Server Error";
        case 503: return "Service Unavailable";
        default:  return "";
      }
    }





//=================================================================================================
// REGISTRY
//=================================================================================================

    
  //===============================================================================================
  // SECTION: Accessors
  
    static function get( $name, $default = null )     // returns the current value of a (registered) global variable/counter/whatever.
    {
      if( $generator = array_fetch_value(static::$generators, $name) )
      {
        return TypeConverter::coerce_type(Callback::do_call($generator), $default);
      }
      else
      {
        return array_fetch_value(static::$data, $name, $default);
      }
    }


    static function fetch( $name, $default = null )   // returns the current value of a global variable, or triggers an error if the variable hasn't been registered with the script.
    {
      if( $generator = array_fetch_value(static::$generators, $name) )
      {
        return TypeConverter::coerce_type(Callback::do_call($generator), $default);
      }
      elseif( array_has_member(static::$data, $name) )
      {
        return array_fetch_value(static::$data, $name, $default);
      }
      elseif( func_num_args() == 1 )
      {
        static::fail("unable_to_fetch_script_resource", "name", $name);
      }

      return $default;
    }


    static function set( $name, $amount )
    {
      static::$data[$name] = $amount;
      return $amount;
    }

    static function append( $name, $value )
    {
      isset(static::$data[$name]) && is_array(static::$data[$name]) or static::$data[$name] = array();
      static::$data[$name][] = $value;
      return $value;
    }
  
    static function concat( $name, $value )
    {
      if( is_array($value) )
      {
        isset(static::$data[$name]) && is_array(static::$data[$name]) or static::$data[$name] = array();
        static::$data[$name] = array_merge(static::$data[$name], $value);        
      }
      else
      {
        static::$data[$name] = ((string)@static::$data[$name]) . $value;
      }
    }

    static function accumulate( $statistic, $amount = 0 )
    {
      static::set($statistic, @static::$data[$statistic] + $amount);
    }

    static function increment( $statistic )
    {
      static::accumulate($statistic, 1);
    }

    static function decrement( $statistic )
    {
      static::accumulate($statistic, -1);
    }

    static function record( $statistic, $value )
    {
      if( !array_key_exists($statistic, static::$data) || !is_array(static::$data[$statistic]) )
      {
        static::$data[$statistic] = array();
      }

      static::$data[$statistic][] = $value;
    }



  //===============================================================================================
  // SECTION: Generated values
  
    static function register_value_generator( $name, $callback )
    {
      static::$generators[$name] = $callback;
    }
  
    
      
  //===============================================================================================
  // SECTION: Profiling

    static function note_time( $label, $note = null )
    {
      static::record("timings", new Script_Annotation(static::get("duration"), $label, $note));
    }



  //===============================================================================================
  // SECTION: Keys
  
    static function get_sorted_keys()
    {
      $keys = array_merge(array_keys(static::$generators), array_keys(static::$data));
      sort($keys);
      return $keys;
    }


    static function get_key_width()
    {
      $width = 0;
      foreach( static::get_sorted_keys() as $key )
      {
        $length = strlen($key);
        $length <= $width or $width = $length;
      }

      return $width;
    }

  

  //===============================================================================================
  // SECTION: Internals
  
    static protected $data       = null;
    static protected $generators = null;
    
    static function initialize_registry()
    {
      static::$data       = array();
      static::$generators = array();
      
      static::$generators["duration"         ] = Callback::for_method("Script", "generate_duration"         );
      static::$generators["peak_memory_usage"] = Callback::for_method("Script", "generate_peak_memory_usage");
      static::$generators["memory_usage_peak"] = Callback::for_method("Script", "generate_memory_usage_peak");
      static::$generators["memory_usage"     ] = Callback::for_method("Script", "generate_memory_usage"     );
    }
  
  
    static function generate_duration()
    {
      return microtime(true) - static::$start_microtime;
    }
    
    static function generate_peak_memory_usage()
    {
      return memory_get_peak_usage();
    }
    
    static function generate_memory_usage_peak()
    {
      return memory_get_peak_usage();
    }
    
    static function generate_memory_usage()
    {
      return memory_get_usage();
    }



    
    
//=================================================================================================
// SYSTEM CACHE
//=================================================================================================

  
  //===============================================================================================
  // SECTION: System Cache
  
  
    static function set_system_cache( $cache )
    {
      static::$system_cache = $cache;
    }
    
    
    static function get_system_cache()   // if not explicitly set, returns one created from configuration family SYSTEM_CACHE_CONNECTION, starting from _TYPE
    {
      if( !static::$system_cache and $type = Configuration::get("SYSTEM_CACHE_CONNECTION_TYPE") )
      {
        if( $cache = Cache::connect_from_configuration(static::get_system_name(), $type, "SYSTEM_CACHE_CONNECTION") )
        {
          Script::set_system_cache($cache);
        }
      }
      
      return static::$system_cache;
    }
    
    
    static function get_from_system_cache( $key, $max_age = null )
    {
      if( $cache = static::get_system_cache() )
      {
        return $cache->get($key, $max_age);
      }
      
      return null;
    }
    
    
    static function set_to_system_cache( $key, $value )
    {
      if( $cache = static::get_system_cache() )
      {
        return $cache->set($key, $value);
      }

      return false;
    }
    
    
    
  //===============================================================================================
  // SECTION: Internals
  
    protected static $system_cache;
    
    static function initialize_cache()
    {
      static::$system_cache = null;
      $type = Configuration::get("SYSTEM_CACHE_CONNECTION_TYPE") and Cache::preload_from_configuration($type);
    }
      
  
  
  
  
//=================================================================================================
// FILTERS AND SIGNALLING
//=================================================================================================


  //===============================================================================================
  // SECTION: Filter registration

  
    static function register_filter( $name, $callback, $priority = 10 )   // Adds a filter to the named filter chain, at the specified priority.
    {
      $index = "f" . static::increment_serial();
      $priority == -1 and $priority = ONE_BILLION;    // Last to run FILO stack
      
      static::$filters[$name][$priority][$index] = $callback;
      ksort(static::$filters[$name]);

      return $index;
    }


    static function unregister_filter( $name, $index, $priority = 10 )    // You must pass the same priority as you used to register it
    {
      unset(static::$filters[$name][$priority][$index]);
    }


    static function register_filters_from( $object, $priority = 10 )      // Iterates over an object's methods looking for filter_<name>() methods, which it registers as filters
    {
      foreach( static::map_filters_from($object) as $event_name => $method_name )
      {
        static::register_filter($event_name, Callback::for_method($object, $method_name), $priority);
      }
    }


    static function map_filters_from( $object )    // Builds a map of event_name => method_name for all filter_<event>() methods on $object.
    {
      $map = static::map_event_handlers_and_filters_from($object);
      return $map["filters"];
    }



  //===============================================================================================
  // SECTION: Filter processing


    static function filter( $name, $value )        // Calls all registered filters (progressively, in order) on the specified value. As a convenience, you can call filter_<name>() for any filter <name>.
    {
      $extra_parameters = array_slice(func_get_args(), 2);

      if( is_array($name) )
      {
        $ignore_errors = static::$ignore_errors;
        static::$ignore_errors = false;

        $names = $name;
        foreach( $names as $name )
        {
          $value = call_user_func_array(array(__CLASS__, $ignore_errors ? "filter_and_ignore_exceptions" : "filter"), array_merge(array($name, $value), $extra_parameters));
        }
      }
      elseif( is_string($name) )
      {
        $ignore_errors = static::$ignore_errors;
        static::$ignore_errors = false;
      
        $name = strtolower($name);
        if( array_key_exists($name, static::$filters) )
        {
          static::$filter_stack->push((object)array("name" => $name, "stop" => false));

          foreach( static::$filters[$name] as $priority => $callbacks )
          {
            $priority == ONE_BILLION and $callbacks = array_reverse($callbacks);   // Last to run FILO stack            
            foreach( $callbacks as $callback )
            {
              $parameters = array_merge(array($value, $extra_parameters));
                            
              if( $ignore_errors )
              {
                try {$value = Callback::do_call_with_array($callback, $parameters);} catch (Exception $e) {warn("Ignored exception from $name filter:", $e);}
              }
              else
              {
                $value = Callback::do_call_with_array($callback, $parameters);
              }
              
              if( static::$filter_stack->top()->stop )
              {
                break 2;
              }
            }
          }

          static::$filter_stack->pop();
        }
      }
      else
      {
        $parameters = func_get_args(1);
        $callback   = $name;
        
        if( $ignore_errors )
        {
          try {$value = Callback::do_call_with_array($callback, $parameters);} catch (Exception $e) {warn("Ignored exception from $name filter:", $e);}
        }
        else
        {
          $value = Callback::do_call_with_array($callback, $parameters);
        }
      }

      return $value;
    }


    static function filter_and_ignore_exceptions( $name, $value )
    {
      static::$ignore_errors = true;
      return call_user_func_array(array("Script", "filter"), func_get_args());
    }


    static function skip_remaining_filters()   // When called during filter processing, causes the value you return to be used without further filtering.
    {
      if( $frame = static::$filter_stack->top() )
      {
        $frame->stop = true;
      }
    }



  //=================================================================================================
  // SECTION: Signal registration


    static function register_event_handler( $name, $callback, $priority = 10 )   // Adds an event handler to the named event chain, at the specified priority.
    {
      $index = "h" . static::increment_serial();
      
      $priority == -1 and $priority = ONE_BILLION;   // Special last-to-run FILO stack
      
      array_key_exists($name    , static::$event_handlers       ) or static::$event_handlers[$name]            = array();
      array_key_exists($priority, static::$event_handlers[$name]) or static::$event_handlers[$name][$priority] = array();
      
      static::$event_handlers[$name][$priority][$index] = $callback;
      ksort(static::$event_handlers[$name]);

      return $index;
    }

    static function unregister_event_handler( $name, $index, $priority = 10 )
    {
      unset(static::$event_handlers[$name][$priority][$index]);
    }
    
    static function has_event_handler_for( $name )
    {
      return !empty(static::$event_handlers[name]);
    }


    static function register_signal( $name, $callback, $priority = 10 )
    {
      return static::register_event_handler($name, $callback, $priority);
    }

    static function register_handler( $name, $callback, $priority = 10 )
    {
      return static::register_event_handler($name, $callback, $priority);
    }


    static function register_event_handlers_from( $object, $priority = 10 )    // Iterates over an object's methods looking for handle_<event>() methods, which it registers as event handlers.
    {
      foreach( static::map_event_handlers_from($object) as $event_name => $method_name )
      {
        static::register_event_handler($event_name, Callback::for_method($object, $method_name), $priority);
      }
    }


    static function map_event_handlers_from( $object )    // Builds a map of event_name => method_name for all handle_<event>() methods on $object.
    {
      $map = map_event_handlers_and_filters_from($object);
      return $map["event_handlers"];
    }    
    

    
  //===============================================================================================
  // SECTION: Signal processing
  
    
    static function signal( $names )    // Calls all registered event handlers for the named event.
    {
      $args       = null;
      $exceptions = null;
      
      $ignore_errors = static::$ignore_errors;
      static::$ignore_errors = false;
      
      foreach( (array)$names as $name )
      {
        $name = strtolower($name);
        if( array_key_exists($name, static::$event_handlers) )
        {
          static::$event_stack->push((object)array("name" => $name, "stop" => false));

          is_null($args) and $args = array_slice(func_get_args(), 1);
          foreach( static::$event_handlers[$name] as $priority => $callbacks )
          {
            $priority == ONE_BILLION and $callbacks = array_reverse($callbacks);   // special last-to-run FILO stack
            foreach( $callbacks as $callback )
            {
              if( $ignore_errors )
              {
                try {Callback::do_call_with_array($callback, $args);} catch (Exception $e) {warn("Ignored exception from $name signal:", $e);}
              }
              else
              {
                Callback::do_call_with_array($callback, $args);
              }
              
              if( static::$event_stack->top()->stop )
              {
                break 2;
              }              
            }
          }

          static::$event_stack->pop();
        }
      }
      
      return $exceptions;
    }
    
    
    static function signal_and_ignore_exceptions( $name )
    {
      static::$ignore_errors = true;
      call_user_func_array(array("Script", "signal"), func_get_args());
    }


    static function dispatch( $names )   // Similar to signal(), but stops at and returns the first non-null value produced by any handler. Returns null otherwise.
    {
      $args   = null;
      $result = null;
      
      $ignore_errors = static::$ignore_errors;
      static::$ignore_errors = false;
            
      foreach( (array)$names as $name )
      {
        $name = strtolower($name);
        if( array_key_exists($name, static::$event_handlers) )
        {
          static::$event_stack->push((object)array("name" => $name, "stop" => false));

          is_null($args) and $args = array_slice(func_get_args(), 1);
          foreach( static::$event_handlers[$name] as $priority => $callbacks )
          {
            $priority == ONE_BILLION and $callbacks = array_reverse($callbacks);   // Special last-to-run FILO stack
            foreach( $callbacks as $callback )
            {
              if( $ignore_errors )
              {
                try {$result = Callback::do_call_with_array($callback, $args);} catch (Exception $e) {warn("Ignored exception from $name dispatch:", $e);}
              }
              else
              {
                $result = Callback::do_call_with_array($callback, $args);
              }

              if( $result || static::$event_stack->top()->stop )
              {
                break 2;
              }
            }
          }

          static::$event_stack->pop();
        }
      }

      return $result;
    }


    static function skip_remaining_event_handlers()   // When called during event handling, stops further processing of the event.
    {
      if( $frame = static::$event_stack->top() )
      {
        $frame->stop = true;
      }
    }
    


  //===============================================================================================
  // SECTION: Other information
  
    static function register_event_handlers_and_filters_from( $object, $priority = 10 )   // Iterates over an object's methods looking for handle_<event>() and filter_<name>() methods, which is registers appropriately.
    {
      $count = 0;

      foreach( get_class_methods($object) as $method_name )
      {
        if( strpos($method_name, "filter_") === 0 )
        {
          $event_name = substr($method_name, strlen("filter_"));
          static::register_filter($event_name, Callback::for_method($object, $method_name), $priority);
          $count++;
        }
        elseif( strpos($method_name, "handle_") === 0 )
        {
          $event_name = substr($method_name, strlen("handle_"));
          static::register_event_handler($event_name, Callback::for_method($object, $method_name), $priority);
          $count++;
        }
      }

      return $count;
    }


    static function map_event_handlers_and_filters_from( $object )    // Builds a map of type => map of event_name => method_name for all handle_<event>() methods on $object.
    {
      $map = array("filters" => array(), "event_handlers" => array());
      foreach( get_class_methods($object) as $method_name )
      {
        if( strpos($method_name, "handle_") === 0 )
        {
          $event_name = substr($method_name, strlen("handle_"));
          $map["event_handlers"][$event_name] = $method_name;
        }
        elseif( strpos($method_name, "filter_") === 0 )
        {
          $filter_name = substr($method_name, strlen("filter_"));
          $map["filters"][$filter_name] = $method_name;
        }
      }

      return $map;
    }



  //===============================================================================================
  // SECTION: Internals

    protected static $filters;
    protected static $filter_stack;
    protected static $event_handlers;
    protected static $event_stack;
    
    protected static $highest_serial;
    protected static $ignore_errors;
  
    static function initialize_signals_and_filters()
    {
      static::$filters        = array();
      static::$filter_stack   = new Script_Stack();
      static::$event_handlers = array();
      static::$event_stack    = new Script_Stack();
      
      static::$highest_serial = 0;    
      static::$ignore_errors  = false;
    }

    static function increment_serial()
    {
      static::$highest_serial++;
      return static::$highest_serial;
    }
    


  

//=================================================================================================
// LANGUAGE SUPPORT
//=================================================================================================


  //===============================================================================================
  // SECTION: PHP helpers
  
    static function eval_in_binding( $__statement, $__binding, $__output = false )
    {
      foreach( $__binding as $__key => $__value )
      {
        if( preg_match("/^\w+$/", $__key) )
        {
          $$__key = $__value;
        }
      }
  
      if( $__output )
      {
        ob_start();
        Features::enabled("development_mode") ? eval("?>$__statement") : @eval("?>$__statement");
        return ob_get_clean();
      }
      else
      {
        return Features::enabled("development_mode") ? eval($__statement) : @eval($__statement);
      }
    }
  
    static function safe_require_once( $path )
    {
      ob_start();
      require_once $path;

      $filth = ob_get_clean();
      if( Features::enabled("debugging") && strlen($filth) > 0 )
      {
        warn("$path outputs data during loading:", capture_var_dump($filth));
        abort("$path outputs data during loading");
      }
    }



  //===============================================================================================
  // SECTION: Recursion management

    static function get_recursion_depth( $name )
    {
      return array_fetch_value(static::$recursion_trackers, $name, 0);
    }

    static function enter_recursion( $name, $return_tracker = true )
    {
      array_has_member(static::$recursion_trackers, $name) or static::$recursion_trackers[$name] = 0;
      static::$recursion_trackers[$name] += 1;
  
      return $return_tracker ? new Script_RecursionMonitor($name) : static::get_recursion_depth($name);
    }

    static function exit_recursion( $name )
    {
      if( $depth = array_fetch_value(static::$recursion_trackers, $name, 0) )
      {
        static::$recursion_trackers[$name] = $depth - 1;
      }
  
      return static::get_recursion_depth($name);
    }



  //===============================================================================================
  // SECTION: Internals

    protected static $recursion_trackers;
    
    static function initialize_language()
    {
      static::$recursion_trackers = array();
    }
    
    
    

    
//=================================================================================================
// INTERNALS
//=================================================================================================

    static function initialize()
    {
      static::initialize_coordinates();
      static::initialize_request();
      static::initialize_response();
      static::initialize_registry();
      static::initialize_cache();
      static::initialize_signals_and_filters();
      static::initialize_language();
      
      register_shutdown_function(array(__CLASS__, "signal_tear_down"));
    }
    
    
    static function signal_tear_down()
    {
      static::signal_and_ignore_exceptions("beginning_teardown");
      static::signal_and_ignore_exceptions("teardown_complete");
    }
    
    
    static function __callStatic( $name, $args )
    {
      $m = null;
      
      if( substr($name, 0, 5) == "send_" and preg_match('/^(send_.*?)_and_exit$/', $name, $m) and $method = $m[1] and method_exists("Script", $method) )
      {
        call_user_func_array(array("Script", $method), $args);
        exit;
      }
      
      trigger_error(sprintf("unknown method %s::%s", __CLASS__, $name), E_USER_ERROR);
    }
  }

  Script::initialize();




//===============================================================================================
// SECTION: Support classes (should never be created directly)


  class Script_RecursionMonitor
  {
    function __construct( $name )
    {
      $this->name = $name;
    }
    
    function __destruct()
    {
      Script::exit_recursion($this->name);
    }
  }
  
  
  class Script_Stack
  {
    function __construct()
    {
      $this->frames = array();
    }

    function push( $frame )
    {
      array_push($this->frames, $frame);
    }

    function pop()
    {
      return array_pop($this->frames);
    }

    function top()
    {
      return @$this->frames[count($this->frames) - 1];
    }
  }
  
    
  class Script_Annotation
  {
    function __construct( $elapsed, $label, $notes )
    {
      $this->elapsed = $elapsed;
      $this->label   = $label;
      $this->notes   = $notes;
    }
  
    function get_notes_as_string( $allow_null = true )
    {
      if( $allow_null && is_null($this->notes) )
      {
        return null;
      }
    
      return is_string($this->notes) ? $this->notes : capture_var_dump($this->notes);
    }

    function __toString()
    {
      return sprintf("%dms %s%s", intval($this->elapsed * 1000), $this->label, $this->notes ? sprintf(": %s", $this->get_notes_as_string()) : "");
    }
  }
  