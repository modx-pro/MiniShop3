<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import DataTable from 'primevue/datatable'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import RadioButton from 'primevue/radiobutton'
import Select from 'primevue/select'
import SelectButton from 'primevue/selectbutton'
import Toast from 'primevue/toast'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, ref, watch } from 'vue'

import request from '../request.js'

const { _ } = useLexicon()
const toast = useToast()

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
const fieldsError = ref(null)
const previewError = ref(null)

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

const resolveImportErrorMessage = (err, fallbackKey) => err?.message || _(fallbackKey)

const notifyImportError = (detail, consoleLabel, err) => {
  console.error(consoleLabel, err ?? detail)
  toast.add({
    severity: 'error',
    summary: _('error'),
    detail,
    life: 5000,
  })
}

const clearPreviewData = () => {
  csvHeaders.value = []
  csvPreview.value = []
  totalRows.value = 0
  fieldMapping.value = []
  detectedEncoding.value = ''
  exceedsLimit.value = false
  schedulerAvailable.value = false
}

// Methods
const loadAvailableFields = async () => {
  try {
    const response = await request.get('/api/mgr/import/fields')
    const data = response.object || response

    availableFields.value = data.fields || []
    keyFields.value = data.key_fields || []

    if (keyFields.value.length > 0 && !updateKey.value) {
      updateKey.value =
        keyFields.value.find(k => k.value === 'article')?.value || keyFields.value[0].value
    }
  } catch (err) {
    fieldsError.value = resolveImportErrorMessage(err, 'ms3_import_error_load_fields')
    notifyImportError(fieldsError.value, 'Failed to load fields:', err)
  }
}

const previewFile = async () => {
  if (!filePath.value) return

  previewError.value = null

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
    clearPreviewData()
    previewError.value = resolveImportErrorMessage(err, 'ms3_import_error_load_preview')
    notifyImportError(previewError.value, 'Failed to preview file:', err)
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
      name: 'pagetitle',
      title: 'pagetitle',
      product: 'pagetitle',
      category: 'parent',
      category_id: 'parent',
      parent_id: 'parent',
      sku: 'article',
      art: 'article',
      cost: 'price',
      old_cost: 'old_price',
      discount_price: 'old_price',
      stock: 'stock',
      remains: 'stock',
      quantity: 'stock',
      qty: 'stock',
      image: 'gallery',
      photo: 'gallery',
      picture: 'gallery',
      brand: 'vendor',
      manufacturer: 'vendor',
      country: 'made_in',
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

    // Processor envelope: { success, message, object: { … } }
    const data = response.object || response
    importId.value = data.import_id || ''

    if (data.scheduled) {
      importResult.value = {
        success: true,
        message: _('ms3_import_scheduled_success'),
        scheduled: true,
      }
      importCompleted.value = true
    } else if (!useScheduler.value) {
      importResult.value = {
        success: response.success !== false,
        total: data.total || 0,
        created: data.created || 0,
        updated: data.updated || 0,
        errors: data.errors || 0,
        skipped: data.skipped || 0,
      }
      importCompleted.value = true
    }
  } catch (err) {
    const message = resolveImportErrorMessage(err, 'ms3_import_error')
    importResult.value = { success: false, message }
    notifyImportError(message, 'Import failed:', err)
    importCompleted.value = true
  } finally {
    importRunning.value = false
  }
}

const resetImport = () => {
  currentStep.value = 1
  filePath.value = ''
  uploadedFileName.value = ''
  clearPreviewData()
  importId.value = ''
  importProgress.value = null
  importRunning.value = false
  importCompleted.value = false
  importResult.value = null
  uploadError.value = null
  fieldsError.value = null
  previewError.value = null
}

const triggerFileInput = () => {
  fileInputRef.value?.click()
}

const handleFileSelect = async event => {
  const file = event.target.files?.[0]
  if (!file) return

  if (!file.name.toLowerCase().endsWith('.csv')) {
    uploadError.value = _('ms3_utilities_import_file_ext_err') || 'Only CSV files allowed'
    return
  }

  uploading.value = true
  uploadError.value = null
  previewError.value = null

  try {
    const response = await request.upload('/api/mgr/import/upload', file)
    const data = response.object || response
    filePath.value = data.file
    uploadedFileName.value = data.original_name || file.name
    await previewFile()
  } catch (err) {
    uploadError.value = resolveImportErrorMessage(err, 'ms3_import_upload_error')
    notifyImportError(uploadError.value, 'Upload failed:', err)
  } finally {
    uploading.value = false
    if (fileInputRef.value) fileInputRef.value.value = ''
  }
}

const goToStep = step => {
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
    <Toast />
    <p class="tab-description">{{ _('ms3_utilities_import_description') }}</p>

    <Message
      v-if="fieldsError"
      severity="error"
      :closable="true"
      class="import-global-error"
      @close="fieldsError = null"
    >
      {{ fieldsError }}
    </Message>

    <div class="step-indicators">
      <div
        class="step-indicator"
        :class="{ active: currentStep === 1, completed: currentStep > 1 }"
        @click="goToStep(1)"
      >
        <span class="step-number">1</span>
        <span class="step-title">{{ _('ms3_import_step_upload') }}</span>
      </div>
      <div class="step-connector" :class="{ active: currentStep > 1 }"></div>
      <div
        class="step-indicator"
        :class="{
          active: currentStep === 2,
          completed: currentStep > 2,
          disabled: !canProceedToStep2,
        }"
        @click="goToStep(2)"
      >
        <span class="step-number">2</span>
        <span class="step-title">{{ _('ms3_import_step_mapping') }}</span>
      </div>
      <div class="step-connector" :class="{ active: currentStep > 2 }"></div>
      <div
        class="step-indicator"
        :class="{ active: currentStep === 3, disabled: !canProceedToStep3 }"
        @click="goToStep(3)"
      >
        <span class="step-number">3</span>
        <span class="step-title">{{ _('ms3_import_step_import') }}</span>
      </div>
    </div>

    <!-- Step 1: File Upload -->
    <div v-show="currentStep === 1" class="step-content">
      <h3>{{ _('ms3_import_select_file') }}</h3>

      <div class="upload-section">
        <div class="upload-area" :class="{ uploading: uploading }" @click="triggerFileInput">
          <input
            ref="fileInputRef"
            type="file"
            accept=".csv"
            style="display: none"
            @change="handleFileSelect"
          />
          <div class="upload-icon">
            <i v-if="!uploading" class="pi pi-cloud-upload"></i>
            <i v-else class="pi pi-spin pi-spinner"></i>
          </div>
          <div class="upload-text">
            <span v-if="!uploading">{{ _('ms3_import_drop_or_click') }}</span>
            <span v-else>{{ _('ms3_import_uploading') }}</span>
          </div>
          <div class="upload-hint">{{ _('ms3_import_csv_only') }}</div>
        </div>
        <Message v-if="uploadError" severity="error" :closable="true" @close="uploadError = null">{{
          uploadError
        }}</Message>
        <Message
          v-if="previewError"
          severity="error"
          :closable="true"
          @close="previewError = null"
        >
          {{ previewError }}
        </Message>
      </div>

      <div v-if="filePath" class="selected-file">
        <div class="selected-file-header">
          <i class="pi pi-file"></i>
          <span class="file-name">{{ uploadedFileName || filePath }}</span>
          <Button icon="pi pi-times" severity="secondary" text rounded @click="resetImport" />
        </div>
      </div>

      <div v-if="filePath" class="settings-section">
        <h4>{{ _('ms3_import_settings') }}</h4>
        <div class="setting-row">
          <label>{{ _('ms3_import_delimiter') }}</label>
          <SelectButton
            v-model="delimiter"
            :options="delimiterOptions"
            option-label="label"
            option-value="value"
          />
        </div>
        <div class="setting-row">
          <Checkbox v-model="skipHeader" :binary="true" input-id="skipHeader" />
          <label for="skipHeader">{{ _('ms3_import_skip_header') }}</label>
        </div>
      </div>

      <div v-if="totalRows > 0" class="file-info">
        <Message severity="info" :closable="false">
          {{ _('ms3_import_file_info') }}: {{ totalRows }} {{ _('ms3_import_rows') }}
          <span v-if="detectedEncoding" class="encoding-info">
            | {{ _('ms3_import_encoding') }}: {{ detectedEncoding }}</span
          >
        </Message>
        <Message
          v-if="detectedEncoding && detectedEncoding !== 'UTF-8'"
          severity="warn"
          :closable="false"
        >
          {{ _('ms3_import_encoding_converted', { from: detectedEncoding }) }}
        </Message>
        <Message v-if="exceedsLimit && !schedulerAvailable" severity="warn" :closable="false">{{
          _('ms3_import_exceeds_limit_warning')
        }}</Message>
      </div>

      <div class="step-actions">
        <Button
          :label="_('ms3_import_next')"
          icon="pi pi-arrow-right"
          icon-pos="right"
          :disabled="!canProceedToStep2"
          @click="currentStep = 2"
        />
      </div>
    </div>

    <!-- Step 2: Field Mapping -->
    <div v-show="currentStep === 2" class="step-content">
      <h3>{{ _('ms3_import_field_mapping') }}</h3>

      <Message v-if="missingRequiredFields.length > 0" severity="warn" :closable="false">
        {{ _('ms3_import_required_fields_warning') }}:
        <strong>{{ missingRequiredFields.join(', ') }}</strong>
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
            <Select
              v-model="fieldMapping[data.index]"
              :options="availableFields"
              option-label="label"
              option-value="value"
              :placeholder="_('ms3_import_select_field')"
              class="field-select"
              filter
              show-clear
            />
          </template>
        </Column>
        <Column :header="_('ms3_import_preview')">
          <template #body="{ data }">
            <span v-if="csvPreview[skipHeader ? 1 : 0]" class="preview-value">{{
              csvPreview[skipHeader ? 1 : 0][data.index] || '—'
            }}</span>
          </template>
        </Column>
      </DataTable>

      <div class="update-settings">
        <h4>{{ _('ms3_import_update_settings') }}</h4>
        <div class="setting-row">
          <Checkbox v-model="updateExisting" :binary="true" input-id="updateExisting" />
          <label for="updateExisting">{{ _('ms3_import_update_existing') }}</label>
        </div>
        <div v-if="updateExisting" class="setting-row">
          <label>{{ _('ms3_import_update_key') }}:</label>
          <Select
            v-model="updateKey"
            :options="keyFields"
            option-label="label"
            option-value="value"
            class="key-select"
          />
        </div>
      </div>

      <div class="step-actions">
        <Button
          :label="_('ms3_import_back')"
          icon="pi pi-arrow-left"
          severity="secondary"
          @click="currentStep = 1"
        />
        <Button
          :label="_('ms3_import_next')"
          icon="pi pi-arrow-right"
          icon-pos="right"
          :disabled="!canProceedToStep3"
          @click="currentStep = 3"
        />
      </div>
    </div>

    <!-- Step 3: Import -->
    <div v-show="currentStep === 3" class="step-content">
      <h3>{{ _('ms3_import_preview_title') }}</h3>

      <div v-if="!importRunning && !importCompleted" class="preview-section">
        <h4>{{ _('ms3_import_summary') }}</h4>
        <div class="summary-info">
          <div class="summary-row">
            <span class="summary-label">{{ _('ms3_import_file') }}:</span
            ><span class="summary-value">{{ uploadedFileName || filePath }}</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">{{ _('ms3_import_total_rows') }}:</span
            ><span class="summary-value">{{ totalRows }}</span>
          </div>
          <div class="summary-row">
            <span class="summary-label">{{ _('ms3_import_mapped_fields') }}:</span
            ><span class="summary-value">{{ fieldMapping.filter(f => f).length }}</span>
          </div>
        </div>

        <div class="import-options">
          <h4>{{ _('ms3_import_options') }}</h4>
          <div v-if="exceedsLimit" class="import-mode-selection">
            <Message severity="info" :closable="false">{{
              _('ms3_import_large_file_info')
            }}</Message>
            <div v-if="schedulerAvailable" class="mode-options">
              <div class="mode-option">
                <RadioButton v-model="useScheduler" :value="false" input-id="modeSync" /><label
                  for="modeSync"
                  >{{ _('ms3_import_mode_sync') }}</label
                >
              </div>
              <div class="mode-option">
                <RadioButton v-model="useScheduler" :value="true" input-id="modeAsync" /><label
                  for="modeAsync"
                  >{{ _('ms3_import_mode_async') }}</label
                >
              </div>
            </div>
          </div>
          <div class="setting-row">
            <Checkbox v-model="debugMode" :binary="true" input-id="debugMode" /><label
              for="debugMode"
              >{{ _('ms3_import_debug_mode') }}</label
            >
          </div>
        </div>

        <div class="step-actions">
          <Button
            :label="_('ms3_import_back')"
            icon="pi pi-arrow-left"
            severity="secondary"
            @click="currentStep = 2"
          />
          <Button
            :label="_('ms3_import_start')"
            icon="pi pi-play"
            :loading="loading"
            @click="startImport"
          />
        </div>
      </div>

      <div v-if="importRunning" class="progress-section">
        <h4>{{ _('ms3_import_in_progress') }}</h4>
        <ProgressBar mode="indeterminate" class="import-progress-bar" />
        <p>{{ _('ms3_import_please_wait') }}</p>
      </div>

      <div v-if="importCompleted && importResult" class="result-section">
        <Message :severity="importResult.success ? 'success' : 'error'" :closable="false">
          <template v-if="importResult.scheduled">{{ importResult.message }}</template>
          <template v-else-if="importResult.success">
            <div class="result-content">
              <h4>{{ _('ms3_import_complete') }}</h4>
              <div class="result-stats">
                <div class="result-stat">
                  <span class="result-label">{{ _('ms3_import_total_processed') }}:</span
                  ><span class="result-value">{{ importResult.total }}</span>
                </div>
                <div class="result-stat">
                  <span class="result-label">{{ _('ms3_import_created') }}:</span
                  ><span class="result-value success">{{ importResult.created }}</span>
                </div>
                <div class="result-stat">
                  <span class="result-label">{{ _('ms3_import_updated') }}:</span
                  ><span class="result-value info">{{ importResult.updated }}</span>
                </div>
                <div v-if="importResult.errors > 0" class="result-stat">
                  <span class="result-label">{{ _('ms3_import_errors') }}:</span
                  ><span class="result-value error">{{ importResult.errors }}</span>
                </div>
              </div>
            </div>
          </template>
          <template v-else>{{ importResult.message }}</template>
        </Message>
        <div class="step-actions">
          <Button :label="_('ms3_import_new')" icon="pi pi-plus" @click="resetImport" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.import-products {
  padding: 1.25rem;
  max-width: 62.5rem;
}
.import-global-error {
  margin-bottom: 1rem;
}
.step-indicators {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.875rem;
  padding: 1.25rem 0;
}
.step-indicator {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 1rem;
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.2s;
  background: var(--ms3-bg-muted);
}
.step-indicator:hover:not(.disabled) {
  background: var(--ms3-bg-neutral);
}
.step-indicator.active {
  background: var(--ms3-accent-primary);
  color: var(--ms3-text-on-primary);
}
.step-indicator.completed {
  background: var(--ms3-text-success);
  color: var(--ms3-text-on-primary);
}
.step-indicator.disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.step-number {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.1);
  font-weight: 600;
}
.step-indicator.active .step-number,
.step-indicator.completed .step-number {
  background: rgba(255, 255, 255, 0.2);
}
.step-title {
  font-weight: 500;
}
.step-connector {
  width: 2.5rem;
  height: 0.125rem;
  background: var(--ms3-border-color-alt);
  margin: 0 0.5rem;
}
.step-connector.active {
  background: var(--ms3-text-success);
}
.step-content {
  padding: 1.25rem 0;
}
.step-content h3 {
  margin-bottom: 1.25rem;
  font-size: 1.25rem;
  font-weight: 600;
}
.step-content h4 {
  margin: 1.25rem 0 0.625rem;
  font-size: 1rem;
  font-weight: 500;
}
.file-source-tabs {
  margin-bottom: 1.25rem;
}
.source-tab-buttons {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
}
.source-tab-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.625rem 1.25rem;
  border: var(--ms3-border-width) solid var(--ms3-border-color-alt);
  background: var(--ms3-bg-surface);
  border-radius: var(--ms3-radius-md);
  cursor: pointer;
  transition: all 0.2s;
  font-size: 0.9rem;
}
.source-tab-btn:hover {
  background: var(--ms3-bg-muted);
}
.source-tab-btn.active {
  background: var(--ms3-accent-primary);
  color: var(--ms3-text-on-primary);
  border-color: var(--ms3-accent-primary);
}
.source-tab-content {
  min-height: 9.375rem;
}
.upload-area {
  border: var(--ms3-border-width-focus) dashed var(--ms3-border-color-alt);
  border-radius: var(--ms3-radius-lg);
  padding: 2.5rem 1.25rem;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: var(--ms3-bg-gray-50);
}
.upload-area:hover {
  border-color: var(--ms3-accent-primary);
  background: var(--ms3-bg-accent);
}
.upload-area.uploading {
  pointer-events: none;
  opacity: 0.7;
}
.upload-icon {
  font-size: 3rem;
  color: var(--ms3-text-muted);
  margin-bottom: 0.9375rem;
}
.upload-text {
  font-size: 1.1rem;
  margin-bottom: 0.5rem;
}
.upload-hint {
  font-size: 0.85rem;
  color: var(--ms3-text-muted);
}
.modx-browser-section {
  text-align: center;
  padding: 2.5rem 1.25rem;
  background: var(--ms3-bg-gray-50);
  border-radius: var(--ms3-radius-lg);
  border: var(--ms3-border-width) solid var(--ms3-border-color-alt);
}
.browser-hint {
  margin-bottom: 1.25rem;
  color: var(--ms3-text-muted);
}
.selected-file {
  margin: 1.25rem 0;
  padding: 0.75rem 1rem;
  background: var(--ms3-bg-success-light);
  border-radius: var(--ms3-radius-md);
  border: var(--ms3-border-width) solid var(--ms3-border-success-light);
}
.selected-file-header {
  display: flex;
  align-items: center;
  gap: 0.625rem;
}
.selected-file-header i {
  color: var(--ms3-text-success-dark);
  font-size: 1.2rem;
}
.file-name {
  flex: 1;
  font-weight: 500;
  color: var(--ms3-text-success-dark);
}
.settings-section,
.update-settings,
.import-options {
  background: var(--ms3-bg-muted);
  padding: 0.9375rem;
  border-radius: 0.375rem;
  margin: 1.25rem 0;
}
.setting-row {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin: 0.625rem 0;
}
.setting-row label {
  cursor: pointer;
}
.file-info {
  margin: 1.25rem 0;
}
.step-actions {
  display: flex;
  gap: 0.625rem;
  justify-content: flex-end;
  margin-top: 1.875rem;
  padding-top: 1.25rem;
  border-top: var(--ms3-border-width) solid var(--ms3-border-color-alt);
}
.mapping-table {
  margin: 1.25rem 0;
}
.column-info {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}
.column-letter {
  font-weight: 600;
  color: var(--ms3-text-hint);
}
.column-name {
  color: var(--ms3-text-muted);
}
.mapping-arrow {
  color: var(--ms3-text-muted);
}
.field-select {
  width: 100%;
  min-width: 12.5rem;
}
.preview-value {
  color: var(--ms3-text-muted);
  font-size: 0.875rem;
  max-width: 9.375rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  display: block;
}
.key-select {
  min-width: 9.375rem;
}
.summary-info {
  background: var(--ms3-bg-surface);
  padding: 0.9375rem;
  border-radius: var(--ms3-radius-md);
  border: var(--ms3-border-width) solid var(--ms3-border-color-alt);
}
.summary-row {
  display: flex;
  gap: 0.625rem;
  padding: 0.5rem 0;
  border-bottom: var(--ms3-border-width) solid var(--ms3-border-divider);
}
.summary-row:last-child {
  border-bottom: none;
}
.summary-label {
  font-weight: 500;
  min-width: 9.375rem;
}
.mode-options {
  display: flex;
  flex-direction: column;
  gap: 0.625rem;
  margin-top: 0.9375rem;
}
.mode-option {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.625rem;
  background: var(--ms3-bg-surface);
  border-radius: var(--ms3-radius-sm);
}
.mode-option label {
  cursor: pointer;
}
.progress-section {
  text-align: center;
  padding: 2.5rem 1.25rem;
}
.import-progress-bar {
  height: 0.5rem;
  margin: 1.25rem 0;
}
.result-section {
  margin-top: 1.25rem;
}
.result-content h4 {
  margin: 0 0 0.9375rem;
}
.result-stats {
  display: flex;
  gap: 1.25rem;
  flex-wrap: wrap;
}
.result-stat {
  display: flex;
  gap: 0.3125rem;
}
.result-value {
  font-weight: 600;
}
.result-value.success {
  color: var(--ms3-text-success-result);
}
.result-value.info {
  color: var(--ms3-text-info-result);
}
.result-value.error {
  color: var(--ms3-text-error-result);
}
.encoding-info {
  font-weight: 500;
}
</style>
