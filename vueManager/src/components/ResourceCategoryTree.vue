<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Checkbox from 'primevue/checkbox'
import ContextMenu from 'primevue/contextmenu'
import Tree from 'primevue/tree'
import { computed, onMounted, ref, watch } from 'vue'

import request from '../request.js'

/**
 * Reusable MODX resource tree with independent checkbox selection.
 *
 * Used by product Categories tab and option category pickers. PrimeVue Tree
 * selection-mode=checkbox cascades parent/child checks with no opt-out in v4,
 * so checkboxes are rendered manually inside the node slot.
 */

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  apiUrl: { type: String, required: true },
  apiParams: { type: Object, default: () => ({}) },
  /** Category ids that stay checked and cannot be toggled off (e.g. product parent). */
  lockedIds: { type: Array, default: () => [] },
  inputIdPrefix: { type: String, default: 'resource-cat-' },
})

const emit = defineEmits(['update:modelValue'])
const { _ } = useLexicon()

const nodes = ref([])
const expandedKeys = ref({})
const loading = ref(false)
const contextMenu = ref(null)
const contextNode = ref(null)
const checkedSet = ref(new Set())

const lockedSet = computed(() => new Set(props.lockedIds.map(id => Number(id))))

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

async function fetchChildren(parent = 0) {
  const params = {
    parent,
    ...props.apiParams,
    categories: JSON.stringify(Array.isArray(props.modelValue) ? props.modelValue : []),
  }

  const response = await request.get(props.apiUrl, params)
  const results = response?.results || []
  mergeCheckedFromApi(results)
  return results.map(row => toTreeNode(row))
}

function toTreeNode(row) {
  return {
    key: String(row.id),
    id: row.id,
    label: row.label,
    leaf: !!row.leaf,
    data: {
      class_key: row.class_key,
      selectable: row.selectable !== false,
      locked: isApiRowLocked(row),
      published: row.published,
      hidemenu: row.hidemenu,
    },
  }
}

function mergeCheckedFromApi(rows) {
  const next = new Set(checkedSet.value)
  for (const row of rows) {
    if (row.checked || lockedSet.value.has(Number(row.id))) {
      next.add(row.id)
    }
  }
  checkedSet.value = next
}

function isApiRowLocked(row) {
  return Boolean(row.locked) || lockedSet.value.has(Number(row.id))
}

function isSelectableNode(node) {
  return !!node?.data?.selectable
}

function isLockedNode(node) {
  return !!node?.data?.locked || lockedSet.value.has(Number(node?.id))
}

function isChecked(node) {
  return checkedSet.value.has(node.id)
}

function toggleNode(node, checked) {
  if (!isSelectableNode(node) || (isLockedNode(node) && !checked)) {
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
    ensureLockedChecked()
  } finally {
    loading.value = false
  }
}

function ensureLockedChecked() {
  const next = new Set(checkedSet.value)
  lockedSet.value.forEach(id => next.add(id))
  checkedSet.value = next
  emitSelection()
}

async function onNodeExpand(node) {
  if (node.children !== undefined) return
  const children = await fetchChildren(node.id)
  replaceNodeInPlace(node.key, { ...node, children })
}

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
  if (!node.id) {
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

async function bulkToggleChecks(node, checked) {
  if (!node) return
  await ensureChildrenLoaded(node)

  const next = new Set(checkedSet.value)
  function walk(n) {
    if (isSelectableNode(n)) {
      if (checked || isLockedNode(n)) {
        next.add(n.id)
      } else {
        next.delete(n.id)
      }
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
    // Sync internal check state from the parent and enforce locked IDs. Emit back ONLY
    // when locked enforcement actually added something the parent doesn't already have.
    // Calling emitSelection() unconditionally here echoes the value we just received,
    // which reassigns props.modelValue and retriggers this watch — an infinite recursive
    // loop that freezes the page (#546).
    const incoming = new Set(newIds || [])
    const next = new Set(incoming)
    lockedSet.value.forEach(id => next.add(id))
    checkedSet.value = next
    if (next.size !== incoming.size) {
      emitSelection()
    }
  },
  { deep: true }
)

watch(lockedSet, () => ensureLockedChecked())

onMounted(() => {
  loadRoot()
})

defineExpose({
  refresh: loadRoot,
})
</script>

<template>
  <div class="resource-category-tree">
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
            :disabled="isLockedNode(node)"
            :input-id="inputIdPrefix + node.id"
            class="tree-node-check"
            @update:model-value="toggleNode(node, $event)"
          />
          <label
            v-if="isSelectableNode(node)"
            :for="inputIdPrefix + node.id"
            class="tree-node-label"
            :class="{ 'tree-node-label-locked': isLockedNode(node) }"
          >
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
.vueApp .resource-category-tree {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 20rem;
}

.vueApp .resource-category-tree .tree-body {
  flex: 1;
  overflow: auto;
  border: 1px solid var(--p-tree-border-color, #e5e7eb);
  border-radius: 0.375rem;
}

.vueApp .resource-category-tree .tree-node-row {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.vueApp .resource-category-tree .tree-node-check {
  flex-shrink: 0;
}

.vueApp .resource-category-tree .tree-node-label {
  cursor: pointer;
  user-select: none;
}

/* PrimeVue Checkbox box can overflow the flex item, so gap alone is not enough (#555). */
.vueApp .resource-category-tree .tree-node-check + .tree-node-label {
  margin-inline-start: 0.5rem;
}

.vueApp .resource-category-tree .tree-node-label-locked {
  font-weight: 600;
}

.vueApp .resource-category-tree .tree-node-row-navigation {
  opacity: 0.72;
}

.vueApp .resource-category-tree .tree-node-label-navigation {
  cursor: default;
  font-style: italic;
}
</style>
