<?php if (defined($inc = "CORE_SCRIPTREPORTER_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class ScriptReporter
  {

    static function format_script_report_as_comment()
    {
      $pairs = array();
      foreach( Script::get_sorted_keys() as $key )
      {
        $value = Script::get($key);
        if( !is_object($value) )
        {
          is_array($value) or $value = array($value);
          foreach( $value as $entry )
          {
            $pairs[] = sprintf("%s=\"%s\"", $key, $entry);
          }
        }
      }

      return sprintf("<!-- %s -->", implode(" ", $pairs));
    }


    static function format_script_report_as_text( $raw = false, $width = null )
    {
      $lines = array();

      if( is_null($width) )
      {
        $width = Script::get_key_width();
      }

      foreach( Script::get_sorted_keys() as $key )
      {
        $first = true;
        $value = Script::get($key);
        if( !is_object($value) )
        {
          $array = is_array($value) ? array_flatten($value) : array($value);
          foreach( $array as $value )
          {
            $lines[] = sprintf("%-${width}s: %s", $first ? $key : "", $value);
            $first = false;
          }
        }
      }

      return $raw ? $lines : implode("\n", $lines);
    }


    static function format_script_report_as_html()
    {
      require_once "html.php";

      $pairs = array();
      foreach( Script::get_sorted_keys() as $key )
      {
        $value = Script::get($key);
        if( !is_object($value) )
        {
          $pairs[] = tags(dt($key), dd("#$key", is_array($value) ? menu($value) : text($value)));
        }
      }

      return tag("dl", $pairs);
    }


    static function format_script_report_as_array()
    {
      $array = array();
      foreach( Script::get_sorted_keys() as $key )
      {
        $value = Script::get($key);
        if( !is_object($value) )
        {
          $array[$key] = $value;
        }
      }

      return $array;
    }


    static function format_script_report_as_json()
    {
      return json_encode(Script::format_script_report_as_array());
    }


    static function dump_script_report_to_error_log()
    {
      error_log(sprintf("%s %s statistics: %s", Script::$script_name, Script::get_id(), implode("  ", Script::format_script_report_as_text(true, 0))));
    }


    static function dump_script_report_to_debug_log()
    {
      debug(sprintf("%s %s statistics: %s", Script::$script_name, Script::get_id(), implode("  ", Script::format_script_report_as_text(true, 0))));
    }
  
  }