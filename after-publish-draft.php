<?php
/*
Plugin Name: After Publish Draft
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
// eg ABT_Model_Base = abtforwp/admin/Model/Base (case sensitive)
spl_autoload_register(array('After_Publish_Draft', 'autoloader'));

new After_Publish_Draft();