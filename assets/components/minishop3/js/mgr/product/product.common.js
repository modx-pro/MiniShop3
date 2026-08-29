ms3.panel.Product = function (config) {
  config = config || {}
  ms3.panel.Product.superclass.constructor.call(this, config)
}
Ext.extend(ms3.panel.Product, MODx.panel.Resource, {
  getFields: function (config) {
    const fields = []
    const originals = MODx.panel.Resource.prototype.getFields.call(this, config)
    const thumbPanel = {
      anchor: '100%',
      cls: 'modx-resource-panel',
      collapsible: false,
      id: 'ms3-product-image-panel',
      items: [
        {
          xtype: 'displayfield',
          id: 'ms3-product-image-wrap',
          html: String.format(
            '<img src="{0}" id="ms3-product-image"/>',
            config.record['thumb'] || ms3.config.default_thumb
          ),
          style: { 'textAlign': 'center' }
        }
      ],
      labelSeparator: '',
      layout: 'form'

    }

    for (const i in originals) {
      if (!originals.hasOwnProperty(i)) {
        continue
      }
      const item = originals[i]

      if (item.id === 'modx-header-breadcrumbs') {
        item.items[0].html = '<h2>' + _('ms3_product_new') + '</h2>'
        fields.push(item)
      } else if (item.id === 'modx-resource-tabs') {
        item.stateful = parseInt(MODx.config.ms3_product_remember_tabs) === 1
        item.stateId = 'ms3-product-' + config.mode + '-tabpanel'
        item.stateEvents = ['tabchange']
        item.collapsible = false
        item.getState = function () {
          return { activeTab: this.items.indexOf(this.getActiveTab()) }
        }

        const tabs = []

        item.items.forEach((tab, key) => {
          switch (tab.id) {
            case 'modx-resource-settings':
              tab.items.forEach((tabItem, key_ti) => {
                switch (tabItem.id) {
                  case 'modx-resource-main-columns':
                    tabItem.items.forEach((column, key_c) => {
                      switch (column.id) {
                        case 'modx-resource-main-left':
                          break
                        case 'modx-resource-main-right':
                          item.items[key].items[key_ti].items[key_c].items.unshift(thumbPanel)
                          break
                      }
                    })
                    break
                }
              })
              tabs.push(tab)

              // Vue tab "Product" with nested tabs (Properties, Gallery, Categories, Links, Options)
              // Only show for existing products (not in create mode)
              if (config.mode !== 'create') {
                tabs.push(this.getProductTab(config))
              }

              break
            case 'modx-page-settings':
            default:
              tabs.push(tab)
              break
          }

        })

        item.items = tabs
        fields.push(item)
      }
    }
    return fields

  },

  /**
   * Get Product tab with nested Vue TabView
   * Contains: Properties, Gallery, Categories, Links, Options
   */
  getProductTab: function (config) {
    return {
      title: _('ms3_tab_product'),
      id: 'ms3-product-tab',
      layout: 'fit',
      items: [{
        xtype: 'panel',
        border: false,
        id: 'ms3-vue-product-tabs-panel',
        html: '<div id="ms3-vue-product-tabs" class="vueApp"></div>',
        listeners: {
          afterrender: function () {
            // Dispatch event to mount Vue ProductTabs application
            const event = new CustomEvent('ms3:mountProductTabs', {
              detail: {
                targetId: 'ms3-vue-product-tabs',
                productId: config.record.id,
                record: config.record,
                config: {
                  show_gallery: ms3.config.show_gallery,
                  show_categories: ms3.config.show_categories,
                  show_links: ms3.config.show_links,
                  show_options: ms3.config.show_options,
                  option_fields: ms3.config.option_fields || [],
                  media_source: ms3.config.media_source || {},
                  connector_url: ms3.config.connector_url
                }
              }
            })
            document.dispatchEvent(event)
          }
        }
      }]
    }
  }
})
