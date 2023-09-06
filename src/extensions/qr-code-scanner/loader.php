<?php namespace IFWP_Pro;

final class QR_Code_Scanner extends \__Singleton {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return array
	 */
	static public function _extensions($extensions){
		$dir = plugin_dir_path(__FILE__);
		$dirname = wp_basename($dir);
		$extension = __canonicalize($dirname);
		$extensions[$extension] = 'QR Code Scanner';
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
	public function _init(){
		add_shortcode('qr_code_scanner', [$this, '_qr_code_scanner']);
	}

	/**
	 * @return string
	 */
	public function _qr_code_scanner($atts, $content = ''){
		$atts = shortcode_atts([], $atts, 'qr_code_scanner');
        // Check for Beaver Builder.
        if(isset($_GET['fl_builder'])){
			$content = '<div>qr-code-scanner</div>';
        } else {
			if(!__has_plugin_filter('qr_code_scan')){
	            $message = sprintf(__("Method '%s' not implemented. Must be overridden in subclass."), __plugin_prefix('qr_code_scan'));
				$message = __first_p($message);
	            $content = '<div class="alert alert-danger mb-0" role="alert">' .  $message . '</div>';
	        } else {
				$content = '<div id="' . __plugin_slug('qr-code-scanner-wrapper') . '">';
	            $content .= '<div class="d-none">QR Code Scanner requires Bootstrap v4.6+</div>';
				$content .= '<div class="bg-dark embed-responsive embed-responsive-1by1 mb-3 rounded-lg">';
				$content .= '<div class="embed-responsive-item">';
				$content .= '<div id="' . __plugin_slug('qr-code-scanner') . '" class="h-100 overflow-hidden w-100"></div>';
				$content .= '</div>'; // embed-responsive-item
				$content .= '</div>'; // embed-responsive
				$content .= '<div id="' . __plugin_slug('qr-code-scanner-buttons') . '">';
				$content .= '<button type="button" class="btn btn-block btn-lg btn-primary" id="' . __plugin_slug('qr-code-scanner-start') . '" style="display: none;">Start scanning</button>';
				$content .= '<button type="button" class="btn btn-block btn-lg btn-secondary" id="' . __plugin_slug('qr-code-scanner-stop') . '" style="display: none;">Stop scanning</button>';
				$content .= '</div>'; // buttons
				$content .= '</div>'; // wrapper
			}
		}
		return $content;
    }

	/**
	 * @return void
	 */
	public function _rest_api_init(){
		register_rest_route(__plugin_slug(false) . '/v1', '/qr-code-scan', [
            'callback' => [$this, '_scan'],
            'methods' => 'POST',
            'permission_callback' => '__return_true',
        ]);
    }

	/**
	 * @return void
	 */
	public function _scan($request){
		$decoded_text = $request->get_param('decoded_text');
		$data = [
			'message' => sprintf(__("Method '%s' not implemented. Must be overridden in subclass."), __plugin_prefix('qr_code_scan')),
			'metadata' => [],
			'status' => 'danger',
		];
		$data = __apply_plugin_filters('qr_code_scan', $data, $decoded_text);
        $data = shortcode_atts([
            'message' => __('Error') . '.',
			'metadata' => [],
			'status' => 'danger',
        ], $data);
		if(!in_array($data['status'], ['danger', 'info', 'success', 'warning'])){
			$data['status'] = 'danger';
		}
		if(!$data['message']){
			$data['message'] = __('Error') . '.';
		}
        if(!__is_associative_array($data['metadata'])){
            $data['metadata'] = [];
        }
		$response = new \WP_REST_Response($data);
		$response->set_headers([
	        'Cache-Control' => 'no-cache',
	    ]);
        return $response;
    }

    /**
     * @return void
     */
    public function _wp_enqueue_scripts(){
        global $post;
        if(!is_page() or !has_shortcode($post->post_content, 'qr_code_scanner')){
            return;
        }
        __enqueue('html5-qrcode', 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js', [], '2.3.8');
        __plugin_enqueue('qr-code-scanner.js', ['html5-qrcode', 'wp-api'], [
            'duplicated_message' => __apply_plugin_filters('qr_code_scan_duplicated_message', __('A duplicate event already exists.')),
			'loading_message' => __apply_plugin_filters('qr_code_scan_loading_message', __('Loading&hellip;')),
        ]);
    }

	/**
	 * @return void
	 */
	public function _wp_footer(){
		ob_start(); ?>
<div class="modal" id="<?php echo __plugin_slug('qr-code-scanner-modal'); ?>" tabindex="-1" aria-labelledby="<?php echo __plugin_slug('qr-code-scanner-modal-title'); ?>" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
		<div class="modal-content shadow-lg">
			<div class="modal-header">
				<h5 class="modal-title text-truncate" id="<?php echo __plugin_slug('qr-code-scanner-modal-title'); ?>">QR Code Scanner</h5>
			</div>
			<div id="<?php echo __plugin_slug('qr-code-scanner-modal-body'); ?>" class="modal-body"><?php echo __('Loading&hellip;'); ?></div>
			<div class="modal-footer">
				<button type="button" id="<?php echo __plugin_slug('qr-code-scanner-resume'); ?>" class="btn btn-block btn-lg btn-primary"><?php echo __('Resume'); ?></button>
			</div>
		</div>
	</div>
</div><?php
		$out = ob_get_clean();
		echo "\n" . $out . "\n";
    }

	/**
	 * @return void
	 */
	public function load(){
        add_action('init', [$this, '_init']);
		add_action('rest_api_init', [$this, '_rest_api_init']);
        add_action('wp_enqueue_scripts', [$this, '_wp_enqueue_scripts']);
		add_action('wp_footer', [$this, '_wp_footer']);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
