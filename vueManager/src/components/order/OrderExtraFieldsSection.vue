<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Fieldset from 'primevue/fieldset'
import { computed, inject } from 'vue'

import { ORDER_CONTEXT_KEY } from '../../composables/orderContext.js'
import { isFullWidthExtraFieldXtype } from '../../utils/structuredExtraField.js'
import DynamicField from '../DynamicField.vue'

const props = defineProps({
  extraFields: { type: Array, default: () => [] },
  legend: { type: String, default: '' },
})

const orderCtx = inject(ORDER_CONTEXT_KEY, null)
if (!orderCtx) {
  throw new Error('[OrderExtraFieldsSection] orderContext is required. Use OrderView as parent.')
}

const { order } = orderCtx
const { _ } = useLexicon()

const visibleFields = computed(() => props.extraFields.filter(field => field.active !== false))

function getFieldConfig(field) {
  return {
    id: String(field.id ?? field.key),
    name: field.key,
    xtype: field.xtype || 'textfield',
    label: field.label || field.key,
    description: field.description || '',
    config: {
      repeater_config: field.repeater_config,
      key_value_config: field.key_value_config,
    },
    repeater_config: field.repeater_config,
    key_value_config: field.key_value_config,
  }
}

function getWidthClass(field) {
  return isFullWidthExtraFieldXtype(field.xtype) ? 'col-12' : 'col-6'
}
</script>

<template>
  <Fieldset
    v-if="visibleFields.length"
    :legend="legend || _('ms3_vue_order_extra_fields')"
    class="mb-3"
    :toggleable="true"
  >
    <div class="fields-grid">
      <div
        v-for="field in visibleFields"
        :key="field.key"
        :class="['field-wrapper', getWidthClass(field)]"
      >
        <div class="field">
          <label>{{ field.label || field.key }}</label>
          <DynamicField
            v-model="order[field.key]"
            :field-config="getFieldConfig(field)"
            :id-prefix="`order-extra-${field.key}`"
          />
          <small v-if="field.description" class="field-description">
            {{ field.description }}
          </small>
        </div>
      </div>
    </div>
  </Fieldset>
</template>

<style scoped src="./orderFieldsLayout.css"></style>

<style scoped>
.field-description {
  color: var(--ms3-text-muted);
  font-size: 0.75rem;
  margin-top: 0.25rem;
}
</style>
