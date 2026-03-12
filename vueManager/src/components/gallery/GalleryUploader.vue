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
import { onBeforeUnmount, onMounted } from 'vue'

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

/**
 * Локализация Uppy Dashboard (строки интерфейса и плюрализация для ru/en).
 */
const buildUppyLocale = () => {
  const isRu = (window.MODx?.cultureKey || 'en').toLowerCase().startsWith('ru')
  return {
    strings: {
      back: _('ms3_gallery_uppy_back'),
      addMoreFiles: _('ms3_gallery_uppy_add_more_files'),
      addingMoreFiles: _('ms3_gallery_uppy_adding_more'),
      dropPasteFiles: _('ms3_gallery_uppy_drop_paste'),
      browse: _('ms3_gallery_uppy_browse'),
      browseFiles: _('ms3_gallery_uppy_browse_files'),
      browseFolders: _('ms3_gallery_uppy_browse_folders'),
      uploadComplete: _('ms3_gallery_uppy_upload_complete'),
      uploadFailed: _('ms3_gallery_uppy_upload_failed'),
      /** Сообщение об ошибке загрузки одного файла (подставляет имя: %{file}) */
      failedToUpload: _('ms3_gallery_uppy_failed_to_upload'),
      uploading: _('ms3_gallery_uppy_uploading'),
      complete: _('ms3_gallery_uppy_complete'),
      cancel: _('ms3_gallery_uppy_cancel'),
      remove: _('ms3_gallery_uppy_remove'),
      edit: _('ms3_gallery_uppy_edit'),
      retry: _('ms3_gallery_uppy_retry'),
      addMore: _('ms3_gallery_uppy_add_more'),
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
      noDuplicates: _('ms3_gallery_uppy_duplicate_file'),
      additionalRestrictionsFailed: _('ms3_gallery_uppy_restrictions_failed'),
      done: _('ms3_gallery_uppy_done'),
      upload: _('ms3_gallery_uppy_upload'),
      pause: _('ms3_gallery_uppy_pause'),
      resume: _('ms3_gallery_uppy_resume'),
      paused: _('ms3_gallery_uppy_paused'),
      save: _('ms3_gallery_uppy_save'),
      saveChanges: _('ms3_gallery_uppy_save_changes'),
      finishEditingFile: _('ms3_gallery_uppy_finish_editing_file'),
      editing: _('ms3_gallery_uppy_editing'),
      removeFile: _('ms3_gallery_uppy_remove_file'),
      editFile: _('ms3_gallery_uppy_edit_file'),
      editImage: _('ms3_gallery_uppy_edit_image'),
      dropHint: _('ms3_gallery_uppy_drop_hint'),
      error: _('ms3_gallery_uppy_error'),
      showErrorDetails: _('ms3_gallery_uppy_show_error_details'),
      uploadPaused: _('ms3_gallery_uppy_upload_paused'),
      resumeUpload: _('ms3_gallery_uppy_resume_upload'),
      pauseUpload: _('ms3_gallery_uppy_pause_upload'),
      retryUpload: _('ms3_gallery_uppy_retry_upload'),
      cancelUpload: _('ms3_gallery_uppy_cancel_upload'),
      uploadingXFiles: {
        0: _('ms3_gallery_uppy_uploading_x_files_0'),
        1: _('ms3_gallery_uppy_uploading_x_files_1'),
      },
      processingXFiles: {
        0: _('ms3_gallery_uppy_processing_x_files_0'),
        1: _('ms3_gallery_uppy_processing_x_files_1'),
      },
      poweredBy: _('ms3_gallery_uppy_powered_by'),
      filesUploadedOfTotal: {
        0: _('ms3_gallery_uppy_files_uploaded_of_total_0'),
        1: _('ms3_gallery_uppy_files_uploaded_of_total_1'),
      },
      dataUploadedOfTotal: _('ms3_gallery_uppy_data_uploaded_of_total'),
      dataUploadedOfUnknown: _('ms3_gallery_uppy_data_uploaded_of_unknown'),
      xTimeLeft: _('ms3_gallery_uppy_x_time_left'),
      uploadXNewFiles: {
        0: _('ms3_gallery_uppy_upload_x_new_files_0'),
        1: _('ms3_gallery_uppy_upload_x_new_files_1'),
      },
      xMoreFilesAdded: {
        0: _('ms3_gallery_uppy_x_more_files_added_0'),
        1: _('ms3_gallery_uppy_x_more_files_added_1'),
      },
      closeModal: _('ms3_gallery_uppy_close_modal'),
      dashboardTitle: _('ms3_gallery_uppy_dashboard_title'),
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
    height: 400,
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
    /**
     * Парсит ответ сервера для Uppy: ожидается JSON с success и object.url/file.
     * Если в ответ попал мусор (PHP notice/warning перед JSON), извлекается фрагмент между первой { и последней }.
     */
    getResponseData(responseTextOrXhr, response) {
      let text = ''
      if (typeof responseTextOrXhr === 'string') {
        text = responseTextOrXhr
      } else if (responseTextOrXhr && typeof responseTextOrXhr === 'object') {
        text = responseTextOrXhr.responseText ?? responseTextOrXhr.response ?? ''
      }
      text = String(text ?? '').trim()

      let data
      try {
        data = JSON.parse(text)
      } catch (parseErr) {
        const firstBrace = text.indexOf('{')
        const lastBrace = text.lastIndexOf('}')
        if (firstBrace !== -1 && lastBrace > firstBrace) {
          try {
            data = JSON.parse(text.slice(firstBrace, lastBrace + 1))
            console.warn(
              '[MiniShop3 GalleryUploader] Response contained non-JSON prefix/suffix (e.g. PHP notice). Extracted JSON fragment. Check server logs for PHP errors.',
              { responsePreview: text.slice(0, 200) }
            )
          } catch (_e) {
            data = null
          }
        }
        if (!data || typeof data !== 'object') {
          const errMsg = _('ms3_gallery_uppy_response_error') || 'Server returned an invalid response. Check server logs for PHP errors.'
          throw new Error(errMsg)
        }
      }
      if (!data || typeof data !== 'object') return {}
      if (data.success !== true || !data.object) {
        const msg = data.message || _('ms3_gallery_uppy_upload_failed')
        throw new Error(msg)
      }
      // Uppy ожидает объект с полем url; MODX возвращает object.url или object.file
      return { ...data, url: data.object.url || data.object.file }
    },
  })

  uppy.on('upload-success', (file, response) => {
    // File uploaded successfully
    emit('upload-success', { file, response })
  })

  uppy.on('upload-error', (file, error, response) => {
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

const updateSettings = newSettings => {
  if (uppy) {
    uppy.setOptions(newSettings)
  }
}

const addFiles = files => {
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
  border: var(--ms3-border-width-focus) dashed var(--ms3-border-upload);
  border-radius: 0.5rem;
  overflow: hidden;
}

.uppy-container :deep(.uppy-Dashboard--isDraggingOver) {
  border-color: var(--ms3-accent-green);
}
</style>
