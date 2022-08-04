minishop.panel.Settings = function (config) {
    config = config || {};
    Ext.apply(config, {
        cls: 'container',
        items: [{
            html: '<h2>' + _('minishop') + ' :: ' + _('ms_settings') + '</h2>',
            cls: 'modx-page-header',
        }, {
            xtype: 'modx-tabs',
            id: 'minishop-settings-tabs',
            stateful: true,
            stateId: 'minishop-settings-tabs',
            stateEvents: ['tabchange'],
            cls: 'minishop-panel',
            getState: function () {
                return {
                    activeTab: this.items.indexOf(this.getActiveTab())
                };
            },
            items: [{
                title: _('ms_deliveries'),
                layout: 'anchor',
                items: [{
                    html: _('ms_deliveries_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'minishop-grid-delivery',
                    cls: 'main-wrapper',
                }]
            }, {
                title: _('ms_payments'),
                layout: 'anchor',
                items: [{
                    html: _('ms_payments_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'minishop-grid-payment',
                    cls: 'main-wrapper',
                }]
            }, {
                title: _('ms_statuses'),
                layout: 'anchor',
                items: [{
                    html: _('ms_statuses_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'minishop-grid-status',
                    cls: 'main-wrapper',
                }]
            }, {
                title: _('ms_vendors'),
                layout: 'anchor',
                items: [{
                    html: _('ms_vendors_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'minishop-grid-vendor',
                    cls: 'main-wrapper',
                }]
            }, {
                title: _('ms_links'),
                layout: 'anchor',
                items: [{
                    html: _('ms_links_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    xtype: 'minishop-grid-link',
                    cls: 'main-wrapper',
                }]
            }, {
                title: _('ms_options'),
                layout: 'anchor',
                items: [{
                    html: _('ms_options_intro'),
                    bodyCssClass: 'panel-desc',
                }, {
                    layout: 'column',
                    cls: 'main-wrapper',
                    items: [{
                        xtype: 'minishop-tree-option-categories',
                        optionGrid: 'minishop-grid-option',
                        columnWidth: .25
                    }, {
                        xtype: 'minishop-grid-option',
                        columnWidth: .75,
                    }]
                }]
            }]
        }]
    });
    minishop.panel.Settings.superclass.constructor.call(this, config);
};
Ext.extend(minishop.panel.Settings, MODx.Panel);
Ext.reg('minishop-panel-settings', minishop.panel.Settings);
