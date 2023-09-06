<?php namespace IFWP_Pro;

final class Cloudinary extends \__Singleton {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	static public function _extensions($extensions){
		$dir = plugin_dir_path(__FILE__);
		$dirname = wp_basename($dir);
		$extension = __canonicalize($dirname);
		$extensions[$extension] = 'Cloudinary image sizes';
		return $extensions;
	}

	/**
	 * @return void
	 */
	static public function register_extension(){
		__add_plugin_filter('extensions', [__CLASS__, '_extensions']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	private $config = [], $image_sizes = [];

	/**
	 * @return bool|WP_Error
	 */
	private function use_cloudinary($preferred_version = '1.20.2'){
		$class = 'Cloudinary';
		if(class_exists($class)){
			return true;
		}
		$dir = __remote_lib('https://github.com/cloudinary/cloudinary_php/archive/refs/tags/' . $preferred_version . '.zip', 'cloudinary_php-' . $preferred_version);
		if(is_wp_error($dir)){
			return $dir;
		}
		$file = $dir . '/autoload.php';
		if(!file_exists($file)){
			return __error(__('File doesn&#8217;t exist?'), $file);
		}
		require_once($file);
		return class_exists($class);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	public function _fl_builder_photo_sizes_select($sizes){
		if(!$this->image_sizes){
			return $sizes;
		}
		if(!isset($sizes['full'])){
			return $sizes;
		}
		$id = __attachment_url_to_postid($sizes['full']['url']);
		if(!$id){
			return $sizes;
		}
		foreach($this->image_sizes as $size => $args){
			if(isset($sizes[$size])){
				continue;
			}
			$md5 = __md5($args);
			$meta_key = __plugin_prefix($md5);
			$result = get_post_meta($id, $meta_key, true);
			if(!$result){
				continue;
			}
			$sizes[$size] = [
				'filename' => $result['public_id'] . '.' . $result['format'],
				'height' => $result['height'],
				'url' => $result['secure_url'],
				'width' => $result['width'],
			];
		}
		uasort($sizes, [$this, 'ascending_sort']);
		return $sizes;
	}

	/**
	 * @return false|int
	 */
	public function _image_downsize($out, $id, $size){
        if(!$this->image_sizes){
			return $out;
		}
		if($out){
			return $out;
		}
		if(!wp_attachment_is_image($id)){
			return $out;
		}
		if(!is_scalar($size)){
			return $out;
		}
		if(!isset($this->image_sizes[$size])){
			return $out;
		}
		$args = $this->image_sizes[$size];
		$md5 = __md5($args);
		$meta_key = __plugin_prefix($md5);
		$result = get_post_meta($id, $meta_key, true);
		if(!$result){
			$result = $this->upload_attachment($id, $size);
			if(is_wp_error($result)){
				return false; // Silence is golden.
			}
		}
		return [$result['secure_url'], $result['width'], $result['height'], true];
	}

	/**
	 * @return array
	 */
	public function _image_size_names_choose($sizes){
        if(!$this->image_sizes){
			return $sizes;
		}
		foreach($this->image_sizes as $size => $args){
			$sizes[$size] = $args['name'];
		}
		return $sizes;
	}

	/**
	 * @return void
	 */
	public function add_image_size($name = '', $options = []){
		$config = $this->config();
		if(is_wp_error($config)){
			return;
		}
		$image_sizes = get_intermediate_image_sizes();
		$size = sanitize_title($name);
		if(in_array($size, $image_sizes)){
			return;
		}
		add_image_size($size); // fake - required
		$this->image_sizes[$size] = [
			'name' => $name,
			'options' => $options,
		];
	}

	/**
	 * @return int
	 */
	public function ascending_sort($a, $b){
		if($a['width'] === $b['width']){
			if($a['height'] === $b['height']){
				return 0;
			}
			if($a['height'] < $b['height']){
				return -1;
			}
			return 1;
		}
		if($a['width'] < $b['width']){
			return -1;
		}
		return 1;
	}

	/**
	 * @return array|WP_Error
	 */
	public function config($values = []){
		if($this->config){
			return $this->config;
		}
		$remote_lib = $this->use_cloudinary();
		if(is_wp_error($remote_lib)){
			return $remote_lib;
		}
        if(!$values){
            return __error(sprintf(__('Missing parameter(s): %s'), 'Access Keys') . '.');
        }
		if(is_array($values) and __array_keys_exists(['api_key', 'api_secret', 'cloud_name'], $values)){
			$config = \Cloudinary::config($values);
			$this->config = $config;
			return $config;
		} elseif(is_string($values) and preg_match('/^(?:CLOUDINARY_URL=)?(?:cloudinary:\/\/)(\d+):([^:@]+)@([^@]+)$/', $values, $matches)){
			$config = \Cloudinary::config([
				'api_key' => $matches[1],
				'api_secret' => $matches[2],
				'cloud_name' => $matches[3],
			]);
			$this->config = $config;
			return $config;
		} else {
            return __error(sprintf(__('Invalid parameter(s): %s'), 'Access Keys') . '.');
        }
	}

	/**
	 * @return void
	 */
	public function load(){
        add_filter('fl_builder_photo_sizes_select', [$this, '_fl_builder_photo_sizes_select']);
		add_filter('image_downsize', [$this, '_image_downsize'], 10, 3);
		add_filter('image_size_names_choose', [$this, '_image_size_names_choose']);
	}

	/**
	 * @return array|WP_Error
	 */
	public function upload($file = '', $options = []){
		if(!@file_exists($file)){
			return __error(__('File doesn&#8217;t exist?'), $file);
		}
		try {
			$result = \Cloudinary\Uploader::upload($file, $options);
		} catch(\Throwable $t){
			return __error($t->getMessage());
		} catch(\Exception $e){
			return __error($e->getMessage());
		}
		return $result;
	}

	/**
	 * @return array|WP_Error
	 */
	public function upload_attachment($id = 0, $size = ''){
		if(!wp_attachment_is_image($id)){
			return __error(__('File is not an image.'));
		}
		if(!is_scalar($size)){
			return __error(sprintf(__('Invalid parameter(s): %s'), 'size') . '.');
		}
		$size = sanitize_title($size);
		if(!isset($this->image_sizes[$size])){
			return __error(sprintf(__('Missing parameter(s): %s'), 'size') . '.');
		}
		$args = $this->image_sizes[$size];
		$md5 = __md5($args);
		$meta_key = __plugin_prefix($md5);
		$result = get_post_meta($id, $meta_key, true);
		if($result){
			return $result; // Short-circuit if the result already exists.
		}
		$metadata = wp_get_attachment_metadata($id);
        $max_file_size = 10 * MB_IN_BYTES; // TODO: check for paid plans. https://support.cloudinary.com/hc/en-us/articles/202520592-Do-you-have-a-file-size-limit-
		if((int) $metadata['filesize'] < $max_file_size){
			$file = get_attached_file($id);
		} else {
			$sizes = $metadata['sizes'];
			$sizes = wp_list_pluck($sizes, 'filesize', 'file');
			arsort($sizes);
			$attachment_url = wp_get_attachment_url($id);
			$base_url = str_replace(wp_basename($attachment_url), '', $attachment_url);
			$file_url = '';
			foreach($sizes as $file => $filesize){
				if((int) $filesize > $max_file_size){
					continue;
				}
				$file_url = $base_url . $file;
				break;
			}
			if(!$file_url){
				return __error(__('Invalid URL.'));
			}
			$upload_dir = wp_get_upload_dir();
			$file = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
		}
		$result = $this->upload($file, $args['options']);
		if(is_wp_error($result)){
			return $result;
		}
		update_post_meta($id, $meta_key, $result);
		return $result;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
