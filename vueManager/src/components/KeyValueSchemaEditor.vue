<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { computed } from 'vue'

import { defaultKeyValueConfig } from '../utils/keyValueField.js'

const props = defineProps({
  modelValue: { type: Object, default: () => defaultKeyValueConfig() },
})

const emit = defineEmits(['update:modelValue'])
const { _ } = useLexicon()

const config = computed({
  get: () => ({ ...defaultKeyValueConfig(), ...props.modelValue }),
  set: value => emit('update:modelValue', value),
})

const modeOptions = computed(() => [
  { label: _('ms3_vue_key_value_mode_free'), value: 'free' },
  { label: _('ms3_vue_key_value_mode_fixed'), value: 'fixed' },
])

const valueTypeOptions = computed(() => [
  { label: _('ms3_vue_key_value_value_type_string'), value: 'string' },
  { label: _('ms3_vue_key_value_value_type_number'), value: 'number' },
])

function updateConfig(patch) {
  config.value = { ...config.value, ...patch }
}

function addKey() {
  const keys = [...(config.value.keys || [])]
  keys.push({
    key: `key_${keys.length + 1}`,
    label: '',
    valueType: 'string',
    required: false,
  })
  updateConfig({ keys })
}

function removeKey(index) {
  const keys = [...(config.value.keys || [])]
  keys.splice(index, 1)
  updateConfig({ keys })
}

function updateKey(index, patch) {
  const keys = (config.value.keys || []).map((keyDef, keyIndex) => {
    if (keyIndex !== index) {
      return keyDef
    }
    return { ...keyDef, ...patch }
  })
  updateConfig({ keys })
}
</script>

<template>
  <div class="key-value-schema-editor">
    <div class="schema-toolbar">
      <div class="field">
        <label>{{ _('ms3_vue_key_value_mode') }}</label>
        <Select
          :model-value="config.mode"
          :options="modeOptions"
          option-label="label"
          option-value="value"
          class="w-full"
          @update:model-value="updateConfig({ mode: $event })"
        />
      </div>
    </div>

    <template v-if="config.mode === 'fixed'">
      <div class="keys-header">
        <span>{{ _('ms3_vue_key_value_keys') }}</span>
        <Button
          icon="pi pi-plus"
          :label="_('ms3_vue_key_value_add_key')"
          size="small"
          severity="secondary"
          @click="addKey"
        />
      </div>

      <div v-for="(keyDef, index) in config.keys" :key="index" class="key-row">
        <InputText
          :model-value="keyDef.key"
          class="key-input"
          :placeholder="_('ms3_vue_key_value_key')"
          @update:model-value="updateKey(index, { key: $event })"
        />
        <InputText
          :model-value="keyDef.label"
          class="key-input"
          :placeholder="_('ms3_vue_key_value_label')"
          @update:model-value="updateKey(index, { label: $event })"
        />
        <Select
          :model-value="keyDef.valueType || 'string'"
          :options="valueTypeOptions"
          option-label="label"
          option-value="value"
          class="key-input"
          @update:model-value="updateKey(index, { valueType: $event })"
        />
        <div class="key-required">
          <Checkbox
            :model-value="!!keyDef.required"
            :binary="true"
            :input-id="`key-required-${index}`"
            @update:model-value="updateKey(index, { required: $event })"
          />
          <label :for="`key-required-${index}`">{{ _('ms3_vue_key_value_required') }}</label>
        </div>
        <Button
          icon="pi pi-times"
          severity="danger"
          text
          rounded
          size="small"
          @click="removeKey(index)"
        />
      </div>
      <div v-if="!config.keys?.length" class="text-500 text-sm mt-2">
        {{ _('ms3_vue_key_value_no_keys') }}
      </div>
    </template>
  </div>
</template>

<style>
.vueApp .key-value-schema-editor {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.vueApp .key-value-schema-editor .schema-toolbar {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.75rem;
}

.vueApp .key-value-schema-editor .keys-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-weight: 600;
  margin-top: 0.5rem;
}

.vueApp .key-value-schema-editor .key-row {
  display: grid;
  grid-template-columns: 1fr 1fr 10rem auto 2.5rem;
  gap: 0.5rem;
  align-items: center;
}

.vueApp .key-value-schema-editor .key-required {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  white-space: nowrap;
}

@media (max-width: 48rem) {
  .vueApp .key-value-schema-editor .key-row {
    grid-template-columns: 1fr;
  }
}
</style>
