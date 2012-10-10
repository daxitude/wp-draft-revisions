<?php

class DRP_Admin_Notice {
	
	private $notices = array(
		'prev' => array(),
		'next' => array(),
		'now' => array()
	);
	
	private $defaults = array(
		'text' => '',
		'type' => 'updated', // || error
		'position' => null
	);
	
	// $notices should be an array of arrays of notice and type keys
	// type should be updated (yellow) or error (red)
	public function __construct() {
		// make sure the session has started
		if ( !isset($_SESSION) ) session_start();
		
		$this->load();
		add_action('admin_notices', array($this, 'print_notices'));
	}
	
	public function load() {
        $this->notices['prev'] = isset($_SESSION['notices']) ? $_SESSION['notices'] : array();
        return $this->save();
    }

	public function save() {
        $_SESSION['notices'] = $this->notices['next'];
        return $this;
    }
	
	public function add($notice) {
//		$position = isset($notice['position']) ? $notice['position'] : count($this->notices['next']);
		$this->notices['next'][] = $notice;
		return $this->save();
	}
	
	public function now($notice) {
		$this->notices['now'][] = $notice;
        return $this->save();
	}
	
	public function print_notices() {
		$notices = array_merge($this->notices['prev'], $this->notices['now']);
		// @todo sort array by position
		$html = '';
		foreach ($notices as $notice) {
			$html .= '<div class="'.$notice['type'].'"><p>' . $notice['text'] . '</p></div>';
		}
		echo $html;
	}
	
}