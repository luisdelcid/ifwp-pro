class IFWP_Pro_Additional_Customization_Code extends __Singleton {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    /**
     * @return void
     */
    edit(id, mode){
        var $this = this;
        if(!jQuery('#' + id).length){
            return;
        }
        if(typeof $this.editors[id] !== 'undefined'){
            return;
        }
        jQuery('#' + id).hide();
        jQuery('<div class="' + __plugin_slug('ace-container') + '"><div id="' + id + '-ace"></div></div>').insertBefore('#' + id);
        $this.editors[id] = ace.edit(id + '-ace');
        $this.editors[id].$blockScrolling = Infinity;
        $this.editors[id].setOptions({
            enableBasicAutocompletion: true,
            enableLiveAutocompletion: true,
            fontSize: 16,
            maxLines: Infinity,
            minLines: 10,
            wrap: true,
        });
        $this.editors[id].getSession().on('change', function(){
            jQuery('#' + id).val($this.editors[id].getSession().getValue()).trigger('change');
        });
        $this.editors[id].getSession().setMode('ace/mode/' + mode);
        $this.editors[id].getSession().setValue(jQuery('#' + id).val());
        $this.editors[id].setTheme('ace/theme/monokai');
    }

	/**
	 * @return void
	 */
	load(){
        var $this = this;
        $this.editors = [];
        jQuery(function($){
            if('undefined' === typeof(ace)){
                return;
            }
            ace.config.set('basePath', $this.l10n.base_path);
            ace.require('ace/ext/language_tools');
            $this.edit(__plugin_prefix('additional_css'), 'css');
            $this.edit(__plugin_prefix('additional_javascript'), 'javascript');
        });
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

}

IFWP_Pro_Additional_Customization_Code.get_instance();
