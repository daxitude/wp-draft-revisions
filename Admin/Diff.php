<?php
/*
 * performs a diff between 2 posts including on taxonomies and meta
 */
class DPR_Admin_Diff {
	
	// the left and right sides of the comparison (each a Post)
	private $left;
	private $right;
	// fields to add to the comparison
	private $fields_to_add = array(
		'menu_order' => 'Menu Order',
		'comment_status' => 'Comment Status',
		'ping_status' => 'Ping Status',
		'post_password' => 'Post Password',
		'post_name' => 'Post Name'
	);
	
	// meta keys not to include in the comparison
	private $dont_include_meta = array( '_edit_last', '_edit_lock' );

	// store the parents attachments
	private $parents_attachments = false;
	
	// left is always the parent
	public function __construct(&$left, &$right) {
		$this->left = &$left;
		$this->right = &$right;
		
		$this->setup_post_meta($this->left);
		$this->setup_post_meta($this->right);
		$this->setup_post_taxonomies($this->left);
		$this->setup_post_taxonomies($this->right);
		$this->setup_post_attachments($this->left, true);
		$this->setup_post_attachments($this->right);
		
		add_filter('_wp_post_revision_fields', array($this, 'add_rev_fields'));
	}
	
	// setup post meta to be diffed

	private function setup_post_meta($post) {
		$meta = get_post_custom($post->ID);
		
		foreach( $meta as $key => $value ) {
			if ( in_array($key, $this->dont_include_meta) ) continue;
			$post->$key = $value[0];
			$this->fields_to_add[$key] = $key;
		}
	}
	
	// setup post taxonomies to be diffed
	private function setup_post_taxonomies($post) {
		$taxis = get_object_taxonomies( get_post_type($post->ID) );
		$all = array();

		foreach ($taxis as $tax) {
			$terms = wp_get_object_terms( $post->ID, $tax );
			$term_names = array_map(create_function('$term', 'return $term->name;'), $terms);
			$post->$tax = join(', ', $term_names);
			$this->fields_to_add[$tax] = $tax;
		}
	}
	
	// attachments don't copy from parent to draft and remain when a draft is published,
	// so for comparisons we'll symbolically show the parent attachments on the draft post
	private function setup_post_attachments($post, $parent = false) {
		$attachments = get_posts(array(
			'post_type' => 'attachment',
			'posts_per_page' => -1,
			'post_parent' => $post->ID,
		));
				
		if ( $parent )
			$this->parents_attachments = $attachments;
		else if( $this->parents_attachments )
			$attachments = array_merge($attachments, $this->parents_attachments);

		if (!$attachments) return;

		$key = 'Attachments';
		$this->fields_to_add[$key] = $key;
		$post->key = '';
		
		foreach ( $attachments as $attachment ) {
			$src = wp_get_attachment_image_src( $attachment->ID, 'thumbnail', true );
			$edit_link = get_edit_post_link($attachment->ID);
			$post->$key .= $src[0] . "\n";
		}
	}
	
	// callback to add fields to diff. will be returned on call to _wp_post_revision_fields()
	public function add_rev_fields($fields) {
		$fields += $this->fields_to_add;
		return $fields;
	}
	
	// follows the core functionality in wp's native diff method
	public function diff() {
		// make sure the keys are set on $left and $right so we don't get any undefined errors
		$field_keys = array_fill_keys($this->fields_to_add, null);
		$left = array_merge($field_keys, (array) $this->left);
		$right = array_merge($field_keys, (array) $this->right);
		
		$identical = true;
		$rev_fields = array();
		foreach ( _wp_post_revision_fields() as $field => $field_title ) {
			$left_content = apply_filters( "_wp_post_revision_field_$field", $left[$field], $field );
			$right_content = apply_filters( "_wp_post_revision_field_$field", $right[$field], $field );
			
			if ( !$content = wp_text_diff( $left_content, $right_content ) )
				continue; // There is no difference between left and right
			$identical = false;
			$rev_fields[] = array(
				'field' => $field,
				'title' => $field_title,
				'content' => $content
			);
		}

		return $identical ? false : $rev_fields;
	}
	
}