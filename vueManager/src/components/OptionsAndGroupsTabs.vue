<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { ref } from 'vue'

import OptionGroupsGrid from './OptionGroupsGrid.vue'
import OptionsGrid from './OptionsGrid.vue'

/**
 * Wrapper for the product options admin page: tabs "Options" and "Groups" (#10).
 *
 * Groups admin UI (CRUD + drag-n-drop sort) alongside options on the Settings page.
 */

const { _ } = useLexicon()

const STORAGE_KEY = 'ms3-options-active-tab'

function loadInitialTab() {
  try {
    const v = localStorage.getItem(STORAGE_KEY)
    return v === '1' ? '1' : '0'
  } catch {
    return '0'
  }
}

const activeTab = ref(loadInitialTab())

function persistTab(value) {
  try {
    localStorage.setItem(STORAGE_KEY, String(value))
  } catch {
    // ignore quota / private-mode errors
  }
}
</script>

<template>
  <div class="ms3-options-tabs">
    <Tabs v-model:value="activeTab" @update:value="persistTab">
      <TabList>
        <Tab value="0">{{ _('ms3_options') }}</Tab>
        <Tab value="1">{{ _('ms3_option_groups') }}</Tab>
      </TabList>
      <TabPanels>
        <TabPanel value="0">
          <OptionsGrid />
        </TabPanel>
        <TabPanel value="1">
          <OptionGroupsGrid />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<style scoped>
.ms3-options-tabs {
  width: 100%;
}
</style>
