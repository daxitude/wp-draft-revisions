<?php

class Draft_Published_Post {
	
	public static $version = 0.2;
	
	public $status_value = 'apd_draft';
	
	private static $options_key = 'apd_options';
	private $options;
	private $_options;
	
	public static $draft_text = 'Save a Draft';
	
	// the fields to copy back and forth
	private $post_fields = array('post_title', 'post_content', 'post_excerpt');
	
	public function __construct() {
		$this->options = array(
			'version' => self::$version,
			'post_types' => array('post', 'page')
		);
		
		$this->install_or_update();
		
		// register the custom post status
		add_action('init', array($this, 'add_post_status'), 2);
		// does post type have to support revisions?
		add_action('pre_post_update', array($this, 'route_create'), 1);		
		add_action('apd_draft_to_publish', array($this, 'route_publish'));
				
		// add action to deal with post deletion
		// add action to deal with post update while a draft is out there?
		
		if (is_admin())
			$this->admin_init();
		
	}
	
	public function add_post_status() {
		register_post_status($this->status_value, array(
			'label' => 'Revision Draft',
			'public' => false,
			'exclude_from_search' => true,
			'show_in_admin_all_list' => false,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop( 'Drafts of Published <span class="count">(%s)</span>',
				'Drafts of Published <span class="count">(%s)</span>' )
		) );
	}
	
	private function admin_init() {
		$this->admin = new DPP_Admin(&$this);
	}
	
	// add support for a post type
	public function add_post_type_support($post_type) {
		$types = $this->get_permitted_post_types();
		$types[] = $post_type;
		$this->update_option(array( 'post_types' => $types ));
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

	// 
	public function route_create($id) {
		global $post;	
		// don't do anything if an autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return $id;

		// if we got this far, check user permissions
		
		// check to see if we are saving a new draft
		if ( isset($_POST['save']) && $_POST['save'] == self::$draft_text ) {
			$draft_id = $this->create_draft($id);
			wp_redirect(admin_url('post.php?action=edit&post=' . $draft_id));
			exit();

		} else {
			return $id;
		}
		
	}
	
	// create a draft of a published post
	public function create_draft($id) {		
		$post = get_post($id);
					
		$author = wp_get_current_user()->ID;
		
		if ( ! $this->post_type_is_supported($post->post_type) ) {
			// should we add a flash msg here?
			return false;
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
		
		return $draft_id;
	}
	
	public function route_publish($post) {
		if ( wp_is_post_revision($post) )
			return false;
		
		$pub_id = $this->publish_draft($post);
		wp_redirect(admin_url('post.php?action=edit&post=' . $pub_id));
		exit();
	}
	
	public function publish_draft($post) {				
		$parent = get_post($post->post_parent);
		
		$data = array_merge((array) $parent, (array) $post);
		$data['post_status'] = 'publish';
		$data['ID'] = $parent->ID;
		
		$published_id = wp_update_post($data);
		
		// return false, let the view display an error
		if ( !$published_id )
			return false;
		
		// copy any meta over as well?
		// copy attachments over, too
		// copy featured image
		// copy all taxonomies
				
		// if everything was successful, we can delete the draft post, true to force hard delete
		if ( ! $deleted = wp_delete_post($post->ID, true) )
			// need to pass thru some error message?
			new DPP_Admin_Notice('zoinks!');
		
		return $published_id;
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
		return get_post($id)->post_status == $this->status_value;
	}
	
	private function meta_has_changed() {
		
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
        if ( strpos($class, 'DPP') !== 0 ) {
            return;
        }
        $file = dirname(__FILE__) . '/' . str_replace('_', DIRECTORY_SEPARATOR, substr($class, 4)) . '.php';

        if ( file_exists($file) ) {
            require_once $file;
        }
    }
	
}

