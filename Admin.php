<?php
/*
 * handles actions in the wp-admin
 */
class DRP_Admin {
	
	private $parent;
	private $notices;
	
	public function __construct(&$parent) {
		global $pagenow;
		// set a reference to the parent (core.php) for access to its methods
		$this->parent = &$parent;		
		// flash-y wp admin notices
		$this->notices = new DRP_Admin_Notice();
		// check to see if we want to display any admin notices on post.php
		add_action('admin_head', array($this, 'maybe_render_admin_notices'));
		// admin menu page to set allowable post types
		add_action('admin_menu', array($this, 'options_page'));		
		// add meta boxes
		add_action('add_meta_boxes', array($this, 'meta_boxes'));
		
		if ('edit.php' == $pagenow)
			add_action('admin_print_scripts', array($this, 'add_js'));
		
		// custom revision page and method
		add_action('load-revision.php', array($this, 'drp_revision'));		
		// add action to deal with post deletion
		add_action('publish_to_trash', array($this, 'post_deletion'));
	}
	
	// add some neccessarily evil js to post.php and edit.php to help manage form data and submit actions
	public function add_js() {
		wp_register_script( 'drpjs', plugins_url( '/assets/drp.dev.js', __FILE__ ), '', '1.0', 'true' );
		wp_enqueue_script( 'drpjs' );
	}
	
	// load-revision.php callback to create our own custom diff page
	// mostly follows wp's revision.php
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
			$this->notices->add(array(
				'text' => '<strong>Whoops!</strong> Those posts aren\'t related.',
				'type' => 'error'
			));
			wp_redirect(get_edit_post_link($left->ID));
			exit();
		}
		
		// do the diff
		$differ = new DRP_Admin_Diff(&$parent, &$draft);
		$rev_fields = $differ->diff();
		
		require_once( './admin-header.php' );
		
		echo DRP_Mustache::render('diff', array(
			'left' => $parent,
			'right' => $draft,
			'rev_fields' => $rev_fields
		));
		
		require_once( './admin-footer.php' );
		// make sure the rest of default revision diff action doesn't happen
		exit();
	}
	
	// callback to post trashing action to warn user about active drafts
	public function post_deletion($post) {
		if ( ! $this->parent->has_draft($post->ID) ) return;
		
		$this->notices->add(array(
			'text' => DRP_Mustache::render('notice/_post_deletion', array('title' => $post->post_title)),
			'type' => 'error'
		));
	}
	
	// check post.php to see if we want to render some admin notices
	public function maybe_render_admin_notices() {
		global $post;
		global $pagenow;
		
		if ( 'post.php' != $pagenow ) return;

		// if it's a parent, warn the user about editing parents with drafts
		if ( $this->parent->has_draft($post->ID) ) {
			$this->notices->now(array(
				'text' => DRP_Mustache::render('notice/_active_drafts'), 
				'type' => 'error'
			));

		// if it's a draft, check to see if the parent's been updated more recently
		} else if ( $this->parent->is_draft($post->ID) ) {
			$parent = get_post($post->post_parent);
			
			if ( $parent->post_modified > $post->post_modified ) {
				$this->notices->now(array(
					'text' => DRP_Mustache::render('notice/_parent_post_updated', array(
						'post_type' => $parent->post_type,
						'right' => $parent->ID,
						'left' => $post->ID
					)),
					'type' => 'updated'
				));
			}
		}
	}
	
	// callback to add the options page to Setttings menu in wp-admin
	public function options_page() {
		add_options_page(
			'Drafts of Revisions Options',
			'Drafts of Revisions',
			'manage_options',
			'drp_options',
			array($this, 'render_options_page')
		);		
	}
	
	// render the options page. also handles updates to the plugin options via a POST request
	public function render_options_page() {		
		if ($_POST)
			$this->parent->update_option($_POST);
		
		$all_types = get_post_types();
		// remove attachment, revision, and nav_menu_item from the list of post type options
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

	// callback for add_meta_boxes to add a meta box to edit.php
	public function meta_boxes() {
		global $post;
		// if post is a draft, render the draft meta box
		if ( $this->parent->is_draft($post->ID) ) {
			$tpl = 'render_draft_meta_box';
			add_action('admin_print_scripts', array($this, 'add_js'));
		
		// if post is a parent, render the parent meta box
		} else if ($this->parent->can_have_drafts($post)) {
			$tpl = 'render_parent_meta_box';
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
	
	// render a meta box for a draft post's edit.php
	public function render_draft_meta_box($post) {
		$parent = get_post($post->post_parent);
		$parent->admin_link = get_edit_post_link($parent->ID, '&');
		echo DRP_Mustache::render('meta_box/_draft', array(
			'parent' => (array) $parent,
			'draft' => $post
		));
	}
	
	// render a meta box for a parent post's edit.php
	public function render_parent_meta_box($post) {
		$kids = get_children(array(
			'post_parent' => $post->ID,
			'post_status' => $this->parent->status_value
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
		echo DRP_Mustache::render('meta_box/_parent', array('kids' => $kids));
	}
	
}