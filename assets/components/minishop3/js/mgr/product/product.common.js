ms3.panel.Product = function (config) {
  config = config || {}
  ms3.panel.Product.superclass.constructor.call(this, config)
}
Ext.extend(ms3.panel.Product, MODx.panel.Resource, {
  /**
   * Save Vue product data BEFORE the ExtJS form is submitted.
   *
   * `beforeSave` does not exist in MODX — the real hook is the `submit()`
   * method of `MODx.FormPanel` (`fireEvent('beforeSubmit') && fm.submit(...)`).
   * `beforeSubmit` is synchronous, so the async Vue save cannot run inside it:
   * defer the first submit, save the Vue tab, then call the parent once.
   *
   * An in-flight save is cached so repeated Save clicks cannot bypass the Vue
   * step or double-submit. If the Vue save fails, the resource form is NOT
   * submitted: a partial save would silently lose the product data (#871).
   */
  submit: function (o) {
    const parentSubmit = ms3.panel.Product.superclass.submit
    if (this.__ms3ProductDataPromise) {
      // A save is already in progress; ignore additional clicks.
      return false
    }
    if (typeof window.MS3_saveProductData === 'function') {
      const form = typeof this.getForm === 'function' ? this.getForm() : null
      if (form && typeof form.isValid === 'function' && !form.isValid()) {
        // Let the parent submit display the validation errors; do not save Vue data.
        return parentSubmit.call(this, o)
      }
      const self = this
      this.__ms3ProductDataPromise = window.MS3_saveProductData()
      this.__ms3ProductDataPromise.then(
        function (result) {
          self.__ms3ProductDataPromise = null
          if (result && result.ok === false) {
            console.warn('[ProductPanel] Vue product data not saved:', result.message || result)
            return
          }
          parentSubmit.call(self, o)
        },
        function (error) {
          self.__ms3ProductDataPromise = null
          console.error('[ProductPanel] Vue product data save failed:', error)
        },
      )
      return false
    }
    return parentSubmit.call(this, o)
  },

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
