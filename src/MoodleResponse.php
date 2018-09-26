<?php

namespace OP;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ArrayList;

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
