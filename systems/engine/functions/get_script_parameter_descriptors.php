<?php
  
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

  //
  // Parses the running script to describe the parameters. Knows only about Script methods.
  // For other parameters, create a filter handler for script_parameters.

  function get_script_parameter_descriptors( $path = null )
  {
    $path or $path = $_SERVER["SCRIPT_FILENAME"];
    $script_parameters = array();

    if( $source = file_get_contents($path) )
    {
      $pattern = '/(?:Script::)(?P<method>(?:get|filter|claim|parse|has|(?:\w+from_parameters))\w*)\((?P<parameters>.*?)\)(?:.*?\/\/(?P<comment>.*$))?/m';
      if( preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) )
      {
        foreach( $matches as $match )
        {
          $descriptor = null;
          $method     = $match["method"];
          $parameters = array_map("trim", preg_split('/\s*,\s*/', $match["parameters"]));

          if( strpos(@$match["comment"], "not a script parameter") !== false )
          {
            continue;
          }

          switch( $method )
          {
            case 'get':
            case 'get_or_fail':
            case 'get_parameter':
            case 'get_parameter_or_fail':
            case 'has_parameter':
            case 'has_parameter_or_fail':
              $descriptor = new ScriptParameterDescriptor($parameters[0], @$match["comment"]);
              $descriptor->is_numeric = count($parameters) > 1 && is_numeric($parameters[1]);
              $descriptor->make_required(strpos($method, "or_fail") || (count($parameters) > 2 && strtolower($parameters[2]) != "false"));
              break;

            case 'filter_parameter':
            case 'filter_parameter_or_fail':
              $descriptor = new ScriptParameterDescriptor($parameters[0], @$match["comment"]);
              $descriptor->add_description("matches: " . $parameters[1]);
              $descriptor->make_required(strpos($method, "or_fail") || (count($parameters) > 3 && strtolower($parameters[3]) != "false"));
              break;

            case 'get_parsed_parameter':
            case 'parse_comma_delimited_pipes_parameter':
            case 'parse_comma_delimited_pipes_parameter_into_map':
              $descriptor = new ScriptParameterDescriptor($parameters[0], @$match["comment"]);
              $descriptor->add_description("a comma-delimited set of pipe-delimited values");
              $descriptor->make_required();
              break;

            case 'parse_comma_delimited_parameter':
              $descriptor = new ScriptParameterDescriptor($parameters[0], @$match["comment"]);
              $descriptor->add_description("a comma-delimited set of values");
              $descriptor->make_required();
              break;
          }

          if( $descriptor )
          {
            $script_parameters[$descriptor->name] = $descriptor;
          }
        }
      }

      $script_parameters = Script::filter("script_parameters", $script_parameters, $source);
      
      if( preg_match_all('/route_service_call\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $source, $matches, PREG_SET_ORDER) )
      {
        foreach( $matches as $match )
        {
          if( $service = @$match[1] )
          {
            if( $path = route_service_call($service) )
            {
              $script_parameters = array_merge($script_parameters, get_script_parameter_descriptors($path));
            }
          }
        }
      }
    }
    
    return $script_parameters;
  }


  