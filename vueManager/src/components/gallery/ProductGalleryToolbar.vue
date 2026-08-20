<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import Select from 'primevue/select'
import { computed, ref, watch } from 'vue'

import { debounce } from '../../utils/modx.js'

const { _ } = useLexicon()

const props = defineProps({
  sources: {
    type: Array,
    default: () => [],
  },
  currentSourceId: {
    type: Number,
    default: 1,
  },
  searchQuery: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['change-source', 'regenerate-all', 'delete-all', 'search'])

const bulkMenu = ref(null)
const localSearch = ref(props.searchQuery)

watch(
  () => props.searchQuery,
  value => {
    if (value !== localSearch.value) {
      localSearch.value = value
    }
  }
)

const sourceOptions = computed(() =>
  props.sources.map(s => ({
    label: s.name,
    value: s.id,
  }))
)

const selectedSource = computed({
  get: () => props.currentSourceId,
  set: val => {
    if (val !== props.currentSourceId) {
      emit('change-source', val)
    }
  },
})

const menuItems = ref([
  {
    label: _('ms3_gallery_file_generate_all'),
    icon: 'pi pi-refresh',
    command: () => emit('regenerate-all'),
  },
  {
    separator: true,
  },
  {
    label: _('ms3_gallery_file_delete_all'),
    icon: 'pi pi-trash',
    class: 'p-menuitem-danger',
    command: () => emit('delete-all'),
  },
])

const debouncedSearch = debounce(query => {
  emit('search', query)
}, 300)

function onSearchInput(event) {
  localSearch.value = event.target.value
  debouncedSearch(localSearch.value)
}

function clearSearch() {
  localSearch.value = ''
  emit('search', '')
}

function toggleMenu(event) {
  bulkMenu.value.toggle(event)
}
</script>

<template>
  <div class="gallery-toolbar">
    <div class="gallery-toolbar-left">
      <Select
        v-if="sources.length > 1"
        v-model="selectedSource"
        :options="sourceOptions"
        option-label="label"
        option-value="value"
        :placeholder="_('ms3_product_source')"
        class="gallery-source-select"
      />
      <span v-else-if="sources.length === 1" class="gallery-source-name">
        {{ sources[0].name }}
      </span>
    </div>

    <div class="gallery-toolbar-search">
      <span class="gallery-search-wrap">
        <i class="pi pi-search" aria-hidden="true" />
        <InputText
          :value="localSearch"
          :placeholder="_('ms3_gallery_search_placeholder')"
          :aria-label="_('ms3_gallery_search_placeholder')"
          class="gallery-search-input"
          @input="onSearchInput"
        />
        <button
          v-if="localSearch"
          type="button"
          class="gallery-search-clear"
          :aria-label="_('ms3_gallery_search_clear')"
          @click="clearSearch"
        >
          <i class="pi pi-times" aria-hidden="true" />
        </button>
      </span>
    </div>

    <div class="gallery-toolbar-right">
      <Button
        type="button"
        icon="pi pi-ellipsis-v"
        :label="_('ms3_actions')"
        severity="secondary"
        outlined
        class="gallery-actions-btn"
        @click="toggleMenu"
      />
      <Menu ref="bulkMenu" :model="menuItems" :popup="true" />
    </div>
  </div>
</template>

<style scoped>
.gallery-toolbar {
  display: flex;
  align-items: center;
  gap: 0.75rem;
    padding: 0 0 0.75rem;
    flex-wrap: wrap;
}

.gallery-toolbar-left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-shrink: 0;
  }
  
  .gallery-toolbar-search {
    flex: 1 1 12rem;
    min-width: 10rem;
    max-width: 24rem;
}

.gallery-toolbar-right {
  display: flex;
  align-items: center;
  margin-inline-start: auto;
  }
  
  .gallery-actions-btn {
    /* Match Select / InputText control height in this toolbar */
    height: 2.25rem;
    padding-block: 0;
}

.gallery-source-select {
  min-width: 12rem;
}

.gallery-source-name {
  font-size: 0.8125rem;
    font-weight: 500;
    color: var(--p-text-muted-color);
    line-height: 1.25;
  }
  
  .gallery-search-wrap {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
  }
  
  .gallery-search-wrap>.pi-search {
    position: absolute;
    left: 0.75rem;
    color: var(--p-text-muted-color);
    z-index: 1;
    pointer-events: none;
  }
  
  .gallery-search-input {
    width: 100%;
    padding-left: 2.25rem;
    padding-right: 2.25rem;
  }
  
  .gallery-search-clear {
    position: absolute;
    right: 0.25rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    padding: 0;
    border: 0;
    border-radius: 0.25rem;
    background: transparent;
  color: var(--p-text-muted-color);
  cursor: pointer;
  }
  
  .gallery-search-clear:hover {
    color: var(--p-text-color);
  }
  
  .gallery-search-clear:focus-visible {
    outline: 2px solid var(--p-primary-color);
    outline-offset: 1px;
}
</style>
