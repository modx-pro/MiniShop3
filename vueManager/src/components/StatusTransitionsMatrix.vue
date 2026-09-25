<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import { Button, Checkbox, Message, Toast, useToast } from 'primevue'
import { computed, onMounted, ref } from 'vue'

import request from '../request.js'

const toast = useToast()
const { _ } = useLexicon()

const loading = ref(false)
const saving = ref(false)
const statuses = ref([])
const mode = ref(0)
const invalid = ref(false)
const unreachable = ref([])
/** @type {import('vue').Ref<Record<string, boolean>>} */
const selected = ref({})

const MODE_OFF = 0
const MODE_ON = 1

function edgeKey(fromId, toId) {
  return `${fromId}:${toId}`
}

function getDisplayName(name) {
  if (!name) return ''
  const translated = _(name)
  return translated !== name ? translated : name
}

function structuralBlockLexiconKey(from, to) {
  if (from.id === to.id) {
    return 'ms3_status_transitions_cell_self'
  }
  if (from.final) {
    return 'ms3_status_transitions_cell_final'
  }
  if (from.fixed && to.position <= from.position) {
    return 'ms3_status_transitions_cell_fixed'
  }
  return ''
}

function isDisabled(from, to) {
  return structuralBlockLexiconKey(from, to) !== ''
}

function disabledReason(from, to) {
  const key = structuralBlockLexiconKey(from, to)
  return key ? _(key) : ''
}

function applyMatrix(data) {
  statuses.value = Array.isArray(data?.statuses) ? data.statuses : []
  mode.value = Number(data?.mode ?? MODE_OFF)
  invalid.value = Boolean(data?.invalid)
  unreachable.value = Array.isArray(data?.unreachable) ? data.unreachable : []

  const next = {}
  for (const pair of data?.edges || []) {
    if (!Array.isArray(pair) || pair.length < 2) continue
    const from = Number(pair[0])
    const to = Number(pair[1])
    if (from > 0 && to > 0) {
      next[edgeKey(from, to)] = true
    }
  }
  selected.value = next
}

async function loadMatrix() {
  loading.value = true
  try {
    const data = await request.get('/api/mgr/statuses/transitions')
    applyMatrix(data)
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('ms3_status_transitions_load_error'),
      life: 5000,
    })
  } finally {
    loading.value = false
  }
}

function collectEdges() {
  const edges = []
  for (const [key, on] of Object.entries(selected.value)) {
    if (!on) continue
    const [fromRaw, toRaw] = key.split(':')
    const from = Number(fromRaw)
    const to = Number(toRaw)
    if (from > 0 && to > 0) {
      edges.push([from, to])
    }
  }
  return edges
}

async function saveMatrix() {
  saving.value = true
  try {
    const data = await request.put('/api/mgr/statuses/transitions', {
      edges: collectEdges(),
    })
    applyMatrix(data)
    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('ms3_status_transitions_saved'),
      life: 3000,
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('ms3_status_transitions_save_error'),
      life: 5000,
    })
  } finally {
    saving.value = false
  }
}

async function clearAllowList() {
  selected.value = {}
  await saveMatrix()
}

const unreachableIds = computed(() => new Set(unreachable.value))

const showUnreachable = computed(
  () => mode.value === MODE_ON && unreachable.value.length > 0
)

function isUnreachable(statusId) {
  return unreachableIds.value.has(statusId)
}

onMounted(() => {
  loadMatrix()
})

defineExpose({
  isDisabled,
  disabledReason,
  applyMatrix,
  collectEdges,
  edgeKey,
  loadMatrix,
})
</script>

<template>
  <div class="ms3-status-transitions">
    <Toast />
    <p class="ms3-status-transitions__intro">
      {{ _('ms3_status_transitions_intro') }}
    </p>

    <Message v-if="mode === MODE_OFF && !invalid" severity="info" :closable="false" class="mb-3">
      {{ _('ms3_status_transitions_mode_off') }}
    </Message>
    <Message v-if="invalid" severity="warn" :closable="false" class="mb-3">
      {{ _('ms3_status_transitions_mode_invalid') }}
    </Message>
    <Message v-if="showUnreachable" severity="warn" :closable="false" class="mb-3">
      {{ _('ms3_status_transitions_unreachable_hint') }}
    </Message>

    <div class="ms3-status-transitions__toolbar">
      <Button
        :label="_('ms3_status_transitions_save')"
        :loading="saving"
        :disabled="loading || statuses.length === 0"
        @click="saveMatrix"
      />
      <Button
        :label="_('ms3_status_transitions_clear')"
        severity="secondary"
        :disabled="loading || saving || mode === MODE_OFF"
        @click="clearAllowList"
      />
      <Button
        :label="_('refresh')"
        severity="secondary"
        text
        :disabled="loading || saving"
        @click="loadMatrix"
      />
    </div>

    <div v-if="loading" class="ms3-status-transitions__loading">
      {{ _('loading') }}
    </div>

    <div v-else-if="statuses.length === 0" class="ms3-status-transitions__empty">
      {{ _('ms3_status_transitions_empty') }}
    </div>

    <div v-else class="ms3-status-transitions__scroll">
      <table class="ms3-status-transitions__table">
        <thead>
          <tr>
            <th class="ms3-status-transitions__corner">
              {{ _('ms3_status_transitions_from_to') }}
            </th>
            <th
              v-for="to in statuses"
              :key="'h-' + to.id"
              :class="{
                'ms3-status-transitions__unreachable': isUnreachable(to.id),
              }"
              :title="getDisplayName(to.name)"
            >
              {{ getDisplayName(to.name) }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="from in statuses" :key="'r-' + from.id">
            <th
              scope="row"
              :class="{
                'ms3-status-transitions__unreachable': isUnreachable(from.id),
              }"
            >
              {{ getDisplayName(from.name) }}
            </th>
            <td
              v-for="to in statuses"
              :key="'c-' + from.id + '-' + to.id"
              :class="{
                'ms3-status-transitions__cell--disabled': isDisabled(from, to),
              }"
              :title="disabledReason(from, to)"
            >
              <Checkbox
                v-model="selected[edgeKey(from.id, to.id)]"
                :binary="true"
                :disabled="isDisabled(from, to)"
                :input-id="'edge-' + from.id + '-' + to.id"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.ms3-status-transitions {
  width: 100%;
}

.ms3-status-transitions__intro {
  margin: 0 0 1rem;
  color: var(--p-text-muted-color, #6b7280);
  max-width: 60rem;
}

.ms3-status-transitions__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.ms3-status-transitions__scroll {
  overflow-x: auto;
  max-width: 100%;
}

.ms3-status-transitions__table {
  border-collapse: collapse;
  min-width: 100%;
}

.ms3-status-transitions__table th,
.ms3-status-transitions__table td {
  border: 1px solid var(--p-content-border-color, #e5e7eb);
  padding: 0.5rem;
  text-align: center;
  vertical-align: middle;
  white-space: nowrap;
}

.ms3-status-transitions__table thead th {
  font-weight: 600;
  background: var(--p-content-hover-background, #f9fafb);
  max-width: 8rem;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ms3-status-transitions__table tbody th {
  text-align: left;
  font-weight: 500;
  background: var(--p-content-hover-background, #f9fafb);
  max-width: 10rem;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ms3-status-transitions__corner {
  text-align: left !important;
}

.ms3-status-transitions__cell--disabled {
  background: var(--p-surface-100, #f3f4f6);
  opacity: 0.7;
}

.ms3-status-transitions__unreachable {
  color: var(--p-orange-600, #d97706);
  font-weight: 600;
}

.ms3-status-transitions__loading,
.ms3-status-transitions__empty {
  padding: 1.5rem 0;
  color: var(--p-text-muted-color, #6b7280);
}

.mb-3 {
  margin-bottom: 0.75rem;
}
</style>
