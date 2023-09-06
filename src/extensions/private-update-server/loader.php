<?php namespace IFWP_Pro;

final class Private_Update_Server extends \__Singleton {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	static public function _extensions($extensions){
		$dir = plugin_dir_path(__FILE__);
		$dirname = wp_basename($dir);
		$extension = __canonicalize($dirname);
		$extensions[$extension] = 'Private update server';
		return $extensions;
	}

	/**
	 * @return void
	 */
	static public function register_extension(){
		__add_plugin_filter('extensions', [__CLASS__, '_extensions']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public function load(){

	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
