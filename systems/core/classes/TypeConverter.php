<?php if (defined($inc = "CORE_TYPECONVERTER_INCLUDED")) { return; } else { define($inc, true); }

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


  class TypeConverter
  {
    static function coerce_type( $value, $exemplar )
    {
      if( !is_null($exemplar) )
      {
        if( is_array($exemplar) )
        {
          is_array($value) or $value = @(array)$value;
        }
        elseif( is_bool($exemplar) )
        {
          if( is_string($value) )
          {
            $lower = strtolower($value);
      
            if( $value === "1" || $value === "true" || $lower === "on" || $lower === "yes" )
            {
              $value = true;
            }
            elseif( $value === "0" || $value === "false" || $lower === "off" || $lower === "no" )
            {
              $value = false;
            }
            else
            {
              $value = @(bool)$value;
            }
          }
          else
          {
            $value = @(bool)$value;
          }
        }
        elseif( is_float($exemplar) )
        {
          $value = @(float)$value;
        }
        elseif( is_int($exemplar) )
        {
          $value = @(integer)$value;
        }
        elseif( is_string($exemplar) && empty($value) )
        {
          $value = "";
        }
        elseif( is_object($exemplar) )
        {
          $value = @(object)$value;
        }
      }

      return $value;
    }



  //===============================================================================================
  // SECTION: Encodings
  
    
    static function encode( $value, $encoding, $default = null )    // encodes the $value with the named $encoding
    {
      $encoded = $default;
      if( $encoding = $this->get_encoding_or_signal($encoding) )
      {
        $encoded = Callback::do_call_with_array($encoding->encoder, array($value));
      }
      
      return $encoded;
    }
    
    
    static function decode( $value, $encoding, $default = null )    // decodes the $value with the named $encoding
    {
      $decoded = $default;
      if( $encoding = $this->get_encoding_or_signal($encoding) )
      {
        $decoded = Callback::do_call_with_array($encoding->decoder, array($value));
      }
      
      return $decoded;
    }
    
    
    static function has_encoding( $name )
    {
      return tree_has(static::$encodings, $name);
    }


    static function get_encoding( $name )
    {
      return is_object($name) ? $name : tree_fetch(static::$encodings, $name);
    }
    
    
    static function get_encoding_or_signal( $name, $signal = "type_manager_missing_encoding" )
    {
      $encoding = static::get_encoding($name) or Script::signal($signal, "name", $name);
      return $encoding;
    }
    
    
    static function register_encoding( $name, $encoder, $decoder )
    {
      static::$encodings[$name] = (object)array("name" => $name, "encoder" => $encoder, "decoder" => $decoder);
    }




  //===============================================================================================
  // SECTION: Internals

  
    protected static $encodings;
  
    static function initialize()
    {
      static::$encodings = array();
    }
  }
  
  
  TypeConverter::initialize();
  