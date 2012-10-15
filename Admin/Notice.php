<?php

/**
 * Copyright (c) 2012 Dax Ponce de Leon
 * Distributed under MIT License
 *
 * Provides a Ruby/Rails-like flash message system for WordPress admin notices.
 * Implementation heavily inspired by Slim Framework for PHP <http://slimframework.com>
 * Sample usage:
 *		
 *		// instantiate within your plugin controller or main class
 * 		$this->notices = new DPR_Admin_Notice();
 *
 *		// later on when you want to add a notice to the current request
 * 		$this->notices->now(array(
 *			'text' => '<strong>Oops! </strong> that\'s not allowed.',
 *			'type' => 'error', // or updated
 *			'position' => 1
 *		));
 *
 * You can add a notice to the next request with $this->notices->add()
 * Notices enqueued with the admin_notices action always print before any of WP's own notices
 *
 * @version 0.1
 * @author daxitude <daxitude@gmail.com>
 * @license MIT
 */

class DPR_Admin_Notice {
	
	// array of the notices for previous, next, and current request
	protected $notices = array(
		'prev' => array(),
		'next' => array(),
		'now' => array()
	);
	
	// defaults for a notice, in case something is left out on a call to add() or now()
	protected $defaults = array(
		'text' => null,
		'type' => 'updated', // || error
		'position' => 999 // kinda hacky but makes sure that default position is lowest
	);
	
	// the class should be instantiated on each new admin request. all messages in
	// previous and now will be merged together and printed on the admin_notices action
	public function __construct() {
		// make sure the session has started
		if ( !isset($_SESSION) ) session_start();
		
		$this->load();
		
		// @todo figure out what these other actions do
		// network_admin_notices, user_admin_notices, all_admin_notices
		add_action('admin_notices', array($this, 'print_notices'));
	}
	
	// load notices from the session into the prev array and clear out the session value
	public function load() {
        $this->notices['prev'] = isset($_SESSION['notices']) ? $_SESSION['notices'] : array();
        return $this->save();
    }
	
	// add all notices for the next request to the session
	public function save() {
        $_SESSION['notices'] = $this->notices['next'];
        return $this;
    }
	
	// add a notice to be loaded on the next request
	// $notice should be an array with keys: text, type, and position
	public function add(array $notice) {
		return $this->_add_a_notice($notice, 'next');
	}
	
	// add a notice to be called on the current request
	// $notice should be an array with keys: text, type, and position
	public function now(array $notice) {
		return $this->_add_a_notice($notice, 'now');
	}
	
	// callback for admin_notices action to print notices on an admin page
	public function print_notices() {
		$notices = $this->get_notices();
		// @todo sort array by position
		$html = '';
		foreach ($notices as $notice) {
			$html .= '<div class="'.$notice['type'].'"><p>' . $notice['text'] . '</p></div>';
		}
		echo $html;
	}
	
	// public method to manually return an array of current notices
	// also used internally on print_notices callback
	public function get_notices() {
		// sort array by position				
		$notices = array_merge($this->notices['prev'], $this->notices['now']);
		uasort($notices, create_function('$a, $b', 'return $a[\'position\'] < $b[\'position\'] ? -1 : 1;'));				
		return $notices;
	}
	
	// internal method called by add() and now()
	// $notice should be an array with keys: text, type, and position
	private function _add_a_notice($notice, $when) {
		if ( ! in_array($when, array('next', 'now')) ) return false;
		// what's faster wp_parse_args or array_merge ?
		$notice = array_merge($this->defaults, $notice);
		$this->notices[$when][] = $notice;
        return $this->save();
	}
	
}