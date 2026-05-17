<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Checkbox from 'primevue/checkbox'
import ContextMenu from 'primevue/contextmenu'
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
})

const emit = defineEmits(['update:modelValue'])
const { _ } = useLexicon()

const nodes = ref([])
const expandedKeys = ref({})
const loading = ref(false)
const contextMenu = ref(null)
const contextNode = ref(null)

/**
 * Independent checkbox state: Set<categoryId>.
 * PrimeVue Tree with selection-mode=checkbox hard-codes parent↔child propagation with no
 * opt-out (v4.3.1). We render a native PrimeVue Checkbox inside the node slot and manage
 * selection ourselves — ticking a parent does NOT cascade to children, unticking a child
 * does NOT unselect the parent.
 */
const checkedSet = ref(new Set())

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

/** Backend adds `selectable`; older responses without it were msCategory-only (all selectable). */
function isApiRowSelectable(row) {
  return typeof row.selectable === 'boolean' ? row.selectable : true
}

function normalizeNode(row) {
  const node = {
    key: String(row.id),
    id: row.id,
    label: row.label,
    leaf: !!row.leaf,
    data: {
      class_key: row.class_key,
      selectable: isApiRowSelectable(row),
      published: row.published,
      hidemenu: row.hidemenu,
    },
    // PrimeVue treats presence of `children` (even empty) as "loaded" — keep undefined until we load.
  }
  if (row.checked) {
    checkedSet.value.add(row.id)
  }
  return node
}

function isSelectableNode(node) {
  return !!node?.data?.selectable
}

function isChecked(node) {
  return checkedSet.value.has(node.id)
}

function toggleNode(node, checked) {
  if (!isSelectableNode(node)) {
    return
  }
  const next = new Set(checkedSet.value)
  if (checked) {
    next.add(node.id)
  } else {
    next.delete(node.id)
  }
  checkedSet.value = next
  emitSelection()
}

async function loadRoot() {
  loading.value = true
  try {
    nodes.value = await fetchChildren(0)
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

  const next = new Set(checkedSet.value)
  function walk(n) {
    if (!isSelectableNode(n)) {
      if (Array.isArray(n.children)) {
        n.children.forEach(walk)
      }
      return
    }
    if (checked) {
      next.add(n.id)
    } else {
      next.delete(n.id)
    }
    if (Array.isArray(n.children)) {
      n.children.forEach(walk)
    }
  }
  walk(node)
  checkedSet.value = next
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

function emitSelection() {
  emit('update:modelValue', Array.from(checkedSet.value))
}

function onNodeContextMenu(event, node) {
  contextNode.value = node
  contextMenu.value?.show(event)
}

watch(
  () => props.modelValue,
  newIds => {
    checkedSet.value = new Set(newIds || [])
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
    <Tree
      v-model:expanded-keys="expandedKeys"
      :value="nodes"
      :loading="loading"
      :filter="true"
      filter-mode="lenient"
      :filter-placeholder="_('search') || 'Поиск'"
      class="tree-body"
      @node-expand="onNodeExpand"
    >
      <template #default="{ node }">
        <span
          class="tree-node-row"
          :class="{ 'tree-node-row-navigation': !isSelectableNode(node) }"
          @contextmenu.prevent="onNodeContextMenu($event, node)"
        >
          <Checkbox
            v-if="isSelectableNode(node)"
            :model-value="isChecked(node)"
            :binary="true"
            :input-id="'opt-cat-' + node.id"
            class="tree-node-check"
            @update:model-value="toggleNode(node, $event)"
          />
          <label v-if="isSelectableNode(node)" :for="'opt-cat-' + node.id" class="tree-node-label">
            {{ node.label }}
          </label>
          <span v-else class="tree-node-label tree-node-label-navigation">{{ node.label }}</span>
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

.vueApp .option-category-tree .tree-body {
  flex: 1;
  overflow: auto;
  border: 1px solid var(--p-tree-border-color, #e5e7eb);
  border-radius: 0.375rem;
}

.vueApp .option-category-tree .tree-node-row {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
}

.vueApp .option-category-tree .tree-node-label {
  cursor: pointer;
  user-select: none;
}

.vueApp .option-category-tree .tree-node-row-navigation {
  opacity: 0.72;
}

.vueApp .option-category-tree .tree-node-label-navigation {
  cursor: default;
  font-style: italic;
}
</style>
