<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import { Button, Tab, TabList, TabPanel, TabPanels, Tabs } from 'primevue'
import { ref, watch } from 'vue'

import { getMs3Config } from '../utils/modx.js'
import ExtraFieldsManager from './ExtraFieldsManager.vue'
import FieldsManagement from './FieldsManagement.vue'
import GridFieldsConfig from './GridFieldsConfig.vue'
import ImportProducts from './ImportProducts.vue'
import ModelFieldsGrid from './ModelFieldsGrid.vue'
import UtilitiesGallery from './UtilitiesGallery.vue'

const { _ } = useLexicon()

const STORAGE_KEY = 'ms3-utilities-active-tab'

/** Ext panel stored component ids; map to Vue tab ids. */
const LEGACY_TAB_IDS = {
  'ms3-utilities-gallery-tab': 'gallery',
  'ms3-utilities-import-tab': 'import',
  'ms3-utilities-fields-management-tab': 'fields-management',
  'ms3-utilities-extra-fields-tab': 'extra-fields',
  'ms3-utilities-grid-fields-config-tab': 'grid-fields-config',
  'ms3-utilities-model-fields-tab': 'model-fields',
}

const tabs = [
  {
    id: 'gallery',
    titleKey: 'ms3_utilities_gallery',
    component: UtilitiesGallery,
  },
  {
    id: 'import',
    titleKey: 'ms3_utilities_import',
    component: ImportProducts,
  },
  {
    id: 'fields-management',
    titleKey: 'ms3_vue_product_fields_title',
    component: FieldsManagement,
  },
  {
    id: 'extra-fields',
    titleKey: 'ms3_extra_fields_title',
    component: ExtraFieldsManager,
  },
  {
    id: 'grid-fields-config',
    titleKey: 'grid_fields_config_title',
    component: GridFieldsConfig,
  },
  {
    id: 'model-fields',
    titleKey: 'ms3_model_fields_title',
    component: ModelFieldsGrid,
  },
]

const TAB_IDS = tabs.map(tab => tab.id)
const DEFAULT_TAB = TAB_IDS[0]

function tabIdFromStorage() {
  try {
    const saved = localStorage.getItem(STORAGE_KEY)
    if (!saved) {
      return DEFAULT_TAB
    }
    if (TAB_IDS.includes(saved)) {
      return saved
    }
    return LEGACY_TAB_IDS[saved] || DEFAULT_TAB
  } catch {
    return DEFAULT_TAB
  }
}

const activeTab = ref(tabIdFromStorage())
const visited = ref({ [activeTab.value]: true })
const canListSettings = Boolean(getMs3Config()?.mssetting_list)

watch(activeTab, id => {
  visited.value[id] = true
  try {
    localStorage.setItem(STORAGE_KEY, id)
  } catch {
    // ignore quota / private mode
  }
})

function goTo(path) {
  window.location.href = path
}
</script>

<template>
  <div class="ms3-utilities-page">
    <div class="ms3-utilities-page__header">
      <h2 class="ms3-utilities-page__title">
        {{ _('ms3_header') }} :: {{ _('ms3_utilities') }}
      </h2>
      <div v-if="canListSettings" class="ms3-utilities-page__actions">
        <Button
          :label="_('ms3_orders')"
          severity="secondary"
          @click="goTo('?a=mgr/orders&namespace=minishop3')"
        />
        <Button
          :label="_('ms3_settings')"
          severity="secondary"
          @click="goTo('?a=mgr/settings&namespace=minishop3')"
        />
      </div>
    </div>

    <Tabs v-model:value="activeTab" class="ms3-utilities-page__tabs">
      <TabList>
        <Tab v-for="tab in tabs" :key="tab.id" :value="tab.id">
          {{ _(tab.titleKey) }}
        </Tab>
      </TabList>
      <TabPanels>
        <TabPanel v-for="tab in tabs" :key="tab.id" :value="tab.id">
          <component :is="tab.component" v-if="visited[tab.id]" />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
/*
 * Page chrome inset + Modx tabpanel padding (15px / 0.9375rem all sides).
 * Do not zero horizontal/bottom on .p-tabpanels — content must not sit flush
 * against the white panel edges (theme default tabs.tabpanel.padding).
 */
.ms3-utilities-page {
  padding: var(--p-modx-space-panel, 15px);
    box-sizing: border-box;
}

.ms3-utilities-page__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0;
    margin-bottom: 0.75rem;
}

.ms3-utilities-page__title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: #333;
}

.ms3-utilities-page__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}
.ms3-utilities-page__tabs :deep(.p-tabpanels) {
  padding: var(--p-tabs-tabpanel-padding, var(--p-modx-space-panel, 15px));
}

.ms3-utilities-page__tabs :deep(.p-tabpanel) {
  padding: 0;
}

/* Shared vertical rhythm for every utilities tab body. */
.ms3-utilities-page__tabs :deep(.tab-description) {
  margin: 0 0 var(--p-modx-space-panel, 15px);
}

.ms3-utilities-page__tabs :deep(.ms3-config-panel + .ms3-config-panel),
.ms3-utilities-page__tabs :deep(.p-card + .p-card) {
  margin-top: 0;
}
</style>
