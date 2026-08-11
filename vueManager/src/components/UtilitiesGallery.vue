<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Fieldset from 'primevue/fieldset'
import InputNumber from 'primevue/inputnumber'
import Message from 'primevue/message'
import ProgressBar from 'primevue/progressbar'
import { computed, onMounted, ref } from 'vue'

import request from '../request.js'
import { getMs3Config } from '../utils/modx.js'

const { _ } = useLexicon()

/**
 * Format string with ExtJS-style placeholders {0}, {1}, etc.
 */
const formatString = (str, ...args) => {
  return str.replace(/\{(\d+)\}/g, (match, index) => {
    return args[index] !== undefined ? args[index] : match
  })
}

const sourceId = ref(1)
const sourceName = ref('')
const totalProducts = ref(0)
const totalFiles = ref(0)
const thumbnailsInfo = ref('')

function loadConfig() {
  const cfg = getMs3Config() || {}
  sourceId.value = parseInt(cfg.utility_gallery_source_id, 10) || 1
  sourceName.value = cfg.utility_gallery_source_name || ''
  totalProducts.value = parseInt(cfg.utility_gallery_total_products, 10) || 0
  totalFiles.value = parseInt(cfg.utility_gallery_total_products_files, 10) || 0
  thumbnailsInfo.value = cfg.utility_gallery_thumbnails || ''
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
      offset: offset.value,
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
    errorMessage.value =
      err.message || _('ms3_utilities_gallery_err_noproducts', 'Error regenerating thumbnails')
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
    <Fieldset
      :legend="_('ms3_utilities_params', 'Parameters')"
      :toggleable="true"
      class="params-fieldset"
    >
      <div class="thumbnails-info" v-html="thumbnailsInfo"></div>
    </Fieldset>

    <!-- Settings -->
    <div class="settings-section">
      <div class="setting-row">
        <label for="limit-input">{{
          _('ms3_utilities_gallery_for_step', 'Products per step')
        }}</label>
        <InputNumber
          v-model="limit"
          input-id="limit-input"
          :min="1"
          :max="100"
          :disabled="isRunning"
          show-buttons
          button-layout="horizontal"
          :step="5"
          decrement-button-class="p-button-secondary"
          increment-button-class="p-button-secondary"
          increment-button-icon="pi pi-plus"
          decrement-button-icon="pi pi-minus"
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
        severity="primary"
        @click="startRegeneration"
      />
      <Button
        v-if="isCompleted"
        :label="_('ms3_utilities_gallery_reset', 'Reset')"
        icon="pi pi-times"
        severity="secondary"
        class="reset-btn"
        @click="resetState"
      />
    </div>

    <!-- Progress Section -->
    <div v-if="isRunning || isCompleted" class="progress-section">
      <div class="progress-labels">
        <span class="progress-percent">{{ progress }}%</span>
        <span v-if="!isCompleted" class="progress-iteration">
          {{ currentIteration }} / {{ totalIterations }}
        </span>
      </div>
      <ProgressBar :value="progress" :show-value="false" class="progress-bar" />
    </div>

    <!-- Status Messages -->
    <Message v-if="isCompleted" severity="success" :closable="false" class="status-message">
      <i class="pi pi-check-circle"></i>
      {{ _('ms3_utilities_gallery_done', 'Done!') }}
      {{ _('ms3_utilities_gallery_done_message', `Updated ${updatedCount} products`) }}
    </Message>

    <Message
      v-if="errorMessage"
      severity="error"
      :closable="true"
      class="status-message"
      @close="errorMessage = null"
    >
      {{ errorMessage }}
    </Message>
  </div>
</template>

<style scoped>
.utilities-gallery {
  padding: 1.25rem;
  max-width: 50rem;
}

.info-card {
  margin-bottom: 1.25rem;
}

.info-content {
  line-height: 1.8;
}

.info-content :deep(strong) {
  color: var(--ms3-text-accent-dark);
}

.params-fieldset {
  margin-bottom: 1.25rem;
}

.thumbnails-info {
  font-family: monospace;
  font-size: 0.9rem;
  line-height: 1.6;
  background: var(--ms3-bg-muted);
  padding: 0.625rem;
  border-radius: 0.25rem;
}

.thumbnails-info :deep(strong) {
  color: var(--ms3-text-hint);
}

.settings-section {
  background: var(--ms3-bg-muted);
  padding: 0.9375rem 1.25rem;
  border-radius: 0.375rem;
  margin-bottom: 1.25rem;
}

.setting-row {
  display: flex;
  align-items: center;
  gap: 0.9375rem;
}

.setting-row label {
  font-weight: 500;
  min-width: 9.375rem;
}

.action-section {
  display: flex;
  gap: 0.625rem;
  margin-bottom: 1.25rem;
}

.reset-btn {
  margin-left: auto;
}

.progress-section {
  background: var(--ms3-bg-surface);
  border: var(--ms3-border-width) solid var(--ms3-border-color-alt);
  border-radius: 0.375rem;
  padding: 0.9375rem;
  margin-bottom: 1.25rem;
}

.progress-labels {
  display: flex;
  justify-content: space-between;
  margin-bottom: 0.625rem;
  font-weight: 600;
}

.progress-percent {
  color: var(--ms3-text-accent-dark);
}

.progress-iteration {
  color: var(--ms3-text-muted);
}

.progress-bar {
  height: 0.5rem;
}

.progress-bar :deep(.p-progressbar-value) {
  background: var(--ms3-accent-teal);
}

.status-message {
  margin-top: 0.9375rem;
}

.status-message :deep(.pi-check-circle) {
  margin-right: 0.5rem;
}
</style>
