<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { computed } from 'vue'

import { defaultRepeaterConfig } from '../utils/repeaterField.js'

const props = defineProps({
  modelValue: { type: Object, default: () => defaultRepeaterConfig() },
})

const emit = defineEmits(['update:modelValue'])
const { _ } = useLexicon()

const config = computed({
  get: () => ({ ...defaultRepeaterConfig(), ...props.modelValue }),
  set: value => emit('update:modelValue', value),
})

const columnXtypeOptions = computed(() => [
  { label: _('ms3_vue_xtype_textfield'), value: 'textfield' },
  { label: _('ms3_vue_xtype_numberfield'), value: 'numberfield' },
])

function updateConfig(patch) {
  config.value = { ...config.value, ...patch }
}

function addColumn() {
  const columns = [...(config.value.columns || [])]
  columns.push({
    key: `field_${columns.length + 1}`,
    label: '',
    xtype: 'textfield',
    required: false,
  })
  updateConfig({ columns })
}

function removeColumn(index) {
  const columns = [...(config.value.columns || [])]
  columns.splice(index, 1)
  updateConfig({ columns })
}

function updateColumn(index, patch) {
  const columns = (config.value.columns || []).map((column, columnIndex) => {
    if (columnIndex !== index) {
      return column
    }
    return { ...column, ...patch }
  })
  updateConfig({ columns })
}
</script>

<template>
  <div class="repeater-schema-editor">
    <div class="schema-toolbar">
      <div class="field">
        <label>{{ _('ms3_vue_repeater_rank_field') }}</label>
        <InputText
          :model-value="config.rankField"
          class="w-full"
          @update:model-value="updateConfig({ rankField: $event })"
        />
      </div>
      <div class="field">
        <label>{{ _('ms3_vue_repeater_min_rows') }}</label>
        <InputNumber
          :model-value="config.minRows"
          class="w-full"
          :min="0"
          :use-grouping="false"
          @update:model-value="updateConfig({ minRows: $event ?? 0 })"
        />
      </div>
      <div class="field">
        <label>{{ _('ms3_vue_repeater_max_rows') }}</label>
        <InputNumber
          :model-value="config.maxRows"
          class="w-full"
          :min="0"
          :use-grouping="false"
          :placeholder="_('ms3_vue_repeater_unlimited')"
          @update:model-value="updateConfig({ maxRows: $event === '' ? null : $event })"
        />
      </div>
      <div class="field checkbox-field">
        <Checkbox
          :model-value="config.sortable !== false"
          :binary="true"
          input-id="repeater-sortable"
          @update:model-value="updateConfig({ sortable: $event })"
        />
        <label for="repeater-sortable">{{ _('ms3_vue_repeater_sortable') }}</label>
      </div>
    </div>

    <div class="columns-header">
      <span>{{ _('ms3_vue_repeater_columns') }}</span>
      <Button
        icon="pi pi-plus"
        :label="_('ms3_vue_repeater_add_column')"
        size="small"
        severity="secondary"
        @click="addColumn"
      />
    </div>

    <div v-for="(column, index) in config.columns" :key="index" class="column-row">
      <InputText
        :model-value="column.key"
        class="column-input"
        :placeholder="_('ms3_vue_repeater_column_key')"
        @update:model-value="updateColumn(index, { key: $event })"
      />
      <InputText
        :model-value="column.label"
        class="column-input"
        :placeholder="_('ms3_vue_repeater_column_label')"
        @update:model-value="updateColumn(index, { label: $event })"
      />
      <Select
        :model-value="column.xtype || 'textfield'"
        :options="columnXtypeOptions"
        option-label="label"
        option-value="value"
        class="column-input"
        @update:model-value="updateColumn(index, { xtype: $event })"
      />
      <div class="column-required">
        <Checkbox
          :model-value="!!column.required"
          :binary="true"
          :input-id="`column-required-${index}`"
          @update:model-value="updateColumn(index, { required: $event })"
        />
        <label :for="`column-required-${index}`">{{ _('ms3_vue_repeater_required') }}</label>
      </div>
      <Button
        icon="pi pi-times"
        severity="danger"
        text
        rounded
        size="small"
        :disabled="config.columns.length <= 1"
        @click="removeColumn(index)"
      />
    </div>
  </div>
</template>

<!-- Non-scoped + .vueApp prefix: scoped <style> drops styles across Vite chunks in MS3 admin. -->
<style>
.vueApp .repeater-schema-editor {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.vueApp .repeater-schema-editor .schema-toolbar {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.vueApp .repeater-schema-editor .checkbox-field {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding-top: 1.5rem;
}

.vueApp .repeater-schema-editor .columns-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-weight: 600;
}

.vueApp .repeater-schema-editor .column-row {
  display: grid;
  grid-template-columns: 1fr 1fr 10rem auto 2.5rem;
  gap: 0.5rem;
  align-items: center;
}

.vueApp .repeater-schema-editor .column-required {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  white-space: nowrap;
}

@media (max-width: 48rem) {
  .vueApp .repeater-schema-editor .schema-toolbar,
  .vueApp .repeater-schema-editor .column-row {
    grid-template-columns: 1fr;
  }

  .vueApp .repeater-schema-editor .checkbox-field {
    padding-top: 0;
  }
}
</style>
