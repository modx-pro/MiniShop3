<template>
  <div class="gallery-uploader">
    <div id="uppy-dashboard" class="uppy-container"></div>
  </div>
</template>

<script setup>
import Uppy from '@uppy/core'
import Dashboard from '@uppy/dashboard'
import ImageEditor from '@uppy/image-editor'
import XHRUpload from '@uppy/xhr-upload'
import { useLexicon } from '@vuetools/useLexicon'
import { onBeforeUnmount, onMounted, watch } from 'vue'

const { _ } = useLexicon()

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
    height: 148,
    proudlyDisplayPoweredByUppy: false,
    showProgressDetails: true,
    hideUploadButton: false,
    note: noteText,
    theme: 'light',
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

  uppy.on('upload-success', (file, response) => {
    // File uploaded successfully
    emit('upload-success', { file, response })
  })

  uppy.on('upload-error', (file, error, response) => {
    console.error('Upload error:', file?.name, error)
    emit('upload-error', { file, error, response })
  })

  uppy.on('complete', result => {
    // Upload complete
    emit('upload-complete', result)

    setTimeout(() => {
      result.successful.forEach(file => {
        uppy.removeFile(file.id)
      })
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
}

.uppy-container {
  border: var(--ms3-border-width-focus) dashed var(--ms3-border-upload);
  border-radius: 0.5rem;
  overflow: hidden;
}

.uppy-container :deep(.uppy-Dashboard--isDraggingOver) {
  border-color: var(--ms3-accent-green);
}
</style>
