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
            }
            ]
        }]

    });
    ms3.panel.Utilities.superclass.constructor.call(this, config);
};
Ext.extend(ms3.panel.Utilities, MODx.Panel);
Ext.reg('ms3-utilities', ms3.panel.Utilities);
