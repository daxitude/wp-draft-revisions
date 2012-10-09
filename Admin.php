<?php

class DRP_Admin {
	
	public function __construct(&$parent) {
		global $pagenow;
		
		$this->parent = &$parent;
		
		// check to see if we want to display any admin notices on post.php
		add_action('admin_head', array($this, 'maybe_render_admin_notices'));
		// admin menu page to set allowable post types
		add_action('admin_menu', array($this, 'options_page'));		
		// add meta boxes
		add_action('add_meta_boxes', array($this, 'meta_boxes'));
		
		if ('edit.php' == $pagenow)
			add_action('admin_print_scripts', array($this, 'add_js'));
		
//		add_action('admin_menu', array($this, 'add_diff_page'));
		add_action('load-revision.php', array($this, 'drp_revision'));
				
		// is_preview()
//		add_filter('pre_get_posts', array($this, 'process_preview'));
	}
	
	public function process_preview($query) {
//		print_r($query);
		if (
//			$query->is_main_query() &&
//			$query->is_preview() &&
			$query->is_singular()
//			$query->get( '_ppp' )
		)
			add_filter( 'posts_results', array( $this, 'stuff' ) ); // 10,2
	}
	
	public function stuff($posts) {
//		print_r($posts);
		remove_filter( 'posts_results', array( __CLASS__, 'stuff' ) );
		$posts[0]->post_status = 'publish';
		
		return $posts;
	}
	
	public function add_js() {
		wp_register_script( 'drpjs', plugins_url( '/assets/drp.dev.js', __FILE__ ), '', '1.0', 'true' );
		wp_enqueue_script( 'drpjs' );
	}
	
	public function drp_revision() {
		if ( $_GET['action'] != 'drp_diff' ) return;
		
		require_once(ABSPATH . '/wp-admin/admin.php');

		$left = get_post($_GET['left']);
		$right = get_post($_GET['right']);

		if (
			! current_user_can( 'read_post', $left->ID ) || 
			! current_user_can( 'read_post', $right->ID )
		)
			return;

		// make sure draft is always on the right
		if ( $left->ID == $right->post_parent ) {
			$parent = $left;
			$draft = $right;

		} else if ( $right->ID == $left->post_parent ) {
			$parent = $right;
			$draft = $left;
		} else {
			new DRP_Admin_Notice('<strong>Whoops!</strong> Those posts aren\'t related.', 'error');
			wp_redirect(get_edit_post_link($left->ID));
			exit();
		}

		// do some redirect if they are the same?
		
		// do the diff
		$differ = new DRP_Admin_Diff(&$parent, &$draft);
		$rev_fields = $differ->diff();
		
		// This is so that the correct "Edit" menu item is selected.
		$parent_file = $submenu_file = 'edit.php?post_type=' . $parent->post_type;

		require_once( './admin-header.php' );
		
		echo DRP_Mustache::render('diff', array(
			'left' => $parent,
			'right' => $draft,
			'rev_fields' => $rev_fields
		));
		
		require_once( './admin-footer.php' );
		exit();
	}
	
	public function maybe_render_admin_notices() {
		global $post;
		global $pagenow;
		
		if ( 'post.php' != $pagenow ) return;
		
		if ( $this->parent->has_draft($post->ID) ) {
			new DRP_Admin_Notice(DRP_Mustache::render('notice_active_drafts'), 'error');
		}
		else if ( $this->parent->is_draft($post->ID) ) {
			$parent = get_post($post->post_parent);
			$parent_mod = $parent->post_modified;
			$draft_mod = $post->post_modified;
			
			if ( $parent_mod > $draft_mod ) {
				new DRP_Admin_Notice( DRP_Mustache::render('notice_parent_post_updated', array(
					'post_type' => $parent->post_type,
					'right' => $parent->ID,
					'left' => $post->ID
				)), 'updated');
			}
		}
	}
	
	public function options_page() {
		add_options_page(
			'Drafts of Revisions Options',
			'Draft Revisions',
			'manage_options',
			'drp_options',
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
			
		echo DRP_Mustache::render('options_page', array(
			'all_types' => array_values($all_types)
		));
	}

	// @todo figure out what post statuses should be allowed to transition to a status of drp_draft
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
			'drp-draft',
			'Drafts of Revisions',
			array($this, $tpl),
			$post->post_type,
			'side',
			'high'
		);		
	}
	
	public function render_is_draft_meta_box($post) {
		$parent = get_post($post->post_parent);
		$parent->admin_link = get_edit_post_link($parent->ID, '&');
		echo DRP_Mustache::render('adraft_meta_box', array(
			'parent' => (array) $parent,
			'draft' => $post
		));
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
		echo DRP_Mustache::render('drafts_meta_box', array('kids' => $kids));
	}


/*	
	public function add_diff_page() {
		add_submenu_page(
			'edit.php?post_type=post',
			'Post Diff',
			'Diff',
			'edit_posts',
			'drp_diff',
			array($this, 'render_diff_page')
		);		
	}
	
	public function render_diff_page() {
		echo 'hello world';
	}
*/	
	
}