let ms3 = function (config) {
    config = config || {};
    ms3.superclass.constructor.call(this, config);
};
Ext.extend(ms3, Ext.Component, {
    page: {}, window: {}, grid: {}, tree: {}, panel: {}, combo: {}, config: {}, view: {}, keymap: {}, plugin: {},
});
Ext.reg('ms3', ms3);

ms3 = new ms3();
