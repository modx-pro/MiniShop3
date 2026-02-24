<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Menu from 'primevue/menu'
import Select from 'primevue/select'
import { computed, ref } from 'vue'

const props = defineProps({
  sources: {
    type: Array,
    default: () => [],
  },
  currentSourceId: {
    type: [Number, String],
    default: 1,
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'refresh-all',
  'delete-all',
  'change-source',
])

const { _ } = useLexicon()
const menuRef = ref(null)
const menuOpen = ref(false)

const sourceOptions = computed(() =>
  props.sources.map((s) => ({ label: s.name, value: s.id }))
)

const selectedSource = computed({
  get: () => props.currentSourceId,
  set: (v) => emit('change-source', v),
})

const menuItems = computed(() => [
  {
    label: _('ms3_gallery_file_generate_thumbs'),
    icon: 'pi pi-refresh',
    command: () => emit('refresh-all'),
    disabled: props.loading,
  },
  {
    label: _('ms3_gallery_file_delete_multiple'),
    icon: 'pi pi-trash',
    class: 'text-red-500',
    command: () => emit('delete-all'),
    disabled: props.loading,
  },
])

function toggleMenu(event) {
  menuRef.value?.toggle(event)
}
</script>

<template>
  <div class="product-gallery-toolbar">
    <div class="toolbar-actions">
      <button
        type="button"
        class="toolbar-menu-trigger p-button p-component"
        :class="{ 'menu-open': menuOpen }"
        :disabled="loading"
        aria-haspopup="true"
        aria-controls="gallery-actions-menu"
        @click="toggleMenu"
      >
        <span class="toolbar-menu-trigger-icon pi pi-cog" />
        <span class="toolbar-menu-trigger-chevron pi pi-chevron-down" />
      </button>
      <Menu
        id="gallery-actions-menu"
        ref="menuRef"
        :model="menuItems"
        :popup="true"
        @show="menuOpen = true"
        @hide="menuOpen = false"
      />
    </div>
    <div class="toolbar-source">
      <label for="gallery-source-select">{{ _('ms3_product_source_id') }}</label>
      <Select
        id="gallery-source-select"
        v-model="selectedSource"
        :options="sourceOptions"
        option-label="label"
        option-value="value"
        :placeholder="_('ms3_product_source_id')"
        :disabled="loading"
        class="source-select"
      />
    </div>
  </div>
</template>

<style scoped>
.product-gallery-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}
.toolbar-actions {
  display: flex;
  gap: 0.5rem;
}
.toolbar-menu-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  min-width: 6rem;
  min-height: 2.5rem;
  padding: 0.5rem 0.75rem;
  border: 1px solid var(--p-input-border-color, #ced4da);
  border-radius: var(--p-border-radius, 0.375rem);
  background: var(--p-input-background, #fff);
  color: var(--p-input-text-color, #495057);
  font-size: 1rem;
  cursor: pointer;
}
.toolbar-menu-trigger:hover:not(:disabled) .toolbar-menu-trigger-icon,
.toolbar-menu-trigger.menu-open .toolbar-menu-trigger-icon {
  visibility: hidden;
}
.toolbar-menu-trigger:hover:not(:disabled) {
  border-color: var(--p-input-hover-border-color, #86b7fe);
  background: var(--p-input-hover-background, #fff);
}
.toolbar-menu-trigger:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.toolbar-menu-trigger-icon {
  flex: 1;
  text-align: left;
}
.toolbar-menu-trigger-chevron {
  font-size: 0.75rem;
  opacity: 0.7;
}
.toolbar-source {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin-left: auto;
}
.toolbar-source label {
  white-space: nowrap;
}
.source-select {
  min-width: 10rem;
}
</style>
