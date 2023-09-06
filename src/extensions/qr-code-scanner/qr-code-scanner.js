class IFWP_Pro_QR_Code_Scanner extends __Singleton {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * @return bool
	 */
	is_on(){
        var $this = this;
        if(jQuery.isEmptyObject($this.scanner)){
            return false;
        }
        switch($this.scanner.getState()){
            case Html5QrcodeScannerState.NOT_STARTED:
                return false;
            case Html5QrcodeScannerState.SCANNING:
				return true;
            case Html5QrcodeScannerState.PAUSED:
                return false;
			default:
				return false;
        }
	}

	/**
	 * @return void
	 */
	load(){
        var $this = this;
        $this.last_scanned_data = '';
		$this.scanner = {};
		$this.was_on = false;
        jQuery(function($){
            var modal = $('#' + __plugin_slug('qr-code-scanner-modal')),
                modal_body = $('#' + __plugin_slug('qr-code-scanner-modal-body')),
                modal_title = $('#' + __plugin_slug('qr-code-scanner-modal-title')),
                resume = $('#' + __plugin_slug('qr-code-scanner-resume')),
                start = $('#' + __plugin_slug('qr-code-scanner-start')),
                stop = $('#' + __plugin_slug('qr-code-scanner-stop'));
            $this.scan(__plugin_slug('qr-code-scanner'));
			start.show();
            start.on('click', function(){
				$(this).hide();
				stop.show();
                $this.on();
            });
            stop.on('click', function(){
				$(this).hide();
				start.show();
                $this.off();
				$this.last_scanned_data = '';
            });
			resume.on('click', function(){
				stop.show();
				modal.modal('hide');
				modal_body.html('Loading&hellip;');
                modal_title.html('QR Code Scanner');
                $this.on();
            });
			__enable_document_visibility();
            __add_action('visibilitychange', function(hidden){
                if(hidden){
                    var is_on = $this.is_on();
                    $this.was_on = is_on;
                    if(is_on){
						stop.hide();
						start.show();
                        $this.off();
                    }
                } else {
                    if($this.was_on){
						start.hide();
						stop.show();
                        $this.on();
                    }
                }
            });
        });
	}

	/**
     * @return void
     */
	off(pause = false){
		var $this = this;
        if(jQuery.isEmptyObject($this.scanner)){
            return;
        }
        switch($this.scanner.getState()){
            case Html5QrcodeScannerState.NOT_STARTED:
                // do nothing if not already started
                break;
            case Html5QrcodeScannerState.SCANNING:
				$this.scanner.pause(true);
				if(!pause){
					$this.scanner.stop();
				}
                break;
            case Html5QrcodeScannerState.PAUSED:
				if(!pause){
                	$this.scanner.stop();
				}
                break;
        }
	}

	/**
     * @return void
     */
	on(){
		var $this = this;
		if(jQuery.isEmptyObject($this.scanner)){
            return;
        }
        switch($this.scanner.getState()){
            case Html5QrcodeScannerState.NOT_STARTED:
                $this.start();
                break;
            case Html5QrcodeScannerState.SCANNING:
                // do nothing if already started
                break;
            case Html5QrcodeScannerState.PAUSED:
				$this.scanner.resume();
                break;
        }
	}

	/**
     * @return void
     */
    scan(id){
        var $this = this;
		if(!jQuery('#' + id).length){
			return;
		}
		$this.scanner = new Html5Qrcode(id);
    }

	/**
     * @return void
     */
    start(){
        var $this = this;
        $this.scanner.start({
            facingMode: 'environment',
        }, {
            aspectRatio: 1,
            formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
            fps: 30,
            qrbox: function(viewfinderWidth, viewfinderHeight){
                var qrboxSize = Math.floor(viewfinderWidth * 0.7);
                return {
                    width: qrboxSize,
                    height: qrboxSize,
                };
            },
        }, function(decoded_text, decoded_result){
            $this.success_callback(decoded_text, decoded_result);
        }, function(error_message){
            // Silence is golden.
        }).catch(function(err){
            alert(err);
        });
    }

	/**
     * @return void
     */
    success_callback(decoded_text, decoded_result){
        var $this = this,
            modal = jQuery('#' + __plugin_slug('qr-code-scanner-modal')),
            modal_body = jQuery('#' + __plugin_slug('qr-code-scanner-modal-body')),
            modal_title = jQuery('#' + __plugin_slug('qr-code-scanner-modal-title')),
            resume = jQuery('#' + __plugin_slug('qr-code-scanner-resume')),
            start = jQuery('#' + __plugin_slug('qr-code-scanner-start')),
            stop = jQuery('#' + __plugin_slug('qr-code-scanner-stop'));
        $this.scanner.pause(true);
        start.hide();
        stop.hide();
        modal.modal({
            backdrop: 'static',
            keyboard: false,
        });
        resume.attr('disabled', 'disabled');
        modal_title.html(decoded_text);
        if(decoded_text !== $this.last_scanned_data){
            $this.last_scanned_data = decoded_text;
            jQuery.ajax({
                beforeSend: function(xhr){
                    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                },
                data: {
                    decoded_text: decoded_text,
                },
                method: 'POST',
                url: wpApiSettings.root + __plugin_slug(false) + '/v1/qr-code-scan',
            }).done(function(data, textStatus, jqXHR){
                modal_body.html('<div class="alert alert-' + data.status + ' mb-0" role="alert">' + data.message + '</div>');
                __do_plugin_action('qr_code_scan_done', data, decoded_text);
            }).fail(function(jqXHR, textStatus, errorThrown){
                modal_body.html('<div class="alert alert-danger mb-0" role="alert">' + textStatus + '</div>');
                __do_plugin_action('qr_code_scan_fail', textStatus, decoded_text);
            }).always(function(data_jqXHR, textStatus, jqXHR_errorThrown){
                resume.removeAttr('disabled');
            });
        } else {
            modal_body.html('<div class="alert alert-warning mb-0" role="alert">' + $this.l10n.duplicated_message + '</div>');
            __do_plugin_action('qr_code_scan_duplicated', decoded_text);
            resume.removeAttr('disabled');
        }
    }

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}

IFWP_Pro_QR_Code_Scanner.get_instance();
