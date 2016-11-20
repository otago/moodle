<?php

/**
 * Silverstripe Moodle webservice client. Utilises REST/JSON. JSON is only 
 * supported under Moodle 2.2 and above.
 * 
 * Parts of the script were utlised by Moodle's cURL wrapper.
 * 
 * @see MoodleExamplePage.php for examples on how to use this wrapper.
 * 
 * @see http://docs.moodle.org/25/en/Using_web_services
 * @see http://moodle/admin/settings.php?section=webservicesoverview
 * @see http://docs.moodle.org/dev/Creating_a_web_service_client
 */
class MoodleWebservice {

	private static $instance;   // singleton instance
	private static $token; // JSON authentication token
	private static $errors = array(); // connection errors 
	private static $restformat = 'json';
	private $count = 0;

	/**
	 * asks Moodle for token. If it fails it will return a null object. You can see
	 * errors by looking at MoodleWebservice::getErrors()
	 * 
	 * Alternatively you can set one in moodle.yml, it will still return an instance.
	 * 
	 * @return \MoodleWebservice|null
	 */

	public static function connect() {
		if (!function_exists('curl_init')) {
			MoodleWebservice::$errors [] = 'cURL module must be enabled!';
			return null;
		}
		
		if (MoodleWebservice::$instance) {
			return MoodleWebservice::$instance;
		}

		$authentication = Config::inst()->get('MoodleWebservice', 'authentication');

		if (isset($authentication['statictoken']) && $authentication['statictoken']) {
			MoodleWebservice::$instance = new static();
			MoodleWebservice::$token = $authentication['statictoken'];
			return MoodleWebservice::$instance;
		}

		if (!isset($authentication['username']) || !isset($authentication['username'])) {
			MoodleWebservice::$errors [] = 'Moodle webservice authentication not set in .yml file';
			return null;
		}

		if (!MoodleWebservice::getLocation()) {
			MoodleWebservice::$errors [] = 'Moodle location not set in .yml file';
			return null;
		}

		$ch = curl_init();

		// set URL and other appropriate options
		$urlstr = MoodleWebservice::getLocation() . 'login/token.php?';
		$urlstr .= 'username=' . $authentication['username'];
		$urlstr .= '&password=' . $authentication['password'];
		$urlstr .= '&service=' . $authentication['service'];
		
		curl_setopt($ch, CURLOPT_URL, $urlstr);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		
		$result = curl_exec($ch);

		// failure
		if (!$result) {
			MoodleWebservice::$errors [] = curl_error($ch);
			return null;
		}
		curl_close($ch);

		$authjson = json_decode($result);

		// json has been parsed
		if ($authjson) {
			if (isset($authjson->error)) {
				MoodleWebservice::$errors [] = $authjson->error;
				return null;
			}
			// success!
			if (isset($authjson->token)) {
				MoodleWebservice::$instance = new static();
				MoodleWebservice::$token = $authjson->token;
				return MoodleWebservice::$instance;
			}
		}

		MoodleWebservice::$errors [] = 'cURL returned non-JSON';
		return null;
	}

	/**
	 * returns an array of errors
	 * @return array of errors
	 */
	public static function getErrors() {
		return MoodleWebservice::$errors;
	}

	/**
	 * checks the environment type, and returns the connection string
	 * @return type string
	 */
	public static function getLocation() {
		$urltype = Config::inst()->get('MoodleWebservice', 'authentication');
		if (Director::isTest()) {
			return $urltype['locationTest'];
		} else if (Director::isDev()) {
			return $urltype['locationDev'];
		} else if (Director::isLive()) {
			return $urltype['locationLive'];
		}
		return '';
	}

	/**
	 * will call the remote request to the moodle server
	 * 
	 * @param string $function name of the function being called
	 * @param array|string $params post requires a string, get & put arrays
	 * @param string $method POST|GET|PUT|DELETE|TRACE|OPTIONS default POST
	 * @param array $options additional options
	 * @return MoodleResponse
	 */
	public function call($function, $params, $method = 'POST', $options = array()) {
		$this->error = "";
		$url = MoodleWebservice::getLocation() . 'webservice/rest/server.php'.
				'?wstoken=' . MoodleWebservice::$token . '&wsfunction='.$function;
		
		if(MoodleWebservice::$restformat != 'xml') {
			$url .= '&moodlewsrestformat='.MoodleWebservice::$restformat;
		}
		if(isset($_GET['debug']) && Director::isDev()) {
			echo($url. '<br/>');
		}
				
		$options['RETURNTRANSFER'] = 1;
		if($method == 'POST') {
			return new MoodleResponse($this->post($url, $params, $options), $this->error);
		} else if($method == 'GET') {
			return new MoodleResponse($this->get($url, $params, $options), $this->error);
		} else if($method == 'PUT') {
			return new MoodleResponse($this->put($url, $params, $options), $this->error);
		} else if($method == 'DELETE') {
			return new MoodleResponse($this->delete($url, $options), $this->error);
		} else if($method == 'TRACE') {
			return new MoodleResponse($this->trace($url, $options), $this->error);
		} else if($method == 'OPTIONS') {
			return new MoodleResponse($this->options($url, $options), $this->error);
		}
		return new MoodleResponse("", 'method invalid');
	}

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * *Singleton* via the 'connect' operator from outside of this class.
	 */
	protected function __construct() {
		
	}

	/**
	 * Private clone method to prevent cloning of the instance of the
	 * *Singleton* instance.
	 *
	 * @return void
	 */
	private function __clone() {
		
	}

	/**
	 * Private unserialize method to prevent unserializing of the *Singleton*
	 * instance.
	 *
	 * @return void
	 */
	private function __wakeup() {
		
	}

	public $proxy = false;
	public $response = array();
	public $header = array();
	public $info;
	public $error;
	private $options;
	private $proxy_host = '';
	private $proxy_auth = '';
	private $proxy_type = '';
	private $cookie = false;			// 'curl_cookie.txt' etc.

	/**
	 * Resets the CURL options that have already been set
	 */
	public function resetopt() {
		$this->options = array();
		$this->options['CURLOPT_USERAGENT'] = 'MoodleBot/1.0';
		// True to include the header in the output
		$this->options['CURLOPT_HEADER'] = 0;
		// True to Exclude the body from the output
		$this->options['CURLOPT_NOBODY'] = 0;
		// TRUE to follow any "Location: " header that the server
		// sends as part of the HTTP header (note this is recursive,
		// PHP will follow as many "Location: " headers that it is sent,
		// unless CURLOPT_MAXREDIRS is set).
		//$this->options['CURLOPT_FOLLOWLOCATION']    = 1;
		$this->options['CURLOPT_MAXREDIRS'] = 10;
		$this->options['CURLOPT_ENCODING'] = '';
		// TRUE to return the transfer as a string of the return
		// value of curl_exec() instead of outputting it out directly.
		$this->options['CURLOPT_RETURNTRANSFER'] = 1;
		$this->options['CURLOPT_BINARYTRANSFER'] = 0;
		$this->options['CURLOPT_SSL_VERIFYPEER'] = 0;
		$this->options['CURLOPT_SSL_VERIFYHOST'] = 2;
		$this->options['CURLOPT_CONNECTTIMEOUT'] = 30;
	}

	/**
	 * If a cookie file has been specified, clear it.
	 */
	public function resetcookie() {
		if (!empty($this->cookie)) {
			if (is_file($this->cookie)) {
				$fp = fopen($this->cookie, 'w');
				if (!empty($fp)) {
					fwrite($fp, '');
					fclose($fp);
				}
			}
		}
	}

	
	/**
	 * sets the cURL options
	 * 
	 * @param array $options valies to modify
	 */
	public function setopt($options = array()) {
		if (is_array($options)) {
			foreach ($options as $name => $val) {
				if (stripos($name, 'CURLOPT_') === false) {
					$name = strtoupper('CURLOPT_' . $name);
				}
				$this->options[$name] = $val;
			}
		}
	}

	/**
	 * Reset http method
	 *
	 */
	public function cleanopt() {
		unset($this->options['CURLOPT_HTTPGET']);
		unset($this->options['CURLOPT_POST']);
		unset($this->options['CURLOPT_POSTFIELDS']);
		unset($this->options['CURLOPT_PUT']);
		unset($this->options['CURLOPT_INFILE']);
		unset($this->options['CURLOPT_INFILESIZE']);
		unset($this->options['CURLOPT_CUSTOMREQUEST']);
	}

	/**
	 * Set HTTP Request Header
	 *
	 * @param array $headers
	 *
	 */
	public function setHeader($header) {
		if (is_array($header)) {
			foreach ($header as $v) {
				$this->setHeader($v);
			}
		} else {
			$this->header[] = $header;
		}
	}

	/**
	 * Set HTTP Response Header
	 *
	 */
	public function getResponse() {
		return $this->response;
	}

	/**
	 * private callback function
	 * Formatting HTTP Response Header
	 *
	 * @param mixed $ch Apparently not used
	 * @param string $header
	 * @return int The strlen of the header
	 */
	private function formatHeader($ch, $header) {
		$this->count++;
		if (strlen($header) > 2) {
			list($key, $value) = explode(" ", rtrim($header, "\r\n"), 2);
			$key = rtrim($key, ':');
			if (!empty($this->response[$key])) {
				if (is_array($this->response[$key])) {
					$this->response[$key][] = $value;
				} else {
					$tmp = $this->response[$key];
					$this->response[$key] = array();
					$this->response[$key][] = $tmp;
					$this->response[$key][] = $value;
				}
			} else {
				$this->response[$key] = $value;
			}
		}
		return strlen($header);
	}

	/**
	 * Set options for individual curl instance
	 *
	 * @param object $curl A curl handle
	 * @param array $options
	 * @return object The curl handle
	 */
	private function apply_opt($curl, $options) {
		// Clean up
		$this->cleanopt();
		// set cookie
		if (!empty($this->cookie) || !empty($options['cookie'])) {
			$this->setopt(array('cookiejar' => $this->cookie,
				'cookiefile' => $this->cookie
			));
		}

		// set proxy
		if (!empty($this->proxy) || !empty($options['proxy'])) {
			$this->setopt($this->proxy);
		}
		$this->setopt($options);
		// reset before set options
		curl_setopt($curl, CURLOPT_HEADERFUNCTION, array(&$this, 'formatHeader'));
		// set headers
		if (empty($this->header)) {
			$this->setHeader(array(
				'User-Agent: MoodleBot/1.0',
				'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
				'Connection: keep-alive'
			));
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->header);


		// set options
		foreach ($this->options as $name => $val) {
			if (is_string($name)) {
				$name = constant(strtoupper($name));
			}
			curl_setopt($curl, $name, $val);
		}
		return $curl;
	}

	/**
	 * Download multiple files in parallel
	 *
	 * Calls {@link multi()} with specific download headers
	 *
	 * <code>
	 * $c = new curl;
	 * $c->download(array(
	 *              array('url'=>'http://localhost/', 'file'=>fopen('a', 'wb')),
	 *              array('url'=>'http://localhost/20/', 'file'=>fopen('b', 'wb'))
	 *              ));
	 * </code>
	 *
	 * @param array $requests An array of files to request
	 * @param array $options An array of options to set
	 * @return array An array of results
	 */
	public function download($requests, $options = array()) {
		$options['CURLOPT_BINARYTRANSFER'] = 1;
		$options['RETURNTRANSFER'] = false;
		return $this->multi($requests, $options);
	}

	/*
	 * Mulit HTTP Requests
	 * This function could run multi-requests in parallel.
	 *
	 * @param array $requests An array of files to request
	 * @param array $options An array of options to set
	 * @return array An array of results
	 */
	protected function multi($requests, $options = array()) {
		$count = count($requests);
		$handles = array();
		$results = array();
		$main = curl_multi_init();
		for ($i = 0; $i < $count; $i++) {
			$url = $requests[$i];
			foreach ($url as $n => $v) {
				$options[$n] = $url[$n];
			}
			$handles[$i] = curl_init($url['url']);
			$this->apply_opt($handles[$i], $options);
			curl_multi_add_handle($main, $handles[$i]);
		}
		$running = 0;
		do {
			curl_multi_exec($main, $running);
		} while ($running > 0);
		for ($i = 0; $i < $count; $i++) {
			if (!empty($options['CURLOPT_RETURNTRANSFER'])) {
				$results[] = true;
			} else {
				$results[] = curl_multi_getcontent($handles[$i]);
			}
			curl_multi_remove_handle($main, $handles[$i]);
		}
		curl_multi_close($main);
		return $results;
	}

	/**
	 * Single HTTP Request
	 *
	 * @param string $url The URL to request
	 * @param array $options
	 * @return bool
	 */
	protected function request($url, $options = array()) {
		// create curl instance
		$curl = curl_init($url);
		$options['url'] = $url;
		$options['CURLOPT_TIMEOUT'] = 10;
		$options['CURLOPT_CONNECTTIMEOUT'] = 10;
		 
		
		$this->apply_opt($curl, $options);
		$ret = curl_exec($curl);

		$this->info = curl_getinfo($curl);
		$this->error = curl_error($curl);


		curl_close($curl);

		if (empty($this->error)) {
			return $ret;
		} else {
			return $this->error;
			// exception is not ajax friendly
			//throw new moodle_exception($this->error, 'curl');
		}
	}

	/**
	 * HTTP HEAD method
	 *
	 * @see request()
	 *
	 * @param string $url
	 * @param array $options
	 * @return bool
	 */
	private function head($url, $options = array()) {
		$options['CURLOPT_HTTPGET'] = 0;
		$options['CURLOPT_HEADER'] = 1;
		$options['CURLOPT_NOBODY'] = 1;
		return $this->request($url, $options);
	}

	/**
	 * Recursive function formating an array in POST parameter
	 * @param array $arraydata - the array that we are going to format and add into &$data array
	 * @param string $currentdata - a row of the final postdata array at instant T
	 *                when finish, it's assign to $data under this format: name[keyname][][]...[]='value'
	 * @param array $data - the final data array containing all POST parameters : 1 row = 1 parameter
	 */
	private function format_array_postdata_for_curlcall($arraydata, $currentdata, &$data) {
		foreach ($arraydata as $k => $v) {
			$newcurrentdata = $currentdata;
			if (is_object($v)) {
				$v = (array) $v;
			}
			if (is_array($v)) { //the value is an array, call the function recursively
				$newcurrentdata = $newcurrentdata . '[' . urlencode($k) . ']';
				$this->format_array_postdata_for_curlcall($v, $newcurrentdata, $data);
			} else { //add the POST parameter to the $data array
				$data[] = $newcurrentdata . '[' . urlencode($k) . ']=' . urlencode($v);
			}
		}
	}

	/**
	 * Transform a PHP array into POST parameter
	 * (see the recursive function format_array_postdata_for_curlcall)
	 * @param array $postdata
	 * @return array containing all POST parameters  (1 row = 1 POST parameter)
	 */
	private function format_postdata_for_curlcall($postdata) {
		if (is_object($postdata)) {
			$postdata = (array) $postdata;
		}
		$data = array();
		foreach ($postdata as $k => $v) {
			if (is_object($v)) {
				$v = (array) $v;
			}
			if (is_array($v)) {
				$currentdata = urlencode($k);
				$this->format_array_postdata_for_curlcall($v, $currentdata, $data);
			} else {
				$data[] = urlencode($k) . '=' . urlencode($v);
			}
		}
		$convertedpostdata = implode('&', $data);
		return $convertedpostdata;
	}

	/**
	 * HTTP POST method
	 *
	 * @param string $url
	 * @param array|string $params
	 * @param array $options
	 * @return bool
	 */
	private function post($url, $params = '', $options = array()) {
		$options['CURLOPT_POST'] = 1;
		if (is_array($params)) {
			$params = $this->format_postdata_for_curlcall($params);
		}
		$options['CURLOPT_POSTFIELDS'] = $params;
		return $this->request($url, $options);
	}

	/**
	 * HTTP GET method
	 *
	 * @param string $url
	 * @param array $params
	 * @param array $options
	 * @return bool
	 */
	private function get($url, $params = array(), $options = array()) {
		$options['CURLOPT_HTTPGET'] = 1;

		if (!empty($params)) {
			$url .= (stripos($url, '?') !== false) ? '&' : '?';
			$url .= http_build_query($params, '', '&');
		}
		return $this->request($url, $options);
	}

	/**
	 * HTTP PUT method
	 *
	 * @param string $url
	 * @param array $params
	 * @param array $options
	 * @return bool
	 */
	private function put($url, $params = array(), $options = array()) {
		$file = $params['file'];
		if (!is_file($file)) {
			return null;
		}
		$fp = fopen($file, 'r');
		$size = filesize($file);
		$options['CURLOPT_PUT'] = 1;
		$options['CURLOPT_INFILESIZE'] = $size;
		$options['CURLOPT_INFILE'] = $fp;
		if (!isset($this->options['CURLOPT_USERPWD'])) {
			$this->setopt(array('CURLOPT_USERPWD' => 'anonymous: noreply@moodle.org'));
		}
		$ret = $this->request($url, $options);
		fclose($fp);
		return $ret;
	}

	/**
	 * HTTP DELETE method
	 *
	 * @param string $url
	 * @param array $params
	 * @param array $options
	 * @return bool
	 */
	private function delete($url, $param = array(), $options = array()) {
		$options['CURLOPT_CUSTOMREQUEST'] = 'DELETE';
		if (!isset($options['CURLOPT_USERPWD'])) {
			$options['CURLOPT_USERPWD'] = 'anonymous: noreply@moodle.org';
		}
		$ret = $this->request($url, $options);
		return $ret;
	}

	/**
	 * HTTP TRACE method
	 *
	 * @param string $url
	 * @param array $options
	 * @return bool
	 */
	public function trace($url, $options = array()) {
		$options['CURLOPT_CUSTOMREQUEST'] = 'TRACE';
		$ret = $this->request($url, $options);
		return $ret;
	}

	/**
	 * HTTP OPTIONS method
	 *
	 * @param string $url
	 * @param array $options
	 * @return bool
	 */
	public function options($url, $options = array()) {
		$options['CURLOPT_CUSTOMREQUEST'] = 'OPTIONS';
		$ret = $this->request($url, $options);
		return $ret;
	}

	public function get_info() {
		return $this->info;
	}

}

/**
 * class used to respond with JSON requests 
 */
class MoodleResponse {

	private $error;
	private $content;

	function __construct($content, $error) {
		$this->error = $error;
		$this->content = $content;
		if(is_string($content)) {
			$tmppar = json_decode($this->content);
			if (is_object($tmppar) && isset($tmppar->exception)) {
				$this->error = $content;
				$this->content = null;
			}
		}
	}

	/**
	 * JSON array of the result of the response
	 * @return json array
	 */
	public function Content() {
		return $this->content;
	}

	/**
	 * if there was any error in 
	 * @return string
	 */
	public function Error() {
		return $this->error;
	}

	/**
	 * Recursivity creates the SilverStripe dataobject represntation of content
	 * @param mixed $array
	 * @return \DataObject|\DataList|null
	 */
	private function parseobject($array) {
		if (is_object($array)) {
			if (get_class($array) == 'DataObject') {
				return $array;
			}
			$do = DataObject::create();
			foreach (get_object_vars($array) as $key => $obj) {
				if ($key == '__Type') {
					$do->setField('Title', $obj);
				} else if (is_array($obj) || is_object($obj)) {
					$do->setField($key, $this->parseobject($obj));
				} else {
					$do->setField($key, $obj);
				}
			}
			return $do;
		} else if (is_array($array)) {
			$dataList = ArrayList::create();
			foreach ($array as $key => $obj) {
				$dataList->push($this->parseobject($obj));
			}
			return $dataList;
		}
		return null;
	}

	/**
	 * Returns SilverStripe object representations of content
	 * @return \DataObject|\DataList|null
	 */
	public function Data() {
		if(!is_string($this->content)) return null;
		return $this->parseobject(json_decode($this->content));
	}

}