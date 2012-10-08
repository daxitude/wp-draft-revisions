<?php
require_once 'PHPUnit/Extensions/SeleniumTestCase.php';

// verifyTextPresent, verifyElementPresent, assertTitle, 

/*
 
class Web_Test extends PHPUnit_Extensions_SeleniumTestCase {
	
    protected function setUp() {
        $this->setBrowser('*firefox');
        $this->setBrowserUrl('http://www.pmatechnologies.com/');
    }
 
    public function test_title() {
		$this->open('http://pmatechnologies.com/');
        $this->verifyTextPresent( 'Collaborative Project Planning & Scheduling' );
    }

	function test_options_page() {
		if ( !defined( 'WP_ADMIN' ) )
			define( 'WP_ADMIN', true );
		
//		$this->open('http://dev.pmatech/wp-admin/options-general.php?page=apd_options');
//		$this->assertTrue( $this->isTextPresent('Draft Published Posts') );
//		$this->assertElementContainsText( 'h2', 'Draft Published Posts');
	}

}


*/