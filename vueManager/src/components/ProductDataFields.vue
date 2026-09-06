<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import { Message, Panel, useToast } from 'primevue'
import { computed, onMounted, reactive, ref, watch } from 'vue'

import { groupProductDataSections } from '../utils/groupProductDataSections.js'

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
 * Ordered section list for stable Panel rendering.
 * groupProductDataSections orders by section.sort_order (id-keyed objects would
 * enumerate ascending ids and ignore the section sort — #611).
 */
const sectionEntries = computed(() =>
  groupProductDataSections(visibleFields.value, fieldsConfig.value.sections || {}).map(
    section => ({ key: section.key ?? section.id, section })
  )
)

function isCheckboxField(field) {
  return field?.xtype === 'xcheckbox' || field?.xtype === 'checkbox'
}

/**
 * Keep field order; consecutive checkboxes share one full-width flex row
 * so PageBuilder col-% utilities cannot spread them across the grid.
 */
function groupSectionFields(fields) {
  const rows = []
  let checkboxRun = []

  const flushCheckboxes = () => {
    if (!checkboxRun.length) {
      return
    }
    rows.push({
      type: 'checkboxes',
      key: `cb-${checkboxRun.map(f => f.name).join('-')}`,
      fields: checkboxRun,
    })
    checkboxRun = []
  }

  for (const field of fields || []) {
    if (isCheckboxField(field)) {
      checkboxRun.push(field)
      continue
    }
    flushCheckboxes()
    rows.push({ type: 'field', key: field.name, field })
  }
  flushCheckboxes()
  return rows
}

/** Per-section collapse state (seeded from config.collapsed). */
const sectionCollapsed = reactive({})

watch(
  sectionEntries,
  entries => {
    entries.forEach(({ key, section }) => {
      if (sectionCollapsed[key] === undefined) {
        sectionCollapsed[key] = !!section.collapsed
      }
    })
  },
  { immediate: true }
)

// Load configuration on mount
onMounted(() => {
  loadConfig()
})
</script>

<template>
  <div class="product-data-fields">
    <div v-if="loading" class="loading-indicator" aria-busy="true" aria-live="polite">
      <i class="pi pi-spinner pi-spin" aria-hidden="true"></i>
      <span class="sr-only">{{ _('ms3_vue_loading_config') }}</span>
    </div>

    <Message v-else-if="visibleFields.length === 0" severity="warn">
      {{ _('ms3_vue_no_visible_fields') }}
    </Message>

    <div v-else class="sections-container">
      <Panel
        v-for="{ key: sectionKey, section } in sectionEntries"
        :key="sectionKey"
        v-model:collapsed="sectionCollapsed[sectionKey]"
        :header="section.label || sectionKey"
        toggleable
        class="ms3-utilities-section ms3-config-panel product-data-section"
      >
        <div class="fields-grid">
          <template v-for="row in groupSectionFields(section.fields)" :key="row.key">
            <!-- Packed checkbox row (theme 15px gap, no col-% spread) -->
            <div v-if="row.type === 'checkboxes'" class="field-item field-checkbox-row col-12">
              <div v-for="field in row.fields" :key="field.name" class="checkbox-field">
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
              </div>
            </div>

            <!-- Regular field: label on top, control below -->
            <div
              v-else
              class="field-item"
              :class="
                isFullWidthExtraFieldXtype(row.field.xtype)
                  ? 'col-12'
                  : `col-${row.field.width || 4}`
              "
            >
              <label
                :for="row.field.name"
                class="field-label"
                :title="'[[+' + row.field.name + ']]'"
              >
                {{ row.field.label }}
                <span v-if="row.field.required" class="required">*</span>
              </label>

              <DynamicField
                v-model="fieldValues[row.field.name]"
                :field-config="row.field"
                :disabled="loading || saving"
                @blur="handleFieldChange(row.field.name, $event.value)"
              />

              <small v-if="row.field.description" class="field-description">
                {{ row.field.description }}
              </small>
            </div>
          </template>
        </div>
      </Panel>
    </div>
  </div>
</template>

<style scoped>
.product-data-fields {
  padding: 0;
    width: 100%;
}

.loading-indicator {
  display: flex;
  justify-content: center;
    align-items: center;
    min-height: 8rem;
    color: var(--ms3-text-muted, #64748b);
    font-size: 1.75rem;
  }
  
  .sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
  }
  
  .sections-container {
    display: flex;
    flex-direction: column;
    gap: 0;
  }
  
.fields-grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: var(--p-modx-space-panel, 15px);
  margin: 0;
}

/*
 * PageBuilder ships `.vueApp .col-4 { width: 33.333%; flex: 0 0 auto }` for its
 * flex grid. That shrinks MS3 CSS-grid cells and leaves huge empty gutters.
 */
.fields-grid > .field-item[class*='col-'] {
  width: 100%;
  max-width: none;
  flex: none;
  padding: 0;
  min-width: 0;
}

.field-item {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0;
  box-sizing: border-box;
  grid-column: span 4;
}

/* Checkboxes pack left with theme gap — not one third of the row each. */
.field-item.field-checkbox-row {
  grid-column: span 12;
  display: flex;
  flex-direction: row;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--p-modx-space-panel, 15px);
  width: 100%;
  max-width: none;
  padding: 0;
}

.field-item.field-checkbox-row .checkbox-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  width: max-content;
  max-width: 100%;
}

.field-item :deep(input),
.field-item :deep(textarea),
.field-item :deep(.p-inputtext),
.field-item :deep(.p-inputnumber),
.field-item :deep(.p-select),
.field-item :deep(.p-dropdown),
.field-item :deep(.p-inputchips),
.field-item :deep(.p-multiselect) {
  width: 100%;
}

.col-1 {
  grid-column: span 1;
}
.col-2 {
  grid-column: span 2;
}
.col-3 {
  grid-column: span 3;
}
.col-4 {
  grid-column: span 4;
}
.col-5 {
  grid-column: span 5;
}
.col-6 {
  grid-column: span 6;
}
.col-7 {
  grid-column: span 7;
}
.col-8 {
  grid-column: span 8;
}
.col-9 {
  grid-column: span 9;
}
.col-10 {
  grid-column: span 10;
}
.col-11 {
  grid-column: span 11;
}
.col-12 {
  grid-column: span 12;
}

@media (max-width: 64rem) {
  .col-4,
    .col-5 {
      grid-column: span 6;
  }
}

@media (max-width: 48rem) {
  .field-item:not(.field-checkbox-row),
  .col-1,
  .col-2,
  .col-3,
  .col-4,
  .col-5,
  .col-6,
  .col-7,
  .col-8,
  .col-9,
  .col-10,
  .col-11,
  .col-12 {
    grid-column: span 12;
  }
}

.field-label {
  display: block;
    margin: 0;
    font-weight: 500;
  font-size: 0.875rem;
  color: var(--ms3-text-primary, #333);
}

.field-label .required {
  color: var(--ms3-text-danger-alt, #ef4444);
    margin-inline-start: 0.125rem;
}

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
  color: var(--ms3-text-muted, #64748b);
  font-size: 0.75rem;
  margin-top: 0;
    line-height: 1.35;
}
</style>
