<script setup>
import { ref, computed, onMounted } from 'vue'
import request from '../request.js'
import { useLexicon } from '@vuetools/useLexicon'

import Button from 'primevue/button'
import ProgressBar from 'primevue/progressbar'
import Message from 'primevue/message'
import InputNumber from 'primevue/inputnumber'
import Card from 'primevue/card'
import Fieldset from 'primevue/fieldset'

const { _ } = useLexicon()

/**
 * Format string with ExtJS-style placeholders {0}, {1}, etc.
 */
const formatString = (str, ...args) => {
  return str.replace(/\{(\d+)\}/g, (match, index) => {
    return args[index] !== undefined ? args[index] : match
  })
}

// Get configuration directly from ms3.config (reactive refs)
const sourceId = ref(1)
const sourceName = ref('')
const totalProducts = ref(0)
const totalFiles = ref(0)
const thumbnailsInfo = ref('')

// Load config from data-attributes of mount element
const loadConfig = () => {
  const el = document.getElementById('ms3-vue-utilities-gallery')
  if (el) {
    sourceId.value = parseInt(el.dataset.sourceId) || 1
    sourceName.value = el.dataset.sourceName || ''
    totalProducts.value = parseInt(el.dataset.totalProducts) || 0
    totalFiles.value = parseInt(el.dataset.totalFiles) || 0
    thumbnailsInfo.value = decodeURIComponent(el.dataset.thumbnails || '')
  }
}

// State
const limit = ref(10)
const offset = ref(0)
const total = ref(0)

const isRunning = ref(false)
const isCompleted = ref(false)
const progress = ref(0)
const currentIteration = ref(0)
const totalIterations = ref(0)
const updatedCount = ref(0)
const errorMessage = ref(null)

// Computed
const galleryInfoHtml = computed(() => {
  const template = _('ms3_utilities_gallery_information')
  // Format: {0}=source_name, {1}=source_id, {2}=total_products, {3}=total_files
  return formatString(
    template,
    sourceName.value,
    sourceId.value,
    totalProducts.value,
    totalFiles.value
  )
})

const progressPercent = computed(() => {
  if (total.value === 0) return 0
  return Math.min(100, parseFloat((offset.value / total.value) * 100).toFixed(2))
})

const canStart = computed(() => {
  return !isRunning.value && total.value > 0
})

const statusMessage = computed(() => {
  if (isCompleted.value) {
    return _('ms3_utilities_gallery_done_message', `Updated ${updatedCount.value} products`)
  }
  if (isRunning.value) {
    return _('ms3_utilities_gallery_updating', 'Updating thumbnails...')
  }
  return ''
})

// Methods
const startRegeneration = async () => {
  isRunning.value = true
  isCompleted.value = false
  errorMessage.value = null
  offset.value = 0
  updatedCount.value = 0
  progress.value = 0
  totalIterations.value = Math.ceil(total.value / limit.value)
  currentIteration.value = 0

  await processNextBatch()
}

const processNextBatch = async () => {
  try {
    const response = await request.post('/api/mgr/utilities/gallery/update', {
      limit: limit.value,
      offset: offset.value
    })

    const data = response.object || response

    updatedCount.value += data.updated || 0
    total.value = data.total || total.value
    currentIteration.value = Math.floor(offset.value / limit.value) + 1

    if (data.done) {
      isCompleted.value = true
      isRunning.value = false
      progress.value = 100
    } else {
      offset.value = data.offset
      progress.value = progressPercent.value
      // Continue with next batch
      await processNextBatch()
    }
  } catch (err) {
    console.error('Gallery regeneration failed:', err)
    errorMessage.value = err.message || _('ms3_utilities_gallery_err_noproducts', 'Error regenerating thumbnails')
    isRunning.value = false
  }
}

const resetState = () => {
  isRunning.value = false
  isCompleted.value = false
  offset.value = 0
  progress.value = 0
  updatedCount.value = 0
  currentIteration.value = 0
  errorMessage.value = null
}

onMounted(() => {
  loadConfig()
  total.value = totalProducts.value
  totalIterations.value = Math.ceil(total.value / limit.value)
})
</script>

<template>
  <div class="utilities-gallery">
    <!-- Info Section -->
    <Card class="info-card">
      <template #content>
        <div class="info-content" v-html="galleryInfoHtml"></div>
      </template>
    </Card>

    <!-- Thumbnails Configuration -->
    <Fieldset :legend="_('ms3_utilities_params', 'Parameters')" :toggleable="true" class="params-fieldset">
      <div class="thumbnails-info" v-html="thumbnailsInfo"></div>
    </Fieldset>

    <!-- Settings -->
    <div class="settings-section">
      <div class="setting-row">
        <label for="limit-input">{{ _('ms3_utilities_gallery_for_step', 'Products per step') }}</label>
        <InputNumber
          v-model="limit"
          inputId="limit-input"
          :min="1"
          :max="100"
          :disabled="isRunning"
          showButtons
          buttonLayout="horizontal"
          :step="5"
          decrementButtonClass="p-button-secondary"
          incrementButtonClass="p-button-secondary"
          incrementButtonIcon="pi pi-plus"
          decrementButtonIcon="pi pi-minus"
        />
      </div>
    </div>

    <!-- Action Button -->
    <div class="action-section">
      <Button
        :label="_('ms3_utilities_gallery_refresh', 'Regenerate Thumbnails')"
        icon="pi pi-refresh"
        :loading="isRunning"
        :disabled="!canStart"
        @click="startRegeneration"
        severity="primary"
      />
      <Button
        v-if="isCompleted"
        :label="_('ms3_utilities_gallery_reset', 'Reset')"
        icon="pi pi-times"
        severity="secondary"
        @click="resetState"
        class="reset-btn"
      />
    </div>

    <!-- Progress Section -->
    <div class="progress-section" v-if="isRunning || isCompleted">
      <div class="progress-labels">
        <span class="progress-percent">{{ progress }}%</span>
        <span class="progress-iteration" v-if="!isCompleted">
          {{ currentIteration }} / {{ totalIterations }}
        </span>
      </div>
      <ProgressBar :value="progress" :showValue="false" class="progress-bar" />
    </div>

    <!-- Status Messages -->
    <Message v-if="isCompleted" severity="success" :closable="false" class="status-message">
      <i class="pi pi-check-circle"></i>
      {{ _('ms3_utilities_gallery_done', 'Done!') }}
      {{ _('ms3_utilities_gallery_done_message', `Updated ${updatedCount} products`) }}
    </Message>

    <Message v-if="errorMessage" severity="error" :closable="true" @close="errorMessage = null" class="status-message">
      {{ errorMessage }}
    </Message>
  </div>
</template>

<style scoped>
.utilities-gallery {
  padding: 20px;
  max-width: 800px;
}

.info-card {
  margin-bottom: 20px;
}

.info-content {
  line-height: 1.8;
}

.info-content :deep(strong) {
  color: #1e40af;
}

.params-fieldset {
  margin-bottom: 20px;
}

.thumbnails-info {
  font-family: monospace;
  font-size: 0.9rem;
  line-height: 1.6;
  background: #f8f9fa;
  padding: 10px;
  border-radius: 4px;
}

.thumbnails-info :deep(strong) {
  color: #495057;
}

.settings-section {
  background: #f8f9fa;
  padding: 15px 20px;
  border-radius: 6px;
  margin-bottom: 20px;
}

.setting-row {
  display: flex;
  align-items: center;
  gap: 15px;
}

.setting-row label {
  font-weight: 500;
  min-width: 150px;
}

.action-section {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.reset-btn {
  margin-left: auto;
}

.progress-section {
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 6px;
  padding: 15px;
  margin-bottom: 20px;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  margin-bottom: 10px;
  font-weight: 600;
}

.progress-percent {
  color: #1e40af;
}

.progress-iteration {
  color: #6c757d;
}

.progress-bar {
  height: 8px;
}

.progress-bar :deep(.p-progressbar-value) {
  background: #32AB9A;
}

.status-message {
  margin-top: 15px;
}

.status-message :deep(.pi-check-circle) {
  margin-right: 8px;
}
</style>
