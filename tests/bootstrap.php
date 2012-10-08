<?php
/**
 * Bootstrap the testing environment
 * Uses wordpress tests (http://github.com/nb/wordpress-tests/) which uses PHPUnit
 * @package wordpress-plugin-tests
 *
 * Usage: change the below array to any plugin(s) you want activated during the tests
 *        value should be the path to the plugin relative to /wp-content/
 *
 * Note: Do note change the name of this file. PHPUnit will automatically fire this file when run.
 *
 */

$path = dirname(__FILE__) . '/../../../../wordpress-tests/bootstrap.php';
 
if (file_exists($path)) {
        $GLOBALS['wp_tests_options'] = array(
			'active_plugins' => array( 'draft-published-post/draft-published-post.php' )
        );
        require_once $path;
} else {
        exit("Couldn't find wordpress-tests/bootstrap.php\n");
}