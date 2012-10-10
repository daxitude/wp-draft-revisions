<?php
/*
 * Main plugin class
 */
class Draft_Revisions_Plugin {
	// plugin version number
	public static $version = 0.5;
	// custom status value that gets registered and assigned to draft posts
	public $status_value = 'drp_draft';
	// key for wp_options to store plugin options
	private static $options_key = 'drp_options';
	// array of permitted options
	private $options;
	// internal array for storing retrieved options values
	private $_options;
	// text used on the button on post.php to initiate creation of a draft
	public static $draft_text = 'Save a Draft';	
	// the fields to copy from published post to new draft
	private $post_fields = array('post_title', 'post_content', 'post_excerpt');

	
	public function __construct() {
		// default options
		$this->options = array(
			'version' => self::$version,
			'post_types' => array('post', 'page')
		);
		// install if new, update if version has changed
		$this->install_or_update();		
		// register the custom post status
		add_action('init', array($this, 'add_post_status'), 2);
		// hook into pre_post_update to create new draft
		add_action('pre_post_update', array($this, 'route_create'), 1);		
		// hook into post status transition to publish a draft post
		add_action('drp_draft_to_publish', array($this, 'route_publish'));
		// delete all drafts when a parent gets hard deleted
		add_action('deleted_post', array($this, 'parent_deleted'));
		
		if (is_admin())
			$this->admin_init();		
	}
	
	// register the post status
	// @protected - see wp-includes/query.php line 2684. this enables previews. Ln2659 posts_results
	public function add_post_status() {
		register_post_status($this->status_value, array(
			'label' => 'Draft Revision',
			'public' => false,
			'protected' => true,
			'exclude_from_search' => true,
			'show_in_admin_all_list' => false,
			'show_in_admin_status_list' => true,
			'label_count' => _n_noop( 'Draft Revisions <span class="count">(%s)</span>',
				'Draft Revisions <span class="count">(%s)</span>' )
		) );
	}
	
	// initialize the admin class
	private function admin_init() {
		$this->admin = new DRP_Admin(&$this);
	}
	
	// add support for a post type
	public function add_post_type_support($post_type) {
		$types = $this->get_permitted_post_types();
		$types[] = $post_type;
		$this->update_option(array( 'post_types' => $types ));
	}
	
	// gets the post types allowed for drafts as defined by the user in the options screen
	public function get_permitted_post_types() {
		$types = $this->get_option('post_types');
		return $types;
	}
	
	// checks to see whether a post type is supported
	public function post_type_is_supported($post_type) {
		$types = (array) $this->get_permitted_post_types();
		return in_array($post_type, $types);
	}

	// routes a request to create a new draft
	public function route_create($id) {
		global $post;	
		// don't do anything if an autosave
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return $id;

		// if we got this far, check user permissions
		
		// check to see if we are saving a new draft
		if ( isset($_POST['save']) && $_POST['save'] == self::$draft_text ) {
			$draft_id = $this->create_draft($id);
			wp_redirect( get_edit_post_link($draft_id, '&') );
			exit();

		} else {
			return $id;
		}
		
	}
	
	// create a draft of a published post
	public function create_draft($id) {
		$parent = get_post($id);
					
		$author = wp_get_current_user()->ID;
		
		if ( ! $this->can_have_drafts($parent) )
			return false;

		// so far so good	
		$data = array(
			'post_status' => $this->status_value,
			'post_author' => $author,
			'post_parent' => $parent->ID,
			'post_type' => $parent->post_type
		);
		
		// turn $this->post_fields into array with keys
		$fields = array_fill_keys($this->post_fields, null);
		// intersect with $post
		$post_data = array_intersect_key((array) $parent, $fields);
		// combine the arrays
		$data += $post_data;
		// insert the post
		$draft_id = wp_insert_post($data);

		// return false, let the view display an error
		if ( !$draft_id )
			return false;
		
		// also copy all the meta. featured images are stored in here so this should
		// take care of copying the featured image
		$this->transfer_post_meta($parent->ID, $draft_id);
		// copy all taxonomies
		$this->transfer_post_taxonomies($parent->ID, $draft_id);
		
		// don't worry about copying attachments here
		// on draft publish we'll do a merge of all attachments
				
		// no xfer of comments, those stay with the original
		
		return $draft_id;
	}
	
	// route a request to publish a draft
	public function route_publish($post) {
		if ( wp_is_post_revision($post) )
			return false;
		
		$pub_id = $this->publish_draft($post);
		wp_redirect( get_edit_post_link($pub_id, '&') );
		exit();
	}
	
	// publishes a draft by merging changes back into the parent post and deleting the
	// draft post (hard delete)
	public function publish_draft($post) {
		$parent = get_post($post->post_parent);
		
		$data = array_merge((array) $parent, (array) $post);
		$data['post_status'] = 'publish';
		$data['ID'] = $parent->ID;
		
		$published_id = wp_update_post($data);
		
		// return false, let the view display an error
		if ( !$published_id )
			return false;
		
		// copy any meta over as well. should take care of featured image
		$this->transfer_post_meta($post->ID, $parent->ID, 'update');
		// copy all taxonomies
		$this->transfer_post_taxonomies($post->ID, $parent->ID);

		// copy attachments back to the parent
		$this->transfer_post_attachments($post->ID, $parent->ID);
				
		// if everything was successful, we can delete the draft post, true to force hard delete
		if ( ! $deleted = wp_delete_post($post->ID, true) )
			// need to pass thru some error message?
		
		return $published_id;
	}
	
	// transfers post meta from one post to another
	// @TODO error checking
	private function transfer_post_meta($from_id, $to_id, $method = 'add') {
		$all_meta = get_post_custom($from_id);

		foreach ($all_meta as $key => $value) {
			$method == 'add' ?
				add_post_meta($to_id, $key, $value[0]) :
				update_post_meta($to_id, $key, $value[0]);
		}
	}
	
	// transfers post taxonomies from one post to another
	// @TODO error checking
	private function transfer_post_taxonomies($from_id, $to_id) {
		$taxis = get_object_taxonomies( get_post_type($from_id) );

		foreach ($taxis as $tax) {
		    $terms = wp_get_object_terms( $from_id, $tax );
		    $term = array();
		    foreach ($terms as $t) {
		        $term[] = $t->slug;
		    } 

		    wp_set_object_terms( $to_id, $term, $tax, false );
		}
	}
	
	// transfers post attachments from one post to another
	// this is only called on a publish action to copy any new attachments
	// from the draft back to the parent
	private function transfer_post_attachments($from_id, $to_id) {
		$attachments = get_children(
			array( 'post_parent' => $from_id, 'post_type' => 'attachment', 'numberposts' -1 )
		);
		
		if ( !$attachments ) return;
		
		foreach ($attachments as $pic) {
			$pic->post_parent = $to_id;
			wp_update_post($pic);
		}
	}
	
	// gets all taxonomies associated with a given post type
	public function get_all_post_taxonomies($id) {
		$taxis = get_object_taxonomies( get_post_type($id) );
		$all = array();

		foreach ($taxis as $tax) {
			$terms = wp_get_object_terms( $id, $tax );
			$all[$tax] = array();
			foreach ($terms as $t) {
				$all[$tax][] = $t->term_id;
			}
		}
		return $all;
	}
	
	public function can_have_drafts($parent) {
		return $this->post_type_is_supported($parent->post_type) &&
			in_array($parent->post_status, array('publish', 'private'));
	}
	
	// conditional check to see if a post id has drafts
	public function has_draft($id) {
		$kids = get_children(array(
			'post_parent' => $id,
			'post_status' => $this->status_value
		));
		
		return !!$kids;
	}
	
	// conditional check to see if a post id is a draft
	public function is_draft($id) {
		return get_post($id)->post_status == $this->status_value;
	}
	
	// post deletion callback to delete any drafts when a parent is (hard) deleted
	// Admin.php provides an admin notice when a parent is moved to the trash
	public function parent_deleted($parent_id) {
		if ( !$this->has_draft($parent_id) ) return;
		
		$kids = get_children(array(
			'post_parent' => $parent_id,
			'post_status' => $this->status_value
		));
		foreach ($kids as $kid) {
			wp_delete_post($kid->ID, true); // true to force delete
		}
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

		} else if ($saved_ver !== self::$version) {
			// do some update stuff here
			$this->update_option(array('version' => self::$version));
		}			
	}
	
	// uninstall callback
	public static function uninstall() {
		delete_option(self::$options_key);
	}
	
	// autoloader for DRP-prefixed classes
	public static function autoloader( $class ) {
        if ( strpos($class, 'DRP') !== 0 ) {
            return;
        }
        $file = dirname(__FILE__) . '/' . str_replace('_', DIRECTORY_SEPARATOR, substr($class, 4)) . '.php';

        if ( file_exists($file) ) {
            require_once $file;
        }
    }
	
}

