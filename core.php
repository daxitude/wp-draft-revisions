<?php
/*
 * Main plugin class
 * handles actions in the wp-admin
 */
class Draft_Post_Revisions {
	
	// plugin version number
	public static $version = "0.8.1";
	// key for wp_options to store plugin options
	private static $options_key = 'dpr_options';
	// array of permitted options
	private $options;
	// internal array for storing retrieved options values
	private $_options;
	
	// instances of helping classes
	public $drafter;
	private $notices;
	// text used on the button on post.php to initiate creation of a draft
	public static $draft_text = 'Save a Draft';
	
	public function __construct() {
		global $pagenow;		

		// default options
		$this->options = array(
			'version' => self::$version,
			'post_types' => array('post', 'page')
		);
		// install if new, update if version has changed
		$this->install_or_update();
		
		// flash-y wp admin notices
		$this->notices = new DPR_Admin_Notice();
		// Postdrafter handles creating and merging drafts
		$this->drafter = new DPR_Postdrafter($this);

		// hook into pre_post_update to create new draft
		add_action('pre_post_update', array($this, 'route_create'), 1);		
		// hook into post status transition to publish a draft post
		add_action('dpr_draft_to_publish', array($this, 'route_publish'));
		// admin menu page to set allowable post types
		add_action('admin_menu', array($this, 'options_page'));
		// check to see if we want to display any admin notices on post.php
		add_action('admin_head', array($this, 'maybe_render_admin_notices'));
		// add meta boxes
		add_action('add_meta_boxes', array($this, 'meta_boxes'));
		
		if ('edit.php' == $pagenow)
			add_action('admin_print_scripts', array($this, 'add_js'));
		
		if ('post.php' == $pagenow && isset($_GET['dpr_published']) && $_GET['dpr_published'])
			add_action('admin_footer-post.php', array($this, 'add_autosave_cancel_js'), 999999);	
		
		// custom revision page and method
		add_action('load-revision.php', array($this, 'dpr_revision'));		
		// add action to deal with post deletion
		add_action('publish_to_trash', array($this, 'post_deletion'));
		
		// load language files
		add_action('init', function() { 
				load_plugin_textdomain( 'drafts-of-post-revisions', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		});
		
	}
	
	// proxy to Postdrafter's static add post status method. this is the only thing we do
	// on a public init
	public static function add_post_status() {
		DPR_Postdrafter::add_post_status();
	}
	
	// add some neccessarily evil js to post.php and edit.php to help manage form data and submit actions
	public function add_js() {
		wp_register_script( 'dprjs', plugins_url( '/assets/dpr.dev.js', __FILE__ ), '', '1.0', 'true' );
		wp_enqueue_script( 'dprjs' );
	}
	
	// this is a hack to keep wp autosave from finding different post_content in local storage and
	// subsequently displaying an admin notice
	public function add_autosave_cancel_js() {
		echo '<script>wp.autosave.local.setData(false); wp.autosave.local.checkPost();</script>';
	}
		
	// routes a request to create a new draft
	public function route_create($id) {
		// don't do anything if an autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return $id;
		
		// check to see if we are saving a new draft
		// we are checking the value (= displayed name) of the save button. This value
		// is different in other languages so we have to translate self::$draft_text
		// not a very nice solution, but it works for now.
		if ( isset($_POST['save']) && $_POST['save'] == __(self::$draft_text, 'drafts-of-post-revisions') ) {
			// check user permissions
			if ( ! current_user_can( 'edit_post', $id ) )
				wp_die(__('You don\'t have permission to edit this post.','drafts-of-post-revisions'));
			
			$draft_id = $this->drafter->create_draft($id);
			$redirect_id = $draft_id ? $draft_id : $id;
			// add error message
			if (!$draft_id) {
				$this->notices->add(array(
					'text' => __('<strong>Whoops!</strong> Failed to create the draft. Please try again.','drafts-of-post-revisions'),
					'type' => 'error'
				));
			}				
			wp_redirect( get_edit_post_link($redirect_id, '&') );
			exit();
		} else {
			return $id;
		}
		
	}
		
	// route a request to publish a draft. callback from post status transition action
	public function route_publish($post) {
		// check if is a quick edit so we don't redirect
		// seems like we could check post_view == list
		$ajax = ('inline-save' == $_POST['action']) ? true : false;
		
		if ( wp_is_post_revision($post) )
			return false;
		
		// check user permissions
		if ( ! current_user_can( 'edit_post', $post->ID ) )
			wp_die(__('You don\'t have permission to edit this post.','drafts-of-post-revisions'));

		$pub_id = $this->drafter->publish_draft($post);
		$redirect_id = $pub_id ? $pub_id : $post->ID;		
		// add error message
		if (!$pub_id) {
			$this->notices->add(array(
				'text' => __('<strong>Whoops!</strong> Failed to publish the draft. Please try again.','drafts-of-post-revisions'),
				'type' => 'error'
			));
		}
		
		if ($ajax)
			exit(DPR_Mustachio::render('post_edit_row', array('post' => get_post($pub_id))));
		
		wp_redirect( get_edit_post_link($redirect_id, '&') . "&dpr_published=true" );
		exit();
	}
	
	// load-revision.php callback to create our own custom diff page
	// mostly follows wp's revision.php
	public function dpr_revision() {
		if ( !isset($_GET['action']) || $_GET['action'] != 'dpr_diff' ) return;
		
		// a wp global to set the correct active menu item
		global $parent_file;
		
		require_once(ABSPATH . '/wp-admin/admin.php');

		$left = get_post($_GET['left']);
		$right = get_post($_GET['right']);

		if (
			! current_user_can( 'read_post', $left->ID ) || 
			! current_user_can( 'read_post', $right->ID )
		) {
			$this->notices->add(array(
				'text' => __('<strong>Whoops!</strong> You don\'t have permission to view these posts.', 'drafts-of-post-revisions'),
				'type' => 'error'
			));
			wp_redirect(admin_url('index.php'));
			exit();
		}

		// make sure draft is always on the right
		if ( $left->ID == $right->post_parent ) {
			$parent = $left;
			$draft = $right;

		} else if ( $right->ID == $left->post_parent ) {
			$parent = $right;
			$draft = $left;
		} else {
			$this->notices->add(array(
				'text' => __('<strong>Whoops!</strong> Those posts aren\'t related.', 'drafts-of-post-revisions'),
				'type' => 'error'
			));
			wp_redirect(get_edit_post_link($left->ID));
			exit();
		}
		
		// do the diff
		$differ = new DPR_Admin_Diff($parent, $draft);
		$rev_fields = $differ->diff();
		
		// wp global, sets up the correct menu item to be active
		$parent_file = 'edit.php';
		if ($parent->post_type != 'post')
			$parent_file .= '?post_type=' . $parent->post_type;

		
		require_once( './admin-header.php' );
		
		echo DPR_Mustachio::render('diff', array(
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
		if ( ! $this->drafter->has_draft($post->ID) ) return;
		
		$this->notices->add(array(
			'text' => DPR_Mustachio::render('notice/_post_deletion', array('title' => $post->post_title)),
			'type' => 'error'
		));
	}
	
	// check post.php to see if we want to render some admin notices
	public function maybe_render_admin_notices() {
		global $post;
		global $pagenow;

		if ( 'post.php' != $pagenow ) return;

		// if it's a parent, warn the user about editing parents with drafts
		if ( $this->drafter->has_draft($post->ID) ) {
			$this->notices->now(array(
				'text' => DPR_Mustachio::render('notice/_active_drafts'), 
				'type' => 'error'
			));

		// if it's a draft, check to see if the parent's been updated since the draft was created
		} else if ( $this->drafter->is_draft($post->ID) ) {
			$parent = get_post($post->post_parent);
			
			if ( $parent->post_modified > $post->post_date ) {
				$this->notices->now(array(
					'text' => DPR_Mustachio::render('notice/_parent_post_updated', array(
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
			'dpr_options',
			array($this, 'render_options_page')
		);		
	}
	
	// render the options page. also handles updates to the plugin options via a POST request
	public function render_options_page() {
		if ($_POST)
			$this->update_option($_POST);
		
		$all_types = get_post_types();
		// remove attachment, revision, and nav_menu_item from the list of post type options
		$all_types = array_filter(
			$all_types,
			create_function(
				'$item',
				'return !in_array($item, array(\'attachment\', \'revision\', \'nav_menu_item\'));'
			)
		);
		
		$supported = $this->drafter->get_permitted_post_types();
		
		foreach( $all_types as $key => $value ) {
			$all_types[$key] = array('name' => $value, 'supported' => in_array($value, $supported));
		}
			
		echo DPR_Mustachio::render('options_page', array(
			'all_types' => array_values($all_types)
		));
	}

	// callback for add_meta_boxes to add a meta box to edit.php
	public function meta_boxes() {
		global $post;

		// if post is a draft, render the draft meta box
		if ( $this->drafter->is_draft($post->ID) ) {
			$tpl = 'render_draft_meta_box';
			add_action('admin_print_scripts', array($this, 'add_js'));
		
		// if post is a parent, render the parent meta box
		} else if ($this->drafter->can_have_drafts($post)) {
			$tpl = 'render_parent_meta_box';
		} else {
			return;
		}
		
		add_meta_box(
			'dpr-draft',
			__('Drafts of Revisions', 'drafts-of-post-revisions'),
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
		echo DPR_Mustachio::render('meta_box/_draft', array(
			'parent' => (array) $parent,
			'draft' => $post
		));
	}
	
	// render a meta box for a parent post's edit.php
	public function render_parent_meta_box($post) {
		$kids = get_children(array(
			'post_parent' => $post->ID,
			'post_status' => $this->drafter->get_status_val()
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
		echo DPR_Mustachio::render('meta_box/_parent', array('kids' => $kids));
	}
	
	
	// add an option to wp_options for the first time
	public function add_option($option, $value, $autoload = 'yes') {
		return add_option($option, $value, '', $autoload);
	}
	
	// update an option. if the option is an array, you have to feed in the final array
	// already merged with any existing values
	// @options_n_values array of key/value pairs of options
	public function update_option( array $options_n_values) {
		$options = $this->get_option();
		// but don't want to add anything not permitted
		$value = array_merge($options, $options_n_values);
		$value = array_intersect_key($value, $this->options);
		$this->_options = $value;
		return update_option(self::$options_key, $value);
	}
	
	// get a plugin option
	public function get_option($option = null) {
		if ($this->_options) return $option ? $this->_options[$option] : $this->_options;
		$options = get_option( self::$options_key );
		if (!$options) return false;
		$this->_options = $options;
		return $option ? $options[$option] : $options;
	}
	
	// delete the option
	public static function delete_option($option = null) {
		return delete_option($option || self::$options_key);
	}
	
	// check current version against version stored in wp_options and perform
	// install or update as appropriate
	public function install_or_update() {
		$saved_ver = $this->get_option('version');
		if (!$saved_ver) {
			$this->add_option(self::$options_key, $this->options);

		} else if ($saved_ver !== self::$version) {
			// do some update stuff here
			$this->update_option(array('version' => self::$version));
		}			
	}
	
	public static function options_key() {
		return self::$options_key;
	}
	
	// uninstall callback
	public static function uninstall() {
		delete_option(self::$options_key);
	}
	
	// autoloader for DPR-prefixed classes
	public static function autoloader( $class ) {
        if ( strpos($class, 'DPR') !== 0 ) {
            return;
        }
        $file = dirname(__FILE__) . '/' . str_replace('_', DIRECTORY_SEPARATOR, substr($class, 4)) . '.php';

        if ( file_exists($file) ) {
            require_once $file;
        }
    }
	
}