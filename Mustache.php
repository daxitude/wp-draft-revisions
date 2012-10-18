<?php
/*
 * singleton-ish class for passing data to Mustache to render
 * 
 */
abstract class DPR_Mustache {

	private static $engine;
	private static $dir;

	public static function init() {
		if ( !class_exists( 'Mustache_Autoloader' ) )
			require dirname(__FILE__) . '/Mustache/Autoloader.php';
		
		Mustache_Autoloader::register();
		
		self::$dir = dirname(__FILE__) . '/Admin/templates';
		$m_opts = array('extension' => 'html');
		
		self::$engine = new Mustache_Engine(
			array(
				'loader' => new Mustache_Loader_FilesystemLoader(self::$dir, $m_opts),
				'partials_loader' => new Mustache_Loader_FilesystemLoader(self::$dir, $m_opts),
				'helpers' => array(
					'format_date' => array(__CLASS__, 'helper_format_date'),
					'edit_link' => array(__CLASS__, 'helper_edit_link'),
					'permalink' => array(__CLASS__, 'helper_permalink')
				)
			)
		);
	}

	public static function render( $template, $data = null) {
		return self::$engine->render( $template, $data );
	}
	
	public static function template_path() {
		return self::$dir;
	}
	
	public static function helper_edit_link($id, $mustache) {
		return get_edit_post_link($mustache->render($id));
	}
	
	public static function helper_permalink($id, $mustache) {
		return get_permalink($mustache->render($id));
	}
		
	// formatter for dates
	public static function helper_format_date($date, $mustache) {
		// silly php bug strtotime not returning false for '0000..' mysql null val
		$date = strtotime($mustache->render($date));
		return $date > 0 ? date('D, M d g:h a', $date) : '--';
	}
	

}

DPR_Mustache::init();
