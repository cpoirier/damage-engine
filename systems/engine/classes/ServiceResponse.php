<?php if (defined($inc = "ENGINE_SERVICERESPONSE_INCLUDED")) { return; } else { define($inc, true); }

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


  // For JSON services, this is the standard response, capable of capturing all of the common
  // elements in a way that helps ensure quality operation. Subclass this to add additional
  // data (or just add to an instance directly).

  class ServiceResponse extends JsonTree
  {
    static function build_success( $response_data = null, $data_is_content = null )
    {
      return new static($success = true, $response_data, $data_is_content);
    }
    
    static function build_failure( $error_code )
    {
      $messages = array_flatten(array_slice(func_get_args(), 1));
      return new static($success = false, (object)array("error_code" => $error_code, "messages" => $messages));
    }
    
    
    function __construct( $success = true, $response_data = null, $data_is_content = null )
    {
      $this->reset($success, $response_data, $data_is_content);
    }
    
    
    function to_string()
    {
      $data = deep_copy($this->data);
      $data["request"] = Script::get_script_name();

      $data = Script::filter("service_response_data", $data, $this);
      Features::enabled("debugging") and $data["report"] = ScriptReporter::format_script_report_as_array();
        
      return @json_encode($this->data);
    }
    

    function reset( $success = true, $response_data = null, $data_is_content = null )
    {
      parent::reset();
      
      $this->set("request", null);
      $this->set("response/success", $success    );
      $this->set("response/content", (object)null);

      if( $response_data )
      {
        is_null($data_is_content) and $data_is_content = $success;
        $this->merge_into_content($response_data);
      } 
    }
    


  //===============================================================================================
  // SECTION: With root "response"
  
    function get_response( $path, $default = null )     { $root = $this->with_root("response"); $this->get($path, $default);     }
    function set_response( $path, $value )              { $root = $this->with_root("response"); $this->set($path, $value  );     }
    function delete_from_response( $path )              { $root = $this->with_root("response"); $this->delete($path);            }
    function append_to_response( $path, $value )        { $root = $this->with_root("response"); $this->append($path, $value);    }
    function merge_into_response( $data, $path = null ) { $root = $this->with_root("response"); $this->merge($data, $path);      }
    function increment_in_response( $path, $value = 1 ) { $root = $this->with_root("response"); $this->increment($path, $value); }



  //===============================================================================================
  // SECTION: With root "response/content"

    function get_content( $path, $default = null )      { $root = $this->with_root("response/content"); $this->get($path, $default);     }
    function set_content( $path, $value )               { $root = $this->with_root("response/content"); $this->set($path, $value  );     }
    function delete_from_content( $path )               { $root = $this->with_root("response/content"); $this->delete($path);            }
    function append_to_content( $path, $value )         { $root = $this->with_root("response/content"); $this->append($path, $value);    }
    function merge_into_content( $data, $path = null )  { $root = $this->with_root("response/content"); $this->merge($data, $path);      }
    function increment_in_content( $path, $value = 1 )  { $root = $this->with_root("response/content"); $this->increment($path, $value); }
  


  }
