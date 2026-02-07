<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import request from '../request.js'
import { useLexicon } from '@vuetools/useLexicon'

import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import ProgressBar from 'primevue/progressbar'
import Message from 'primevue/message'
import RadioButton from 'primevue/radiobutton'
import SelectButton from 'primevue/selectbutton'

const { _ } = useLexicon()

// State
const currentStep = ref(1)
const filePath = ref('')
const uploadedFileName = ref('')
const delimiter = ref(';')
const skipHeader = ref(true)
const updateExisting = ref(true)
const updateKey = ref('article')
const useScheduler = ref(false)
const debugMode = ref(false)

// File upload state
const fileInputRef = ref(null)
const uploading = ref(false)
const uploadError = ref(null)

// Data from backend
const availableFields = ref([])
const keyFields = ref([])
const csvHeaders = ref([])
const csvPreview = ref([])
const totalRows = ref(0)
const syncLimit = ref(300)
const exceedsLimit = ref(false)
const schedulerAvailable = ref(false)
const detectedEncoding = ref('')

// Mapping: column index -> field name
const fieldMapping = ref([])

// Import state
const importId = ref('')
const importProgress = ref(null)
const importRunning = ref(false)
const importCompleted = ref(false)
const importResult = ref(null)

// Delimiter options
const delimiterOptions = ref([
  { label: ';', value: ';' },
  { label: ',', value: ',' },
  { label: 'Tab', value: '\t' },
])

// Computed
const missingRequiredFields = computed(() => {
  const required = ['pagetitle', 'parent']
  return required.filter(field => !fieldMapping.value.includes(field))
})

const canProceedToStep2 = computed(() => {
  return filePath.value && csvHeaders.value.length > 0
})

const canProceedToStep3 = computed(() => {
  return missingRequiredFields.value.length === 0
})

// Methods
const loadAvailableFields = async () => {
  try {
    const response = await request.get('/api/mgr/import/fields')
    const data = response.object || response

    availableFields.value = data.fields || []
    keyFields.value = data.key_fields || []

    if (keyFields.value.length > 0 && !updateKey.value) {
      updateKey.value = keyFields.value.find(k => k.value === 'article')?.value || keyFields.value[0].value
    }
  } catch (err) {
    console.error('Failed to load fields:', err)
  }
}

const previewFile = async () => {
  if (!filePath.value) return

  try {
    const response = await request.post('/api/mgr/import/preview', {
      file: filePath.value,
      delimiter: delimiter.value,
      rows: 5,
    })
    const data = response.object || response

    csvHeaders.value = data.headers || []
    csvPreview.value = data.preview || []
    totalRows.value = data.total_rows || 0
    syncLimit.value = data.sync_limit || 300
    exceedsLimit.value = data.exceeds_limit || false
    schedulerAvailable.value = data.scheduler_available || false
    detectedEncoding.value = data.encoding || 'UTF-8'

    fieldMapping.value = csvHeaders.value.map(() => null)
    autoMapFields()
  } catch (err) {
    console.error('Failed to preview file:', err)
  }
}

const autoMapFields = () => {
  const headerLower = csvHeaders.value.map(h => h?.toLowerCase?.() || '')

  headerLower.forEach((header, index) => {
    const exactMatch = availableFields.value.find(f => f.value.toLowerCase() === header)
    if (exactMatch) {
      fieldMapping.value[index] = exactMatch.value
      return
    }

    const commonMappings = {
      'name': 'pagetitle', 'title': 'pagetitle', 'product': 'pagetitle',
      'category': 'parent', 'category_id': 'parent', 'parent_id': 'parent',
      'sku': 'article', 'art': 'article',
      'cost': 'price', 'old_cost': 'old_price', 'discount_price': 'old_price',
      'image': 'gallery', 'photo': 'gallery', 'picture': 'gallery',
      'brand': 'vendor', 'manufacturer': 'vendor', 'country': 'made_in',
    }

    for (const [key, value] of Object.entries(commonMappings)) {
      if (header.includes(key)) {
        const field = availableFields.value.find(f => f.value === value)
        if (field) {
          fieldMapping.value[index] = field.value
          return
        }
      }
    }
  })
}

const startImport = async () => {
  importRunning.value = true
  importCompleted.value = false
  importResult.value = null

  try {
    const mappingObj = {}
    csvHeaders.value.forEach((header, index) => {
      if (fieldMapping.value[index]) {
        mappingObj[index] = fieldMapping.value[index]
      }
    })

    const response = await request.post('/api/mgr/import/start', {
      importfile: filePath.value,
      mapping: JSON.stringify(mappingObj),
      delimiter: delimiter.value,
      skip_header: skipHeader.value,
      update: updateExisting.value,
      key: updateKey.value,
      scheduler: useScheduler.value,
      debug: debugMode.value,
    })

    importId.value = response.import_id || ''

    if (response.scheduled) {
      importResult.value = { success: true, message: _('ms3_import_scheduled_success'), scheduled: true }
      importCompleted.value = true
      importRunning.value = false
    } else if (!useScheduler.value) {
      importResult.value = {
        success: response.success !== false,
        total: response.total || 0,
        created: response.created || 0,
        updated: response.updated || 0,
        errors: response.errors || 0,
        skipped: response.skipped || 0,
      }
      importCompleted.value = true
      importRunning.value = false
    }
  } catch (err) {
    console.error('Import failed:', err)
    importResult.value = { success: false, message: err.message || _('ms3_import_error') }
    importRunning.value = false
    importCompleted.value = true
  }
}

const resetImport = () => {
  currentStep.value = 1
  filePath.value = ''
  uploadedFileName.value = ''
  csvHeaders.value = []
  csvPreview.value = []
  fieldMapping.value = []
  totalRows.value = 0
  detectedEncoding.value = ''
  importId.value = ''
  importProgress.value = null
  importRunning.value = false
  importCompleted.value = false
  importResult.value = null
  uploadError.value = null
}

const triggerFileInput = () => {
  fileInputRef.value?.click()
}

const handleFileSelect = async (event) => {
  const file = event.target.files?.[0]
  if (!file) return

  if (!file.name.toLowerCase().endsWith('.csv')) {
    uploadError.value = _('ms3_utilities_import_file_ext_err') || 'Only CSV files allowed'
    return
  }

  uploading.value = true
  uploadError.value = null

  try {
    const response = await request.upload('/api/mgr/import/upload', file)
    const data = response.object || response
    filePath.value = data.file
    uploadedFileName.value = data.original_name || file.name
    await previewFile()
  } catch (err) {
    console.error('Upload failed:', err)
    uploadError.value = err.message || _('ms3_import_upload_error') || 'Upload failed'
  } finally {
    uploading.value = false
    if (fileInputRef.value) fileInputRef.value.value = ''
  }
}

const goToStep = (step) => {
  if (step === 2 && !canProceedToStep2.value) return
  if (step === 3 && !canProceedToStep3.value) return
  currentStep.value = step
}

watch(delimiter, () => {
  if (filePath.value) previewFile()
})

onMounted(() => {
  loadAvailableFields()
})
</script>

<template>
  <div class="import-products">
    <p class="tab-description">{{ _('ms3_utilities_import_description') }}</p>

    <div class="step-indicators">
      <div class="step-indicator" :class="{ active: currentStep === 1, completed: currentStep > 1 }" @click="goToStep(1)">
        <span class="step-number">1</span>
        <span class="step-title">{{ _('ms3_import_step_upload') }}</span>
      </div>
      <div class="step-connector" :class="{ active: currentStep > 1 }"></div>
      <div class="step-indicator" :class="{ active: currentStep === 2, completed: currentStep > 2, disabled: !canProceedToStep2 }" @click="goToStep(2)">
        <span class="step-number">2</span>
        <span class="step-title">{{ _('ms3_import_step_mapping') }}</span>
      </div>
      <div class="step-connector" :class="{ active: currentStep > 2 }"></div>
      <div class="step-indicator" :class="{ active: currentStep === 3, disabled: !canProceedToStep3 }" @click="goToStep(3)">
        <span class="step-number">3</span>
        <span class="step-title">{{ _('ms3_import_step_import') }}</span>
      </div>
    </div>

    <!-- Step 1: File Upload -->
    <div class="step-content" v-show="currentStep === 1">
      <h3>{{ _('ms3_import_select_file') }}</h3>

      <div class="upload-section">
        <div class="upload-area" @click="triggerFileInput" :class="{ uploading: uploading }">
          <input type="file" ref="fileInputRef" accept=".csv" @change="handleFileSelect" style="display: none" />
          <div class="upload-icon">
            <i class="pi pi-cloud-upload" v-if="!uploading"></i>
            <i class="pi pi-spin pi-spinner" v-else></i>
          </div>
          <div class="upload-text">
            <span v-if="!uploading">{{ _('ms3_import_drop_or_click') }}</span>
            <span v-else>{{ _('ms3_import_uploading') }}</span>
          </div>
          <div class="upload-hint">{{ _('ms3_import_csv_only') }}</div>
        </div>
        <Message v-if="uploadError" severity="error" :closable="true" @close="uploadError = null">{{ uploadError }}</Message>
      </div>

      <div class="selected-file" v-if="filePath">
        <div class="selected-file-header">
          <i class="pi pi-file"></i>
          <span class="file-name">{{ uploadedFileName || filePath }}</span>
          <Button icon="pi pi-times" severity="secondary" text rounded @click="resetImport" />
        </div>
      </div>

      <div class="settings-section" v-if="filePath">
        <h4>{{ _('ms3_import_settings') }}</h4>
        <div class="setting-row">
          <label>{{ _('ms3_import_delimiter') }}</label>
          <SelectButton v-model="delimiter" :options="delimiterOptions" optionLabel="label" optionValue="value" />
        </div>
        <div class="setting-row">
          <Checkbox v-model="skipHeader" :binary="true" inputId="skipHeader" />
          <label for="skipHeader">{{ _('ms3_import_skip_header') }}</label>
        </div>
      </div>

      <div class="file-info" v-if="totalRows > 0">
        <Message severity="info" :closable="false">
          {{ _('ms3_import_file_info') }}: {{ totalRows }} {{ _('ms3_import_rows') }}
          <span v-if="detectedEncoding" class="encoding-info"> | {{ _('ms3_import_encoding') }}: {{ detectedEncoding }}</span>
        </Message>
        <Message v-if="detectedEncoding && detectedEncoding !== 'UTF-8'" severity="warn" :closable="false">
          {{ _('ms3_import_encoding_converted', { from: detectedEncoding }) }}
        </Message>
        <Message v-if="exceedsLimit && !schedulerAvailable" severity="warn" :closable="false">{{ _('ms3_import_exceeds_limit_warning') }}</Message>
      </div>

      <div class="step-actions">
        <Button :label="_('ms3_import_next')" icon="pi pi-arrow-right" iconPos="right" @click="currentStep = 2" :disabled="!canProceedToStep2" />
      </div>
    </div>

    <!-- Step 2: Field Mapping -->
    <div class="step-content" v-show="currentStep === 2">
      <h3>{{ _('ms3_import_field_mapping') }}</h3>

      <Message v-if="missingRequiredFields.length > 0" severity="warn" :closable="false">
        {{ _('ms3_import_required_fields_warning') }}: <strong>{{ missingRequiredFields.join(', ') }}</strong>
      </Message>

      <DataTable :value="csvHeaders.map((h, i) => ({ index: i, header: h }))" class="mapping-table">
        <Column field="header" :header="_('ms3_import_csv_column')">
          <template #body="{ data }">
            <div class="column-info">
              <span class="column-letter">{{ String.fromCharCode(65 + data.index) }}:</span>
              <span class="column-name">"{{ data.header }}"</span>
            </div>
          </template>
        </Column>
        <Column :header="_('ms3_import_maps_to')">
          <template #body><i class="pi pi-arrow-right mapping-arrow"></i></template>
        </Column>
        <Column field="mapping" :header="_('ms3_import_product_field')">
          <template #body="{ data }">
            <Select v-model="fieldMapping[data.index]" :options="availableFields" optionLabel="label" optionValue="value" :placeholder="_('ms3_import_select_field')" class="field-select" filter showClear />
          </template>
        </Column>
        <Column :header="_('ms3_import_preview')">
          <template #body="{ data }">
            <span class="preview-value" v-if="csvPreview[skipHeader ? 1 : 0]">{{ csvPreview[skipHeader ? 1 : 0][data.index] || '—' }}</span>
          </template>
        </Column>
      </DataTable>

      <div class="update-settings">
        <h4>{{ _('ms3_import_update_settings') }}</h4>
        <div class="setting-row">
          <Checkbox v-model="updateExisting" :binary="true" inputId="updateExisting" />
          <label for="updateExisting">{{ _('ms3_import_update_existing') }}</label>
        </div>
        <div class="setting-row" v-if="updateExisting">
          <label>{{ _('ms3_import_update_key') }}:</label>
          <Select v-model="updateKey" :options="keyFields" optionLabel="label" optionValue="value" class="key-select" />
        </div>
      </div>

      <div class="step-actions">
        <Button :label="_('ms3_import_back')" icon="pi pi-arrow-left" severity="secondary" @click="currentStep = 1" />
        <Button :label="_('ms3_import_next')" icon="pi pi-arrow-right" iconPos="right" @click="currentStep = 3" :disabled="!canProceedToStep3" />
      </div>
    </div>

    <!-- Step 3: Import -->
    <div class="step-content" v-show="currentStep === 3">
      <h3>{{ _('ms3_import_preview_title') }}</h3>

      <div class="preview-section" v-if="!importRunning && !importCompleted">
        <h4>{{ _('ms3_import_summary') }}</h4>
        <div class="summary-info">
          <div class="summary-row"><span class="summary-label">{{ _('ms3_import_file') }}:</span><span class="summary-value">{{ uploadedFileName || filePath }}</span></div>
          <div class="summary-row"><span class="summary-label">{{ _('ms3_import_total_rows') }}:</span><span class="summary-value">{{ totalRows }}</span></div>
          <div class="summary-row"><span class="summary-label">{{ _('ms3_import_mapped_fields') }}:</span><span class="summary-value">{{ fieldMapping.filter(f => f).length }}</span></div>
        </div>

        <div class="import-options">
          <h4>{{ _('ms3_import_options') }}</h4>
          <div v-if="exceedsLimit" class="import-mode-selection">
            <Message severity="info" :closable="false">{{ _('ms3_import_large_file_info') }}</Message>
            <div class="mode-options" v-if="schedulerAvailable">
              <div class="mode-option"><RadioButton v-model="useScheduler" :value="false" inputId="modeSync" /><label for="modeSync">{{ _('ms3_import_mode_sync') }}</label></div>
              <div class="mode-option"><RadioButton v-model="useScheduler" :value="true" inputId="modeAsync" /><label for="modeAsync">{{ _('ms3_import_mode_async') }}</label></div>
            </div>
          </div>
          <div class="setting-row"><Checkbox v-model="debugMode" :binary="true" inputId="debugMode" /><label for="debugMode">{{ _('ms3_import_debug_mode') }}</label></div>
        </div>

        <div class="step-actions">
          <Button :label="_('ms3_import_back')" icon="pi pi-arrow-left" severity="secondary" @click="currentStep = 2" />
          <Button :label="_('ms3_import_start')" icon="pi pi-play" @click="startImport" :loading="loading" />
        </div>
      </div>

      <div class="progress-section" v-if="importRunning">
        <h4>{{ _('ms3_import_in_progress') }}</h4>
        <ProgressBar mode="indeterminate" class="import-progress-bar" />
        <p>{{ _('ms3_import_please_wait') }}</p>
      </div>

      <div class="result-section" v-if="importCompleted && importResult">
        <Message :severity="importResult.success ? 'success' : 'error'" :closable="false">
          <template v-if="importResult.scheduled">{{ importResult.message }}</template>
          <template v-else-if="importResult.success">
            <div class="result-content">
              <h4>{{ _('ms3_import_complete') }}</h4>
              <div class="result-stats">
                <div class="result-stat"><span class="result-label">{{ _('ms3_import_total_processed') }}:</span><span class="result-value">{{ importResult.total }}</span></div>
                <div class="result-stat"><span class="result-label">{{ _('ms3_import_created') }}:</span><span class="result-value success">{{ importResult.created }}</span></div>
                <div class="result-stat"><span class="result-label">{{ _('ms3_import_updated') }}:</span><span class="result-value info">{{ importResult.updated }}</span></div>
                <div class="result-stat" v-if="importResult.errors > 0"><span class="result-label">{{ _('ms3_import_errors') }}:</span><span class="result-value error">{{ importResult.errors }}</span></div>
              </div>
            </div>
          </template>
          <template v-else>{{ importResult.message }}</template>
        </Message>
        <div class="step-actions"><Button :label="_('ms3_import_new')" icon="pi pi-plus" @click="resetImport" /></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.import-products { padding: 20px; max-width: 1000px; }
.step-indicators { display: flex; align-items: center; justify-content: center; margin-bottom: 30px; padding: 20px 0; }
.step-indicator { display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; cursor: pointer; transition: all 0.2s; background: #f8f9fa; }
.step-indicator:hover:not(.disabled) { background: #e9ecef; }
.step-indicator.active { background: #3b82f6; color: white; }
.step-indicator.completed { background: #22c55e; color: white; }
.step-indicator.disabled { opacity: 0.5; cursor: not-allowed; }
.step-number { display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: rgba(0, 0, 0, 0.1); font-weight: 600; }
.step-indicator.active .step-number, .step-indicator.completed .step-number { background: rgba(255, 255, 255, 0.2); }
.step-title { font-weight: 500; }
.step-connector { width: 40px; height: 2px; background: #dee2e6; margin: 0 8px; }
.step-connector.active { background: #22c55e; }
.step-content { padding: 20px 0; }
.step-content h3 { margin-bottom: 20px; font-size: 1.25rem; font-weight: 600; }
.step-content h4 { margin: 20px 0 10px; font-size: 1rem; font-weight: 500; }
.file-source-tabs { margin-bottom: 20px; }
.source-tab-buttons { display: flex; gap: 8px; margin-bottom: 16px; }
.source-tab-btn { display: flex; align-items: center; gap: 8px; padding: 10px 20px; border: 1px solid #dee2e6; background: #fff; border-radius: 6px; cursor: pointer; transition: all 0.2s; font-size: 0.9rem; }
.source-tab-btn:hover { background: #f8f9fa; }
.source-tab-btn.active { background: #3b82f6; color: white; border-color: #3b82f6; }
.source-tab-content { min-height: 150px; }
.upload-area { border: 2px dashed #dee2e6; border-radius: 8px; padding: 40px 20px; text-align: center; cursor: pointer; transition: all 0.2s; background: #fafafa; }
.upload-area:hover { border-color: #3b82f6; background: #f0f7ff; }
.upload-area.uploading { pointer-events: none; opacity: 0.7; }
.upload-icon { font-size: 3rem; color: #6c757d; margin-bottom: 15px; }
.upload-text { font-size: 1.1rem; margin-bottom: 8px; }
.upload-hint { font-size: 0.85rem; color: #6c757d; }
.modx-browser-section { text-align: center; padding: 40px 20px; background: #fafafa; border-radius: 8px; border: 1px solid #dee2e6; }
.browser-hint { margin-bottom: 20px; color: #6c757d; }
.selected-file { margin: 20px 0; padding: 12px 16px; background: #e8f5e9; border-radius: 6px; border: 1px solid #c8e6c9; }
.selected-file-header { display: flex; align-items: center; gap: 10px; }
.selected-file-header i { color: #2e7d32; font-size: 1.2rem; }
.file-name { flex: 1; font-weight: 500; color: #2e7d32; }
.settings-section, .update-settings, .import-options { background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 20px 0; }
.setting-row { display: flex; align-items: center; gap: 10px; margin: 10px 0; }
.setting-row label { cursor: pointer; }
.file-info { margin: 20px 0; }
.step-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px; padding-top: 20px; border-top: 1px solid #dee2e6; }
.mapping-table { margin: 20px 0; }
.column-info { display: flex; gap: 8px; align-items: center; }
.column-letter { font-weight: 600; color: #495057; }
.column-name { color: #6c757d; }
.mapping-arrow { color: #6c757d; }
.field-select { width: 100%; min-width: 200px; }
.preview-value { color: #6c757d; font-size: 0.875rem; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
.key-select { min-width: 150px; }
.summary-info { background: #fff; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6; }
.summary-row { display: flex; gap: 10px; padding: 8px 0; border-bottom: 1px solid #eee; }
.summary-row:last-child { border-bottom: none; }
.summary-label { font-weight: 500; min-width: 150px; }
.mode-options { display: flex; flex-direction: column; gap: 10px; margin-top: 15px; }
.mode-option { display: flex; align-items: center; gap: 10px; padding: 10px; background: #fff; border-radius: 4px; }
.mode-option label { cursor: pointer; }
.progress-section { text-align: center; padding: 40px 20px; }
.import-progress-bar { height: 8px; margin: 20px 0; }
.result-section { margin-top: 20px; }
.result-content h4 { margin: 0 0 15px; }
.result-stats { display: flex; gap: 20px; flex-wrap: wrap; }
.result-stat { display: flex; gap: 5px; }
.result-value { font-weight: 600; }
.result-value.success { color: #155724; }
.result-value.info { color: #0c5460; }
.result-value.error { color: #721c24; }
.encoding-info { font-weight: 500; }
</style>
