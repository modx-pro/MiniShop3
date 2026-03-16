<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import ContextMenu from 'primevue/contextmenu'
import InputText from 'primevue/inputtext'
import Paginator from 'primevue/paginator'
import { computed, ref } from 'vue'
import draggable from 'vuedraggable'

import { debounce } from '../../utils/modx.js'

const { _ } = useLexicon()

const props = defineProps({
  images: {
    type: Array,
    default: () => [],
  },
  total: {
    type: Number,
    default: 0,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  pageSize: {
    type: Number,
    default: 20,
  },
})

const emit = defineEmits([
  'sort',
  'search',
  'page-change',
  'edit',
  'show',
  'generate-thumbs',
  'delete',
])

const searchQuery = ref('')
const currentPage = ref(0)

// Context menu
const contextMenuRef = ref(null)
const contextMenuTarget = ref(null)

const contextMenuItems = computed(() => [
  {
    label: _('ms3_gallery_file_update'),
    icon: 'pi pi-pencil',
    command: () => contextMenuTarget.value && emit('edit', contextMenuTarget.value),
  },
  {
    label: _('ms3_gallery_file_show'),
    icon: 'pi pi-external-link',
    command: () => contextMenuTarget.value && emit('show', contextMenuTarget.value),
  },
  {
    label: _('ms3_gallery_file_generate_thumbs'),
    icon: 'pi pi-refresh',
    command: () => contextMenuTarget.value && emit('generate-thumbs', [contextMenuTarget.value.id]),
  },
  {
    separator: true,
  },
  {
    label: _('ms3_gallery_file_delete'),
    icon: 'pi pi-trash',
    class: 'p-menuitem-danger',
    command: () => contextMenuTarget.value && emit('delete', [contextMenuTarget.value.id]),
  },
])

// Local copy for draggable (mutated via v-model)
const localImages = computed({
  get: () => props.images,
  set: () => {
    // Handled by onDragEnd
  },
})

const debouncedSearch = debounce(query => {
  currentPage.value = 0
  emit('search', query)
}, 300)

function onSearchInput(event) {
  searchQuery.value = event.target.value
  debouncedSearch(searchQuery.value)
}

function clearSearch() {
  searchQuery.value = ''
  currentPage.value = 0
  emit('search', '')
}

function onPageChange(event) {
  currentPage.value = event.page
  emit('page-change', { first: event.first, rows: event.rows })
}

function onDragEnd(event) {
  const { oldIndex, newIndex } = event
  if (oldIndex === newIndex) return

  const movedItem = props.images[oldIndex]
  const targetItem = props.images[newIndex]
  if (movedItem && targetItem) {
    emit('sort', { sourceId: movedItem.id, targetId: targetItem.id })
  }
}

function onDblClick(image) {
  emit('edit', image)
}

function onContextMenu(event, image) {
  contextMenuTarget.value = image
  contextMenuRef.value.show(event)
}
</script>

<template>
  <div class="gallery-grid">
    <!-- Search -->
    <div class="gallery-search">
      <span class="p-input-icon-left p-input-icon-right gallery-search-wrap">
        <i class="pi pi-search" />
        <InputText
          :value="searchQuery"
          :placeholder="_('ms3_gallery_search_placeholder')"
          class="gallery-search-input"
          @input="onSearchInput"
        />
        <i v-if="searchQuery" class="pi pi-times gallery-search-clear" @click="clearSearch" />
      </span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="gallery-loading">
      <i class="pi pi-spinner pi-spin" />
    </div>

    <!-- Empty state -->
    <div v-else-if="images.length === 0" class="gallery-empty">
      {{ _('ms3_gallery_empty_text') }}
    </div>

    <!-- Image grid -->
    <draggable
      v-else
      :model-value="localImages"
      item-key="id"
      class="gallery-images"
      ghost-class="gallery-ghost"
      @end="onDragEnd"
    >
      <template #item="{ element }">
        <div
          class="gallery-item"
          :title="_('ms3_gallery_drag_hint')"
          @dblclick="onDblClick(element)"
          @contextmenu.prevent="onContextMenu($event, element)"
        >
          <div class="gallery-item-thumb">
            <img
              v-if="element.thumbnail"
              :src="element.thumbnail"
              :alt="element.name || element.file"
              loading="lazy"
            />
            <div v-else class="gallery-item-icon">
              <i class="pi pi-file" />
            </div>
          </div>
          <div class="gallery-item-name" :title="element.file">
            {{ element.file }}
          </div>
        </div>
      </template>
    </draggable>

    <!-- PrimeVue Context Menu -->
    <ContextMenu ref="contextMenuRef" :model="contextMenuItems" />

    <!-- Paginator -->
    <Paginator
      v-if="total > pageSize"
      :rows="pageSize"
      :total-records="total"
      :first="currentPage * pageSize"
      @page="onPageChange"
    />
  </div>
</template>

<style scoped>
.gallery-grid {
  width: 100%;
}

.gallery-search {
  padding: 0.5rem 0;
}

.gallery-search-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
  width: 20rem;
}

.gallery-search-wrap > i:first-child {
  position: absolute;
  left: 0.75rem;
  color: var(--p-text-muted-color);
  z-index: 1;
}

.gallery-search-input {
  width: 100%;
  padding-left: 2.25rem;
  padding-right: 2.25rem;
}

.gallery-search-clear {
  position: absolute;
  right: 0.75rem;
  cursor: pointer;
  color: var(--p-text-muted-color);
}

.gallery-search-clear:hover {
  color: var(--p-text-color);
}

.gallery-loading {
  display: flex;
  justify-content: center;
  padding: 3rem;
  font-size: 2rem;
  color: var(--p-text-muted-color);
}

.gallery-empty {
  padding: 2rem;
  text-align: center;
  color: var(--p-text-muted-color);
}

.gallery-images {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding: 0.5rem 0;
  min-height: 6rem;
}

.gallery-images :deep(.gallery-item) {
  width: 7.5rem;
  cursor: grab;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.375rem;
  overflow: hidden;
  transition: border-color 0.15s;
  background: var(--p-surface-0);
}

.gallery-images :deep(.gallery-item:hover) {
  border-color: var(--p-primary-color);
}

.gallery-images :deep(.gallery-item-thumb) {
  width: 7.5rem;
  height: 5.625rem;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: var(--p-surface-50);
}

.gallery-images :deep(.gallery-item-thumb img) {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
}

.gallery-images :deep(.gallery-item-icon) {
  font-size: 2rem;
  color: var(--p-text-muted-color);
}

.gallery-images :deep(.gallery-item-name) {
  padding: 0.25rem 0.375rem;
  font-size: 0.6875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-align: center;
  color: var(--p-text-color);
}

.gallery-images :deep(.gallery-ghost) {
  opacity: 0.3;
}
</style>
