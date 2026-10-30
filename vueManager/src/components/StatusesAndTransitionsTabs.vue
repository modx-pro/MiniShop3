<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import { Tab, TabList, TabPanel, TabPanels, Tabs } from 'primevue'
import { ref } from 'vue'

import StatusesGrid from './StatusesGrid.vue'
import StatusTransitionsMatrix from './StatusTransitionsMatrix.vue'

/**
 * Statuses settings: list CRUD + transition allow-list matrix (#785).
 */

const { _ } = useLexicon()

const STORAGE_KEY = 'ms3-statuses-active-tab'
const matrixRef = ref(null)

function loadInitialTab() {
  try {
    const v = localStorage.getItem(STORAGE_KEY)
    return v === '1' ? '1' : '0'
  } catch {
    return '0'
  }
}

const activeTab = ref(loadInitialTab())

function onTabChange(value) {
  try {
    localStorage.setItem(STORAGE_KEY, String(value))
  } catch {
    // ignore quota / private-mode errors
  }
  // Reload matrix when opening Transitions so newly created statuses appear (#785 review).
  if (String(value) === '1') {
    matrixRef.value?.loadMatrix?.()
  }
}
</script>

<template>
  <div class="ms3-statuses-tabs">
    <Tabs v-model:value="activeTab" @update:value="onTabChange">
      <TabList>
        <Tab value="0">{{ _('ms3_statuses') }}</Tab>
        <Tab value="1">{{ _('ms3_status_transitions') }}</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="0">
          <StatusesGrid />
        </TabPanel>
        <TabPanel value="1">
          <StatusTransitionsMatrix ref="matrixRef" />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.ms3-statuses-tabs {
  width: 100%;
}
</style>
