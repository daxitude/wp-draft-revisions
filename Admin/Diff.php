<?php
/*
 * performs a diff between 2 posts including on taxonomies and meta
 */
class DPR_Admin_Diff {
	
	private $left;
	private $right;
	private $fields_to_add = array(
		'menu_order' => 'Menu Order',
		'comment_status' => 'Comment Status',
		'ping_status' => 'Ping Status',
		'post_password' => 'Post Password',
		'post_name' => 'Post Name'
	);
	private $dont_include_meta = array( '_edit_last', '_edit_lock' );
	
	// left is always the parent
	public function __construct(&$left, &$right) {
		$this->left = &$left;
		$this->right = &$right;
		
		$this->setup_post_meta($this->left);
		$this->setup_post_meta($this->right);
		$this->setup_post_taxonomies($this->left);
		$this->setup_post_taxonomies($this->right);
		
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