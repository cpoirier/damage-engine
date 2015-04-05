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

  require_once "get_script_parameter_descriptors.php";
  require_once "html.php";

  Script::set("execution_tag", "o_O");
  Script::set_response_content_type("text/html");
  

  $content = null;
  $rows    = array();
  $method  = "GET";

  $descriptors = get_script_parameter_descriptors();
  Features::enabled("debugging") and $descriptors[] = (object)array("name" => "log_inside", "is_required" => false, "default_value" => "", "description" => "comma-separated list of classes and/or functions to log inside", "method" => "GET");


  //
  // We used to allow manual access parameters to be specified via a query string. This 
  // proved problematic, as browsers consider any GET method to be repeatable, and so 
  // safe to call at any time for any reason. This was causing some operations to be 
  // performed while typing in the browser location bar. It was a problem. We now use
  // proper hash-based navigation and have ditched automatic calling. This code converts
  // old URLs to the new scheme.
  
  $old_school_parameters = array();
  foreach( $descriptors as $descriptor )
  {
    $name  = $descriptor->name;
    $camel = Symbol::convert_snake_to_camel_case($name);
    isset($_GET[$camel]) and !isset($_GET[$name]) and $_GET[$name] = $_GET[$camel];
    
    if( isset($_GET[$name]) and $name != "client_version" )
    {
      $old_school_parameters[$name] = $_GET[$name];
    }
  }
  
  if( $old_school_parameters )
  {
    $hash   = make_query_string($old_school_parameters, $marker = "#");
    $pieces = explode("?", $_SERVER["REQUEST_URI"], 2);
    $url    = $pieces[0] . "?" . $manual_access_key . $hash;
    Script::send_redirect($url);
    exit;
  }


  //
  // If still here, we were called correctly. Build the form.
  
  foreach( $descriptors as $descriptor )
  {
    $name  = $descriptor->name;
    $camel = Symbol::convert_snake_to_camel_case($name);
    isset($_GET[$camel]) and !isset($_GET[$name]) and $_GET[$name] = $_GET[$camel];
    
    $rows[] = tr
    (
      ".parameter" . ($descriptor->is_required ? ".required" : ".optional"),
      td(label($name, $name)),
      td(input($name, "")),
      td(".description", $descriptor->description)
    );

    if( $descriptor->method == "POST" )
    {
      $method = "POST";
    }
  }
  
  $handler =
  '
    var caller = null;
    $(document).ready
    (
      function()
      {
        caller = new Caller("form", "#submit", "#response", "#raw", "#pretty", "#menu");
        
        var parameters = window.location.hash.substring(1).split("&");
        for( var i = 0; i < parameters.length; i++ )
        {
          var pair  = parameters[i].split("=");
          if( pair.length > 1 )
          {
            var name  = decodeURIComponent(pair[0].replace(/\+/g, " "));
            var value = decodeURIComponent(pair[1].replace(/\+/g, " "));

            $("#" + name).val(value);
          }
        }
        
        $(document).keypress
        (
          function( event ) 
          {
            if( event.which == 13 ) 
            {
              doSubmit();
            }
          }
        );
      }
    )

    function capture()
    {
      var hash = "#" + $("form").serialize();
      if( window.location.hash != hash )
      {
        window.location.hash = hash;
        return true;
      }
      
      return false;
    }
    
    function doSubmit()
    {
      capture();
      caller.call(); 
      
      return false;
    }
  ';
      

  $action = explode("?", $_SERVER["REQUEST_URI"]);
  $content = tags
  (
    h1(".header", Script::get_system_name(), " ", ucfirst(Configuration::get("DB_ENVIRONMENT"))),
    div
    (
      "#request",
      h3(Script::get_script_name()),
      tag("form", table($rows, tdcs(2, p(".right", submit("submit", "submit")))), array("method" => $method, "action" => array_shift($action), "onsubmit" => "event.preventDefault(); return doSubmit();")),
      js($handler),
      tag("ul#menu")
    ),
    div
    (
      "#response",
      h4("Response"),
      textarea("raw", "", true),
      div
      (
        "#pretty", 
        aname("", "pretty")
      )
    )
  );

  print html
  (
    head
    (
      title(Script::get_script_name()),
      meta_charset(),
      html_link("/resources/manual.css"),
      script(Configuration::get("JQUERY_CORE_URL", "http://code.jquery.com/jquery-1.11.2.min.js")),
      script("/resources/manual.js"    )
    ),
    body
    ( 
      "." . Configuration::get("DB_ENVIRONMENT"), 
      $content
    )
  );
  
  Features::disable("debugging");
  
  exit;
  