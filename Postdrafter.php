<?php
/*
 * Handles creating drafts and merging them on publish
 */
class DPR_Postdrafter {

	// custom status value that gets registered and assigned to draft posts
	public static $status_value = 'dpr_draft';
	// the fields to copy from published post to new draft
	private $post_fields = array(
		'post_title', 'post_content', 'post_excerpt', 'post_type', 'comment_status',
		'ping_status', 'post_password', 'pinged', 'menu_order', 'post_mime_type', 'comment_count'
	);
	
	public function __construct(&$parent) {
		$this->parent = &$parent;
		// register the custom post status
		add_action('init', array(__CLASS__, 'add_post_status'), 2);
		// delete all drafts when a parent gets hard deleted
		add_action('deleted_post', array($this, 'parent_deleted'));		
	}
	
	public function get_status_val() {
		return self::$status_value;
	}
	
	// register the post status
	// @protected - see wp-includes/query.php line 2684. this enables previews. Ln2659 posts_results
	public static function add_post_status() {
		register_post_status(self::$status_value, array(
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
		
	// add support for a post type
	public function add_post_type_support($post_type) {
		$types = $this->get_permitted_post_types();
		$types[] = $post_type;
		$this->parent->update_option(array( 'post_types' => $types ));
	}
	
	// gets the post types allowed for drafts as defined by the user in the options screen
	public function get_permitted_post_types() {
		$types = $this->parent->get_option('post_types');
		return $types;
	}
	
	// checks to see whether a post type is supported
	public function post_type_is_supported($post_type) {
		$types = (array) $this->get_permitted_post_types();
		return in_array($post_type, $types);
	}
	
	// create a draft of a published post
	public function create_draft($id) {
		$parent = get_post($id);
					
		$author = wp_get_current_user()->ID;
		
		if ( ! $this->can_have_drafts($parent) )
			return false;

		// so far so good, but don't let the draft have the same post_name. that might create
		// wackiness in some queries
		$data = array(
			'post_status' => $this->get_status_val(),
			'post_author' => $author,
			'post_parent' => $parent->ID,
			'post_name' => $parent->post_name . ' draft'
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
	
	// publishes a draft by merging changes back into the parent post and deleting the
	// draft post (hard delete)
	public function publish_draft($post) {
		$parent = get_post($post->post_parent);
		
		// don't want to copy these back to the parent post
		unset($post->post_name, $post->guid, $post->post_parent, $post->post_date, $post->post_date_gmt);
		
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
				
		// if everything was successful, we can delete the draft in question, true to force hard delete
		// other drafts aren't deleted. maybe the user still wants them
		wp_delete_post($post->ID, true);
		
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
			'post_status' => self::$status_value
		));
		
		return !!$kids;
	}
	
	// conditional check to see if a post id is a draft
	public function is_draft($id) {
		return get_post($id)->post_status == self::$status_value;
	}
	
	public function get_drafts($parent_id) {
		$kids = get_children(array(
			'post_parent' => $parent_id,
			'post_status' => self::$status_value
		));
		
		return $kids;
	}
	
	// post deletion callback to delete any drafts when a parent is (hard) deleted
	// Admin.php provides an admin notice when a parent is moved to the trash
	public function parent_deleted($parent_id) {
		if ( !$this->has_draft($parent_id) ) return;
		
		$kids = get_children(array(
			'post_parent' => $parent_id,
			'post_status' => $this->get_status_val()
		));
		foreach ($kids as $kid) {
			wp_delete_post($kid->ID, true); // true to force delete
		}
	}

}

