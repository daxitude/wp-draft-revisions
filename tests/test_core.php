<?php
/**
 * Core Tests
 */

class DPP_Test extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();		
		$this->instance = new Draft_Published_Post();
	}
	
	function rstr() {
		return substr(md5(rand()), 0, 7);
	}
	
	function create_post($opts = null) {
		$author = $this->create_user();
	
		$post = array(
			'post_title' => 'post '.$this->rstr().' title',
			'post_content' => 'a sentence of post content ' . $this->rstr(),
			'post_author' => $author,
			'post_type' => 'post',
			'post_date' => date( 'Y-m-d H:i:s', strtotime( '-1 day' ) ),
			'post_status' => 'publish'
		);
		
		$post = wp_parse_args($opts, $post);

		$id = wp_insert_post( $post );
		return get_post($id);
	}
	
	function create_draft($id) {
		$draft_id = $this->instance->create_draft($id);
		return get_post($draft_id);
	}
	
	function create_user() {
		 
		$user = array(
			'role' => 'administrator',
			'user_login' => 'john' . $this->rstr(),
			'user_pass' => 'password',
			'user_email' => 'john'.$this->rstr().'@example.com',
		);

		$userID = wp_insert_user( $user );

		return $userID;
		 
	}
	
	function test_plugin_activated() {
		$this->assertTrue( class_exists( 'Draft_Published_post' ) );
	}
	
	function test_post_status_exists() {
		$all = get_post_stati();
		$this->assertContains($this->instance->status_value, $all);
	}
	
	function test_registered_post_types() {
		// by default should be post and page
		$types = $this->instance->get_option('post_types');
		$this->assertContains( 'post', $types );
		$this->assertContains( 'page', $types );
	}
	
	function test_adding_post_type_support() {
		$this->instance->add_post_type_support('foo');
		$types = $this->instance->get_option('post_types');		
		$this->assertContains( 'foo', $types );
		$this->assertTrue( $this->instance->post_type_is_supported('foo') );
	}
	
	function test_draft_is_created() {
		$parent = $this->create_post();	
		$draft = $this->create_draft($parent->ID);
		
		$this->assertEquals( $draft->post_status, $this->instance->status_value );
		$this->assertEquals( $parent->post_title, $draft->post_title );
		$this->assertEquals( $parent->ID, $draft->post_parent );
	}
	
	function test_post_type_should_be_permitted() {
		$parent = $this->create_post(array( 'post_type' => 'not_allowed' ));
		$draft = $this->create_draft($parent->ID);
				
		$this->assertNull($draft);
	}
	
	function test_draft_publishes() {
		$new_content = 'this is revised';
		
		$parent = $this->create_post();	
		$draft = $this->create_draft($parent->ID);
		
		wp_update_post(array('ID' => $draft->ID, 'post_content' => $new_content));

		$draft = get_post($draft->ID);
		
		$this->instance->publish_draft($draft);
		
		$test_draft = get_post($draft_id);
		$test_published = get_post($parent->ID);
		
		$this->assertNull($test_draft);
		$this->assertEquals( $test_published->post_content, $new_content );
	}
	
	function test_has_draft() {
		$parent = $this->create_post();	
		$draft = $this->create_draft($parent->ID);
		
		$this->assertTrue( $this->instance->has_draft($parent->ID) );
	}
	
	function test_is_draft() {
		$parent = $this->create_post();	
		$draft = $this->create_draft($parent->ID);
		
		$this->assertTrue( $this->instance->is_draft($draft->ID) );
	}
	
	function test_drafts_not_public() {
		$posts = array();
		$drafts = array();
		$num = 10;
		$i = 0;
			
		while ($i++ < $num) {
			$post = $this->create_post();
			$posts[] = $post;
			$drafts[] = $this->create_draft($post->ID);
		}
		
		$test_posts = get_posts(array('post_type' => 'post', 'numberposts' => -1));
		
		// the default installed post is in the db
		$this->assertEquals( count($test_posts), $num + 1 );
		
	}
	
	
}




