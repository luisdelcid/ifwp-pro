<?php namespace IFWP_Pro;

final class Additional_Code extends \__Singleton {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	static public function _extensions($extensions){
		$dir = plugin_dir_path(__FILE__);
		$dirname = wp_basename($dir);
		$extension = __canonicalize($dirname);
		$extensions[$extension] = 'Additional CSS and JavaScript';
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
    public function _admin_enqueue_scripts($hook_suffix){
		$post_types = [__plugin_slug('css-code'), __plugin_slug('js-code')];
        switch($hook_suffix){
			case 'post.php':
				if(!isset($_GET['action'], $_GET['post'])){
					return;
				}
				if('edit' !== $_GET['action']){
					return;
				}
				if(!in_array(get_post_type($_GET['post']), $post_types)){
					return;
				}
				break;
			case 'post-new.php':
				if(!isset($_GET['post_type'])){
					return;
				}
				if(!in_array($_GET['post_type'], $post_types)){
					return;
				}
				break;
			default:
				return;
		}
		__plugin_enqueue('additional-code.css');
		__enqueue('ace', 'https://cdnjs.cloudflare.com/ajax/libs/ace/1.24.1/ace.js', [], '1.24.1');
		__enqueue('ace-language-tools', 'https://cdnjs.cloudflare.com/ajax/libs/ace/1.24.1/ext-language_tools.min.js', ['ace'], '1.24.1');
		__plugin_enqueue('additional-code.js', ['ace-language-tools'], [
			'base_path' => 'https://cdnjs.cloudflare.com/ajax/libs/ace/1.24.1',
		]);
    }

	/**
	 * @return void
	 */
	public function _init(){
		register_post_type(__plugin_slug('css-code'), [
			'labels' => __post_type_labels('Additional CSS', 'Additional CSS', false),
			'show_in_admin_bar' => false,
			'show_in_menu' => 'themes.php',
			'show_ui' => true,
			'supports' => ['title'],
		]);
		register_post_type(__plugin_slug('js-code'), [
			'labels' => __post_type_labels('Additional JavaScript', 'Additional JavaScript', false),
			'show_in_admin_bar' => false,
			'show_in_menu' => 'themes.php',
			'show_ui' => true,
			'supports' => ['title'],
		]);
	}

	/**
	 * @return array
	 */
	public function _rwmb_meta_boxes($meta_boxes){
		$meta_boxes[] = [
			'fields' => [
				[
					'id' => __plugin_prefix('additional_css'),
					'sanitize_callback' => 'none',
					'type' => 'textarea',
				],
			],
			'post_types' => __plugin_slug('css-code'),
			'style' => 'seamless',
			'title' => 'Additional CSS',
		];
		$meta_boxes[] = [
			'fields' => [
				[
					'id' => __plugin_prefix('additional_javascript'),
					'sanitize_callback' => 'none',
					'type' => 'textarea',
				],
			],
			'post_types' => __plugin_slug('js-code'),
			'style' => 'seamless',
			'title' => 'Additional JavaScript',
		];
		$meta_boxes[] = [
			'context' => 'side',
			'fields' => [
				[
					'admin_columns' => [
						'position' => 'after title',
						'title' => __('Published'),
					],
					'id' => __plugin_prefix('code_status'),
					'std' => 1,
					'type' => 'switch',
				],
			],
			'post_types' => [__plugin_slug('css-code'), __plugin_slug('js-code')],
			'priority' => 'low',
			'title' => __('Published'),
		];
		return $meta_boxes;
	}

	/**
	 * @return void
	 */
	public function _wp_head(){
        $posts = get_posts([
			'meta_query' => [
				[
					'key' => __plugin_prefix('code_status'),
					'value' => 1,
				],
			],
			'post_type' => __plugin_slug('css-code'),
			'posts_per_page' => -1,
		]);
		if($posts){
			echo '<style id="' . __plugin_slug('additional-css') . '">';
			foreach($posts as $post){
				if(apply_filters(__plugin_prefix('additional_css'), true, $post)){
					echo "\n" . '/* ' . esc_html($post->post_title) . '*/' . "\n";
					echo get_post_meta($post->ID, __plugin_prefix('additional_css'), true);
				}
			}
			echo '</style>';
		}
	}

	/**
	 * @return void
	 */
	public function _wp_print_footer_scripts(){
        $posts = get_posts([
			'meta_query' => [
				[
					'key' => __plugin_prefix('code_status'),
					'value' => 1,
				],
			],
			'post_type' => __plugin_slug('js-code'),
			'posts_per_page' => -1,
		]);
        if($posts){
			echo '<script id="' . __plugin_slug('additional-javascript') . '">';
			foreach($posts as $post){
				if(apply_filters(__plugin_prefix('additional_javascript'), true, $post)){
					echo "\n" . '/* ' . esc_html($post->post_title) . '*/' . "\n";
					echo get_post_meta($post->ID, __plugin_prefix('additional_javascript'), true);
				}
			}
			echo '</script>';
		}
	}

	/**
	 * @return void
	 */
	public function load(){
        add_action('admin_enqueue_scripts', [$this, '_admin_enqueue_scripts']);
        add_action('init', [$this, '_init']);
        add_action('wp_head', [$this, '_wp_head']);
        add_action('wp_print_footer_scripts', [$this, '_wp_print_footer_scripts']);
        add_filter('rwmb_meta_boxes', [$this, '_rwmb_meta_boxes']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
