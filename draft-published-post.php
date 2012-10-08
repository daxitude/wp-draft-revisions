<?php
/*
Plugin Name: Drafts of Published Posts
Description: Make drafts of posts even after they've been published!
Version: 0.1
Author: daxitude
Author URI: http://github.com/daxitude/
Plugin URI: @TODO
*/

require_once('core.php');

// register an autoloader. automatically requires files matching a class
// when the class is first used. files must start from the plugin's admin base path
// underscores in class names correspond to folder changes.
// eg DPP_Model_Base = abtforwp/admin/Model/Base (case sensitive)
spl_autoload_register(array('Draft_Published_Post', 'autoloader'));

new Draft_Published_Post();