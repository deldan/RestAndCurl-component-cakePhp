<?php 
 // This file is part of Questionity - http://www.questionity.com/
 // 
 /**
  * Rest Class
  *
  * Mske REST requests to RESTful services with simple syntax.                       
  *
  * @package    Api
  * @subpackage controller.components.rest
  * @copyright  2011 Questionity
  */

/**
 * RestComponent class
 *
 * Rest resource management
 */
class RestclientComponent extends Object
{
   var $components = array('Curl');

    private $rest_server = "";
    private $http_auth;
    private $http_user;
    private $http_pass;

    private $supported_formats = array(
      'xml'             => 'application/xml',
      'json'            => 'application/json',
      'serialize'       => 'application/vnd.php.serialized',
      'php'             => 'text/plain',
      'csv'          => 'text/csv'
   );

    private $auto_detect_formats = array(
      'application/xml'    => 'xml',
      'text/xml'        => 'xml',
      'application/json'   => 'json',
      'text/json'       => 'json',
      'text/csv'        => 'csv',
      'application/csv'    => 'csv',
      'application/vnd.php.serialized' => 'serialize'
   );

   private $format;
   private $mime_type;

   private $response_string;
  
  /**
   * Setup function, Setup RestFull
   *
   * @param array  $config
   * @return void
   */ 
   public function setup($config)
   {
      if (isset($config['server'])) {
         $this->rest_server = $config['server'];
      }
   
      if(substr($this->rest_server, -1, 1) != '/')
      {
         $this->rest_server .= '/';
      }

      $this->http_auth = isset($config['http_auth']) ? $config['http_auth'] : '';
      $this->http_user = isset($config['http_user']) ? $config['http_user'] : '';
      $this->http_pass = isset($config['http_pass']) ? $config['http_pass'] : '';

   }

   /**
    * Get function, get RestFull
    *
    * @param string  $uri
    * @param array   $params
    * @param string  $format
    * @return mixed
    */
   public function get($uri, $params = array(), $format = NULL)
   {
     if($params)
     {
      $uri .= '?'.(is_array($params) ? http_build_query($params) : $params);
     }
   
      return $this->_call('get', $uri, NULL, $format);
   }

   /**
    * Post function, post RestFull
    *
    * @param string  $uri
    * @param array   $params
    * @param string  $format
    * @return mixed
    */
   public function post($uri, $params = array(), $format = NULL)
   {
     return $this->_call('post', $uri, $params, $format);
   }

   /**
    * Post file function
    *
    * @param string  $uri
    * @param array   $params
    * @param string  $format
    * @return mixed
    */
   public function post_file($uri, $params = array(), $format = NULL)
   {
     return $this->_call('post_file', $uri, $params, $format);
   }

   /**
    * Put function, put RestFull
    *
    * @param string  $uri
    * @param array   $params
    * @param string  $format
    * @return mixed
    */
   public function put($uri, $params = array(), $format = NULL)
   {
      return $this->_call('put', $uri, $params, $format);
   }

   /**
    * Delete function, delete RestFull
    *
    * @param string  $uri
    * @param array   $params
    * @param string  $format
    * @return mixed
    */
   public function delete($uri, $params = array(), $format = NULL)
   {
      return $this->_call('delete', $uri, $params, $format);
   }

   /**
    * ApiKey function, apiKey RestFull
    *
    * @param string  $key
    * @param string  $name
    * @return mixed
    */
   public function api_key($key, $name = 'X-API-KEY')
   {
      $this->Curl->http_header($name, $key);
   }
   
   /**
    * language function, language configure RestFull
    *
    * @param array  $lang
    * @return void
    */
   public function language($lang)
   {
      if(is_array($lang))
      {
         $lang = implode(', ', $lang);
      }

      $this->Curl->http_header('Accept-Language', $lang);
   }

   /**
    * Call function, call RestFull
    *
    * @param string  $method
    * @param string  $uri
    * @param array  $params
    * @param string   $format
    * @return mixed
    */
   private function _call($method, $uri, $params = array(), $format = NULL)
   {
      if($format !== NULL)
      {
         $this->format($format);
      }

      $this->_set_headers();

      // Initialize cURL session
      $this->Curl->create($this->rest_server.$uri);
      
      // If authentication is enabled use it
      if ( ($this->http_auth != '') && ($this->http_user != '') )
      {
         $this->Curl->http_login($this->http_user, $this->http_pass, $this->http_auth);
      }
      
      // We still want the response even if there is an error code over 400
      $this->Curl->option('failonerror', FALSE);
      
      // Call the correct method with parameters
      $this->Curl->{$method}($params);
      
      // Execute and return the response from the REST server
      $response = $this->Curl->execute();
      
      if ($response === false) {
         return false;
      }

      // Format and return
      return $this->_format_response($response);
   }


    // 
    /**
     * Format function, If a type is passed in that is not supported, use it as a mime type
     *
     * @param string   $format
     * @return mixed
     */
    public function format($format)
   {
      if(array_key_exists($format, $this->supported_formats))
      {
         $this->format = $format;
         $this->mime_type = $this->supported_formats[$format];
      }

      else
      {
         $this->mime_type = $format;
      }

      return $this;
   }
   
   /**
    * ResutIsValid function, test result si valid RestFull
    *
    * @param array  $result
    * @return boolean
    */
   public function result_is_valid($result) {
      // CURL call failed
      if ($result === false) {
         return false;
      }
      
      if ( (is_object($result) || is_array($result)) && 
           !empty($result->status) ) {
         return true;
      }
      
      return false;
   }
   
   /**
    * ResultIsOk function, test result is ok RestFull
    *
    * @param array  $result
    * @return boolean
    */
   public function result_is_ok($result) {
      if ($this->result_is_valid($result)) {
         if ($result->status == 'OK') {
            return true;
         }
      }
      return false;
   }

   /**
    * ResultIsError function, test result is error RestFull
    *
    * @param array  $result
    * @return boolean
    */
   public function result_is_error($result) {
      if ($this->result_is_valid($result)) {
         if ($result->status == 'ERROR') {
            return true;
         }
      }
      return false;
   }

   /**
    * Debug function, Debug RestFull
    *
    * @return mixed
    */
   public function debug()
   {
      $request = $this->Curl->debug_request();

      echo "=============================================<br/>\n";
      echo "<h2>REST Test</h2>\n";
      echo "=============================================<br/>\n";
      echo "<h3>Request</h3>\n";
      echo $request['url']."<br/>\n";
      echo "=============================================<br/>\n";
      echo "<h3>Response</h3>\n";

      if($this->response_string)
      {
         echo "<code>".nl2br(htmlentities($this->response_string))."</code><br/>\n\n";
      }

      else
      {
         echo "No response<br/>\n\n";
      }

      echo "=============================================<br/>\n";

      if($this->Curl->error_string)
      {
         echo "<h3>Errors</h3>";
         echo "<strong>Code:</strong> ".$this->Curl->error_code."<br/>\n";
         echo "<strong>Message:</strong> ".$this->Curl->error_string."<br/>\n";
         echo "=============================================<br/>\n";
      }

      echo "<h3>Call details</h3>";
      echo "<pre>";
      print_r($this->Curl->info);
      echo "</pre>";

   }

   /**
    * SetHeaders function, Set headers RestFull
    *
    * @return void
    */
   private function _set_headers()
   {
      $this->Curl->http_header('Accept: '.$this->mime_type);
   }

   /**
    * FormatResponse function, format response RestFull
    *
    * @param array  $response
    * @return mixed
    */
   private function _format_response($response)
   {
      $this->response_string =& $response;

      // It is a supported format, so just run its formatting method
      if(array_key_exists($this->format, $this->supported_formats))
      {
         return $this->{"_".$this->format}($response);
      }

      // Find out what format the data was returned in
      $returned_mime = @$this->Curl->info['content_type'];

      // If they sent through more than just mime, stip it off
      if(strpos($returned_mime, ';'))
      {
         list($returned_mime)=explode(';', $returned_mime);
      }

      $returned_mime = trim($returned_mime);

      if(array_key_exists($returned_mime, $this->auto_detect_formats))
      {
         return $this->{'_'.$this->auto_detect_formats[$returned_mime]}($response);
      }

      return $response;
   }

    /**
     * Xml function, Format XML for output
     *
     * @param string  $string
     * @return array
     */
    private function _xml($string)
    { 
      return (array) @simplexml_load_string($string);
      //return $this->xml2array($string);
    }

    /**
     * Csv function, Format HTML for output
     * This function is DODGY! Not perfect CSV support but works with my REST_Controller
     *
     * @param string  $string
     * @return array
     */
    private function _csv($string)
    {
      $data = array();

      // Splits
      $rows = explode("\n", trim($string));
      $headings = explode(',', array_shift($rows));
      foreach( $rows as $row )
      {
         // The substr removes " from start and end
         $data_fields = explode('","', trim(substr($row, 1, -1)));

         if(count($data_fields) == count($headings))
         {
            $data[] = array_combine($headings, $data_fields);
         }

      }

      return $data;
    }

    /**
     * Json function, Encode as JSON
     *
     * @param string  $string
     * @return mixed
     */
    private function _json($string)
    {
      return json_decode(trim($string));
      //return $this->xml2array($string);
    }

    /**
     * Serialize function, Encode as Serialized array
     *
     * @param string  $string
     * @return mixed
     */
    private function _serialize($string)
    {
      return unserialize(trim($string));
    }

    /**
     * Php function, Encode raw PHP
     *
     * @param string  $string
     * @return mixed
     */
    private function _php($string)
    {
      $string = trim($string);
      $populated = array();
      eval("\$populated = \"$string\";");
      return $populated;
    }

}