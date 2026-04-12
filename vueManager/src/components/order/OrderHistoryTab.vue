<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'

defineProps({
  logs: { type: Array, required: true },
  formatDate: { type: Function, required: true },
  formatLogEntry: { type: Function, required: true },
})

const { _ } = useLexicon()
</script>

<template>
  <div class="order-history-tab">
    <DataTable :value="logs" striped-rows responsive-layout="scroll">
      <Column field="timestamp" :header="_('log_date')" style="width: 11.25rem">
        <template #body="{ data }">
          {{ formatDate(data.timestamp || data.createdon) }}
        </template>
      </Column>
      <Column field="action" :header="_('log_action')" />
      <Column field="user_name" :header="_('log_user')" style="width: 9.375rem" />
      <Column field="entry_formatted" :header="_('log_entry')">
        <template #body="{ data }">
          {{ data.entry_formatted || formatLogEntry(data) }}
        </template>
      </Column>
    </DataTable>
  </div>
</template>
