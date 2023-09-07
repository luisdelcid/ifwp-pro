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

	private $dir = '';

	/**
	 * @return void
	 */
	private function download_plugin(){
        if(!is_user_logged_in()){
            auth_redirect();
        }
        $slug = isset($_GET['slug']) ? $_GET['slug'] : '';
        $slugs = $this->slugs();
        if(!in_array($slug, $slugs)){
            __exit_with_error(__('Invalid plugin page.'), 404);
        }
        $dir = $this->dir();
        if(is_wp_error($dir)){
            __exit_with_error($dir);
        }
        $file = $dir . '/packages/' . $slug . '.zip';
        if(!file_exists($file)){
			__exit_with_error(__('Installation package not available.'), 503);
        }
        $data = $this->get_remote_data($slug);
        if(is_wp_error($data)){
            __exit_with_error($data);
        }
        $mimes = [
            'zip' => 'application/zip',
        ];
        $filetype = wp_check_filetype($file, $mimes);
        if(!$filetype['type']){
            __exit_with_error(__('Incompatible Archive.'));
        }
        nocache_headers();
        header('Content-Type: ' . $filetype['type']); // Always send this.
        if(false === strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS')){
            header('Content-Length: ' . filesize($file));
        }
        header('Content-Disposition: attachment; filename="' . $slug . '-' . $data['version'] . '.zip"');
        readfile($file); // If we made it this far, just serve the file.
        flush();
        exit;
	}

    /**
	 * @return array|WP_Error
	 */
	private function get_local_data($slug = ''){
		$basename = $slug . '/' . $slug . '.php';
		$file = WP_PLUGIN_DIR . '/' . $basename;
		if(!is_file($file)){
			return __error(__('File does not exist?'), $file);
		}
		$data = __plugin_data($file, true, false);
		if(is_wp_error($data)){
			return $data;
		}
		return [
			'basename' => $basename,
			'data' => $data,
			'file' => $file,
		];
	}

	/**
	 * @return array|WP_Error
	 */
	private function get_remote_data($slug = ''){
		$url = site_url('wp-update-server/');
		$metadata_url = add_query_arg([
			'action' => 'get_metadata',
			'slug' => $slug,
		], $url);
		$data = __remote_get($metadata_url);
		if(is_wp_error($data)){
			return $data;
		}
		if(!isset($data['download_url'])){
			return __error(__('You need a higher level of permission.'));
		}
		return $data;
	}

	/**
	 * @return array
	 */
	private function slugs(){
		$slugs = __apply_plugin_filters('private_update_server_slugs', []);
		return $slugs;
	}

	/**
	 * @return bool|WP_Error
	 */
	private function update_plugin($slug, $plugin = [], $force = false){
		$dir = $this->dir();
        if(is_wp_error($dir)){
			return $dir;
		}
		$filename = $slug . '.zip';
		$packages = $dir . '/packages'; // Full path, no trailing slash.
		$file = $packages . '/' . $filename;
		if(file_exists($file) and !$force){
			$data = __get_remote_data($slug);
			if(is_wp_error($data)){
				return $data;
			}
			if(version_compare($plugin['data']['Version'], $data['version'], '<=')){
				return true;
			}
		}
		$commands = [];
		if(file_exists($file)){
			$commands[] = 'cd ' . $packages;
			$commands[] = 'rm ' . $filename;
		}
		$commands[] = 'cd ' . WP_PLUGIN_DIR;
		$commands[] = 'zip -r ' . $file . ' ' . $slug;
		$commands = implode(' && ', $commands);
		exec($commands);
		$data = __get_remote_data($slug);
		if(is_wp_error($data)){
			return $data;
		}
		$option = __plugin_prefix('private_update_server_data_' . $slug);
		return update_option($option, $data);
	}

	/**
	 * @return void
	 */
	private function update_plugins(){
        if(!is_user_logged_in()){
            auth_redirect();
        }
        if(!current_user_can('manage_options')){
			__exit_with_error(__('Sorry, you are not allowed to update plugins for this site.'), __('You need a higher level of permission.'), 403);
        }
		$errors = false;
        $slugs = $this->slugs();
        if($slugs){
			$force = (isset($_GET['force']) ? boolval($_GET['force']) : false);
            foreach($slugs as $slug){
                $data = __get_local_data($slug);
                if(is_wp_error($data)){
                    continue;
                }
                $result = $this->update_plugin($slug, $data, $force);
                if(is_wp_error($result)){
					$errors = true; // Silence is golden.
                }
            }
        }
		$url = home_url();
		if($errors){
			$url = add_query_arg([
				'errors' => 1,
			], $url);
		}
        wp_safe_redirect($url);
        exit;
	}

	/**
	 * @return void
	 */
	private function update_server(){
        $remote_lib = $this->use_wp_update_server();
        if(is_wp_error($remote_lib)){
            __exit_with_error($remote_lib);
        }
        $dir = $this->dir();
        if(is_wp_error($dir)){
            __exit_with_error($dir);
        }
        $url = site_url('wp-update-server/');
        require_once(plugin_dir_path(__FILE__) . 'custom-update-server.php');
        $update_server = new Custom_Update_Server($url, $dir);
        $update_server->handleRequest();
        exit;
	}

    /**
	 * @return bool|WP_Error
	 */
	private function use_wp_update_server($preferred_version = '2.0.1'){
		$class = 'Wpup_UpdateServer';
		if(class_exists($class)){
			return true;
		}
		$dir = __remote_lib('https://github.com/YahnisElsts/wp-update-server/archive/refs/tags/v' . $preferred_version . '.zip', 'wp-update-server-' . $preferred_version);
		if(is_wp_error($dir)){
			return $dir;
		}
		$file = $dir . '/loader.php';
		if(!file_exists($file)){
			return __error(__('File doesn&#8217;t exist?'), $file);
		}
		require_once($file);
		return class_exists($class);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
	public function _admin_init(){
		if(!__has_plugin_filter('private_update_server_slugs')){
			return;
		}
		if('/%postname%/' === get_option('permalink_structure')){
			return;
		}
		$message = sprintf(__('Invalid parameter(s): %s'), __('Permalink structure')) . ' (' . __('Post name') . ').';
		$message .= ' ';
		$message .= sprintf('<a href="%s">%s</a>', esc_url(admin_url('options-permalink.php')), __go_to(__('Permalinks'))) . '.';
		__add_admin_notice($message);
	}

	/**
	 * @return void
	 */
	public function _init(){
		if(!__has_plugin_filter('private_update_server_slugs')){
			return;
		}
		$tag = 'plugin_data';
		add_shortcode($tag, [$this, '_plugin_data']);
	}

	/**
	 * @return void
	 */
	public function _parse_request($wp){
		if(!__has_plugin_filter('private_update_server_slugs')){
			return;
		}
        switch($wp->request){
            case 'download-plugin':
                $this->download_plugin();
                break;
            case 'update-plugins':
                $this->update_plugins();
                break;
            case 'wp-update-server':
                $this->update_server();
                break;
        }
	}

	/**
	 * @return string
	 */
	public function _plugin_data($atts, $content = ''){
		$tag = __plugin_prefix('private_update_server_data');
		$atts = shortcode_atts([
			'key' => '',
			'slug' => '',
		], $atts, $tag);
		$key = $atts['key'];
		$slug = $atts['slug'];
		$slugs = $this->slugs();
		if(!in_array($slug, $slugs)){
			return __('Something went wrong.');
		}
		$option = __plugin_prefix('private_update_server_data_' . $slug);
		$data = get_option($option, []);
		if(!$data){
			return __('Plugin not found.');
		}
		$html = '';
		if(array_key_exists($key, $data)){
			$arr = $data;
		} elseif(array_key_exists($key, $data['sections'])){
			$arr = $data['sections'];
		} else {
			return $key . ' ' . __('(not found)');
		}
		$html .= wp_strip_all_tags($arr[$key]);
		if('version' === $key and current_user_can('manage_options')){
			$plugin = $this->get_local_data($slug);
			if(is_wp_error($plugin)){
				$html .= ' &#8212; <span class="text-danger">' . $plugin->get_error_message() . '</span>';
			} else {
				if(version_compare($plugin['data']['Version'], $data['version'], '>')){
					$html .= ' &#8212; <span class="text-danger">' . __first_p(sprintf(__('There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>.'), $plugin['data']['Name'], '#', '', $plugin['data']['Version'])) . '</span>';
				}
			}
		}
		return $html;

        $tag = __plugin_prefix('plugin_data');
		$atts = shortcode_atts([
			'key' => '',
			'slug' => '',
		], $atts, $tag);
		$key = $atts['key'];
		$slug = $atts['slug'];
		$slugs = $this->slugs();
		if(!in_array($slug, $slugs)){
			return __('Something went wrong.');
		}
		$option = __plugin_prefix('plugin_data_' . $slug);
		$data = get_option($option, []);
		if(!$data){
			return __('Plugin not found.');
		}
		$html = '';
		if(array_key_exists($key, $data)){
			$arr = $data;
		} elseif(array_key_exists($key, $data['sections'])){
			$arr = $data['sections'];
		} else {
			return $key . ' ' . __('(not found)');
		}
		$html .= wp_strip_all_tags($arr[$key]);
		if('name' === $key and current_user_can('manage_options')){
			$plugin = $this->get_local_data($slug);
			if(is_wp_error($plugin)){
				$html .= ' &#8212; <span class="text-danger">' . $plugin->get_error_message() . '</span>';
			} else {
				if(version_compare($plugin['data']['Version'], $data['version'], '>')){
					$html .= ' &#8212; <span class="text-danger">' . __first_p(sprintf(__('There is a new version of %1$s available. <a href="%2$s" %3$s>View version %4$s details</a>.'), $plugin['data']['Name'], '#', '', $plugin['data']['Version'])) . '</span>';
				}
			}
		}
		return $html;
	}

    /**
     * @return string|WP_Error
     */
    public function dir(){
        if($this->dir){
            return $this->dir;
        }
        $upload_dir = wp_get_upload_dir();
        if($upload_dir['error']){
            return __error($upload_dir['error']);
        }
        $path = $upload_dir['basedir'];
        $dir = __mkdir_p($path . '/' . __plugin_slug(false) . '/wp-update-server');
        if(is_wp_error($dir)){
            return $dir;
        }
        $fs = __filesystem();
        if(is_wp_error($fs)){
            return $fs;
        }
        $dirlist = $fs->dirlist($dir, false);
        if(empty($dirlist)){
			$from = plugin_dir_path(__FILE__) . 'wp-update-server';
            $result = copy_dir($from, $dir);
            if(is_wp_error($result)){
                $fs->rmdir($dir, true);
                return $result;
            }
        }
        $this->dir = $dir;
        return $dir;
    }

	/**
	 * @return void
	 */
	public function load(){
		add_action('admin_init', [$this, '_admin_init']);
		add_action('init', [$this, '_init']);
		add_action('parse_request', [$this, '_parse_request']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
