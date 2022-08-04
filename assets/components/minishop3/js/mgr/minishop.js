let minishop = function (config) {
    config = config || {};
    minishop.superclass.constructor.call(this, config);
};
Ext.extend(minishop, Ext.Component, {
    page: {}, window: {}, grid: {}, tree: {}, panel: {}, combo: {}, config: {}, view: {}, keymap: {}, plugin: {},
});
Ext.reg('minishop', minishop);

minishop = new minishop();
