<?php
/*
Plugin Name: Drafts of Revisions
Description: Create drafts of WordPress posts/pages/CPTs even after they've been published
Version: 0.3
Author: daxitude
Author URI: http://github.com/daxitude/
Plugin URI: @TODO
*/

require_once('core.php');

// register an autoloader. automatically requires files matching a class
// when the class is first used. files must start from the plugin's admin base path
// underscores in class names correspond to folder changes.
// eg DRP_Admin_Diff = draft-revisions/Admin/Diff (case sensitive)
spl_autoload_register(array('Draft_Revisions_Plugin', 'autoloader'));

new Draft_Revisions_Plugin();