<?php

class DPP_Admin_Notice {
	
	// type should be updated (yellow) or error (red)
	public function __construct($notice, $type) {
		$this->notice = $notice;
		$this->type = $type;
		add_action('admin_notices', array($this, 'print_notice'));
	}
	
	public function print_notice() {
		echo '<div class="'.$this->type.'"><p>' . $this->notice . '</p></div>';
	}
	
}