<?php
/*
Author: Luis del Cid
Author URI: https://luisdelcid.com/
Description: A collection of professional improvements and fixes for WordPress.
Domain Path:
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Network: true
Plugin Name: IFWP Pro
Plugin URI: https://ifwp.pro/
Requires at least: 5.6
Requires PHP: 5.6
Text Domain: ifwp-pro
Version: 0.9.5.1
*/

defined('ABSPATH') or die('Hi there! I\'m just a plugin, not much I can do when called directly.');
add_action('plugins_loaded', function(){
    if(!did_action('magic_loaded')){
        return;
    }
    __plugin_update_check('https://github.com/luisdelcid/ifwp-pro', __FILE__);
    require_once(plugin_dir_path(__FILE__) . 'src/php/loader.php');
    IFWP_Pro\Loader::load(__FILE__);
});
