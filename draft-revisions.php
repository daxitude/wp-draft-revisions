<?php
/*
Plugin Name: Drafts of Post Revisions
Description: Create drafts of WordPress posts/pages/CPTs even after they've been published
Version: 0.8
Author: daxitude
Author URI: http://github.com/daxitude/
Plugin URI: http://github.com/daxitude/wp-draft-revisions
*/

require_once('core.php');

// register an autoloader. automatically requires files matching a class
// when the class is first used. files must start from the plugin's admin base path
// underscores in class names correspond to folder changes.
// eg DPR_Admin_Diff = draft-revisions/Admin/Diff (case sensitive)
spl_autoload_register(array('Draft_Post_Revisions', 'autoloader'));

if ( is_admin() )
	new Draft_Post_Revisions();
else
	// do we need to add post status on a public page?
	// probably, just in case there's a query in use with something like post_status => 'any'
	Draft_Post_Revisions::add_post_status();