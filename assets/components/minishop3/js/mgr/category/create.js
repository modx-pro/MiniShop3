minishop.page.CreateCategory = function (config) {
    config = config || {record: {}};
    config.record = config.record || {};

    Ext.applyIf(config, {
        panelXType: 'minishop-panel-category-create',
        mode: 'create'
    });
    minishop.page.CreateCategory.superclass.constructor.call(this, config);
};
Ext.extend(minishop.page.CreateCategory, MODx.page.CreateResource, {

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
Ext.reg('minishop-page-category-create', minishop.page.CreateCategory);


minishop.panel.CreateCategory = function (config) {
    config = config || {};
    minishop.panel.CreateCategory.superclass.constructor.call(this, config);
};
Ext.extend(minishop.panel.CreateCategory, minishop.panel.Category);
Ext.reg('minishop-panel-category-create', minishop.panel.CreateCategory);
