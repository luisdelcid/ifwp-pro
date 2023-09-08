<?php namespace IFWP_Pro;

final class WP_Update_Server extends \Wpup_UpdateServer {

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return bool
	 */
	private function get_license($request = null){
		if(empty($request->license)){
			$license = [
				'api_key' => '',
				'message' => sprintf(__('Missing parameter(s): %s'), 'API key') . '.',
				'status' => false,
			];
		} else {
            $license = [
    			'api_key' => $request->license,
    			'message' => sprintf(__('Invalid parameter(s): %s'), 'API key') . '.',
    			'status' => false,
    		];
        }
        return __apply_plugin_filters('custom_update_api_license', $license, $request);
    }

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return void
	 */
    protected function checkAuthorization($request){
        parent::checkAuthorization($request);
        if('download' !== $request->action){
            return;
        }
        if(is_user_logged_in()){
            return;
        }
        $license = $this->get_license($request);
        if(!$license['status']){
            $this->exitWithError($license['message'], 403);
        }
    }

	/**
	 * @return array
	 */
	protected function filterMetadata($meta, $request){
		$meta = parent::filterMetadata($meta, $request);
		$meta = __apply_plugin_filters('custom_update_api_metadata', $meta, $request);
		if(is_user_logged_in()){
			return $meta;
		}
		$license = $this->get_license($request);
		if(!$license['status']){
			unset($meta['download_url']);
		} else {
			$meta['download_url'] = self::addQueryArg([
				'license' => $request->license,
			], $meta['download_url']);
		}
		return $meta;
	}

	/**
	 * @return string
	 */
	protected function generateAssetUrl($assetType, $relativeFileName) {
		$directory = $this->normalizeFilePath($this->assetDirectories[$assetType]);
		if(0 === strpos($directory, $this->serverDirectory)){
			$subDirectory = substr($directory, strlen($this->serverDirectory) + 1);
		} else {
			$subDirectory = basename($directory);
		}
		$subDirectory = trim($subDirectory, '/\\');
		$dir = Custom_Update_API::get_instance()->dir();
		if(!is_wp_error($dir)){
			$server_url = __dir_to_url($dir) . '/';
		}
		return $server_url . $subDirectory . '/' . $relativeFileName;
	}

	/**
	 * @return array
	 */
    protected function initRequest($query = null, $headers = null){
        $request = parent::initRequest($query, $headers);
        $request->license = $request->param('license', '');
        return $request;
    }

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}
