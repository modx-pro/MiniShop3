<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import ConfirmDialog from 'primevue/confirmdialog'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { onMounted, ref } from 'vue'

import { useGalleryApi } from '../../composables/useGalleryApi.js'
import GalleryUploader from './GalleryUploader.vue'
import ProductGalleryEditDialog from './ProductGalleryEditDialog.vue'
import ProductGalleryGrid from './ProductGalleryGrid.vue'
import ProductGalleryToolbar from './ProductGalleryToolbar.vue'

const { _ } = useLexicon()
const confirm = useConfirm()
const toast = useToast()

// Isolate from ProductLinksTab ConfirmDialog on product/update (#539)
const UI_GROUP = 'product-gallery'

const props = defineProps({
  productId: {
    type: Number,
    required: true,
  },
  record: {
    type: Object,
    required: true,
  },
  config: {
    type: Object,
    default: () => ({}),
  },
})

const {
  isLoading,
  fetchGalleryList,
  sortFiles,
  deleteFiles,
  deleteAll,
  regenerateThumbs,
  regenerateAll,
  updateFile,
  setPreview,
  updateProductSource,
} = useGalleryApi()

// State
const images = ref([])
const total = ref(0)
const searchQuery = ref('')
const currentStart = ref(0)
const pageSize = 50

// Edit dialog
const editDialogVisible = ref(false)
const editingFile = ref(null)

// Sources from config
const sources = ref(props.config.sources || [])
const currentSourceId = ref(props.record.source_id || props.record.source || 1)

// Connector URL for uploader
const connectorUrl =
  (typeof ms3 !== 'undefined' && ms3?.config?.connector_url) ||
  '/assets/components/minishop3/connector.php'

// Media source config
const mediaSource = props.config.media_source || {}

// Build allowed file types from media source config
const allowedFileTypes = (() => {
  if (mediaSource.allowedFileTypes) {
    const exts = mediaSource.allowedFileTypes
      .split(',')
      .map(e => '.' + e.trim().toLowerCase())
      .filter(Boolean)
    if (exts.length > 0) return exts
  }
  return ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/heic']
})()

/**
 * Update product thumbnail in the page
 */
function updateProductThumb(thumbUrl) {
  if (!thumbUrl) return
  const imgEl = document.getElementById('ms3-product-image')
  if (imgEl) {
    imgEl.src = thumbUrl
  }
}

/**
 * Load gallery images
 */
async function loadImages() {
  try {
    const result = await fetchGalleryList(props.productId, {
      query: searchQuery.value,
      start: currentStart.value,
      limit: pageSize,
    })
    images.value = result.results
    total.value = result.total
    if (result.thumb) {
      updateProductThumb(result.thumb)
    }
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('ms3_gallery_errors'),
      detail: error.message,
      life: 5000,
    })
  }
}

/**
 * Handle search
 */
function onSearch(query) {
  searchQuery.value = query
  currentStart.value = 0
  loadImages()
}

/**
 * Handle page change
 */
function onPageChange({ first }) {
  currentStart.value = first
  loadImages()
}

/**
 * Handle drag-drop sort
 */
async function onSort({ sourceId, targetId }) {
  try {
    const result = await sortFiles(props.productId, sourceId, targetId)
    if (result.thumb) {
      updateProductThumb(result.thumb)
    }
    await loadImages()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('ms3_gallery_errors'),
      detail: error.message,
      life: 5000,
    })
  }
}

/**
 * Handle file edit (double-click or context menu)
 */
function onEdit(image) {
  editingFile.value = image
  editDialogVisible.value = true
}

/**
 * Handle edit dialog save
 */
async function onEditSave(data) {
  try {
    await updateFile(data.id, {
      file: data.file,
      name: data.name,
      description: data.description,
    })
    await loadImages()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('ms3_gallery_errors'),
      detail: error.message,
      life: 5000,
    })
  }
}

/**
 * Handle set as main product preview (#130)
 */
async function onSetPreview(image) {
  if (!image?.id) return
  try {
    const result = await setPreview(props.productId, image.id)
    if (result.thumb) {
      updateProductThumb(result.thumb)
    }
    await loadImages()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('ms3_gallery_errors'),
      detail: error.message,
      life: 5000,
    })
  }
}

/**
 * Show file in new window
 */
function onShow(image) {
  if (image.url) {
    window.open(image.url, '_blank')
  }
}

/**
 * Handle delete files
 */
function onDeleteFiles(ids) {
  const message =
    ids.length === 1
      ? _('ms3_gallery_file_delete_confirm')
      : _('ms3_gallery_file_delete_multiple_confirm')

  confirm.require({
    group: UI_GROUP,
    message,
    header: ids.length === 1 ? _('ms3_gallery_file_delete') : _('ms3_gallery_file_delete_multiple'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        const result = await deleteFiles(ids)
        if (result.thumb) {
          updateProductThumb(result.thumb)
        }
        await loadImages()
      } catch (error) {
        toast.add({
          severity: 'error',
          summary: _('ms3_gallery_errors'),
          detail: error.message,
          life: 5000,
        })
      }
    },
  })
}

/**
 * Handle regenerate thumbnails
 */
async function onGenerateThumbs(ids) {
  try {
    await regenerateThumbs(ids)
    await loadImages()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('ms3_gallery_errors'),
      detail: error.message,
      life: 5000,
    })
  }
}

/**
 * Handle regenerate all
 */
function onRegenerateAll() {
  confirm.require({
    group: UI_GROUP,
    message: _('ms3_gallery_file_generate_thumbs_confirm'),
    header: _('ms3_gallery_file_generate_all'),
    icon: 'pi pi-refresh',
    accept: async () => {
      try {
        const result = await regenerateAll(props.productId)
        if (result.thumb) {
          updateProductThumb(result.thumb)
        }
        await loadImages()
      } catch (error) {
        toast.add({
          severity: 'error',
          summary: _('ms3_gallery_errors'),
          detail: error.message,
          life: 5000,
        })
      }
    },
  })
}

/**
 * Handle delete all
 */
function onDeleteAll() {
  confirm.require({
    group: UI_GROUP,
    message: _('ms3_gallery_file_delete_multiple_confirm'),
    header: _('ms3_gallery_file_delete_all'),
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        const result = await deleteAll(props.productId)
        if (result.thumb) {
          updateProductThumb(result.thumb)
        }
        await loadImages()
      } catch (error) {
        toast.add({
          severity: 'error',
          summary: _('ms3_gallery_errors'),
          detail: error.message,
          life: 5000,
        })
      }
    },
  })
}

/**
 * Handle media source change
 */
function onChangeSource(sourceId) {
  confirm.require({
    group: UI_GROUP,
    message: _('ms3_product_change_source_confirm'),
    header: _('ms3_product_source'),
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      try {
        await updateProductSource(props.productId, sourceId)
      } catch (error) {
        toast.add({
          severity: 'error',
          summary: _('ms3_gallery_errors'),
          detail: error.message,
          life: 5000,
        })
      }
    },
  })
}

/**
 * Handle upload events
 */
function onUploadComplete() {
  loadImages()
}

onMounted(() => {
  loadImages()
})
</script>

<template>
  <div class="product-gallery">
    <ConfirmDialog :group="UI_GROUP" append-to="self" />

    <ProductGalleryToolbar
      :sources="sources"
      :current-source-id="currentSourceId"
      @change-source="onChangeSource"
      @regenerate-all="onRegenerateAll"
      @delete-all="onDeleteAll"
    />

    <GalleryUploader
      :product-id="productId"
      :source-id="currentSourceId"
      :connector-url="connectorUrl"
      :max-file-size="Number(mediaSource.maxUploadSize) || 10485760"
      :max-width="Number(mediaSource.maxUploadWidth) || 1920"
      :max-height="Number(mediaSource.maxUploadHeight) || 1080"
      :allowed-file-types="allowedFileTypes"
      @upload-complete="onUploadComplete"
    />

    <ProductGalleryGrid
      :images="images"
      :total="total"
      :loading="isLoading"
      :page-size="pageSize"
      @sort="onSort"
      @search="onSearch"
      @page-change="onPageChange"
      @edit="onEdit"
      @show="onShow"
      @set-preview="onSetPreview"
      @generate-thumbs="onGenerateThumbs"
      @delete="onDeleteFiles"
    />

    <ProductGalleryEditDialog
      v-model:visible="editDialogVisible"
      :file="editingFile"
      @save="onEditSave"
    />
  </div>
</template>

<style scoped>
.product-gallery {
  width: 100%;
  padding: 0.5rem;
}
</style>
