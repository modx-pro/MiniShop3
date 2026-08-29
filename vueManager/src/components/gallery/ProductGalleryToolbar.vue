<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import { Button, Menu, Select } from 'primevue'
import { computed, ref } from 'vue'

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
})

const emit = defineEmits(['change-source', 'regenerate-all', 'delete-all'])

const bulkMenu = ref(null)

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

const actionsAriaLabel = computed(() => {
  const key = 'ms3_gallery_actions'
  const value = _(key)
  return value && value !== key ? value : 'Gallery actions'
})

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

    <div class="gallery-toolbar-right">
      <Button
        type="button"
        icon="pi pi-ellipsis-v"
        severity="secondary"
        outlined
        class="gallery-bulk-menu-btn"
        :aria-label="actionsAriaLabel"
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
  justify-content: space-between;
  padding: 0.5rem 0;
  gap: 0.5rem;
}

.gallery-toolbar-left {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.gallery-toolbar-right {
  display: flex;
  align-items: center;
}

.gallery-bulk-menu-btn {
  width: var(--p-modx-control-height, 2.25rem);
  height: var(--p-modx-control-height, 2.25rem);
  min-width: var(--p-modx-control-height, 2.25rem);
  padding: 0;
}

.gallery-source-select {
  min-width: 12rem;
}

.gallery-source-name {
  font-size: 0.875rem;
  color: var(--p-text-muted-color);
}
</style>
