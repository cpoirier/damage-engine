<?php if (defined($inc = "ENGINE_STUB_ENVIRONMENT_INCLUDED")) { return; } else { define($inc, true); }

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


  // Attempts to find the service in the stub table. If present, returns the response directly without
  // doing any application-level processing. This is used exclusively for prototyping purposes, and should
  // never see the light of production.

  if( Features::enabled("development_mode") and Features::enabled("stub_mode") )
  {
    if( $stub = $engine->get_stub(substr($_SERVER["REDIRECT_URL"], 1), $_REQUEST, $ds) )
    {
      list($response_code, $response_type, $response_text) = $stub;

      $response = new Response($response_type, $response_code);
      $response->send($response_text);
      exit;
    }
  }

