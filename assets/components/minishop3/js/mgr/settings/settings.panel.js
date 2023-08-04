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
            items: [{
                title: _('ms3_deliveries'),
                layout: 'anchor',
                items: [{
                    html: _('ms3_deliveries_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'ms3-grid-delivery',
                    cls: 'main-wrapper',
                }]
            }, {
                title: _('ms3_payments'),
                layout: 'anchor',
                items: [{
                    html: _('ms3_payments_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'ms3-grid-payment',
                    cls: 'main-wrapper',
                }]
            }, {
                title: _('ms3_statuses'),
                layout: 'anchor',
                items: [{
                    html: _('ms3_statuses_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'ms3-grid-status',
                    cls: 'main-wrapper',
                }]
            }, {
                title: _('ms3_vendors'),
                layout: 'anchor',
                items: [{
                    html: _('ms3_vendors_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'ms3-grid-vendor',
                    cls: 'main-wrapper',
                }]
            }, {
                title: _('ms3_links'),
                layout: 'anchor',
                items: [{
                    html: _('ms3_links_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'ms3-grid-link',
                    cls: 'main-wrapper',
                }]
            }, {
                title: _('ms3_options'),
                layout: 'anchor',
                items: [{
                    html: _('ms3_options_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    layout: 'column',
                    cls: 'main-wrapper',
                    items: [{
                        xtype: 'ms3-tree-option-categories',
                        optionGrid: 'ms3-grid-option',
                        columnWidth: .25
                    }, {
                        xtype: 'ms3-grid-option',
                        columnWidth: .75,
                    }]
                }]
            }]
        }]
    });
    ms3.panel.Settings.superclass.constructor.call(this, config);
};
Ext.extend(ms3.panel.Settings, MODx.Panel);
Ext.reg('ms3-panel-settings', ms3.panel.Settings);
