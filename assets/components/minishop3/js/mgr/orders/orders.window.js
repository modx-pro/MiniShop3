ms3.window.UpdateOrder = function (config) {
  config = config || {};

  Ext.applyIf(config, {
    title: _('ms3_menu_update'),
    width: 750,
    baseParams: {
      action: 'MiniShop3\\Processors\\Order\\Update',
    },
  });
  ms3.window.UpdateOrder.superclass.constructor.call(this, config);
};
Ext.extend(ms3.window.UpdateOrder, ms3.window.Default, {

  getFields: function (config) {
    return {
      xtype: 'modx-tabs',
      activeTab: config.activeTab || 0,
      bodyStyle: {background: 'transparent'},
      deferredRender: false,
      autoHeight: true,
      stateful: true,
      stateId: 'ms3-window-order-update',
      stateEvents: ['tabchange'],
      getState: function () {
        return {activeTab: this.items.indexOf(this.getActiveTab())};
      },
      items: this.getTabs(config)
    };
  },

  getTabs: function (config) {
    const tabs = [{
      title: _('ms3_order'),
      hideMode: 'offsets',
      defaults: {msgTarget: 'under', border: false},
      items: this.getOrderFields(config)
    }, {
      xtype: 'ms3-grid-order-products',
      title: _('ms3_order_products'),
      order_id: config.record.id
    }];

    const address = this.getAddressFields(config);
    if (address.length > 0) {
      tabs.push({
        layout: 'form',
        title: _('ms3_address'),
        hideMode: 'offsets',
        bodyStyle: 'padding:5px 0;',
        defaults: {msgTarget: 'under', border: false},
        items: address
      });
    }

    tabs.push({
      xtype: 'ms3-grid-order-logs',
      title: _('ms3_order_log'),
      order_id: config.record.id
    });

    return tabs;
  },

  getOrderFields: function (config) {
    console.log(config.record.user_id)
    const hiddenRow = {
      xtype: 'hidden',
      name: 'id'
    };


    const itemsFirstRow = [];

    if (config.record.register_user > 0) {
      itemsFirstRow.push({
        columnWidth: .5,
        layout: 'form',
        items: [{
          xtype: 'ms3-combo-user',
          name: 'user_id',
          fieldLabel: _('ms3_user'),
          anchor: '95%',
        }]
      })
    } else {
      itemsFirstRow.push({
        columnWidth: .5,
        layout: 'form',
        items: [{
          xtype: 'ms3-combo-customer',
          name: 'customer_id',
          fieldLabel: _('ms3_customer'),
          anchor: '95%',
        }]
      })
    }


    itemsFirstRow.push({
      columnWidth: .5,
      layout: 'form',
      items: [{
        xtype: 'displayfield',
        name: 'cost',
        fieldLabel: _('ms3_order_cost'),
        anchor: '100%',
        style: 'font-size:1.1em;'
      }]
    })

    const firstRow = {
      layout: 'column',
      defaults: {msgTarget: 'under', border: false},
      style: 'padding:15px 5px;text-align:center;',
      items: itemsFirstRow
    };

    const secondRow = {
      xtype: 'fieldset',
      layout: 'column',
      style: 'padding:15px 5px;text-align:center;',
      defaults: {msgTarget: 'under', border: false},
      items: [{
        columnWidth: .33,
        layout: 'form',
        items: [
          {xtype: 'displayfield', name: 'num', fieldLabel: _('ms3_num'), anchor: '100%'},
          {xtype: 'displayfield', name: 'cart_cost', fieldLabel: _('ms3_cart_cost'), anchor: '100%'}
        ]
      }, {
        columnWidth: .33,
        layout: 'form',
        items: [
          {xtype: 'displayfield', name: 'createdon', fieldLabel: _('ms3_createdon'), anchor: '100%'},
          {xtype: 'displayfield', name: 'delivery_cost', fieldLabel: _('ms3_delivery_cost'), anchor: '100%'}
        ]
      }, {
        columnWidth: .33,
        layout: 'form',
        items: [
          {xtype: 'displayfield', name: 'updatedon', fieldLabel: _('ms3_updatedon'), anchor: '100%'},
          {xtype: 'displayfield', name: 'weight', fieldLabel: _('ms3_weight'), anchor: '100%'}
        ]
      }]
    }

    const thirdRow = {
      layout: 'column',
      defaults: {msgTarget: 'under', border: false},
      anchor: '100%',
      items: [{
        columnWidth: .48,
        layout: 'form',
        items: [{
          xtype: 'ms3-combo-status',
          name: 'status',
          fieldLabel: _('ms3_status'),
          anchor: '100%',
          order_id: config.record.id
        }, {
          xtype: 'ms3-combo-delivery',
          name: 'delivery',
          fieldLabel: _('ms3_delivery'),
          anchor: '100%'
        }, {
          xtype: 'ms3-combo-payment',
          name: 'payment',
          fieldLabel: _('ms3_payment'),
          anchor: '100%',
          delivery_id: config.record.delivery
        }]
      }, {
        columnWidth: .5,
        layout: 'form',
        items: [
          {xtype: 'textarea', name: 'order_comment', fieldLabel: _('ms3_order_comment'), anchor: '100%', height: 170}
        ]
      }]
    }

    const fields = [];
    fields.push(hiddenRow)
    fields.push(firstRow)
    fields.push(secondRow)
    fields.push(thirdRow)
    return fields;
  },

  getAddressFields: function (config) {
    const all = {
      first_name: {},
      last_name: {},
      phone: {},
      email: {},
      index: {},
      country: {},
      region: {},
      metro: {},
      building: {},
      city: {},
      street: {},
      room: {},
      entrance: {},
      floor: {},
    };
    const fields = [], tmp = [];
    for (let i = 0; i < ms3.config['order_address_fields'].length; i++) {
      const field = ms3.config['order_address_fields'][i];
      if (all[field]) {
        Ext.applyIf(all[field], {
          xtype: 'textfield',
          name: 'addr_' + field,
          fieldLabel: _('ms3_' + field)
        });
        all[field].anchor = '100%';
        tmp.push(all[field]);
      }
    }

    const addx = function (w1, w2) {
      if (!w1) {
        w1 = .5;
      }
      if (!w2) {
        w2 = .5;
      }
      return {
        layout: 'column',
        defaults: {msgTarget: 'under', border: false},
        items: [
          {columnWidth: w1, layout: 'form', items: []},
          {columnWidth: w2, layout: 'form', items: []}
        ]
      };
    };

    let n;
    if (tmp.length > 0) {
      for (i = 0; i < tmp.length; i++) {
        if (i % 2===0) {
          fields.push(addx());
        }
        if (i <= 1) {
          n = 0;
        } else {
          n = Math.floor(i / 2);
        }
        fields[n].items[i % 2].items.push(tmp[i]);
      }
      if (ms3.config['order_address_fields'].in_array('text_address')) {
        fields.push(
            {
              xtype: 'textarea',
              name: 'addr_text_address',
              fieldLabel: _('ms3_text_address'),
              anchor: '98%',
              style: 'min-height: 50px;border:1px solid #efefef;width:95%;'
            }
        );
      }

      if (ms3.config['order_address_fields'].in_array('comment')) {
        fields.push(
            {
              xtype: 'displayfield',
              name: 'addr_comment',
              fieldLabel: _('ms3_comment'),
              anchor: '98%',
              style: 'min-height: 50px;border:1px solid #efefef;width:95%;'
            }
        );
      }

    }

    return fields;
  },

  getKeys: function () {
    return {
      key: Ext.EventObject.ENTER, shift: true, fn: function () {
        this.submit()
      }, scope: this
    }
  },

});
Ext.reg('ms3-window-order-update', ms3.window.UpdateOrder);
