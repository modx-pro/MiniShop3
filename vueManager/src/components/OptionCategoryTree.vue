<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import ContextMenu from 'primevue/contextmenu'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Tree from 'primevue/tree'
import { computed, onMounted, ref, watch } from 'vue'

import request from '../request.js'

/**
 * MODX resource tree with checkbox selection for category picking.
 *
 * Features matching the legacy ExtJS tree (ms3-tree-option-categories):
 *  - Checkbox selection with hierarchical propagation (PrimeVue handles parent/partial states)
 *  - Lazy loading of children on node expand
 *  - Context menu per node: refresh, expand tree, collapse tree, select all (with children), clear all
 *  - Filter by label
 *  - Initial pre-check from `modelValue` (category ids) + optional option_id that auto-fetches
 *    current category links
 */

const props = defineProps({
  /** Currently checked category ids — supports v-model. */
  modelValue: { type: Array, default: () => [] },
  /** Pre-fetch checked state from the option's current links. Pass 0/null to skip. */
  optionId: { type: [Number, String], default: 0 },
  /** Root resource id. 0 = site root. */
  rootParent: { type: Number, default: 0 },
})

const emit = defineEmits(['update:modelValue'])
const { _ } = useLexicon()

const nodes = ref([])
const selectionKeys = ref({})
const expandedKeys = ref({})
const loading = ref(false)
const filterValue = ref('')
const contextMenu = ref(null)
const contextNode = ref(null)

const contextMenuItems = computed(() => [
  {
    label: _('directory_refresh') || 'Обновить',
    icon: 'pi pi-refresh',
    command: () => refreshNode(contextNode.value),
  },
  { separator: true },
  {
    label: _('expand_tree') || 'Развернуть',
    icon: 'pi pi-chevron-down',
    command: () => expandBranch(contextNode.value, true),
  },
  {
    label: _('collapse_tree') || 'Свернуть',
    icon: 'pi pi-chevron-up',
    command: () => expandBranch(contextNode.value, false),
  },
  { separator: true },
  {
    label: _('ms3_menu_select_all') || 'Выделить все',
    icon: 'pi pi-check-square',
    command: () => bulkToggleChecks(contextNode.value, true),
  },
  {
    label: _('ms3_menu_clear_all') || 'Снять все',
    icon: 'pi pi-stop',
    command: () => bulkToggleChecks(contextNode.value, false),
  },
])

/**
 * Fetch a level of the tree. parent=0 = root.
 */
async function fetchChildren(parent = 0) {
  const params = { parent }
  if (props.optionId) {
    params.option_id = props.optionId
  }
  if (props.modelValue && props.modelValue.length > 0) {
    params.categories = JSON.stringify(props.modelValue)
  }

  const response = await request.get('/api/mgr/options/tree', params)
  const results = response?.results || []
  return results.map(row => normalizeNode(row))
}

function normalizeNode(row) {
  const node = {
    key: String(row.id),
    id: row.id,
    label: row.label,
    leaf: !!row.leaf,
    data: {
      class_key: row.class_key,
      published: row.published,
      hidemenu: row.hidemenu,
    },
    // PrimeVue treats presence of `children` (even empty) as "loaded" — keep undefined until we load.
  }
  if (row.checked) {
    selectionKeys.value[node.key] = { checked: true, partialChecked: false }
  }
  return node
}

async function loadRoot() {
  loading.value = true
  try {
    nodes.value = await fetchChildren(props.rootParent)
  } finally {
    loading.value = false
  }
}

async function onNodeExpand(node) {
  if (node.children !== undefined) return
  const children = await fetchChildren(node.id)
  // Vue reactivity: assign via findAndReplace to keep treeview in sync.
  replaceNodeInPlace(node.key, { ...node, children })
}

/**
 * Replace a node somewhere in the tree by key, preserving ordering.
 */
function replaceNodeInPlace(key, replacement) {
  function walk(list) {
    for (let i = 0; i < list.length; i++) {
      if (list[i].key === key) {
        list[i] = replacement
        return true
      }
      if (Array.isArray(list[i].children)) {
        if (walk(list[i].children)) return true
      }
    }
    return false
  }
  walk(nodes.value)
  nodes.value = [...nodes.value]
}

async function refreshNode(node) {
  if (!node) return
  if (node.id === 0 || node.id === undefined) {
    await loadRoot()
    return
  }
  const children = await fetchChildren(node.id)
  replaceNodeInPlace(node.key, { ...node, children })
  expandedKeys.value = { ...expandedKeys.value, [node.key]: true }
}

function expandBranch(node, expand) {
  if (!node) return
  const next = { ...expandedKeys.value }
  function walk(n) {
    if (!n) return
    if (expand) {
      next[n.key] = true
    } else {
      delete next[n.key]
    }
    if (Array.isArray(n.children)) {
      n.children.forEach(walk)
    }
  }
  walk(node)
  expandedKeys.value = next
}

/**
 * Toggle checkbox for a node and all its loaded descendants.
 * For lazy children that are not yet loaded, we pre-fetch them recursively.
 */
async function bulkToggleChecks(node, checked) {
  if (!node) return
  await ensureChildrenLoaded(node)

  const next = { ...selectionKeys.value }
  function walk(n) {
    if (checked) {
      next[n.key] = { checked: true, partialChecked: false }
    } else {
      delete next[n.key]
    }
    if (Array.isArray(n.children)) {
      n.children.forEach(walk)
    }
  }
  walk(node)
  selectionKeys.value = next
  emitSelection()
}

async function ensureChildrenLoaded(node) {
  if (node.leaf) return
  if (!Array.isArray(node.children)) {
    const children = await fetchChildren(node.id)
    replaceNodeInPlace(node.key, { ...node, children })
    node.children = children
  }
  for (const child of node.children) {
    await ensureChildrenLoaded(child)
  }
}

function onSelectionChange() {
  emitSelection()
}

function emitSelection() {
  const ids = []
  for (const key in selectionKeys.value) {
    const val = selectionKeys.value[key]
    if (val?.checked) {
      ids.push(parseInt(key, 10))
    }
  }
  emit('update:modelValue', ids)
}

function onNodeContextMenu(event, node) {
  contextNode.value = node
  contextMenu.value?.show(event)
}

watch(
  () => props.modelValue,
  newIds => {
    const next = {}
    ;(newIds || []).forEach(id => {
      next[String(id)] = { checked: true, partialChecked: false }
    })
    // Preserve partial-checked ancestors that the user hasn't explicitly ticked.
    // PrimeVue recalculates them once children are expanded, so we only rewrite explicit picks here.
    selectionKeys.value = next
  },
  { deep: true }
)

onMounted(() => {
  loadRoot()
})

defineExpose({
  refresh: loadRoot,
})
</script>

<template>
  <div class="option-category-tree">
    <IconField class="tree-filter">
      <InputIcon><i class="pi pi-search" /></InputIcon>
      <InputText v-model="filterValue" class="w-full" :placeholder="_('search') || 'Поиск'" />
    </IconField>

    <Tree
      v-model:selection-keys="selectionKeys"
      v-model:expanded-keys="expandedKeys"
      :value="nodes"
      selection-mode="checkbox"
      :loading="loading"
      :filter="true"
      filter-mode="lenient"
      :filter-value="filterValue"
      :propagate-selection-up="false"
      :propagate-selection-down="false"
      class="tree-body"
      @update:selection-keys="onSelectionChange"
      @node-expand="onNodeExpand"
    >
      <template #default="{ node }">
        <span class="tree-node-label" @contextmenu.prevent="onNodeContextMenu($event, node)">
          {{ node.label }}
        </span>
      </template>
    </Tree>

    <ContextMenu ref="contextMenu" :model="contextMenuItems" />
  </div>
</template>

<style>
/* Non-scoped with .vueApp prefix — avoids Vite scoped-hash mismatch across chunks */
.vueApp .option-category-tree {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 20rem;
}

.vueApp .option-category-tree .tree-filter {
  margin-bottom: 0.5rem;
  display: block;
}

.vueApp .option-category-tree .tree-body {
  flex: 1;
  overflow: auto;
  border: 1px solid var(--p-tree-border-color, #e5e7eb);
  border-radius: 0.375rem;
}

.vueApp .option-category-tree .tree-node-label {
  cursor: default;
  user-select: none;
}
</style>
