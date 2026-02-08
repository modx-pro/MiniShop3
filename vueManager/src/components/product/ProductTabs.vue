<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import TabPanel from 'primevue/tabpanel'
import TabView from 'primevue/tabview'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

import ProductDataFields from '../ProductDataFields.vue'

const props = defineProps({
  productId: {
    type: Number,
    required: true,
  },
  record: {
    type: Object,
    required: true,
  },
  config: {
    type: Object,
    default: () => ({}),
  },
})

const { _ } = useLexicon()
useToast() // Required for Toast component to work

// Active tab index
const activeTab = ref(0)

// Track mounted ExtJS components
const mountedExtComponents = ref({})

// Track if tabs are ready
const tabsReady = ref(false)

// Plugin Registry tabs
const pluginTabs = ref([])

// Configuration from ms3.config
const showGallery = computed(() => props.config.show_gallery !== false)
const showCategories = computed(() => props.config.show_categories !== false)
const showLinks = computed(() => props.config.show_links !== false)
const showOptions = computed(() => props.config.show_options !== false && hasOptions.value)
const hasOptions = computed(() => {
  return props.config.option_fields && props.config.option_fields.length > 0
})

// Define tab configuration
const tabConfig = computed(() => {
  const tabs = [
    {
      key: 'properties',
      title: _('ms3_tab_product_data'),
      type: 'vue',
      component: 'ProductDataFields',
      position: 0,
    },
  ]

  if (showGallery.value) {
    tabs.push({
      key: 'gallery',
      title: _('ms3_tab_product_gallery'),
      type: 'extjs',
      xtype: 'ms3-gallery-page',
      extConfig: {
        record: props.record,
        pageSize: 50,
        border: false,
      },
      position: 1,
    })
  }

  if (showCategories.value) {
    tabs.push({
      key: 'categories',
      title: _('ms3_tab_product_categories'),
      type: 'extjs',
      xtype: 'ms3-tree-categories',
      extConfig: {
        parent: props.record.parent || 0,
        resource: props.record.id || 0,
        categories: props.record.categories || [],
      },
      position: 2,
    })
  }

  if (showLinks.value) {
    tabs.push({
      key: 'links',
      title: _('ms3_tab_product_links'),
      type: 'extjs',
      xtype: 'ms3-product-links',
      extConfig: {
        record: props.record,
      },
      position: 3,
    })
  }

  if (showOptions.value) {
    tabs.push({
      key: 'options',
      title: _('ms3_tab_product_options'),
      type: 'extjs-options',
      position: 4,
    })
  }

  // Add plugin tabs with record injected
  pluginTabs.value.forEach(pluginTab => {
    const tab = { ...pluginTab }

    // For ExtJS plugin tabs, inject record into extConfig
    if (tab.type === 'extjs' || tab.type === 'plugin-extjs') {
      tab.extConfig = {
        record: props.record,
        ...tab.extConfig,
      }
    }

    tabs.push(tab)
  })

  // Sort by position
  return tabs.sort((a, b) => (a.position || 0) - (b.position || 0))
})

/**
 * Wait for element to appear in DOM
 */
function waitForElement(id, callback, maxAttempts = 20) {
  let attempts = 0
  const check = () => {
    const element = document.getElementById(id)
    if (element) {
      callback(element)
    } else if (attempts < maxAttempts) {
      attempts++
      setTimeout(check, 50)
    } else {
      console.warn(`[ProductTabs] Element #${id} not found after ${maxAttempts} attempts`)
    }
  }
  check()
}

/**
 * Mount ExtJS component in container
 */
function mountExtJS(tabKey, tabData) {
  if (mountedExtComponents.value[tabKey]) {
    // Already mounted
    return
  }

  const containerId = `ms3-product-tab-${tabKey}`

  waitForElement(containerId, container => {
    try {
      // Check if Ext is available
      if (typeof Ext === 'undefined') {
        console.error('[ProductTabs] Ext is not defined')
        return
      }

      const extComponent = Ext.create({
        xtype: tabData.xtype,
        renderTo: container,
        width: '100%',
        ...tabData.extConfig,
      })

      mountedExtComponents.value[tabKey] = extComponent

      // For gallery panel - manually call initialize() to init Vue uploader
      // and fix source combo value
      if (tabKey === 'gallery' && typeof extComponent.initialize === 'function') {
        setTimeout(() => {
          extComponent.initialize()

          // Fix source combo - set value after store loads
          const sourceCombo = Ext.getCmp('ms3-resource-source')
          if (sourceCombo && tabData.extConfig.record) {
            const sourceValue =
              tabData.extConfig.record.source || tabData.extConfig.record.source_id
            if (sourceValue && sourceCombo.store) {
              if (sourceCombo.store.getCount() > 0) {
                sourceCombo.setValue(sourceValue)
              } else {
                sourceCombo.store.on(
                  'load',
                  function () {
                    sourceCombo.setValue(sourceValue)
                  },
                  null,
                  { single: true }
                )
                if (!sourceCombo.store.isLoading) {
                  sourceCombo.store.load()
                }
              }
            }
          }
        }, 100)
      }
    } catch (error) {
      console.error(`[ProductTabs] Failed to mount ExtJS component ${tabKey}:`, error)
    }
  })
}

/**
 * Mount Options tab with vertical tabs (special handling)
 */
function mountOptionsTab() {
  if (mountedExtComponents.value['options']) {
    return
  }

  const containerId = 'ms3-product-tab-options'

  waitForElement(containerId, container => {
    try {
      if (typeof Ext === 'undefined') {
        console.error('[ProductTabs] Ext is not defined')
        return
      }

      // Build option groups from config
      const options = props.config.option_fields || []
      const optionGroups = []

      for (let i = 0; i < options.length; i++) {
        const option = options[i]
        const field = ms3.utils.getExtField(
          { record: props.record, mode: 'update' },
          option.key,
          option,
          'extra-field'
        )

        if (!field) continue

        let found = false
        for (let j = 0; j < optionGroups.length; j++) {
          if (optionGroups[j].category === option.category) {
            optionGroups[j].items.push(field)
            found = true
            break
          }
        }

        if (!found) {
          optionGroups.push({
            id: 'ms3-options-tab-' + option.category,
            layout: 'form',
            labelAlign: 'top',
            category: option.category,
            title: option.category_name || _('ms3_ft_nogroup'),
            bodyCssClass: 'main-wrapper',
            items: [field],
          })
        }
      }

      if (optionGroups.length === 0) {
        return
      }

      const vtabs = Ext.create({
        xtype: 'modx-vtabs',
        renderTo: container,
        autoTabs: true,
        border: false,
        plain: true,
        deferredRender: false,
        id: 'ms3-options-vtabs-vue',
        items: optionGroups,
      })

      mountedExtComponents.value['options'] = vtabs
    } catch (error) {
      console.error('[ProductTabs] Failed to mount Options tab:', error)
    }
  })
}

/**
 * Handle tab change - mount ExtJS components lazily
 */
function onTabChange(event) {
  const newIndex = event.index
  const currentTab = tabConfig.value[newIndex]

  if (!currentTab) return

  // Use nextTick to ensure Vue has rendered the tab content
  nextTick(() => {
    if (currentTab.type === 'extjs' && !mountedExtComponents.value[currentTab.key]) {
      mountExtJS(currentTab.key, currentTab)
    } else if (currentTab.type === 'extjs-options' && !mountedExtComponents.value['options']) {
      mountOptionsTab()
    }
  })
}

/**
 * Destroy all mounted ExtJS components
 */
function destroyExtComponents() {
  Object.keys(mountedExtComponents.value).forEach(key => {
    const component = mountedExtComponents.value[key]
    if (component && typeof component.destroy === 'function') {
      try {
        component.destroy()
      } catch (e) {
        console.warn(`[ProductTabs] Error destroying component ${key}:`, e)
      }
    }
  })
  mountedExtComponents.value = {}
}

/**
 * Register plugin tab
 */
function registerPluginTab(tabData) {
  // Validate tab data
  if (!tabData.key || !tabData.title) {
    console.warn('[ProductTabs] Invalid plugin tab data:', tabData)
    return false
  }

  // Check for duplicates
  const exists = pluginTabs.value.some(t => t.key === tabData.key)
  if (exists) {
    console.warn(`[ProductTabs] Tab with key "${tabData.key}" already registered`)
    return false
  }

  pluginTabs.value.push({
    key: tabData.key,
    title: tabData.title,
    type: tabData.type || 'vue',
    component: tabData.component,
    xtype: tabData.xtype,
    extConfig: tabData.extConfig || {},
    props: tabData.props || {},
    position: tabData.position || 100,
  })

  return true
}

// Expose register method for plugin registry
defineExpose({
  registerPluginTab,
  getActiveTab: () => activeTab.value,
})

onMounted(() => {
  tabsReady.value = true

  // Register this instance in global registry
  if (window.MS3ProductTabsRegistry) {
    window.MS3ProductTabsRegistry._instance = {
      registerPluginTab,
    }
  }
})

onBeforeUnmount(() => {
  destroyExtComponents()

  if (window.MS3ProductTabsRegistry) {
    window.MS3ProductTabsRegistry._instance = null
  }
})
</script>

<template>
  <div class="product-tabs">
    <Toast />

    <TabView v-if="tabsReady" v-model:activeIndex="activeTab" @tab-change="onTabChange">
      <TabPanel v-for="tab in tabConfig" :key="tab.key" :header="tab.title">
        <!-- Vue component: ProductDataFields -->
        <template v-if="tab.type === 'vue' && tab.component === 'ProductDataFields'">
          <ProductDataFields :product-id="productId" :product-data="record" />
        </template>

        <!-- ExtJS component container -->
        <template v-else-if="tab.type === 'extjs'">
          <div :id="`ms3-product-tab-${tab.key}`" class="extjs-container"></div>
        </template>

        <!-- ExtJS Options with vtabs -->
        <template v-else-if="tab.type === 'extjs-options'">
          <div id="ms3-product-tab-options" class="extjs-container extjs-options-container"></div>
        </template>

        <!-- Plugin Vue component -->
        <template v-else-if="tab.type === 'vue' && tab.component">
          <component
            :is="tab.component"
            v-bind="tab.props"
            :product-id="productId"
            :record="record"
          />
        </template>

        <!-- Plugin ExtJS component -->
        <template v-else-if="tab.type === 'plugin-extjs'">
          <div :id="`ms3-product-tab-${tab.key}`" class="extjs-container"></div>
        </template>
      </TabPanel>
    </TabView>
  </div>
</template>

<style scoped>
.product-tabs {
  width: 100%;
  min-height: 25rem;
}

.extjs-container {
  min-height: 18.75rem;
  width: 100%;
}

.extjs-options-container {
  min-height: 25rem;
}

/* Ensure ExtJS components fill their containers */
.extjs-container :deep(.x-panel) {
  width: 100% !important;
}

/* Fix padding for ExtJS panels inside Vue tabs */
.extjs-container :deep(.x-panel-body) {
  padding: 0.625rem;
}

/* Gallery specific styles */
#ms3-product-tab-gallery :deep(.x-panel) {
  background: transparent;
}

/* Categories tree styles */
#ms3-product-tab-categories :deep(.x-tree-view) {
  min-height: 18.75rem;
}

/* Links grid styles */
#ms3-product-tab-links :deep(.x-grid-view) {
  min-height: 12.5rem;
}
</style>
