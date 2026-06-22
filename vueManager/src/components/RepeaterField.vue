<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import { computed, ref, watch } from 'vue'
import draggable from 'vuedraggable'

import {
  defaultRepeaterConfig,
  normalizeRepeaterRows,
  parseRepeaterConfig,
  parseRepeaterModelValue,
  stripRepeaterRowMeta,
} from '../utils/repeaterField.js'

const props = defineProps({
  modelValue: { type: [Array, String], default: () => [] },
  config: { type: [Object, String], default: () => defaultRepeaterConfig() },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { _ } = useLexicon()

const schema = computed(() => parseRepeaterConfig(props.config))
const columnCount = computed(() => schema.value.columns?.length || 2)

const internalRows = ref([])
let isInternalEmit = false

watch(
  [() => props.modelValue, () => props.config],
  () => {
    if (isInternalEmit) {
      isInternalEmit = false
      return
    }

    internalRows.value = normalizeRepeaterRows(
      parseRepeaterModelValue(props.modelValue),
      schema.value
    )
  },
  { immediate: true, deep: true }
)

function emitRows() {
  const normalized = normalizeRepeaterRows(internalRows.value, schema.value)
  isInternalEmit = true
  emit('update:modelValue', stripRepeaterRowMeta(normalized, schema.value))
}

const canAddRow = computed(() => {
  const maxRows = schema.value.maxRows
  if (maxRows === null || maxRows === undefined || maxRows === '') {
    return true
  }
  return internalRows.value.length < Number(maxRows)
})

const canRemoveRow = computed(() => {
  const minRows = Number(schema.value.minRows ?? 0)
  return internalRows.value.length > minRows
})

function addRow() {
  if (!canAddRow.value || props.disabled) {
    return
  }

  const blank = {}
  for (const column of schema.value.columns || []) {
    if (column.key) {
      blank[column.key] = column.xtype === 'numberfield' ? null : ''
    }
  }
  internalRows.value = [...internalRows.value, blank]
  emitRows()
}

function removeRow(index) {
  if (!canRemoveRow.value || props.disabled) {
    return
  }
  const next = [...internalRows.value]
  next.splice(index, 1)
  internalRows.value = next
  emitRows()
}

function updateCell(index, key, value) {
  internalRows.value = internalRows.value.map((row, rowIndex) => {
    if (rowIndex !== index) {
      return row
    }
    return { ...row, [key]: value }
  })
  emitRows()
}

function onDragEnd() {
  emitRows()
}
</script>

<template>
  <div
    class="ms3-repeater-field"
    :style="{ '--ms3-repeater-cols': columnCount }"
  >
    <div v-if="!schema.columns?.length" class="ms3-repeater-empty">
      {{ _('ms3_vue_repeater_no_columns') }}
    </div>

    <template v-else>
      <div class="ms3-repeater-header">
        <span v-if="schema.sortable !== false" class="ms3-repeater-handle-col" aria-hidden="true" />
        <span
          v-for="column in schema.columns"
          :key="column.key"
          class="ms3-repeater-header-cell"
        >
          {{ column.label || column.key }}
          <span v-if="column.required" class="required-mark">*</span>
        </span>
        <span class="ms3-repeater-actions-col" aria-hidden="true" />
      </div>

      <draggable
        v-model="internalRows"
        item-key="_ms3RowId"
        :handle="schema.sortable !== false ? '.drag-handle' : undefined"
        :disabled="disabled || schema.sortable === false"
        :animation="150"
        class="ms3-repeater-rows"
        @end="onDragEnd"
      >
        <template #item="{ element, index }">
          <div class="ms3-repeater-row">
            <span
              v-if="schema.sortable !== false"
              class="drag-handle ms3-repeater-handle-col"
              :title="_('ms3_vue_repeater_drag_hint')"
            >
              <i class="pi pi-bars" />
            </span>

            <div
              v-for="column in schema.columns"
              :key="`${index}-${column.key}`"
              class="ms3-repeater-cell"
            >
              <InputNumber
                v-if="column.xtype === 'numberfield'"
                :model-value="element[column.key]"
                class="w-full"
                :disabled="disabled"
                :use-grouping="false"
                @update:model-value="updateCell(index, column.key, $event)"
              />
              <InputText
                v-else
                :model-value="element[column.key] ?? ''"
                class="w-full"
                :disabled="disabled"
                @update:model-value="updateCell(index, column.key, $event)"
              />
            </div>

            <Button
              icon="pi pi-times"
              severity="danger"
              text
              rounded
              size="small"
              class="ms3-repeater-actions-col"
              :disabled="disabled || !canRemoveRow"
              @click="removeRow(index)"
            />
          </div>
        </template>
      </draggable>

      <Button
        icon="pi pi-plus"
        :label="_('ms3_vue_repeater_add_row')"
        severity="secondary"
        size="small"
        class="ms3-repeater-add"
        :disabled="disabled || !canAddRow"
        @click="addRow"
      />
    </template>
  </div>
</template>

<!-- Non-scoped + .vueApp prefix: scoped <style> drops styles across Vite chunks in MS3 admin. -->
<style>
.vueApp .ms3-repeater-field {
  width: 100%;
  border: 1px solid var(--p-content-border-color, #e5e7eb);
  border-radius: 0.375rem;
  padding: 0.75rem;
  background: var(--p-content-background, #fff);
}

.vueApp .ms3-repeater-empty {
  color: var(--p-text-muted-color, #6b7280);
  font-size: 0.875rem;
}

.vueApp .ms3-repeater-header,
.vueApp .ms3-repeater-row {
  display: grid;
  grid-template-columns: 2rem repeat(var(--ms3-repeater-cols, 2), minmax(0, 1fr)) 2.5rem;
  gap: 0.5rem;
  align-items: center;
}

.vueApp .ms3-repeater-header {
  margin-bottom: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--p-text-muted-color, #6b7280);
}

.vueApp .ms3-repeater-rows {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.vueApp .ms3-repeater-field .drag-handle {
  cursor: grab;
  color: var(--p-text-muted-color, #9ca3af);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.vueApp .ms3-repeater-field .drag-handle:active {
  cursor: grabbing;
}

.vueApp .ms3-repeater-field .required-mark {
  color: var(--p-red-500, #ef4444);
}

.vueApp .ms3-repeater-add {
  margin-top: 0.75rem;
}
</style>
