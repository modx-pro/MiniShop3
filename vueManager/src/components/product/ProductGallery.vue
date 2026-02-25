<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import ConfirmDialog from 'primevue/confirmdialog'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, inject, onMounted, ref, watch } from 'vue'

import { useGalleryApi } from '../../composables/useGalleryApi.js'
import GalleryUploader from '../gallery/GalleryUploader.vue'
import ProductGalleryEditDialog from './ProductGalleryEditDialog.vue'
import ProductGalleryGrid from './ProductGalleryGrid.vue'
import ProductGalleryToolbar from './ProductGalleryToolbar.vue'

const props = defineProps({
  productId: {
    type: Number,
    required: true,
  },
  record: {
    type: Object,
    default: () => ({}),
  },
  config: {
    type: Object,
    default: () => ({}),
  },
})

const { _ } = useLexicon()
const toast = useToast()
const confirm = useConfirm()

const updateProductThumb = inject('updateProductThumb', null)

function showError(err) {
  toast.add({
    severity: 'error',
    summary: _('ms3_vue_error'),
    detail: err?.message || _('error_loading_data'),
    life: 5000,
  })
}

function reloadWithThumb(result) {
  if (result?.thumb && updateProductThumb) updateProductThumb(result.thumb)
  return loadList()
}

function confirmAndExecute(confirmOptions, execute) {
  confirm.require({
    ...confirmOptions,
    accept: () => execute().catch(showError),
  })
}

const {
  isLoading,
  fetchGalleryList,
  sortFiles,
  deleteFiles,
  deleteAll,
  regenerateThumbs,
  regenerateAll,
  updateFile,
  updateProductSource,
} = useGalleryApi()

const list = ref([])
const total = ref(0)
const first = ref(0)
const rows = ref(50)
const searchQuery = ref('')

const editDialogVisible = ref(false)
const editingFile = ref(null)

const sources = computed(() => props.config.sources || [])
const currentSourceId = computed(
  () => props.record?.source ?? props.record?.source_id ?? 1
)
const connectorUrl = computed(
  () => props.config.connector_url || '/assets/components/minishop3/connector.php'
)

async function loadList() {
  try {
    const { results, total: t, thumb } = await fetchGalleryList(props.productId, {
      query: searchQuery.value,
      start: first.value,
      limit: rows.value,
    })
    list.value = results
    total.value = t
    if (thumb && updateProductThumb) updateProductThumb(thumb)
  } catch (err) {
    showError(err)
  }
}

function onSearch(q) {
  searchQuery.value = q
  first.value = 0
  loadList()
}

function onPageChange(newFirst) {
  first.value = newFirst
  loadList()
}

async function onReorder({ sourceId, targetId }) {
  try {
    const { thumb } = await sortFiles(props.productId, sourceId, targetId)
    if (thumb && updateProductThumb) updateProductThumb(thumb)
    await loadList()
  } catch (err) {
    showError(err)
  }
}

function onEdit(file) {
  editingFile.value = file
  editDialogVisible.value = true
}

function onShow(file) {
  if (file?.url) window.open(file.url)
}

function onGenerateThumbs(file) {
  if (!file?.id) return
  regenerateThumbs([file.id]).then(() => loadList()).catch(showError)
}

function onDeleteOne(file) {
  if (!file?.id) return
  confirmAndExecute(
    {
      message: _('ms3_gallery_file_delete_confirm'),
      header: _('ms3_gallery_file_delete'),
      icon: 'pi pi-exclamation-triangle',
      acceptClass: 'p-button-danger',
    },
    () => deleteFiles([file.id]).then(loadList)
  )
}

function onRefreshAll() {
  confirmAndExecute(
    {
      message: _('ms3_gallery_file_generate_thumbs_confirm'),
      header: _('ms3_gallery_file_generate_thumbs'),
      icon: 'pi pi-refresh',
    },
    () => regenerateAll(props.productId).then(reloadWithThumb)
  )
}

function onDeleteAll() {
  confirmAndExecute(
    {
      message: _('ms3_gallery_file_delete_multiple_confirm'),
      header: _('ms3_gallery_file_delete_multiple'),
      icon: 'pi pi-exclamation-triangle',
      acceptClass: 'p-button-danger',
    },
    () => deleteAll(props.productId).then(reloadWithThumb)
  )
}

function onChangeSource(sourceId) {
  if (Number(sourceId) === Number(currentSourceId.value)) return
  confirmAndExecute(
    {
      message: _('ms3_product_change_source_confirm'),
      header: _('ms3_product_source_id'),
      icon: 'pi pi-exclamation-triangle',
    },
    () => updateProductSource(props.productId, sourceId)
  )
}

function onSaveEdit({ id, file, name, description }) {
  updateFile(id, { file, name, description }).then(() => loadList()).catch(showError)
}

function onUploadSuccess() {
  loadList()
}

onMounted(() => {
  loadList()
})

watch(
  () => props.productId,
  () => {
    first.value = 0
    loadList()
  }
)
</script>

<template>
  <div class="product-gallery">
    <ConfirmDialog append-to="body" pt:root:class="ms3-gallery-confirm-dialog" />
    <ProductGalleryToolbar
      :sources="sources"
      :current-source-id="currentSourceId"
      :loading="isLoading"
      @refresh-all="onRefreshAll"
      @delete-all="onDeleteAll"
      @change-source="onChangeSource"
    />
    <GalleryUploader
      :product-id="productId"
      :source-id="currentSourceId"
      :connector-url="connectorUrl"
      @upload-success="onUploadSuccess"
    />
    <ProductGalleryGrid
      :items="list"
      :total="total"
      :first="first"
      :rows="rows"
      :loading="isLoading"
      @update:first="onPageChange"
      @search="onSearch"
      @reorder="onReorder"
      @edit="onEdit"
      @show="onShow"
      @generate-thumbs="onGenerateThumbs"
      @delete="onDeleteOne"
    />
    <ProductGalleryEditDialog
      v-model:visible="editDialogVisible"
      :file="editingFile"
      @save="onSaveEdit"
    />
  </div>
</template>

<style scoped>
.product-gallery {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  width: 100%;
  padding: 0.5rem 0;
}
</style>

<!-- ConfirmDialog append-to="body" renders outside .product-gallery -->
<style>
.ms3-gallery-confirm-dialog {
  width: var(--ms3-modal-width, 28rem);
  max-width: 90vw;
  overflow-x: hidden;
  border-radius: var(--ms3-radius-lg, var(--p-border-radius));
}
.ms3-gallery-confirm-dialog .p-dialog-content {
  white-space: normal;
  word-wrap: break-word;
}
</style>
