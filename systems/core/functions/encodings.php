<?php if (defined($inc = "CORE_TYPE_CONVERSION_INCLUDED")) { return; } else { define($inc, true); }

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


  function csv_decode( $string, $trim = true )
  {
    if( $trim && empty($string) )
    {
      return array();
    }
    else
    {
      return $trim ? array_map("trim", str_getcsv($string)) : str_getcsv($string);
    }
  }
  
  function csv_encode( $list )
  {
    $string = null;
    if( $fp = fopen("php://temp", "r+") )
    {
      fputscv($fp, $list, $delimiter = ',', $enclosure = '"');
      rewind($fp);
      $string = retrim(fgets($fp), "\n");
      fclose($fp);
    }
    
    return $string;
  }
  
  TypeConverter::register_encoding("csv", "csv_encode", "csv_decode");
  
  
  
  
  
  function nvp_decode( $string )
  {
    if( substr($string, 0, 5) == "json=" )
    {
      $json = trim(substr($string, 5));
      substr($json, -1, 1) == ";" and $json = substr($json, 0, -1);
      return json_decode($json);
    }
    else
    {
      $pairs = array();
      $list  = array_filter(preg_split('/;\s*/', $string));
      foreach( $list as $pair )
      {
        @list($name, $value) = explode("=", $pair, 2);
        $pairs[$name] = $value;
      }
      
      return $pairs;
    }
    
  }
  
  function nvp_encode( $pairs )
  {
    $list = array();
    foreach( $pairs as $name => $value )
    {
      if( !is_scalar($value) )
      {
        return sprintf("json=%s; ", json_encode($pairs));
      }

      strpos($value, ';') === false or Script::fail("invalid_content_for_name_value_pair_field", array("name" => $name, "value" => $value));
      $list[] = sprintf("%s=%s;", $name, $value); 
    }
    
    return implode(" ", $list) . (empty($list) ? "" : " ");
  }
  
  TypeConverter::register_encoding("nvp", "nvp_encode", "nvp_decode");
  
  
  
  
  function qs_decode( $string )
  {
    $parsed = array();
    if( function_exists("mb_parse_str") )
    {
      mb_parse_str($string, $parsed);
    }
    else
    {
      parse_str($string, $parsed);
    }
    
    return $parsed;
  }

  function qs_encode( $pairs )
  {
    $cleaned = array();
    foreach( $pairs as $key => $value )
    {
      if( is_numeric($key) )
      {
        $cleaned[$value] = "";
      }
      else
      {
        $cleaned[$key] = $value;
      }
    }
    
    return http_build_query($cleaned);
  }
  
  
  TypeConverter::register_encoding("query_string", "qs_encode" , "qs_decode" );
  TypeConverter::register_encoding("qs"          , "qs_encode" , "qs_decode" );
  