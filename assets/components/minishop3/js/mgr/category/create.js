ms3.page.CreateCategory = function (config) {
    config = config || {record: {}};
    config.record = config.record || {};
    console.log('ms3.page.CreateCategory')

    Ext.applyIf(config, {
        panelXType: 'ms3-panel-category-create',
        mode: 'create'
    });
    ms3.page.CreateCategory.superclass.constructor.call(this, config);
};
Ext.extend(ms3.page.CreateCategory, MODx.page.CreateResource, {

    getButtons: function (config) {
        const buttons = [];
        const originals = MODx.page.CreateResource.prototype.getButtons.call(this, config);
        for (const i in originals) {
            if (!originals.hasOwnProperty(i)) {
                continue;
            }
            const button = originals[i];
            switch (button.id) {
                case 'modx-abtn-save':
                    button.text = '<i class="icon icon-check"></i> ' + button.text;
                    break;
                case 'modx-abtn-cancel':
                    button.text = '<i class="icon icon-ban"></i> ' + button.text;
                    button.handler = this.cancel;
                    break;
                case 'modx-abtn-help':
                    button.text = '<i class="icon icon-question-circle"></i>';
                    break;
            }
            buttons.push(button)
        }

        return buttons;
    },

    cancel: function () {
        const id = MODx.request.parent;
        const action = id != 0
            ? 'resource/update'
            : 'welcome';

        MODx.loadPage(action, 'id=' + id)
    },

});
Ext.reg('ms3-page-category-create', ms3.page.CreateCategory);


ms3.panel.CreateCategory = function (config) {
    config = config || {};
    ms3.panel.CreateCategory.superclass.constructor.call(this, config);
};
Ext.extend(ms3.panel.CreateCategory, ms3.panel.Category);
Ext.reg('ms3-panel-category-create', ms3.panel.CreateCategory);
