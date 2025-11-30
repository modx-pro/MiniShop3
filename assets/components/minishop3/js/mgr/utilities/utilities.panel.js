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
                layout: 'anchor',
                items: [{
                    html: _('ms3_utilities_gallery_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'ms3-utilities-gallery',
                    cls: 'main-wrapper',
                }]
            },
                {
                    title: _('ms3_utilities_import'),
                    layout: 'anchor',
                    items: [{
                        html: _('ms3_utilities_import_intro'),
                        bodyCssClass: 'panel-desc',
                    }, {
                        xtype: 'ms3-utilities-import',
                        cls: 'main-wrapper',
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
                                // Монтируем Vue приложение после рендера панели
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
                    title: 'Расширение объектов',
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
                                // Монтируем Vue приложение после рендера панели
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
                                // Монтируем Vue приложение после рендера панели
                                const event = new CustomEvent('ms3:mountVueGridFieldsConfig', {
                                    detail: {
                                        targetId: '#ms3-grid-fields-config-vue-wrapper'
                                    }
                                });
                                document.dispatchEvent(event);
                            }
                        }
                    }]
                }
                // Старая ExtJS вкладка "Расширение объектов" удалена - используется новый Vue виджет
            ]
        }]

    });
    ms3.panel.Utilities.superclass.constructor.call(this, config);

    // fixExtraFieldsPanelHeight метод удалён вместе со старым ExtJS виджетом
};
Ext.extend(ms3.panel.Utilities, MODx.Panel, {
    // Старые методы для ExtJS extra fields удалены
});
Ext.reg('ms3-panel-utilities', ms3.panel.Utilities);
