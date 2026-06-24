<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
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
let isInternalEmit = false

watch(
  [() => props.modelValue, () => props.config],
  () => {
    if (isInternalEmit) {
      isInternalEmit = false
      return
    }

    internalMap.value = normalizeKeyValueMap(
      parseKeyValueModelValue(props.modelValue),
      schema.value
    )
  },
  { immediate: true, deep: true }
)

function emitMap() {
  const normalized = normalizeKeyValueMap(internalMap.value, schema.value)
  isInternalEmit = true
  emit('update:modelValue', normalized)
}

// For free mode: we need an array of pairs to work with UI
const freeModePairs = ref([])

watch(
  internalMap,
  (newMap) => {
    if (schema.value.mode === 'free') {
      const pairs = Object.entries(newMap).map(([key, value]) => ({
        key,
        value,
        _ms3Id: Math.random().toString(36).substring(7)
      }))
      // Only update if keys/values actually changed to avoid cursor jumps
      const currentKeys = freeModePairs.value.map(p => p.key).join('|')
      const newKeys = pairs.map(p => p.key).join('|')
      const currentValues = freeModePairs.value.map(p => p.value).join('|')
      const newValues = pairs.map(p => p.value).join('|')
      
      if (currentKeys !== newKeys || currentValues !== newValues || (freeModePairs.value.length === 0 && pairs.length > 0)) {
        freeModePairs.value = pairs
      }
    }
  },
  { immediate: true, deep: true }
)

function updateFixedValue(key, value) {
  internalMap.value = { ...internalMap.value, [key]: value }
  emitMap()
}

function updateFreePair(index, patch) {
  const pair = freeModePairs.value[index]
  const updatedPair = { ...pair, ...patch }
  freeModePairs.value[index] = updatedPair
  
  // Rebuild map from pairs
  const nextMap = {}
  freeModePairs.value.forEach(p => {
    if (p.key) {
      nextMap[p.key] = p.value
    }
  })
  internalMap.value = nextMap
  emitMap()
}

function addFreePair() {
  freeModePairs.value.push({
    key: '',
    value: '',
    _ms3Id: Math.random().toString(36).substring(7)
  })
}

function removeFreePair(index) {
  freeModePairs.value.splice(index, 1)
  const nextMap = {}
  freeModePairs.value.forEach(p => {
    if (p.key) {
      nextMap[p.key] = p.value
    }
  })
  internalMap.value = nextMap
  emitMap()
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
      <div class="ms3-key-value-header">
        <span class="ms3-key-value-header-cell">{{ _('ms3_vue_key_value_key') }}</span>
        <span class="ms3-key-value-header-cell">{{ _('ms3_vue_key_value_value') }}</span>
        <span class="ms3-key-value-actions-col" aria-hidden="true" />
      </div>

      <div class="ms3-key-value-rows">
        <div v-for="(pair, index) in freeModePairs" :key="pair._ms3Id" class="ms3-key-value-row free-row">
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
            <InputText
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
