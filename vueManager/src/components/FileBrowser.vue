<script setup>
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { computed, ref, watch } from 'vue'

import { normalizeImagePath } from '../utils/displayFormatters.js'

const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  source: {
    type: [Number, String],
    default: null,
  },
  allowedFileTypes: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const inputValue = ref(props.modelValue)

// Sync inputValue with modelValue prop
watch(
  () => props.modelValue,
  newValue => {
    inputValue.value = newValue
  }
)

// Update v-model when input changes
function onInputChange(event) {
  const value = event.target.value
  inputValue.value = value
  emit('update:modelValue', value)
}

// Computed source for MODX Browser
const browserSource = computed(() => {
  if (props.source) {
    return props.source
  }
  // Use MODX default media source
  if (typeof MODx !== 'undefined' && MODx.config && MODx.config.default_media_source) {
    return MODx.config.default_media_source
  }
  return 1 // Fallback to source ID 1
})

/**
 * Open MODX file browser
 */
function openBrowser() {
  if (props.disabled) return

  // Check if MODx is available
  if (typeof MODx === 'undefined' || !MODx.load) {
    console.error('[FileBrowser] MODx.load is not available')
    return
  }

  // Generate unique ID for this browser instance
  const browserId = Ext.id()

  // Create callback to handle file selection
  const onSelectFile = data => {
    if (data && data.fullRelativeUrl) {
      inputValue.value = data.fullRelativeUrl
      emit('update:modelValue', data.fullRelativeUrl)
    } else if (data && data.url) {
      inputValue.value = data.url
      emit('update:modelValue', data.url)
    }
  }

  // Load MODX browser with configuration matching ExtJS implementation
  const browser = MODx.load({
    xtype: 'modx-browser',
    id: browserId,
    multiple: true,
    source: browserSource.value,
    rootVisible: false,
    allowedFileTypes: props.allowedFileTypes,
    wctx: 'web',
    openTo: '',
    rootId: '/',
    hideSourceCombo: false,
    hideFiles: true,
    listeners: {
      select: {
        fn: onSelectFile,
      },
    },
  })

  if (browser) {
    // Enable OK button when it gets disabled (MODX behavior)
    if (browser.win && browser.win.buttons && browser.win.buttons[0]) {
      browser.win.buttons[0].on('disable', function () {
        this.enable()
      })
    }
    browser.show()
  }
}

/**
 * Clear the current value
 */
function clearValue() {
  inputValue.value = ''
  emit('update:modelValue', '')
}

/**
 * Check if path is an image
 */
function isImage(path) {
  if (!path) return false
  const ext = path.split('.').pop().toLowerCase()
  return ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'].includes(ext)
}

/**
 * Get full image URL with leading slash
 */
function getImageUrl(path) {
  return normalizeImagePath(path)
}
</script>

<template>
  <div class="ms3-file-browser">
    <div class="file-browser-input">
      <InputText
        :value="inputValue"
        :placeholder="placeholder"
        :disabled="disabled"
        class="file-browser-text"
        @input="onInputChange"
      />
      <Button
        v-if="inputValue && !disabled"
        icon="pi pi-times"
        severity="secondary"
        text
        class="clear-button"
        @click="clearValue"
      />
      <Button
        icon="pi pi-folder-open"
        :disabled="disabled"
        class="browse-button"
        @click="openBrowser"
      />
    </div>
    <!-- Preview thumbnail if image -->
    <div v-if="inputValue && isImage(inputValue)" class="file-preview">
      <img :src="getImageUrl(inputValue)" :alt="inputValue" />
    </div>
  </div>
</template>

<style>
/* FileBrowser styles - not scoped because component is used inside Dialog (teleported to body) */
.ms3-file-browser {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.ms3-file-browser .file-browser-input {
  display: flex;
  align-items: center;
  gap: 0;
}

.ms3-file-browser .file-browser-text {
  flex: 1;
  border-top-right-radius: 0 !important;
  border-bottom-right-radius: 0 !important;
}

.ms3-file-browser .clear-button {
  border-radius: 0 !important;
  padding: 0.5rem;
}

.ms3-file-browser .browse-button {
  border-top-left-radius: 0 !important;
  border-bottom-left-radius: 0 !important;
}

.ms3-file-browser .file-preview {
  width: 9.375rem;
  height: 9.375rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 0.0625rem solid var(--ms3-border-color);
  border-radius: 0.25rem;
  background: var(--ms3-bg-slate);
  overflow: hidden;
}

.ms3-file-browser .file-preview img {
  max-width: 9.375rem;
  max-height: 9.375rem;
  width: auto;
  height: auto;
  object-fit: contain;
}
</style>
