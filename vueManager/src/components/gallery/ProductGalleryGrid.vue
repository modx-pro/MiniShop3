<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import ContextMenu from 'primevue/contextmenu'
import Paginator from 'primevue/paginator'
import { computed, ref, watch } from 'vue'
import draggable from 'vuedraggable'

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
  searchQuery: {
    type: String,
    default: '',
  },
})

const emit = defineEmits([
  'sort',
  'page-change',
  'edit',
  'show',
  'set-preview',
  'generate-thumbs',
  'delete',
])

const currentPage = ref(0)

// Context menu
const contextMenuRef = ref()
const contextMenuTarget = ref(null)

const contextMenuItems = computed(() => {
  const items = [
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
  ]

  if (contextMenuTarget.value && !contextMenuTarget.value.is_preview) {
    items.push({
      label: _('ms3_gallery_file_set_preview'),
      icon: 'pi pi-star',
      command: () => contextMenuTarget.value && emit('set-preview', contextMenuTarget.value),
    })
  }

  items.push(
    {
      label: _('ms3_gallery_file_generate_thumbs'),
      icon: 'pi pi-refresh',
      command: () =>
        contextMenuTarget.value && emit('generate-thumbs', [contextMenuTarget.value.id]),
    },
    { separator: true },
    {
      label: _('ms3_gallery_file_delete'),
      icon: 'pi pi-trash',
      class: 'p-menuitem-danger',
      command: () => contextMenuTarget.value && emit('delete', [contextMenuTarget.value.id]),
    }
  )

  return items
})

// Local copy for draggable — allows instant visual reorder
const localImages = ref([])

watch(
  () => props.images,
  val => {
    localImages.value = [...val]
  },
  { immediate: true }
)

const emptyMessage = computed(() =>
  props.searchQuery.trim()
    ? _('ms3_gallery_search_empty')
    : _('ms3_gallery_empty_text')
)

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

function onEdit(image) {
  emit('edit', image)
}

function onContextMenu(event, image) {
  contextMenuTarget.value = image
  contextMenuRef.value.show(event)
}

function onItemKeydown(event, image) {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    onEdit(image)
    return
  }
  if (event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10')) {
    event.preventDefault()
    onContextMenu(event, image)
  }
}

function onEditClick(event, image) {
  event.stopPropagation()
  onEdit(image)
}
</script>

<template>
  <div class="gallery-grid">
    <!-- Loading -->
    <div v-if="loading" class="gallery-loading" role="status">
      <i class="pi pi-spinner pi-spin" aria-hidden="true" />
    </div>

    <!-- Empty state -->
    <div v-else-if="images.length === 0" class="gallery-empty">
      <i class="pi pi-images gallery-empty-icon" aria-hidden="true" />
      <p class="gallery-empty-text">{{ emptyMessage }}</p>
    </div>

    <!-- Image grid -->
    <draggable
      v-else
      v-model="localImages"
      item-key="id"
      class="gallery-images"
      ghost-class="gallery-ghost"
      @end="onDragEnd"
    >
      <template #item="{ element }">
        <div
          class="gallery-item"
          :class="{ 'gallery-item--preview': element.is_preview }"
          tabindex="0"
          :aria-label="element.name || element.file"
          :title="_('ms3_gallery_item_hint')"
          @dblclick="onEdit(element)"
          @contextmenu.prevent="onContextMenu($event, element)"
          @keydown="onItemKeydown($event, element)"
        >
          <div class="gallery-item-thumb">
            <span v-if="element.is_preview" class="gallery-item-badge">
              {{ _('ms3_gallery_file_preview_badge') }}
            </span>
            <img
              v-if="element.thumbnail"
              :src="element.thumbnail"
              :alt="element.name || element.file"
              loading="lazy"
            />
            <div v-else class="gallery-item-icon">
              <i class="pi pi-file" aria-hidden="true" />
            </div>
            <button
              type="button"
              class="gallery-item-edit"
              tabindex="-1"
              :aria-label="_('ms3_gallery_file_update')"
              @click="onEditClick($event, element)"
            >
              <i class="pi pi-pencil" aria-hidden="true" />
            </button>
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

.gallery-loading {
  display: flex;
  justify-content: center;
  padding: 3rem;
  font-size: 2rem;
  color: var(--p-text-muted-color);
}

.gallery-empty {
  display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    padding: 2.5rem 1.5rem;
  text-align: center;
}

.gallery-empty-icon {
  font-size: 1.75rem;
  color: var(--p-text-muted-color);
  opacity: 0.7;
}

.gallery-empty-text {
  margin: 0;
  max-width: 28rem;
  font-size: 0.875rem;
  line-height: 1.45;
  color: var(--p-text-muted-color);
}

.gallery-images {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  padding: 0.25rem 0 0;
  min-height: 6rem;
}

.gallery-images :deep(.gallery-item) {
  width: 7.5rem;
  cursor: grab;
  border: 1px solid var(--p-surface-200);
  border-radius: 0.375rem;
  overflow: hidden;
  transition:
      border-color 0.15s,
      box-shadow 0.15s;
  background: var(--p-surface-0);
}

.gallery-images :deep(.gallery-item:hover) {
  border-color: var(--p-primary-color);
}

.gallery-images :deep(.gallery-item:focus-visible) {
  outline: 2px solid var(--p-primary-color);
  outline-offset: 2px;
}
.gallery-images :deep(.gallery-item--preview) {
  border-color: var(--p-primary-color);
  box-shadow: 0 0 0 1px color-mix(in srgb, var(--p-primary-color) 35%, transparent);
}

.gallery-images :deep(.gallery-item-thumb) {
  width: 7.5rem;
  height: 5.625rem;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: var(--p-surface-50);
  position: relative;
}

.gallery-images :deep(.gallery-item-badge) {
  position: absolute;
  top: 0.25rem;
  left: 0.25rem;
  z-index: 1;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.625rem;
  font-weight: 600;
  line-height: 1.2;
  color: var(--p-primary-contrast-color, #fff);
  background: var(--p-primary-color);
}

.gallery-images :deep(.gallery-item-thumb img) {
  max-width: 100%;
  max-height: 100%;
  object-fit: contain;
  outline: 1px solid color-mix(in srgb, var(--p-surface-900) 8%, transparent);
    outline-offset: -1px;
}

.gallery-images :deep(.gallery-item-icon) {
  font-size: 2rem;
  color: var(--p-text-muted-color);
}

.gallery-images :deep(.gallery-item-edit) {
  position: absolute;
  right: 0.25rem;
  bottom: 0.25rem;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 1.75rem;
  height: 1.75rem;
  padding: 0;
  border: 0;
  border-radius: 0.25rem;
  background: color-mix(in srgb, var(--p-surface-0) 92%, transparent);
  color: var(--p-text-color);
  box-shadow: 0 1px 2px color-mix(in srgb, var(--p-surface-900) 18%, transparent);
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.15s;
}

.gallery-images :deep(.gallery-item:hover .gallery-item-edit),
.gallery-images :deep(.gallery-item:focus-within .gallery-item-edit) {
  opacity: 1;
}

.gallery-images :deep(.gallery-item-edit:focus-visible) {
  opacity: 1;
  outline: 2px solid var(--p-primary-color);
  outline-offset: 1px;
}

@media (prefers-reduced-motion: reduce) {

  .gallery-images :deep(.gallery-item),
  .gallery-images :deep(.gallery-item-edit) {
    transition: none;
  }
}
.gallery-images :deep(.gallery-item-name) {
  padding: 0.375rem 0.375rem;
    font-size: 0.75rem;
    line-height: 1.25;
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
