<?php
/**
 * Core Tests
 * not much coverage
 * @todo figure out how to use something like selenium for ui and integration tests
 */

class DPR_Test_Core extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();		
		$this->instance = new Draft_Post_Revisions();
		$this->drafter = &$this->instance->drafter;
	}
		
	function test_plugin_activated() {
		$this->assertTrue( class_exists( 'Draft_Post_Revisions' ) );
	}	
	
	
	function test_plugin_uninstalls() {
		Draft_Post_Revisions::uninstall();
		$options = get_option(Draft_Post_Revisions::options_key());
		
		$this->assertFalse($options);
	}
}