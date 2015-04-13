<?php if (defined($inc = "CORE_DATABASECONNECTOR_INCLUDED")) { return; } else { define($inc, true); }

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

  
  class DatabaseConnector
  {
    function __construct()
    {
    }

    function connect( $statistics_collector = null, $for_writing = true, $throw_on_failure = null )
    {
      abort("override");
    }


    function connect_for_writing( $statistics_collector = null, $throw_on_failure = null )
    {
      return $this->connect($statistics_collector, $for_writing = true, $throw_on_failure);
    }

    function connect_for_reading( $statistics_collector = null, $throw_on_failure = null )
    {
      return $this->connect($statistics_collector, $for_writing = false, $throw_on_failure);
    }

  }