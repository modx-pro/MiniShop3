<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import ContextMenu from 'primevue/contextmenu'
import InputGroup from 'primevue/inputgroup'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Paginator from 'primevue/paginator'
import ProgressSpinner from 'primevue/progressspinner'
import { computed, ref, watch } from 'vue'
import draggable from 'vuedraggable'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  total: {
    type: Number,
    default: 0,
  },
  first: {
    type: Number,
    default: 0,
  },
  rows: {
    type: Number,
    default: 20,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:first',
  'search',
  'reorder',
  'edit',
  'show',
  'generate-thumbs',
  'delete',
])

const { _ } = useLexicon()

const searchQuery = ref('')
let searchDebounceTimer = null
const DEBOUNCE_MS = 300

const contextMenuRef = ref(null)
const contextFile = ref(null)

/** Локальная копия списка для vuedraggable; порядок меняется при перетаскивании. */
const orderedItems = ref([])
watch(
  () => props.items,
  (val) => {
    orderedItems.value = val?.length ? [...val] : []
  },
  { immediate: true }
)

const menuModel = computed(() => {
  const file = contextFile.value
  if (!file) return []
  const isImage = file.type === 'image'
  return [
    {
      label: _('ms3_gallery_file_update'),
      icon: 'pi pi-pencil',
      command: () => emit('edit', file),
    },
    {
      label: _('ms3_gallery_file_show'),
      icon: 'pi pi-external-link',
      command: () => emit('show', file),
    },
    ...(isImage
      ? [
          {
            label: _('ms3_gallery_file_generate_thumbs'),
            icon: 'pi pi-refresh',
            command: () => emit('generate-thumbs', file),
          },
        ]
      : []),
    {
      label: _('ms3_gallery_file_delete'),
      icon: 'pi pi-trash',
      class: 'text-red-500',
      command: () => emit('delete', file),
    },
  ]
})

function onSearchInput() {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    emit('search', searchQuery.value.trim())
  }, DEBOUNCE_MS)
}

function onSearchClick() {
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)
  emit('search', searchQuery.value.trim())
}

function onClearSearch() {
  searchQuery.value = ''
  emit('search', '')
}

function onPage(e) {
  emit('update:first', e.first)
}

function onContextMenu(event, file) {
  event.preventDefault()
  contextFile.value = file
  contextMenuRef.value?.show(event)
}

function onDblClick(file) {
  emit('edit', file)
}

/**
 * Обработчик окончания перетаскивания (vuedraggable @end).
 * Вычисляет sourceId (перемещённый файл) и targetId (сосед для API Sort: «поставить относительно него»).
 * При сдвиге в начало/конец списка targetIndex ограничивается границами массива.
 */
function onDragEnd(evt) {
  if (evt.oldIndex === evt.newIndex) return
  const list = orderedItems.value
  if (!list?.length) return
  const sourceId = list[evt.newIndex]?.id ?? list[evt.oldIndex]?.id
  let targetIndex =
    evt.oldIndex < evt.newIndex ? evt.newIndex - 1 : evt.newIndex + 1
  if (targetIndex < 0) targetIndex = evt.newIndex + 1
  if (targetIndex >= list.length) targetIndex = evt.newIndex - 1
  if (targetIndex < 0 || targetIndex >= list.length) return
  const targetId = list[targetIndex]?.id
  if (sourceId != null && targetId != null && sourceId !== targetId) {
    emit('reorder', { sourceId, targetId })
  }
}

watch(
  () => props.first,
  (v) => {
    if (searchQuery.value !== undefined) return
  }
)
</script>

<template>
  <div class="product-gallery-grid">
    <div class="grid-toolbar">
      <InputGroup class="search-input-group">
        <InputText
          v-model="searchQuery"
          :placeholder="_('search')"
          class="search-input p-inputtext-sm"
          @input="onSearchInput"
          @keyup.enter="onSearchClick"
        />
        <Button
          icon="pi pi-search"
          severity="secondary"
          :aria-label="_('search')"
          @click="onSearchClick"
        />
        <Button
          v-if="searchQuery"
          icon="pi pi-times"
          severity="secondary"
          :aria-label="_('clear')"
          @click="onClearSearch"
        />
      </InputGroup>
    </div>

    <div class="grid-content-wrapper">
      <div v-if="loading" class="grid-loading-overlay" aria-hidden="true">
        <ProgressSpinner
          style="width: 2.5rem; height: 2.5rem"
          stroke-width="4"
        />
        <span class="grid-loading-label">{{ _('ms3_vue_loading') }}</span>
      </div>
      <Message
        v-else-if="!items.length"
        severity="info"
        :closable="false"
        class="grid-empty-msg"
      >
        {{ _('ms3_gallery_empty_text') }}
      </Message>
      <draggable
        v-else
        v-model="orderedItems"
        class="grid-items"
        item-key="id"
        handle=".drag-handle"
        ghost-class="grid-item-dragging"
        drag-class="grid-item-drag"
        @end="onDragEnd"
      >
        <template #item="{ element: item }">
          <div
            class="grid-item ms3-gallery-thumb-wrap"
            @contextmenu="onContextMenu($event, item)"
            @dblclick="onDblClick(item)"
          >
            <i class="pi pi-bars drag-handle" :aria-label="_('ms3_gallery_drag_hint')" />
            <div class="ms3-gallery-thumb">
              <img
                :src="item.thumbnail || item.url"
                :alt="item.name"
                draggable="false"
              />
            </div>
            <div class="grid-item-name">{{ item.name || item.file }}</div>
          </div>
        </template>
      </draggable>
    </div>

    <Paginator
      v-if="total > rows && !loading"
      :first="first"
      :rows="rows"
      :total-records="total"
      @page="onPage"
    />

    <ContextMenu ref="contextMenuRef" :model="menuModel" />
  </div>
</template>

<style scoped>
.product-gallery-grid {
  min-height: 9.375rem;
}
.grid-toolbar {
  margin-bottom: var(--ms3-spacing-3);
}
.search-input-group {
  max-width: 20rem;
}
.search-input {
  flex: 1;
  min-width: 0;
}
.grid-content-wrapper {
  position: relative;
  min-height: 9.375rem;
}
.grid-loading-overlay {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--ms3-spacing-3);
  padding: 1.25rem;
  background: var(--ms3-bg-gray-50);
  border-radius: var(--ms3-radius-sm);
  color: var(--ms3-text-hint);
}
.grid-loading-label {
  font-size: 0.875rem;
}
.grid-empty-msg {
  margin: 0;
}
.grid-empty-msg :deep(.p-inline-message) {
  width: 100%;
}
.grid-items {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(7.5rem, 1fr));
  gap: var(--ms3-spacing-3);
}
.grid-items :deep(> *) {
  min-width: 0;
}
.grid-item-dragging {
  opacity: 0.5;
}
.grid-item-drag {
  cursor: grabbing;
}
.grid-item {
  position: relative;
  border: var(--ms3-border-width) solid var(--ms3-border-color-alt);
  border-radius: var(--ms3-radius-sm);
  padding: var(--ms3-spacing-1);
  background: var(--ms3-bg-gray-50);
}
.drag-handle {
  position: absolute;
  top: var(--ms3-spacing-1);
  right: var(--ms3-spacing-1);
  cursor: grab;
  color: var(--ms3-text-hint);
  font-size: 0.75rem;
}
.drag-handle:hover {
  color: var(--ms3-text-primary);
}
.grid-item:hover {
  border-color: var(--ms3-border-neutral);
}
.ms3-gallery-thumb {
  width: 7.5rem;
  height: 5.625rem;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
}
.ms3-gallery-thumb img {
  max-width: 7.5rem;
  max-height: 5.625rem;
  object-fit: contain;
  user-drag: none;
  -webkit-user-drag: none;
}
.grid-item-name {
  font-size: 0.75rem;
  text-align: center;
  margin-top: var(--ms3-spacing-1);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
</style>
