<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { onMounted, onUnmounted, ref, watch } from 'vue'

import DeliveriesGrid from './DeliveriesGrid.vue'
import LinksGrid from './LinksGrid.vue'
import OptionsAndGroupsTabs from './OptionsAndGroupsTabs.vue'
import PaymentsGrid from './PaymentsGrid.vue'
import StatusesGrid from './StatusesGrid.vue'
import VendorsGrid from './VendorsGrid.vue'

const { _ } = useLexicon()

const tabs = [
  {
    id: 'deliveries',
    titleKey: 'ms3_deliveries',
    introKey: 'ms3_deliveries_intro',
    component: DeliveriesGrid,
  },
  {
    id: 'payments',
    titleKey: 'ms3_payments',
    introKey: 'ms3_payments_intro',
    component: PaymentsGrid,
  },
  {
    id: 'statuses',
    titleKey: 'ms3_statuses',
    introKey: 'ms3_statuses_intro',
    component: StatusesGrid,
  },
  {
    id: 'vendors',
    titleKey: 'ms3_vendors',
    introKey: 'ms3_vendors_intro',
    component: VendorsGrid,
  },
  {
    id: 'links',
    titleKey: 'ms3_links',
    introKey: 'ms3_links_intro',
    component: LinksGrid,
  },
  {
    id: 'options',
    titleKey: 'ms3_options',
    introKey: 'ms3_options_intro',
    component: OptionsAndGroupsTabs,
  },
]

const TAB_IDS = tabs.map(tab => tab.id)
const DEFAULT_TAB = TAB_IDS[0]

function tabIdFromHash() {
  const match = /^#tab-(.+)$/.exec(window.location.hash)
  return match && TAB_IDS.includes(match[1]) ? match[1] : null
}

const activeTab = ref(tabIdFromHash() || DEFAULT_TAB)
const visited = ref({ [activeTab.value]: true })

// Controller injects msorder_list into ms3.config (#523).
const canListOrders =
  typeof ms3 !== 'undefined' && Boolean(ms3.config?.msorder_list)

watch(activeTab, id => {
  visited.value[id] = true
  const nextHash = `#tab-${id}`
  if (window.location.hash !== nextHash) {
    window.location.hash = nextHash
  }
})

function onHashChange() {
  const id = tabIdFromHash()
  if (id && id !== activeTab.value) {
    activeTab.value = id
  }
}

onMounted(() => {
  window.addEventListener('hashchange', onHashChange)
})

onUnmounted(() => {
  window.removeEventListener('hashchange', onHashChange)
})

function navigateTo(path) {
  window.location.href = path
}
</script>

<template>
  <div class="ms3-settings-page">
    <div class="ms3-settings-page__header">
      <h2 class="ms3-settings-page__title">
        {{ _('ms3_header') }} :: {{ _('ms3_settings') }}
      </h2>
      <div v-if="canListOrders" class="ms3-settings-page__actions">
        <Button
          :label="_('ms3_orders')"
          class="p-button-sm"
          severity="contrast"
          @click="navigateTo('?a=mgr/orders&namespace=minishop3')"
        />
        <Button
          :label="_('ms3_utilities')"
          class="p-button-sm p-button-secondary"
          @click="navigateTo('?a=mgr/utilities&namespace=minishop3')"
        />
      </div>
    </div>

    <Tabs v-model:value="activeTab" class="ms3-settings-page__tabs">
      <TabList>
        <Tab v-for="tab in tabs" :key="tab.id" :value="tab.id">
          {{ _(tab.titleKey) }}
        </Tab>
      </TabList>
      <TabPanels>
        <TabPanel v-for="tab in tabs" :key="tab.id" :value="tab.id">
          <!-- eslint-disable-next-line vue/no-v-html -->
          <p class="ms3-settings-page__intro" v-html="_(tab.introKey)" />
          <component :is="tab.component" v-if="visited[tab.id]" />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.ms3-settings-page {
  padding: 1.25rem;
}

.ms3-settings-page__header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 1rem;
}

.ms3-settings-page__title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
  color: #333;
}

.ms3-settings-page__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.ms3-settings-page__intro {
  margin: 0 0 1rem;
  padding: 0.75rem 1rem;
  color: #555;
  font-size: 0.875rem;
  line-height: 1.45;
  background: #f6f6f6;
  border-left: 3px solid #ccc;
}
</style>
