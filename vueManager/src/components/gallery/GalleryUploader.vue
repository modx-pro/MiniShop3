<template>
  <div class="gallery-uploader">
    <div id="uppy-dashboard" class="uppy-container"></div>
  </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount } from 'vue'
import Uppy from '@uppy/core'
import Dashboard from '@uppy/dashboard'
import XHRUpload from '@uppy/xhr-upload'
import ImageEditor from '@uppy/image-editor'

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
    default: () => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/heic'],
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

const initUppy = () => {
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
    locale: {
      strings: {
        // English localization
        dropPasteFiles: 'Drop files here or %{browse}',
        browse: 'browse',
        uploadComplete: 'Upload complete',
        uploadFailed: 'Upload failed',
        uploading: 'Uploading...',
        complete: 'Complete',
        cancel: 'Cancel',
        remove: 'Remove',
        edit: 'Edit',
        retry: 'Retry',
        addMore: 'Add more',
        xFilesSelected: {
          0: '%{smart_count} file selected',
          1: '%{smart_count} files selected',
          2: '%{smart_count} files selected',
        },
      },
    },
  })

  uppy.use(Dashboard, {
    target: '#uppy-dashboard',
    inline: true,
    width: '100%',
    height: 400,
    proudlyDisplayPoweredByUppy: false,
    showProgressDetails: true,
    hideUploadButton: false,
    note: `Maximum size: ${formatBytes(props.maxFileSize)}`,
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
      'Accept': 'application/json',
    },
  })

  uppy.on('upload-success', (file, response) => {
    console.log('File uploaded:', file.name, response)
    emit('upload-success', { file, response })
  })

  uppy.on('upload-error', (file, error, response) => {
    console.error('Upload error:', file?.name, error)
    emit('upload-error', { file, error, response })
  })

  uppy.on('complete', (result) => {
    console.log('Upload complete:', result)
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

const updateSettings = (newSettings) => {
  if (uppy) {
    uppy.setOptions(newSettings)
  }
}

const addFiles = (files) => {
  if (uppy) {
    files.forEach(file => {
      uppy.addFile(file)
    })
  }
}

defineExpose({
  updateSettings,
  addFiles,
  uppy,
})
</script>

<style src="../../../node_modules/@uppy/core/dist/style.min.css"></style>
<style src="../../../node_modules/@uppy/dashboard/dist/style.min.css"></style>
<style src="../../../node_modules/@uppy/image-editor/dist/style.min.css"></style>

<style scoped>
.gallery-uploader {
  width: 100%;
}

.uppy-container {
  border: 0.125rem dashed #ddd;
  border-radius: 0.5rem;
  overflow: hidden;
}

.uppy-container :deep(.uppy-Dashboard--isDraggingOver) {
  border-color: #4CAF50;
}
</style>
