<?php namespace IFWP_Pro;

final class Loader {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    private static $extensions = [];

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	public static function _mb_settings_pages($settings_pages){
		$tabs = __apply_plugin_filters('tabs', [
			//'extensions' => esc_html__('Extensions', 'meta-box-aio'),
            'extensions' => 'Improvements and fixes',
		]);
        $name = __plugin_meta('Name');
        $settings_pages[] = [
            'columns' => 1,
            'id' => __plugin_slug('ext'),
            'menu_title' => $name,
            'option_name' => __plugin_prefix('ext'),
			'page_title' => $name . ' &#8212; ' . __('Settings'),
            'parent' => 'options-general.php',
			'style' => 'no-boxes',
			'tab_style' => 'left',
            'tabs' => $tabs,
        ];
		return $settings_pages;
	}

	/**
	 * @return array
	 */
	public static function _rwmb_meta_boxes($meta_boxes){
		$fields = [];
		$options = __apply_plugin_filters('extensions', []);
		if($options){
			asort($options, SORT_NATURAL | SORT_FLAG_CASE);
			$options = array_map(function($option){
				$option = trim($option);
				return $option;
			}, $options);
			$fields[] = [
				'columns' => 12,
				'id' => 'extensions',
				//'name' => esc_html__('Available Extensions', 'meta-box-aio') . ' (' . count($options) . ')',
                'name' => 'Available improvements and fixes (' . count($options) . ')',
				'options' => $options,
				'select_all_none' => true,
				'type' => 'checkbox_list',
			];
		} else {
			$fields[] = [
				'columns' => 12,
				'std' => __('Not available') . '.',
				'type' => 'custom_html',
			];
		}
        $meta_boxes[] = [
            'fields' => $fields,
			'id' => __plugin_slug('mb'),
            'settings_pages' => __plugin_slug('ext'),
			'tab' => 'extensions',
            'title' => esc_html__('Extensions', 'meta-box-aio'),
        ];
		return $meta_boxes;
	}

	/**
	 * @return bool
	 */
	public static function is_extension_active($extension = ''){
        $extension = __canonicalize($extension);
        return in_array($extension, self::$extensions);
    }

	/**
	 * @return void
	 */
	public static function load($file = ''){
		if(!file_exists($file)){
			return;
		}
		$missing = [];
		if(!__is_plugin_active('meta-box/meta-box.php')){
			$missing[] = 'Meta Box';
		}
		if(!__is_plugin_active('meta-box-aio/meta-box-aio.php')){
			$missing[] = 'Meta Box AIO';
		}
		if($missing){
			$message = __plugin_meta('Name') . ' &#8212; ' . sprintf(__('Missing parameter(s): %s'), __implode_and($missing)) . '.';
			__add_admin_notice($message);
			return;
		}
		$option = __plugin_prefix('ext');
		$value = (array) get_option($option, []);
		self::$extensions = isset($value['extensions']) ? $value['extensions'] : [];
		foreach(glob(plugin_dir_path($file) . 'src/extensions/*', GLOB_ONLYDIR) as $dir){
			$file = trailingslashit($dir) . 'loader.php';
			if(!file_exists($file)){
				continue;
			}
			require_once($file);
			$dirname = wp_basename($dir);
			$extension = __canonicalize($dirname);
			$class = __NAMESPACE__ . '\\' . $extension;
			if(!class_exists($class)){
				continue;
			}
			$callable = [$class, 'register_extension'];
			if(!is_callable($callable)){
				continue;
			}
			call_user_func($callable);
            if(!self::is_extension_active($extension)){
                continue;
            }
			$callable = [$class, 'get_instance'];
			if(!is_callable($callable)){
				continue;
			}
			call_user_func($callable);
		}
		add_action('mb_settings_pages', [__CLASS__, '_mb_settings_pages']);
		add_action('rwmb_meta_boxes', [__CLASS__, '_rwmb_meta_boxes']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
