<?php

class After_Publish_Draft {
	
	public static $version = 0.2;
	
	private $status_value = 'apd_draft';
	
	private static $options_key = 'apd_options';
	private $options;
	private $_options;
	
	public static $status_name = 'Save a Draft';
	
	// the fields to copy back and forth
	private $post_fields = array('post_title', 'post_content', 'post_excerpt');
	
	public function __construct() {
		$this->options = array(
			'version' => self::$version,
			'post_types' => array('post', 'page')
		);
		
		$this->install_or_update();
		
		// hook into all queries to remove any drafts from the results
//		add_filter( 'pre_get_posts', array($this, 'filter_drafts') );
		// does post type have to support revisions?
		add_action('pre_post_update', array($this, 'create_draft'), 1);
		// register the custom post status
		add_action('init', array($this, 'add_post_status'));
		
		// add action to deal with post deletion
		// add action to deal with post update?
		
		if (is_admin())
			$this->admin_init();
	}
	
	public function add_post_status() {
		register_post_status($this->status_value, array(
			'label' => 'Draft Revision',
			'public' => false,
			'exclude_from_search' => true,
			'show_in_admin_all_list' => false,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop( 'Draft Revisions <span class="count">(%s)</span>',
				'Draft Revisions <span class="count">(%s)</span>' )
		) );
	}
	
	private function admin_init() {
		$this->admin = new APD_Admin(&$this);
	}
	
	// filter all queries to remove the draft posts
	public function filter_drafts($query) {
//		$draft_ids = (array) $this->get_option('draft_ids');
		// pass some ids along if post__not_in is already set on the query
//		$already = $query->get('post__not_in');
		
		// list_of_drafts custom set on get_children call on admin page
//		if ( !$query->is_singular && !$query->get('list_of_drafts') )
//			$query->set( 'post_status', $this->status_value );
		
		print_r($query->get('post_status'));
	}
	
	// add support for a post type
	public function add_post_type_support($post_type) {
		$types = $this->get_permitted_post_types();
		$this->update_option(array( 'post_types' => $types + $post_type ));
	}
	
	// gets the post types allowed for drafts as defined by the user
	public function get_permitted_post_types() {
		$types = $this->get_option('post_types');
		return $types;
	}
	
	// checks to see whether a post type is supported
	public function post_type_is_supported($post_type) {
		$types = (array) $this->get_permitted_post_types();
		return in_array($post_type, $types);
	}
	
	// create a draft of a published post
	public function create_draft($id) {
		global $post;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return $id;
		
		if ( !isset($_POST['save']) || $_POST['save'] != self::$draft_text )
			return $id;
		
		// check user permissions?
					
		$author = wp_get_current_user()->ID;
		
		if ( ! $this->post_type_is_supported($post->post_type) ) {
			// should we add a flash msg here?
			return $id;
		}

		// so far so good	
		$data = array(
			'post_status' => $this->status_value,
			'post_author' => $author,
			'post_parent' => $post->ID
		);
		
		// turn $this->post_fields into array with keys
		$fields = array_fill_keys($this->post_fields, null);
		// intersect with $post
		$post_data = array_intersect_key((array) $post, $fields);
		// combine the arrays
		$data += $post_data;
		// insert the post
		$draft_id = wp_insert_post($data);

		// return false, let the view display an error
		if ( !$draft_id )
			return false;
		
		// also copy all the meta
		// copy attachments over, too
		// copy featured image
		// copy all taxonomies
		
		// add it to the list to filter out
//		$this->add_draft_id($draft_id);
		
		wp_redirect(admin_url('post.php?action=edit&post=' . $draft_id));
		exit();
	}
	
	public function publish_draft() {
		global $post;
		
		$parent = get_parent($post->ID);
		
		// copy any meta over as well?
		
		// copy attachments over, too
		// copy featured image
		// copy all taxonomies
		
		wp_update_post($data);
		
//		$this->remove_draft_id($post->ID);
		
	}
	
	public function deleted_post() {
		
	}
	
	public function has_draft($id) {
		$kids = get_children(array(
			'post_parent' => $id,
			'post_status' => $this->status_value
		));
		
		return !!$kids;
	}
	
	public function is_draft($id) {
//		$ids = $this->get_draft_ids();

		

		return in_array($id, $ids);
	}
	
	private function meta_has_changed() {
		
	}

/*	
	public function get_draft_ids() {
		return (array) $this->get_option('draft_ids');
	}

	
	private function add_draft_id($id) {
		// an array of the ids
		$ids = $this->get_draft_ids();
		array_push($ids, $id);
		$this->update_option( array('draft_ids' => $ids) );
	}
	
	private function remove_draft_id($id) {
		$ids = $this->get_option('draft_ids');
		// remove the $id from the array
		unset($ids[$id]);
		$this->update_option( array('draft_ids' => $ids) );
	}
*/	
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
	
	public function install_or_update() {
		$saved_ver = $this->get_option('version');
		if (!$saved_ver) {
			$this->add_option(self::$options_key, $this->options);
		}
		else if ($saved_ver !== self::$version) {
			// do some update stuff here
			$this->update_option(array('version' => self::$version));
		}			
	}
	
	public static function uninstall() {
		delete_option(self::$options_key);
	}
	
	// autoloader
	public static function autoloader( $class ) {
        if ( strpos($class, 'APD') !== 0 ) {
            return;
        }
        $file = dirname(__FILE__) . '/' . str_replace('_', DIRECTORY_SEPARATOR, substr($class, 4)) . '.php';

        if ( file_exists($file) ) {
            require_once $file;
        }
    }
	
}

