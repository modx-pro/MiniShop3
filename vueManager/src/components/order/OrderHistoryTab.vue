<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import { Column, DataTable, Tag } from 'primevue'

import { useOrderFormatters } from '../../composables/useOrderFormatters.js'
import { useOrderLogFormatters } from '../../composables/useOrderLogFormatters.js'

defineProps({
  logs: { type: Array, required: true },
})

const { formatDate, formatPrice } = useOrderFormatters()
const { formatLogEntry, formatLogAction, logActionSeverity } = useOrderLogFormatters({
  formatPrice,
})

const { _ } = useLexicon()
</script>

<template>
  <div class="order-history-tab">
    <DataTable
      :value="logs"
      striped-rows
      responsive-layout="scroll"
      data-key="id"
    >
      <template #empty>
        <div class="history-empty">{{ _('log_empty') }}</div>
      </template>
      <Column field="timestamp" :header="_('log_date')" style="width: 11.25rem">
        <template #body="{ data }">
          {{ formatDate(data.timestamp || data.createdon) }}
        </template>
      </Column>
      <Column field="action" :header="_('log_action')" style="width: 9.5rem">
        <template #body="{ data }">
          <Tag
            :value="formatLogAction(data.action)"
            :severity="logActionSeverity(data.action)"
          />
        </template>
      </Column>
      <Column field="user_name" :header="_('log_user')" style="width: 9.375rem" />
      <Column field="entry" :header="_('log_entry')">
        <template #body="{ data }">
          {{ formatLogEntry(data) }}
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<style scoped>
.history-empty {
  padding: 1.5rem 0.625rem;
  color: var(--ms3-text-muted, #696969);
  text-align: center;
}
</style>
