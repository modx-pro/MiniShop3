ms3.panel.Gallery = function (config) {
    config = config || {};

    Ext.apply(config, {
        border: false,
        id: 'ms3-gallery-page',
        baseCls: 'x-panel',
        items: [{
            border: false,
            style: {padding: '10px 5px'},
            xtype: 'ms3-gallery-page-toolbar',
            id: 'ms3-gallery-page-toolbar',
            record: config.record,
        }, {
            border: false,
            style: {padding: '10px 5px'},
            html: '<div id="ms3-gallery-uploader" class="vueApp"></div>'
        }, {
            border: false,
            style: {padding: '5px'},
            layout: 'anchor',
            items: [{
                border: false,
                xtype: 'ms3-gallery-images-panel',
                id: 'ms3-gallery-images-panel',
                cls: 'modx-pb-view-ct',
                product_id: config.record.id,
                pageSize: config.pageSize
            }]
        }]
    });
    ms3.panel.Gallery.superclass.constructor.call(this, config);

    this.on('afterrender', function () {
        const gallery = this;
        window.setTimeout(function () {
            gallery.initialize();
        }, 100);
    });
};
Ext.extend(ms3.panel.Gallery, MODx.Panel, {
    vueUploaderInstance: null,

    initialize: function () {
        if (this.initialized) {
            return;
        }
        this._initUploader();
        this.initialized = true;
    },

    _initUploader: function () {
        if (typeof window.MS3_initGalleryUploader !== 'function') {
            console.error('[MS3 Gallery] Vue uploader not loaded. Make sure gallery-uploader.min.js is included.');
            MODx.msg.alert(_('error'), 'Gallery uploader not loaded. Please refresh the page.');
            return;
        }

        const allowedTypes = ms3.config.media_source.allowedFileTypes || MODx.config.upload_images || 'jpg,jpeg,png,gif,webp';
        const allowedFileTypes = allowedTypes.split(',').map(ext => {
            const mimeTypes = {
                'jpg': 'image/jpeg',
                'jpeg': 'image/jpeg',
                'png': 'image/png',
                'gif': 'image/gif',
                'webp': 'image/webp',
                'avif': 'image/avif',
                'heic': 'image/heic'
            };
            return mimeTypes[ext.trim()] || 'image/' + ext.trim();
        });

        this.vueUploaderInstance = window.MS3_initGalleryUploader({
            containerId: 'ms3-gallery-uploader',
            productId: this.record.id,
            sourceId: this.record.source,
            connectorUrl: ms3.config.connector_url,
            maxFileSize: ms3.config.media_source.maxUploadSize || MODx.config.upload_maxsize || 10485760,
            maxWidth: ms3.config.media_source.maxUploadWidth || 1920,
            maxHeight: ms3.config.media_source.maxUploadHeight || 1080,
            allowedFileTypes: allowedFileTypes,
            onUploadSuccess: this.onUploadSuccess.bind(this),
            onUploadError: this.onUploadError.bind(this),
            onUploadComplete: this.onUploadComplete.bind(this)
        });

        if (!this.vueUploaderInstance) {
            console.error('[MS3 Gallery] Failed to initialize Vue uploader');
        }
    },

    onUploadSuccess: function (data) {
        const file = data.file;
        const response = data.response;
        console.log('[MS3 Gallery] Upload success:', file.name, response);
    },

    onUploadError: function (data) {
        const file = data.file;
        const error = data.error;
        const fileName = file ? file.name : 'File';
        const errorMsg = error.message || 'Upload failed';
        console.error('[MS3 Gallery] Upload error:', fileName, error);
        MODx.msg.alert(_('error'), fileName + ': ' + errorMsg);
    },

    onUploadComplete: function (result) {
        console.log('[MS3 Gallery] Upload complete:', result);

        const panel = Ext.getCmp('ms3-gallery-images-panel');
        if (panel) {
            panel.view.getStore().reload();

            MODx.Ajax.request({
                url: ms3.config.connector_url,
                params: {
                    action: 'MiniShop3\Processors\Product\Get',
                    id: this.record.id
                },
                listeners: {
                    success: {
                        fn: function (r) {
                            if (r.object && r.object.thumb) {
                                panel.view.updateThumb(r.object.thumb);
                            }
                        }
                    }
                }
            });
        }
    },

    destroy: function () {
        if (this.vueUploaderInstance && this.vueUploaderInstance.destroy) {
            this.vueUploaderInstance.destroy();
            this.vueUploaderInstance = null;
        }
        ms3.panel.Gallery.superclass.destroy.call(this);
    }

});
Ext.reg('ms3-gallery-page', ms3.panel.Gallery);
