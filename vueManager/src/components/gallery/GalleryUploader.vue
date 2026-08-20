<template>
  <div class="gallery-uploader" :class="{ 'gallery-uploader--has-files': hasFiles }">
    <div id="uppy-dashboard" class="uppy-container"></div>
  </div>
</template>

<script setup>
import Uppy from '@uppy/core'
import Dashboard from '@uppy/dashboard'
import ImageEditor from '@uppy/image-editor'
import XHRUpload from '@uppy/xhr-upload'
import { useLexicon } from '@vuetools/useLexicon'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

const { _ } = useLexicon()

const EMPTY_HEIGHT = 96
const FILE_ROW_HEIGHT = 48
const CHROME_HEIGHT = 92
const MAX_HEIGHT = 220

const props = defineProps({
  productId: {
    type: [Number, String],
    required: true,
  },
  sourceId: {
    type: [Number, String],
    default: 1,
  },
  connectorUrl: {
    type: String,
    required: true,
  },
  maxFileSize: {
    type: Number,
    default: 10485760, // 10MB
  },
  maxWidth: {
    type: Number,
    default: 1920,
  },
  maxHeight: {
    type: Number,
    default: 1080,
  },
  allowedFileTypes: {
    type: Array,
    default: () => [
      'image/jpeg',
      'image/png',
      'image/gif',
      'image/webp',
      'image/avif',
      'image/heic',
    ],
  },
})

const emit = defineEmits(['upload-success', 'upload-error', 'upload-complete'])

const hasFiles = ref(false)

let uppy = null

onMounted(() => {
  initUppy()
})

onBeforeUnmount(() => {
  if (uppy) {
    uppy.close()
  }
})

const buildUppyLocale = () => {
  const isRu = (window.MODx?.cultureKey || 'en').toLowerCase().startsWith('ru')
  return {
    strings: {
      dropPasteFiles: _('ms3_gallery_uppy_drop_paste'),
      browse: _('ms3_gallery_uppy_browse'),
      browseFiles: _('ms3_gallery_uppy_browse_files'),
      browseFolders: _('ms3_gallery_uppy_browse_folders'),
      back: _('ms3_gallery_uppy_back'),
      addMoreFiles: _('ms3_gallery_uppy_add_more_files'),
      dropHint: _('ms3_gallery_uppy_drop_hint'),
      uploadComplete: _('ms3_gallery_uppy_upload_complete'),
      uploadFailed: _('ms3_gallery_uppy_upload_failed'),
      uploading: _('ms3_gallery_uppy_uploading'),
      complete: _('ms3_gallery_uppy_complete'),
      cancel: _('ms3_gallery_uppy_cancel'),
      remove: _('ms3_gallery_uppy_remove'),
      edit: _('ms3_gallery_uppy_edit'),
      retry: _('ms3_gallery_uppy_retry'),
      addMore: _('ms3_gallery_uppy_add_more'),
      error: _('ms3_gallery_uppy_error'),
      failedToUpload: _('ms3_gallery_uppy_failed_to_upload'),
      noDuplicates: _('ms3_gallery_uppy_no_duplicates'),
      noFilesFound: _('ms3_gallery_uppy_no_files_found'),
      pauseUpload: _('ms3_gallery_uppy_pause_upload'),
      resumeUpload: _('ms3_gallery_uppy_resume_upload'),
      xFilesSelected: {
        0: _('ms3_gallery_uppy_x_files_selected_0'),
        1: _('ms3_gallery_uppy_x_files_selected_1'),
        2: _('ms3_gallery_uppy_x_files_selected_2'),
      },
      uploadXFiles: {
        0: _('ms3_gallery_uppy_upload_x_files_0'),
        1: _('ms3_gallery_uppy_upload_x_files_1'),
        2: _('ms3_gallery_uppy_upload_x_files_2'),
      },
    },
    pluralize: isRu
      ? n =>
          n % 10 === 1 && n % 100 !== 11
            ? 0
            : n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20)
              ? 1
              : 2
      : n => (n === 1 ? 0 : 1),
  }
}

function dashboardHeightForCount(count) {
  if (count <= 0) {
    return EMPTY_HEIGHT
  }
  // Cap visible rows; overflow scrolls inside the files list
  const visibleRows = Math.min(count, 3)
  return Math.min(MAX_HEIGHT, CHROME_HEIGHT + visibleRows * FILE_ROW_HEIGHT)
}

function syncDashboardHeight() {
  if (!uppy) {
    return
  }
  const count = uppy.getFiles().length
  hasFiles.value = count > 0
  const dashboard = uppy.getPlugin('Dashboard')
  if (dashboard) {
    dashboard.setOptions({ height: dashboardHeightForCount(count) })
  }
}

const initUppy = () => {
  const locale = buildUppyLocale()
  uppy = new Uppy({
    id: 'gallery-uploader',
    autoProceed: false,
    allowMultipleUploadBatches: true,
    restrictions: {
      maxFileSize: props.maxFileSize,
      maxNumberOfFiles: null,
      minNumberOfFiles: null,
      allowedFileTypes: props.allowedFileTypes,
    },
    locale,
  })

  const noteText = _('ms3_gallery_uppy_note_max_size').replace(
    '%{maxSize}',
    formatBytes(props.maxFileSize)
  )

  uppy.use(Dashboard, {
    target: '#uppy-dashboard',
    inline: true,
    width: '100%',
    height: EMPTY_HEIGHT,
    proudlyDisplayPoweredByUppy: false,
    showProgressDetails: true,
    hideUploadButton: false,
    note: noteText,
    theme: 'light',
    // Prevent one selected file from expanding into a full-screen panel
    singleFileFullScreen: false,
    thumbnailWidth: 48,
    thumbnailHeight: 48,
  })

  uppy.use(ImageEditor, {
    target: Dashboard,
    quality: 0.8,
  })

  uppy.use(XHRUpload, {
    endpoint: buildUploadUrl(),
    method: 'POST',
    formData: true,
    fieldName: 'file',
    timeout: 60000, // 60 seconds
    headers: {
      Accept: 'application/json',
    },
  })

  uppy.on('file-added', syncDashboardHeight)
  uppy.on('file-removed', syncDashboardHeight)
  uppy.on('files-added', syncDashboardHeight)

  uppy.on('upload-success', (file, response) => {
    emit('upload-success', { file, response })
  })

  uppy.on('upload-error', (file, error, response) => {
    console.error('Upload error:', file?.name, error)
    emit('upload-error', { file, error, response })
  })

  uppy.on('complete', result => {
    emit('upload-complete', result)

    setTimeout(() => {
      result.successful.forEach(file => {
        uppy.removeFile(file.id)
      })
      syncDashboardHeight()
    }, 2000)
  })

  uppy.on('restriction-failed', (file, error) => {
    console.warn('Restriction failed:', file?.name, error)
  })
}

const buildUploadUrl = () => {
  const params = new URLSearchParams({
    action: 'MiniShop3\\Processors\\Gallery\\Upload',
    id: props.productId,
    source: props.sourceId,
    ctx: 'mgr',
    HTTP_MODAUTH: window.MODx?.siteId || '',
  })

  return `${props.connectorUrl}?${params.toString()}`
}

const formatBytes = (bytes, decimals = 2) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const dm = decimals < 0 ? 0 : decimals
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i]
}

// Watch sourceId to rebuild upload URL
watch(
  () => props.sourceId,
  () => {
    if (uppy) {
      const xhrPlugin = uppy.getPlugin('XHRUpload')
      if (xhrPlugin) {
        xhrPlugin.setOptions({ endpoint: buildUploadUrl() })
      }
    }
  }
)
</script>

<style src="../../../node_modules/@uppy/core/dist/style.min.css"></style>
<style src="../../../node_modules/@uppy/dashboard/dist/style.min.css"></style>
<style src="../../../node_modules/@uppy/image-editor/dist/style.min.css"></style>

<style scoped>
.gallery-uploader {
  width: 100%;
  max-width: 100%;
}

.gallery-uploader:not(.gallery-uploader--has-files) {
  width: fit-content;
  max-width: 100%;
}

.uppy-container {
  border: var(--ms3-border-width-focus, 2px) dashed var(--ms3-border-upload, #c5c9ce);
  border-radius: var(--ms3-radius-md, 0.375rem);
  overflow: hidden;
  background: var(--ms3-bg-gray-50, var(--ms3-bg-muted, #f8f9fa));
  transition:
    border-color 0.15s ease,
    background-color 0.15s ease;
}

.gallery-uploader:not(.gallery-uploader--has-files) .uppy-container {
  width: fit-content;
  max-width: 100%;
  min-width: min(100%, 22rem);
}

.gallery-uploader:not(.gallery-uploader--has-files) .uppy-container:hover {
  border-color: var(--p-primary-color, #6cb24a);
  background: var(--ms3-bg-accent, #f4faf0);
}

.gallery-uploader--has-files .uppy-container {
  width: 100%;
  border-style: solid;
  background: var(--p-surface-0, #fff);
}

/* Don't stretch empty space between file list and upload button */
.gallery-uploader--has-files .uppy-container :deep(.uppy-Dashboard-filesContainer) {
  flex: 0 1 auto !important;
  max-height: 9.5rem;
}

.gallery-uploader--has-files .uppy-container :deep(.uppy-Dashboard-innerWrap) {
  justify-content: flex-start;
}

.uppy-container :deep(.uppy-Dashboard) {
  width: 100% !important;
}

.gallery-uploader:not(.gallery-uploader--has-files) .uppy-container :deep(.uppy-Dashboard),
.gallery-uploader:not(.gallery-uploader--has-files) .uppy-container :deep(.uppy-Dashboard-inner),
.gallery-uploader:not(.gallery-uploader--has-files)
  .uppy-container
  :deep(.uppy-Dashboard-innerWrap) {
  width: auto !important;
  max-width: 100% !important;
  height: auto !important;
  min-height: 0 !important;
}

.uppy-container :deep(.uppy-Dashboard-inner) {
  border: none !important;
  background: transparent !important;
}

.uppy-container :deep(.uppy-Dashboard--isDraggingOver) {
  box-shadow: inset 0 0 0 2px var(--p-primary-color, #6cb24a);
}

/* Empty dropzone: icon + copy + real button CTA (Import-like) */
.gallery-uploader:not(.gallery-uploader--has-files) .uppy-container :deep(.uppy-Dashboard-AddFiles) {
  margin: 0 !important;
  border: none !important;
  height: 100% !important;
  display: flex !important;
  flex-direction: row !important;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-start;
  gap: 0.75rem 1rem;
  padding: 0.75rem 1.25rem !important;
}

.gallery-uploader:not(.gallery-uploader--has-files)
  .uppy-container
  :deep(.uppy-Dashboard-AddFiles::before) {
  content: '\e944';
  font-family: 'primeicons';
  font-size: 1.5rem;
  line-height: 1;
  color: var(--p-primary-color, #6cb24a);
  flex-shrink: 0;
}

.uppy-container :deep(.uppy-Dashboard-AddFiles-title) {
  display: inline-flex !important;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-start;
  gap: 0.5rem 0.75rem;
  font-size: 0.875rem !important;
  font-weight: 500 !important;
  line-height: 1.35 !important;
  max-width: none;
  margin: 0 !important;
  color: var(--p-text-color, #212529);
}

.uppy-container :deep(.uppy-Dashboard-browse) {
  display: inline-flex !important;
  align-items: center;
  justify-content: center;
  margin: 0 !important;
  padding: 0.375rem 0.875rem !important;
  border: 1px solid var(--p-primary-color, #6cb24a) !important;
  border-radius: var(--ms3-radius-md, 0.375rem) !important;
  background: var(--p-primary-color, #6cb24a) !important;
  color: var(--p-primary-contrast-color, #fff) !important;
  font-size: 0.8125rem !important;
  font-weight: 600 !important;
  line-height: 1.25 !important;
  text-decoration: none !important;
  box-shadow: none !important;
  cursor: pointer;
  vertical-align: middle;
}

.uppy-container :deep(.uppy-Dashboard-browse:hover),
.uppy-container :deep(.uppy-Dashboard-browse:focus-visible) {
  background: var(--p-primary-600, #528738) !important;
  border-color: var(--p-primary-600, #528738) !important;
  color: var(--p-primary-contrast-color, #fff) !important;
  outline: 2px solid var(--p-primary-color, #6cb24a);
  outline-offset: 2px;
}

.uppy-container :deep(.uppy-Dashboard-note) {
  font-size: 0.75rem !important;
  line-height: 1.3 !important;
  margin-top: 0 !important;
  width: auto;
  text-align: start;
  color: var(--p-text-muted-color, #6c757d);
}

/* Selected files: force list rows even at md/lg/xl (Uppy otherwise uses tall floated cards) */
.uppy-container :deep(.uppy-Dashboard-files) {
  padding: 0.25rem 0.5rem !important;
}

.uppy-container :deep(.uppy-Dashboard-files)::after {
  display: none !important;
}

.uppy-container :deep(.uppy-Dashboard-Item),
.uppy-container :deep(.uppy-size--md .uppy-Dashboard-Item),
.uppy-container :deep(.uppy-size--lg .uppy-Dashboard-Item),
.uppy-container :deep(.uppy-size--xl .uppy-Dashboard-Item) {
  float: none !important;
  display: flex !important;
  align-items: center !important;
  width: 100% !important;
  max-width: none !important;
  height: auto !important;
  min-height: 2.75rem;
  margin: 0 !important;
  padding: 0.375rem 0.5rem !important;
  border-bottom: 1px solid var(--ms3-border-color-alt, #eaeaea) !important;
  position: relative !important;
}

.uppy-container :deep(.uppy-Dashboard-Item-preview),
.uppy-container :deep(.uppy-size--md .uppy-Dashboard-Item-preview),
.uppy-container :deep(.uppy-size--lg .uppy-Dashboard-Item-preview),
.uppy-container :deep(.uppy-size--xl .uppy-Dashboard-Item-preview) {
  width: 2.5rem !important;
  height: 2.5rem !important;
  flex-shrink: 0;
}

.uppy-container :deep(.uppy-Dashboard-Item-previewImg),
.uppy-container :deep(.uppy-Dashboard-Item-previewIconWrap) {
  width: 2.5rem !important;
  height: 2.5rem !important;
}

.uppy-container :deep(.uppy-Dashboard-Item-fileInfoAndButtons),
.uppy-container :deep(.uppy-size--md .uppy-Dashboard-Item-fileInfoAndButtons),
.uppy-container :deep(.uppy-size--lg .uppy-Dashboard-Item-fileInfoAndButtons),
.uppy-container :deep(.uppy-size--xl .uppy-Dashboard-Item-fileInfoAndButtons) {
  align-items: center !important;
  padding: 0 0.25rem 0 0.5rem !important;
}

.uppy-container :deep(.uppy-size--md .uppy-Dashboard-Item-action--remove) {
  position: static !important;
  inset: auto !important;
  margin-inline-start: 0.25rem;
}

.uppy-container :deep(.uppy-Dashboard-Item-name) {
  font-size: 0.8125rem !important;
  line-height: 1.25 !important;
  margin-bottom: 0.125rem !important;
}

.uppy-container :deep(.uppy-Dashboard-Item-status) {
  font-size: 0.75rem !important;
}

.uppy-container :deep(.uppy-DashboardContent-bar) {
  min-height: 2.25rem !important;
  height: auto !important;
  padding: 0.25rem 0.5rem !important;
}

.uppy-container :deep(.uppy-StatusBar) {
  height: auto !important;
  min-height: 2.5rem;
  border-top: var(--ms3-border-width, 1px) solid var(--ms3-border-color-alt, #e5e7eb);
}

.uppy-container :deep(.uppy-StatusBar:not([aria-hidden='true']).is-waiting) {
  height: auto !important;
  min-height: 2.75rem;
}

.uppy-container :deep(.uppy-StatusBar-actions) {
  padding: 0.375rem 0.5rem !important;
}

.uppy-container :deep(.uppy-StatusBar.is-waiting .uppy-StatusBar-actionBtn--upload) {
  background-color: var(--p-primary-color, #6cb24a) !important;
  font-size: 0.8125rem !important;
  padding: 0.5rem 0.875rem !important;
}

.uppy-container :deep(.uppy-StatusBar.is-waiting .uppy-StatusBar-actionBtn--upload:hover) {
  background-color: var(--p-primary-600, #528738) !important;
}

@media (prefers-reduced-motion: reduce) {
  .uppy-container {
    transition: none;
  }
}
</style>
