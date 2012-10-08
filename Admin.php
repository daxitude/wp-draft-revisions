<?php

class DPP_Admin {
	
	public function __construct(&$parent) {		
		$this->parent = &$parent;
		
		// admin menu page to set allowable post types
		add_action('admin_menu', array($this, 'options_page'));
		
		// add meta boxes
		add_action('add_meta_boxes', array($this, 'meta_boxes'));
		
	}
	
	public function add_js() {
		wp_register_script( 'dppjs', plugins_url( '/assets/dpp.dev.js', __FILE__ ), '', '1.0', 'true' );
		wp_enqueue_script( 'dppjs' );
	}
	
	public function options_page() {
		add_options_page(
			'Drafts of Published Post Options',
			'Draft Published',
			'manage_options',
			'dpp_options',
			array($this, 'render_options_page')
		);
		
	}
	
	public function render_options_page() {
		
		if ($_POST) {
			$this->parent->update_option($_POST);
		}
		
		$all_types = get_post_types();
		// remove attachment, revision, and nav_menu_item from the list of options
		$all_types = array_filter(
			$all_types,
			create_function(
				'$item',
				'return !in_array($item, array(\'attachment\', \'revision\', \'nav_menu_item\'));'
			)
		);
		
		$supported = $this->parent->get_permitted_post_types();
		
		foreach( $all_types as $key => $value ) {
			$all_types[$key] = array('name' => $value, 'supported' => in_array($value, $supported));
		}
			
		echo DPP_Mustache::render('options_page', array(
			'all_types' => array_values($all_types)
		));
	}

	// @todo figure out what post statuses should be allowed to transition to a status of apd_draft
	public function meta_boxes() {
		global $post;
		if (
			!$this->parent->post_type_is_supported($post->post_type) ||
			!in_array($post->post_status, array('publish', 'private', $this->parent->status_value))
		)
			return;
		
		if ( $this->parent->is_draft($post->ID) ) {
			$tpl = 'render_is_draft_meta_box';
			add_action('admin_print_scripts', array($this, 'add_js'));
		}
		else {
			$tpl = 'render_drafts_meta_box';
		}
		
		add_meta_box(
			'apd-draft',
			'Drafts of Published',
			array($this, $tpl),
			$post->post_type,
			'side',
			'high'
		);		
	}
	
	public function render_is_draft_meta_box($post) {
		$parent = get_post($post->post_parent);
		$parent->admin_link = get_edit_post_link($parent->ID, '&');
		echo DPP_Mustache::render('adraft_meta_box', array('parent' => (array) $parent ));
	}
	
	public function render_drafts_meta_box($post) {
		$kids = get_children(array(
//			'list_of_drafts' => true,
			'post_parent' => $post->ID,
			'post_status' => $this->parent->status_value
//			'post__in' => $this->parent->get_draft_ids()
		));
		
		// turn kids into a basic array and replace author id with author nicename
		$kids = array_values($kids);
		$kids = array_map(
			create_function(
				'$kid',
				'$kid->post_author = get_the_author_meta(\'user_nicename\', $kid->post_author);return $kid;'
			),
			$kids
		);
		echo DPP_Mustache::render('drafts_meta_box', array('kids' => $kids));
	}
	

	
	
}