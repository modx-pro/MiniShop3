<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { computed, ref, watch } from 'vue'

import {
  defaultKeyValueConfig,
  normalizeKeyValueMap,
  parseKeyValueConfig,
  parseKeyValueModelValue,
} from '../utils/keyValueField.js'

const props = defineProps({
  modelValue: { type: [Object, String], default: () => ({}) },
  config: { type: [Object, String], default: () => defaultKeyValueConfig() },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const { _ } = useLexicon()

const schema = computed(() => parseKeyValueConfig(props.config))

const internalMap = ref({})
const freeModePairs = ref([])
const freeModeDuplicateKey = ref('')
let isInternalEmit = false
let nextFreePairId = 1

function createFreePairId() {
  nextFreePairId += 1
  return `kv-${nextFreePairId}`
}

function mapToFreePairs(map) {
  return Object.entries(map).map(([key, value]) => ({
    key,
    value: value ?? '',
    _ms3Id: createFreePairId(),
  }))
}

function freePairsToMap(pairs) {
  const nextMap = {}
  const seen = new Set()
  let duplicate = ''

  for (const pair of pairs) {
    const key = (pair.key || '').trim()
    if (key === '') {
      continue
    }
    if (seen.has(key)) {
      duplicate = key
      continue
    }
    seen.add(key)
    nextMap[key] = pair.value
  }

  freeModeDuplicateKey.value = duplicate
  return nextMap
}

function freePairValueType(key) {
  const trimmed = (key || '').trim()
  if (!trimmed) {
    return 'string'
  }
  const keyDef = (schema.value.keys || []).find(item => item.key === trimmed)
  return keyDef?.valueType === 'number' ? 'number' : 'string'
}

watch(
  [() => props.modelValue, () => props.config],
  () => {
    if (isInternalEmit) {
      isInternalEmit = false
      return
    }

    const normalized = normalizeKeyValueMap(
      parseKeyValueModelValue(props.modelValue),
      schema.value
    )
    internalMap.value = normalized
    freeModeDuplicateKey.value = ''

    if (schema.value.mode === 'free') {
      freeModePairs.value = mapToFreePairs(normalized)
    }
  },
  { immediate: true, deep: true }
)

function emitMap(map) {
  const normalized = normalizeKeyValueMap(map, schema.value)
  isInternalEmit = true
  internalMap.value = normalized
  emit('update:modelValue', normalized)
}

function updateFixedValue(key, value) {
  emitMap({ ...internalMap.value, [key]: value })
}

function emitFreeModeFromPairs() {
  emitMap(freePairsToMap(freeModePairs.value))
}

function updateFreePair(index, patch) {
  freeModePairs.value[index] = { ...freeModePairs.value[index], ...patch }
  emitFreeModeFromPairs()
}

function addFreePair() {
  freeModePairs.value.push({
    key: '',
    value: '',
    _ms3Id: createFreePairId(),
  })
}

function removeFreePair(index) {
  freeModePairs.value.splice(index, 1)
  emitFreeModeFromPairs()
}
</script>

<template>
  <div class="ms3-key-value-field">
    <!-- Fixed Mode -->
    <div v-if="schema.mode === 'fixed'" class="ms3-key-value-fixed">
      <div v-if="!schema.keys?.length" class="ms3-key-value-empty">
        {{ _('ms3_vue_key_value_no_keys') }}
      </div>
      <div v-else class="ms3-key-value-rows">
        <div v-for="keyDef in schema.keys" :key="keyDef.key" class="ms3-key-value-row fixed-row">
          <label class="ms3-key-value-label-cell">
            {{ keyDef.label || keyDef.key }}
            <span v-if="keyDef.required" class="required-mark">*</span>
          </label>
          <div class="ms3-key-value-value-cell">
            <InputNumber
              v-if="keyDef.valueType === 'number'"
              :model-value="internalMap[keyDef.key]"
              class="w-full"
              :disabled="disabled"
              :use-grouping="false"
              @update:model-value="updateFixedValue(keyDef.key, $event)"
            />
            <InputText
              v-else
              :model-value="internalMap[keyDef.key] ?? ''"
              class="w-full"
              :disabled="disabled"
              @update:model-value="updateFixedValue(keyDef.key, $event)"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Free Mode -->
    <div v-else class="ms3-key-value-free">
      <Message
        v-if="freeModeDuplicateKey"
        severity="warn"
        class="ms3-key-value-duplicate"
        :closable="false"
      >
        {{ _('ms3_vue_key_value_duplicate_key', { key: freeModeDuplicateKey }) }}
      </Message>

      <div class="ms3-key-value-header">
        <span class="ms3-key-value-header-cell">{{ _('ms3_vue_key_value_key') }}</span>
        <span class="ms3-key-value-header-cell">{{ _('ms3_vue_key_value_value') }}</span>
        <span class="ms3-key-value-actions-col" aria-hidden="true" />
      </div>

      <div class="ms3-key-value-rows">
        <div
          v-for="(pair, index) in freeModePairs"
          :key="pair._ms3Id"
          class="ms3-key-value-row free-row"
        >
          <div class="ms3-key-value-key-cell">
            <InputText
              :model-value="pair.key"
              class="w-full"
              :disabled="disabled"
              :placeholder="_('ms3_vue_key_value_key')"
              @update:model-value="updateFreePair(index, { key: $event })"
            />
          </div>
          <div class="ms3-key-value-value-cell">
            <InputNumber
              v-if="freePairValueType(pair.key) === 'number'"
              :model-value="pair.value === '' || pair.value == null ? null : Number(pair.value)"
              class="w-full"
              :disabled="disabled"
              :use-grouping="false"
              @update:model-value="updateFreePair(index, { value: $event })"
            />
            <InputText
              v-else
              :model-value="pair.value"
              class="w-full"
              :disabled="disabled"
              :placeholder="_('ms3_vue_key_value_value')"
              @update:model-value="updateFreePair(index, { value: $event })"
            />
          </div>
          <Button
            icon="pi pi-times"
            severity="danger"
            text
            rounded
            size="small"
            class="ms3-key-value-actions-col"
            :disabled="disabled"
            @click="removeFreePair(index)"
          />
        </div>
      </div>

      <Button
        icon="pi pi-plus"
        :label="_('ms3_vue_key_value_add_pair')"
        severity="secondary"
        size="small"
        class="ms3-key-value-add"
        :disabled="disabled"
        @click="addFreePair"
      />
    </div>
  </div>
</template>

<style>
.vueApp .ms3-key-value-field {
  width: 100%;
  border: 1px solid var(--p-content-border-color, #e5e7eb);
  border-radius: 0.375rem;
  padding: 0.75rem;
  background: var(--p-content-background, #fff);
}

.vueApp .ms3-key-value-empty {
  color: var(--p-text-muted-color, #6b7280);
  font-size: 0.875rem;
}

.vueApp .ms3-key-value-duplicate {
  margin-bottom: 0.75rem;
}

.vueApp .ms3-key-value-header,
.vueApp .ms3-key-value-row {
  display: grid;
  gap: 0.5rem;
  align-items: center;
}

.vueApp .ms3-key-value-row.fixed-row {
  grid-template-columns: 10rem 1fr;
  margin-bottom: 0.5rem;
}

.vueApp .ms3-key-value-header,
.vueApp .ms3-key-value-row.free-row {
  grid-template-columns: 1fr 1fr 2.5rem;
}

.vueApp .ms3-key-value-header {
  margin-bottom: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--p-text-muted-color, #6b7280);
}

.vueApp .ms3-key-value-rows {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.vueApp .ms3-key-value-label-cell {
  font-size: 0.875rem;
  font-weight: 500;
}

.vueApp .ms3-key-value-field .required-mark {
  color: var(--p-red-500, #ef4444);
}

.vueApp .ms3-key-value-add {
  margin-top: 0.75rem;
}
</style>
