<?php

use OP\MoodleWebservice;

class MoodleExamplePage_Controller extends Page_Controller {
	
	public function init(){
		return parent::init();
	}
	
	/**
	 * returns a user's profile
	 * @return \Dataobject|null
	 */
	public function getUserProfile() {
		// connect to moodle
		$moodle = MoodleWebservice::connect();
		if(!$moodle) {
			return Debug::message('Failed to connect to Moodle Webservice');
		}
		
		// create a user list containing one generic user
		$params = array('userlist' => array(
			(object) array(
				'userid'=>'2',
				'courseid' => '1'
			)
		));
		
		// call the function core_user_get_course_user_profiles
		$userprofile = $moodle->call ('core_user_get_course_user_profiles', $params);
		
		// return it
		return $userprofile->Data();
	}
	
	
	/**
	 * returns a list of courses given a user ID
	 * @return \Dataobject|null
	 */
	public function getProgrammes() {
		$moodle = MoodleWebservice::connect();
		if(!$moodle) {
			return Debug::message('Failed to connect to Moodle Webservice');
		}
		
		// find programmes belonging to this user
		$params = array('userid' => '2');
		$courselist = $moodle->call ('core_enrol_get_users_courses', $params);
		return $courselist->Data();
	}
	
	/**
	 * returns a list of users, given a search field
	 * @return \Dataobject|null
	 */
	public function getUser() {
		$moodle = MoodleWebservice::connect();
		if(!$moodle) {
			return Debug::message('Failed to connect to Moodle Webservice');
		}
		
		//$params = array('values' => array('student_hub'), 'field' => 'username');
		$params = array('values' => array('1337'), 'field' => 'idnumber');
		$userdata = $moodle->call ('core_user_get_users_by_field', $params);
		
		return $userdata->Data();
	}
	
}
