<?php if (defined($inc = "MYSQLIERROR_INCLUDED")) { return; } else { define($inc, true); }

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

  class MySQLiError extends Exception
  {    
    public $reason;
    
    function __construct( $reason, $errno, $error )
    {
      parent::__construct($reason);
      $this->reason = $reason;
      $this->errno  = $errno;
      $this->error  = $error;
    }
  }