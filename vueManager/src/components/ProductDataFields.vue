<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Card from 'primevue/card'
import Fieldset from 'primevue/fieldset'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref } from 'vue'

import request from '../request.js'
import {
  isFullWidthExtraFieldXtype,
  parseStructuredExtraFieldValue,
} from '../utils/structuredExtraField.js'
import DynamicField from './DynamicField.vue'

const props = defineProps({
  productId: {
    type: Number,
    required: true,
  },
  productData: {
    type: Object,
    default: () => ({}),
  },
})

const toast = useToast()
const { _ } = useLexicon()

// Loading state
const loading = ref(false)
const saving = ref(false)

// Fields and sections configuration
const fieldsConfig = ref({
  fields: [],
  sections: {},
})

// Field values
const fieldValues = ref({})

// Loaded product data from API
const loadedProductData = ref({})

// Page key
const pageKey = 'product_data'

/**
 * Load product data
 */
async function loadProductData() {
  try {
    const response = await request.get(`/api/mgr/product-data/${props.productId}`)

    if (response) {
      loadedProductData.value = response
      return response
    } else {
      console.error('[ProductDataFields] Invalid product data response:', response)
      toast.add({
        severity: 'error',
        summary: _('ms3_vue_error'),
        detail: _('ms3_vue_error_load_product_data'),
        life: 5000,
      })
      return null
    }
  } catch (error) {
    console.error('[ProductDataFields] Error loading product data:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_error'),
      detail: error.message || _('ms3_vue_error_loading_product_data'),
      life: 5000,
    })
    return null
  }
}

/**
 * Load fields configuration
 */
async function loadConfig() {
  loading.value = true

  try {
    // Load fields configuration and product data in parallel
    const [configResponse, productDataResponse] = await Promise.all([
      request.get(`/api/mgr/config/page-fields/${pageKey}`),
      loadProductData(),
    ])

    if (configResponse && configResponse.fields) {
      fieldsConfig.value = configResponse

      // Initialize field values from loaded product data
      configResponse.fields.forEach(field => {
        const fieldName = field.name
        if (productDataResponse && productDataResponse[fieldName] !== undefined) {
          let value = productDataResponse[fieldName]

          // For checkboxes convert value to number
          if (field.xtype === 'xcheckbox' || field.xtype === 'checkbox') {
            // Handle boolean, string and number
            if (typeof value === 'boolean') {
              value = value ? 1 : 0
            } else if (typeof value === 'string') {
              value = value === 'true' || value === '1' ? 1 : 0
            } else {
              value = parseInt(value) || 0
            }
          } else {
            value = parseStructuredExtraFieldValue(field.xtype, value)
          }

          fieldValues.value[fieldName] = value
        } else {
          // For checkboxes default 0, for others null
          if (field.xtype === 'xcheckbox' || field.xtype === 'checkbox') {
            fieldValues.value[fieldName] = 0
          } else {
            fieldValues.value[fieldName] = null
          }
        }
      })
    } else {
      console.error('[ProductDataFields] Invalid response:', configResponse)
      toast.add({
        severity: 'error',
        summary: _('ms3_vue_error'),
        detail: _('ms3_vue_error_load_fields_config'),
        life: 5000,
      })
    }
  } catch (error) {
    console.error('[ProductDataFields] Error loading config:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_error'),
      detail: error.message || _('ms3_vue_error_loading_config'),
      life: 5000,
    })
  } finally {
    loading.value = false
  }
}

/**
 * Save product data
 */
// eslint-disable-next-line no-unused-vars
async function saveProductData() {
  saving.value = true
  try {
    const response = await request.put(
      `/api/mgr/product-data/${props.productId}`,
      fieldValues.value
    )

    if (response && response.updated) {
      toast.add({
        severity: 'success',
        summary: _('ms3_vue_success_title'),
        detail: _('ms3_vue_product_data_saved'),
        life: 3000,
      })
    } else {
      toast.add({
        severity: 'error',
        summary: _('ms3_vue_error'),
        detail: _('ms3_vue_error_save_product_data'),
        life: 5000,
      })
    }
  } catch (error) {
    console.error('Error saving product data:', error)
    toast.add({
      severity: 'error',
      summary: _('ms3_vue_error'),
      detail: error.message || _('ms3_vue_error_saving_data'),
      life: 5000,
    })
  } finally {
    saving.value = false
  }
}

/**
 * Handle field change
 */
function handleFieldChange(fieldId, value) {
  fieldValues.value[fieldId] = value
}

/**
 * Get only visible fields
 */
const visibleFields = computed(() => {
  return fieldsConfig.value.fields.filter(field => field.visible !== false)
})

/**
 * Group fields by sections
 * Show only !hidden sections
 */
const fieldsBySections = computed(() => {
  const sections = {}

  visibleFields.value.forEach(field => {
    const sectionKey = field.section || 'default'
    const sectionConfig = fieldsConfig.value.sections[sectionKey]

    // Skip hidden sections
    if (sectionConfig && sectionConfig.hidden === true) {
      return
    }

    if (!sections[sectionKey]) {
      sections[sectionKey] = {
        ...sectionConfig,
        fields: [],
      }
    }

    sections[sectionKey].fields.push(field)
  })

  return sections
})

// Load configuration on mount
onMounted(() => {
  loadConfig()
})
</script>

<template>
  <div class="product-data-fields">
    <Card>
      <template #title>
        <span>{{ _('ms3_vue_product_data_title') }}</span>
      </template>

      <template #content>
        <Message v-if="loading" severity="info">
          {{ _('ms3_vue_loading_config') }}
        </Message>

        <Message v-else-if="visibleFields.length === 0" severity="warn">
          {{ _('ms3_vue_no_visible_fields') }}
        </Message>

        <div v-else class="sections-container">
          <Fieldset
            v-for="(section, sectionKey) in fieldsBySections"
            :key="sectionKey"
            :legend="section.label || sectionKey"
            :toggleable="true"
            :collapsed="section.collapsed"
            class="section-fieldset"
          >
            <div class="fields-grid">
              <div
                v-for="field in section.fields"
                :key="field.name"
                :class="[
                  'field-item',
                  isFullWidthExtraFieldXtype(field.xtype)
                    ? 'col-12'
                    : `col-${field.width || 4}`,
                  { 'field-checkbox': field.xtype === 'xcheckbox' || field.xtype === 'checkbox' },
                ]"
              >
                <!-- Checkbox layout: checkbox + label in one line -->
                <template v-if="field.xtype === 'xcheckbox' || field.xtype === 'checkbox'">
                  <div class="checkbox-wrapper">
                    <DynamicField
                      v-model="fieldValues[field.name]"
                      :field-config="field"
                      :disabled="loading || saving"
                      @blur="handleFieldChange(field.name, $event.value)"
                    />
                    <label
                      :for="field.name"
                      class="field-label checkbox-label"
                      :title="'[[+' + field.name + ']]'"
                    >
                      {{ field.label }}
                      <span v-if="field.required" class="required">*</span>
                    </label>
                  </div>
                  <small v-if="field.description" class="field-description">
                    {{ field.description }}
                  </small>
                </template>

                <!-- Regular field: label on top, field below -->
                <template v-else>
                  <label :for="field.name" class="field-label" :title="'[[+' + field.name + ']]'">
                    {{ field.label }}
                    <span v-if="field.required" class="required">*</span>
                  </label>

                  <DynamicField
                    v-model="fieldValues[field.name]"
                    :field-config="field"
                    :disabled="loading || saving"
                    @blur="handleFieldChange(field.name, $event.value)"
                  />

                  <small v-if="field.description" class="field-description">
                    {{ field.description }}
                  </small>
                </template>
              </div>
            </div>
          </Fieldset>
        </div>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.product-data-fields {
  padding: 1.25rem;
}

.fields-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 1.25rem;
  margin: -0.625rem; /* Compensate field padding */
}

.field-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.625rem;
  box-sizing: border-box;
}

.field-item :deep(input),
.field-item :deep(textarea),
.field-item :deep(.p-inputtext),
.field-item :deep(.p-inputnumber),
.field-item :deep(.p-dropdown) {
  width: 100%;
}

/* 12-column grid system */
.col-1 {
  flex: 0 0 calc(8.333% - 1.25rem);
  max-width: calc(8.333% - 1.25rem);
}
.col-2 {
  flex: 0 0 calc(16.666% - 1.25rem);
  max-width: calc(16.666% - 1.25rem);
}
.col-3 {
  flex: 0 0 calc(25% - 1.25rem);
  max-width: calc(25% - 1.25rem);
}
.col-4 {
  flex: 0 0 calc(33.333% - 1.25rem);
  max-width: calc(33.333% - 1.25rem);
}
.col-5 {
  flex: 0 0 calc(41.666% - 1.25rem);
  max-width: calc(41.666% - 1.25rem);
}
.col-6 {
  flex: 0 0 calc(50% - 1.25rem);
  max-width: calc(50% - 1.25rem);
}
.col-7 {
  flex: 0 0 calc(58.333% - 1.25rem);
  max-width: calc(58.333% - 1.25rem);
}
.col-8 {
  flex: 0 0 calc(66.666% - 1.25rem);
  max-width: calc(66.666% - 1.25rem);
}
.col-9 {
  flex: 0 0 calc(75% - 1.25rem);
  max-width: calc(75% - 1.25rem);
}
.col-10 {
  flex: 0 0 calc(83.333% - 1.25rem);
  max-width: calc(83.333% - 1.25rem);
}
.col-11 {
  flex: 0 0 calc(91.666% - 1.25rem);
  max-width: calc(91.666% - 1.25rem);
}
.col-12 {
  flex: 0 0 calc(100% - 1.25rem);
  max-width: calc(100% - 1.25rem);
}

/* Responsive: on tablets col-4 becomes col-6 */
@media (max-width: 64rem) {
  .col-4 {
    flex: 0 0 calc(50% - 1.25rem);
    max-width: calc(50% - 1.25rem);
  }
}

/* Responsive: on mobile all fields full width */
@media (max-width: 48rem) {
  .field-item {
    flex: 0 0 calc(100% - 1.25rem) !important;
    max-width: calc(100% - 1.25rem) !important;
  }
}

.field-label {
  font-weight: 600;
  font-size: 0.875rem;
  color: var(--ms3-text-primary);
}

.field-label .required {
  color: var(--ms3-text-danger-alt);
  margin-left: 0.125rem;
}

/* Checkbox: horizontal layout */
.checkbox-wrapper {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.checkbox-wrapper :deep(.field-wrapper) {
  width: auto;
}

.checkbox-label {
  margin: 0;
  cursor: pointer;
  user-select: none;
}

.field-description {
  color: var(--ms3-text-muted);
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.sections-container {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.section-fieldset {
  margin-bottom: 0;
}
</style>
