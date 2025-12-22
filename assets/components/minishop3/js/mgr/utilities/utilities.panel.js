ms3.panel.Utilities = function (config) {
    config = config || {};
    Ext.apply(config, {
        cls: 'container',
        items: [{
            html: '<h2>' + _('ms3_header') + ' :: ' + _('ms3_utilities') + '</h2>',
            cls: 'modx-page-header',
        }, {
            xtype: 'modx-tabs',
            id: 'ms3-utilities-tabs',
            stateful: true,
            stateId: 'ms3-utilities-tabs',
            stateEvents: ['tabchange'],
            cls: 'ms3-panel',
            getState: function () {
                return {
                    activeTab: this.items.indexOf(this.getActiveTab())
                };
            },
            items: [{
                title: _('ms3_utilities_gallery'),
                id: 'ms3-utilities-gallery-tab',
                layout: 'fit',
                autoScroll: true,
                items: [{
                    xtype: 'panel',
                    id: 'ms3-vue-utilities-gallery-panel',
                    border: false,
                    autoHeight: true,
                    html: '<div id="ms3-vue-utilities-gallery" class="vueApp" style="min-height: 400px;"' +
                          ' data-source-id="' + (ms3.config.utility_gallery_source_id || 1) + '"' +
                          ' data-source-name="' + (ms3.config.utility_gallery_source_name || '') + '"' +
                          ' data-total-products="' + (ms3.config.utility_gallery_total_products || 0) + '"' +
                          ' data-total-files="' + (ms3.config.utility_gallery_total_products_files || 0) + '"' +
                          ' data-thumbnails="' + encodeURIComponent(ms3.config.utility_gallery_thumbnails || '') + '"' +
                          '></div>',
                    listeners: {
                        afterrender: function() {
                            // Mount Vue application after panel render
                            const event = new CustomEvent('ms3:mountVueUtilitiesGallery', {
                                detail: {
                                    targetId: '#ms3-vue-utilities-gallery'
                                }
                            });
                            document.dispatchEvent(event);
                        }
                    }
                }]
            },
                {
                    title: _('ms3_utilities_import'),
                    id: 'ms3-utilities-import-tab',
                    layout: 'fit',
                    autoScroll: true,
                    items: [{
                        xtype: 'panel',
                        id: 'ms3-vue-import-panel',
                        border: false,
                        autoHeight: true,
                        html: '<div id="ms3-vue-import" class="vueApp" style="min-height: 600px;"></div>',
                        listeners: {
                            afterrender: function() {
                                // Mount Vue application after panel render
                                const event = new CustomEvent('ms3:mountVueImport', {
                                    detail: {
                                        targetId: '#ms3-vue-import'
                                    }
                                });
                                document.dispatchEvent(event);
                            }
                        }
                    }]
                },
                {
                    title: _('ms3_vue_product_fields_title'),
                    id: 'ms3-utilities-fields-management-tab',
                    layout: 'fit',
                    autoScroll: true,
                    items: [{
                        xtype: 'panel',
                        id: 'ms3-vue-fields-management-panel',
                        border: false,
                        autoHeight: true,
                        html: '<div id="ms3-vue-fields-management" class="vueApp" style="min-height: 600px;"></div>',
                        listeners: {
                            afterrender: function() {
                                // Mount Vue application after panel render
                                const event = new CustomEvent('ms3:mountVueFieldsManagement', {
                                    detail: {
                                        targetId: '#ms3-vue-fields-management'
                                    }
                                });
                                document.dispatchEvent(event);
                            }
                        }
                    }]
                },
                {
                    title: _('ms3_extra_fields_title'),
                    id: 'ms3-utilities-extra-fields-tab',
                    layout: 'fit',
                    autoScroll: true,
                    items: [{
                        xtype: 'panel',
                        id: 'ms3-vue-extra-fields-panel',
                        border: false,
                        autoHeight: true,
                        html: '<div id="ms3-vue-extra-fields" class="vueApp" style="min-height: 600px;"></div>',
                        listeners: {
                            afterrender: function() {
                                // Mount Vue application after panel render
                                const event = new CustomEvent('ms3:mountVueExtraFields', {
                                    detail: {
                                        targetId: '#ms3-vue-extra-fields'
                                    }
                                });
                                document.dispatchEvent(event);
                            }
                        }
                    }]
                },
                {
                    title: _('grid_fields_config_title'),
                    id: 'ms3-utilities-grid-fields-config-tab',
                    layout: 'fit',
                    autoScroll: true,
                    items: [{
                        xtype: 'panel',
                        id: 'ms3-vue-grid-fields-config-panel',
                        border: false,
                        autoHeight: true,
                        html: '<div id="ms3-grid-fields-config-vue-wrapper" class="vueApp" style="min-height: 600px;"></div>',
                        listeners: {
                            afterrender: function() {
                                // Mount Vue application after panel render
                                const event = new CustomEvent('ms3:mountVueGridFieldsConfig', {
                                    detail: {
                                        targetId: '#ms3-grid-fields-config-vue-wrapper'
                                    }
                                });
                                document.dispatchEvent(event);
                            }
                        }
                    }]
                },
                {
                    title: _('ms3_model_fields_title'),
                    id: 'ms3-utilities-model-fields-tab',
                    layout: 'fit',
                    autoScroll: true,
                    items: [{
                        xtype: 'panel',
                        id: 'ms3-vue-model-fields-panel',
                        border: false,
                        autoHeight: true,
                        html: '<div id="ms3-model-fields-vue-wrapper" class="vueApp" style="min-height: 600px;"></div>',
                        listeners: {
                            afterrender: function() {
                                // Mount Vue application after panel render
                                const event = new CustomEvent('ms3:mountVueModelFields', {
                                    detail: {
                                        targetId: '#ms3-model-fields-vue-wrapper'
                                    }
                                });
                                document.dispatchEvent(event);
                            }
                        }
                    }]
                }
                // Old ExtJS "Object Extension" tab removed - using new Vue widget instead
            ]
        }]

    });
    ms3.panel.Utilities.superclass.constructor.call(this, config);

    // fixExtraFieldsPanelHeight method removed along with old ExtJS widget
};
Ext.extend(ms3.panel.Utilities, MODx.Panel, {
    // Old methods for ExtJS extra fields removed
});
Ext.reg('ms3-panel-utilities', ms3.panel.Utilities);
