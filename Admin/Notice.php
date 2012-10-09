<?php

class DRP_Admin_Notice {
	
	// type should be updated (yellow) or error (red)
	public function __construct($notice, $type = 'updated') {
		$this->notice = $notice;
		$this->type = $type;
		add_action('admin_notices', array($this, 'print_notice'));
	}
	
	public function print_notice() {
		echo '<div class="'.$this->type.'"><p>' . $this->notice . '</p></div>';
	}
	
}