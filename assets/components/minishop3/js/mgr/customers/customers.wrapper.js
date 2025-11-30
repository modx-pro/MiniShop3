/**
 * Простой HTML wrapper для Vue приложения управления клиентами
 *
 * Создает контейнер для монтирования Vue приложения
 */

// Создаем простой компонент для MODX
Ext.reg('ms3-customers-vue-wrapper', Ext.extend(Ext.Component, {
    initComponent: function() {
        Ext.apply(this, {
            html: '<div id="ms3-customers-vue-wrapper" class="vueApp" style="padding: 20px; height: 100%;"></div>'
        });

        this.constructor.superclass.initComponent.call(this);
    }
}));
