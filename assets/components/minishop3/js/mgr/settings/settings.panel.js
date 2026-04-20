ms3.panel.Settings = function (config) {
    config = config || {};
    Ext.apply(config, {
        cls: 'container',
        items: [{
            html: '<h2>' + _('ms3_header') + ' :: ' + _('ms3_settings') + '</h2>',
            cls: 'modx-page-header',
        }, {
            xtype: 'modx-tabs',
            id: 'ms3-settings-tabs',
            stateful: true,
            stateId: 'ms3-settings-tabs',
            stateEvents: ['tabchange'],
            cls: 'ms3-panel',
            getState: function () {
                return {
                    activeTab: this.items.indexOf(this.getActiveTab())
                };
            },
            listeners: {
                tabchange: function (tabPanel, tab) {
                    window.location.hash = '#tab-' + tab.id;
                },
                render: function (tabPanel) {
                    let tabHash = window.location.hash.substring(1);
                    if (tabHash) {
                        let tabId = tabHash.replace("tab-", "");
                        let tab = tabPanel.get(tabId);
                        if (tab) {
                            tabPanel.setActiveTab(tab);
                        }
                    }
                }
            },
            items: [{
                title: _('ms3_deliveries'),
                layout: 'anchor',
                id: 'deliveries',
                items: [{
                    html: _('ms3_deliveries_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'container',
                    cls: 'main-wrapper',
                    html: '<div id="ms3-vue-deliveries" class="vueApp"></div>',
                }]
            }, {
                title: _('ms3_payments'),
                layout: 'anchor',
                id: 'payments',
                items: [{
                    html: _('ms3_payments_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'container',
                    cls: 'main-wrapper',
                    html: '<div id="ms3-vue-payments" class="vueApp"></div>',
                }]
            }, {
                title: _('ms3_statuses'),
                layout: 'anchor',
                id: 'statuses',
                items: [{
                    html: _('ms3_statuses_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'container',
                    cls: 'main-wrapper',
                    html: '<div id="ms3-vue-statuses" class="vueApp"></div>',
                }]
            }, {
                title: _('ms3_vendors'),
                layout: 'anchor',
                id: 'vendors',
                items: [{
                    html: _('ms3_vendors_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'container',
                    cls: 'main-wrapper',
                    html: '<div id="ms3-vue-vendors" class="vueApp"></div>',
                }]
            }, {
                title: _('ms3_links'),
                layout: 'anchor',
                id: 'links',
                items: [{
                    html: _('ms3_links_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'container',
                    cls: 'main-wrapper',
                    html: '<div id="ms3-vue-links" class="vueApp"></div>',
                }]
            }, {
                title: _('ms3_options'),
                layout: 'anchor',
                id: 'options',
                items: [{
                    html: _('ms3_options_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'container',
                    cls: 'main-wrapper',
                    html: '<div id="ms3-vue-options" class="vueApp"></div>',
                }]
            }]
        }]
    });
    ms3.panel.Settings.superclass.constructor.call(this, config);
};
Ext.extend(ms3.panel.Settings, MODx.Panel);
Ext.reg('ms3-panel-settings', ms3.panel.Settings);
