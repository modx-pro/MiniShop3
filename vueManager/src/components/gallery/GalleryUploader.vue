<template>
  <div class="gallery-uploader">
    <div ref="dashboardEl" class="uppy-container" />
  </div>
</template>

<script setup>
import Uppy from '@uppy/core'
import Dashboard from '@uppy/dashboard'
import ImageEditor from '@uppy/image-editor'
import XHRUpload from '@uppy/xhr-upload'
import { useLexicon } from '@vuetools/useLexicon'
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const { _ } = useLexicon()

/** Compact drop target while idle; expands when files are queued or uploading. */
const HEIGHT_IDLE = 168
const HEIGHT_WITH_FILES = 300
const HEIGHT_BUSY = 380

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

const dashboardEl = ref(null)
let uppy = null

onMounted(async () => {
  await nextTick()
  initUppy()
})

onBeforeUnmount(() => {
  if (uppy) {
    uppy.close()
    uppy = null
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
      addingMoreFiles: _('ms3_gallery_uppy_adding_more_files'),
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

function dashboardHeightForState() {
  if (!uppy) {
    return HEIGHT_IDLE
  }
  const files = uppy.getFiles()
  const count = files.length
  if (count === 0) {
    return HEIGHT_IDLE
  }
  const uploading = files.some(
    f => f.progress?.uploadStarted && !f.progress?.uploadComplete && !f.error
  )
  if (uploading || count > 6) {
    return HEIGHT_BUSY
  }
  return HEIGHT_WITH_FILES
}

function syncDashboardHeight() {
  const plugin = uppy?.getPlugin('Dashboard')
  if (!plugin) {
    return
  }
  plugin.setOptions({ height: dashboardHeightForState() })
}

const initUppy = () => {
  if (!dashboardEl.value) {
    return
  }

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
    target: dashboardEl.value,
    inline: true,
    width: '100%',
    height: HEIGHT_IDLE,
    proudlyDisplayPoweredByUppy: false,
    showProgressDetails: true,
    hideUploadButton: false,
    hideProgressAfterFinish: true,
    // Gallery is multi-file; one file should not monopolize the panel.
    singleFileFullScreen: false,
    note: noteText,
    theme: 'light',
    doneButtonHandler: () => {
      uppy.cancelAll()
      syncDashboardHeight()
    },
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
    timeout: 60000,
    headers: {
      Accept: 'application/json',
    },
  })

  uppy.on('file-added', () => {
    syncDashboardHeight()
  })
  uppy.on('file-removed', () => {
    syncDashboardHeight()
  })
  uppy.on('upload', () => {
    syncDashboardHeight()
  })

  uppy.on('upload-success', (file, response) => {
    emit('upload-success', { file, response })
  })

  uppy.on('upload-error', (file, error, response) => {
    console.error('Upload error:', file?.name, error)
    emit('upload-error', { file, error, response })
  })

  uppy.on('complete', result => {
    emit('upload-complete', result)
    syncDashboardHeight()

    setTimeout(() => {
      if (!uppy) {
        return
      }
      result.successful.forEach(file => {
        uppy.removeFile(file.id)
      })
      syncDashboardHeight()
    }, 1500)
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
}

/* Theme surface: no second dashed frame outside Uppy’s AddFiles. */
.uppy-container {
  border-radius: var(--p-content-border-radius, 3px);
  overflow: hidden;
}

.uppy-container :deep(.uppy-Dashboard-inner) {
  background-color: var(--p-surface-ground, #f4f4f4);
  border: 1px solid var(--p-content-border-color, #ccc);
  border-radius: var(--p-content-border-radius, 3px);
  width: 100% !important;
  max-width: 100%;
  transition: height 0.2s ease;
}

.uppy-container :deep(.uppy-Dashboard-AddFiles) {
  border-color: var(--p-content-border-color, #ccc);
  border-radius: var(--p-content-border-radius, 3px);
}

.uppy-container :deep(.uppy-Dashboard--isDraggingOver) .uppy-Dashboard-AddFiles,
.uppy-container :deep(.uppy-Dashboard-dropFilesHereHint) {
  border-color: var(--p-primary-color, #234368);
}

.uppy-container :deep(.uppy-Dashboard-AddFiles-title) {
  font-size: 0.9375rem;
  font-weight: 500;
  color: var(--p-text-color, #333);
  margin-top: 0.5rem;
  margin-bottom: 0.25rem;
}

/* Uppy hides .AddFiles-info below height-md; keep the size note in the compact idle zone. */
.uppy-container :deep(.uppy-Dashboard-AddFiles-info) {
  display: block !important;
  position: static !important;
  padding-top: 0.25rem;
  padding-bottom: 0.5rem;
  margin-top: 0;
}

.uppy-container :deep(.uppy-Dashboard-note) {
  color: var(--p-text-muted-color, #757575);
  font-size: 0.8125rem;
}

.uppy-container :deep(.uppy-Dashboard-browse),
.uppy-container :deep(.uppy-DashboardContent-back),
.uppy-container :deep(.uppy-DashboardContent-save),
.uppy-container :deep(.uppy-DashboardContent-addMore),
.uppy-container :deep(.uppy-StatusBar-actionBtn:not(.uppy-StatusBar-actionBtn--upload)) {
  color: var(--p-primary-color, #234368);
}

.uppy-container :deep(.uppy-Dashboard-browse:focus),
.uppy-container :deep(.uppy-Dashboard-browse:hover) {
  border-bottom-color: var(--p-primary-color, #234368);
}

/* Primary CTA = theme success (same as mgr success buttons). */
.uppy-container :deep(.uppy-StatusBar.is-waiting .uppy-StatusBar-actionBtn--upload) {
  background-color: var(--p-button-success-background, #6cb24a);
  min-height: var(--p-modx-control-height, 2.25rem);
  padding-block: 0.5rem;
  border-radius: var(--p-button-border-radius, 3px);
}

.uppy-container :deep(.uppy-StatusBar.is-waiting .uppy-StatusBar-actionBtn--upload:hover) {
  background-color: var(--p-button-success-hover-background, #5a9a3c);
}

.uppy-container :deep(.uppy-StatusBar-progress) {
  background-color: var(--p-primary-color, #234368);
}

.uppy-container :deep(.uppy-StatusBar.is-complete .uppy-StatusBar-progress),
.uppy-container :deep(.uppy-StatusBar.is-complete .uppy-StatusBar-statusIndicator) {
  background-color: var(--p-button-success-background, #6cb24a);
  color: var(--p-button-success-background, #6cb24a);
}

.uppy-container :deep(.uppy-DashboardTab-iconMyDevice),
.uppy-container :deep(.uppy-StatusBar-spinner) {
  color: var(--p-primary-color, #234368);
  fill: var(--p-primary-color, #234368);
}

.uppy-container :deep(.uppy-Dashboard-Item-action:focus),
.uppy-container :deep(.uppy-StatusBar-actionCircleBtn:focus),
.uppy-container :deep(.uppy-DashboardContent-back:focus),
.uppy-container :deep(.uppy-DashboardContent-addMore:focus) {
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--p-primary-color, #234368) 35%, transparent);
}
</style>
