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


  if( !Features::enabled("security") and $directory == "*" || is_filename($directory) )
  {
    if( preg_match('/^\w+$/', $directory) and $path = Script::find_system_component("services/$directory/index.php") )
    {
      require_once $path;
      exit;
    }
      
    if( $path = Script::find_system_component("includes/html_environment.php") )
    {
      require_once $path;
    }

    $environment = Configuration::get("DB_ENVIRONMENT");
  
    global $response; $response = Script::$response;
    $response->set_title(ucfirst($environment) . " Services");
    $response->add_html_header(html_link("/resources/manual.css"));
    $response->add_body_class($environment);
    $response->add_body_element(h1(ucfirst($environment) . " Services"));

    $groups = array();
    foreach( Script::find_system_components_matching("services/$directory/*.php") as $path )
    {
      $pieces  = explode("/", $path);
      $service = convert_snake_to_pascal_case(basename(array_pop($pieces), ".php"));
      $group   = convert_snake_to_pascal_case(array_pop($pieces));
      $url     = sprintf("/%s/%s?o_O", $group, $service);
    
      structure_fetch_value($groups, array($group, $service)) or @$groups[$group][$service] = li(ahref($service, $url));
    }
    
    ksort($groups);
    foreach( $groups as $group => $services )
    {
      ksort($services);
      
      $directory == "*" and $group = ahref($group, "$group/");
    
      $response->add_body_element(h3($group));
      $response->add_body_element(ul($services));
    }
    
    $directory != "*" and $response->add_body_element(h3(ahref(i("more..."), "../")));
  
    Script::respond();
  }

  