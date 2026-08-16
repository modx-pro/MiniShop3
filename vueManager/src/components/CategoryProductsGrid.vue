<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import ConfirmDialog from 'primevue/confirmdialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import { computed, defineProps, onMounted, ref, watch } from 'vue'
import draggable from 'vuedraggable'

import { useGroupedToast, useUiGroup } from '../composables/uiGroup.js'
import { useCategoryProductsInlineEdit } from '../composables/useCategoryProductsInlineEdit.js'
import { useSelection } from '../composables/useSelection.js'
import { useStaleRequestGuard } from '../composables/useStaleRequestGuard.js'
import {
  GridColumnEditorType,
  isSelectLikeEditorType,
  normalizeGridColumnEditorType,
} from '../constants/gridColumnEditorTypes.js'
import request from '../request.js'
import ActionsColumn from './ActionsColumn.vue'

const props = defineProps({
  categoryId: {
    type: Number,
    required: true,
  },
})

const { _ } = useLexicon()

// From entry provideUiGroup('category-products'); fallback keeps confirm/toast alive
// if the grid is mounted outside that entry (#538/#539).
const UI_GROUP = useUiGroup() || 'category-products'
const toast = useGroupedToast(UI_GROUP)

// Bulk selection
const {
  selectedItems,
  hasSelection,
  selectionCount,
  processing: bulkProcessing,
  clearSelection,
  confirmBulkDelete,
} = useSelection({
  entityName: 'product',
  uiGroup: UI_GROUP,
  deleteBulk: async ids => {
    await request.post(`/api/mgr/categories/${props.categoryId}/products/multiple`, {
      method: 'delete',
      ids,
      ...nestedMutationParams(),
    })
  },
  onSuccess: () => loadProducts(),
  getItemName: item => item.pagetitle || `#${item.id}`,
})

const columns = ref([])
const filters = ref({})
const { runGuarded } = useStaleRequestGuard()
const loading = ref(false)
const products = ref([])
const totalRecords = ref(0)
const ROWS_STORAGE_KEY = 'ms3_category_products_rows'
const rowsPerPageOptions = [10, 20, 25, 50, 100]

const first = ref(0)
const rows = ref(20)
const filterValues = ref({})
const nested = ref(false)
const dragEnabled = ref(true)
const sortField = ref('menuindex')
const sortOrder = ref(1)
const selectAll = ref(false)

const referencePathsByKey = ref({})

const {
  inlineEditValue,
  inlineEditSaving,
  inlineEditInputRef,
  isBooleanColumn,
  isEditingCell,
  startInlineEdit,
  saveInlineEdit,
  cancelInlineEdit,
  selectOptionsForColumn,
  selectUsesClear,
} = useCategoryProductsInlineEdit({
  products,
  referencePathsByKey,
  categoryId: computed(() => props.categoryId),
  nested,
  request,
  toast,
  _,
})

// Default thumbnail from system settings

const defaultThumb =
  (typeof ms3 !== 'undefined' ? ms3.config?.default_thumb : null) ||
  '/assets/components/minishop3/img/mgr/ms3_small.png'

/**
 * Get sorted filters list
 */
const sortedFilters = computed(() => {
  return Object.entries(filters.value)
    .filter(([, config]) => config.visible !== false)
    .map(([key, config]) => ({ key, ...config }))
    .sort((a, b) => (a.position || 100) - (b.position || 100))
})

/**
 * Check if drag-drop is available (only when sorted by menuindex and not nested)
 */
const canDrag = computed(() => {
  return dragEnabled.value && sortField.value === 'menuindex' && !nested.value
})

/** Pass nested grid mode to category product mutations (scope must match list). */
function nestedMutationParams() {
  return { nested: nested.value ? 1 : 0 }
}

/**
 * Load products list
 */
async function loadProducts() {
  try {
    await runGuarded(loading, async (signal, isCurrent) => {
      const params = {
        start: first.value,
        limit: rows.value,
        sort: sortField.value,
        dir: sortOrder.value === 1 ? 'ASC' : 'DESC',
        nested: nested.value ? 1 : 0,
      }

      // Apply filter values. Option/relation columns are JOIN-ed at runtime — backend
      // reads their filters as `filter_{fieldName}` (see CategoryProductsListService).
      // Builtin product/data filters keep the original direct-param contract.
      Object.keys(filterValues.value).forEach(key => {
        const value = filterValues.value[key]
        if (value === null || value === undefined || value === '') {
          return
        }
        const col = columns.value.find(c => c.name === key)
        if (col && (col.type === 'option' || col.type === 'relation')) {
          params[`filter_${key}`] = value
        } else {
          params[key] = value
        }
      })

      const response = await request.get(
        `/api/mgr/categories/${props.categoryId}/products`,
        params,
        { signal }
      )

      if (!isCurrent()) {
        return
      }

      if (response && response.results) {
        products.value = response.results
        totalRecords.value = response.total || 0
      } else {
        console.error('[CategoryProductsGrid] Invalid response:', response)
        products.value = []
        totalRecords.value = 0
      }
    })
  } catch (error) {
    console.error('[CategoryProductsGrid] Error loading products:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_loading_data'),
      life: 5000,
    })
  }
}

/**
 * Handle pagination (for DataTable lazy loading)
 */
// eslint-disable-next-line no-unused-vars
function onPage(event) {
  first.value = event.first
  rows.value = event.rows
  loadProducts()
}

/**
 * Handle sorting (for DataTable lazy loading)
 */
// eslint-disable-next-line no-unused-vars
function onSort(event) {
  sortField.value = event.sortField
  sortOrder.value = event.sortOrder
  loadProducts()
}

/**
 * Handle drag-drop reordering
 */
async function onDragEnd() {
  if (!canDrag.value) return

  try {
    // Calculate new menuindex values based on current order
    const items = products.value.map((product, index) => ({
      id: product.id,
      menuindex: first.value + index,
    }))

    await request.post(`/api/mgr/categories/${props.categoryId}/products/sort`, {
      items,
      ...nestedMutationParams(),
    })

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('products_reordered'),
      life: 3000,
    })
  } catch (error) {
    console.error('[CategoryProductsGrid] Error saving order:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000,
    })
    // Reload to restore original order
    await loadProducts()
  }
}

/**
 * View product on site
 */
function viewProduct(product) {
  if (product.preview_url) {
    window.open(product.preview_url, '_blank')
  }
}

/**
 * Edit product (redirect to product page)
 */
function editProduct(product) {
  MODx.loadPage('resource/update', 'id=' + product.id)
}

/**
 * Delete product
 */
async function deleteProduct(product) {
  try {
    await request.post(`/api/mgr/categories/${props.categoryId}/products/multiple`, {
      method: 'delete',
      ids: [product.id],
      ...nestedMutationParams(),
    })

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('product_deleted'),
      life: 3000,
    })

    await loadProducts()
  } catch (error) {
    console.error('[CategoryProductsGrid] Error deleting product:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_deleting_data'),
      life: 5000,
    })
  }
}

/**
 * Create new product
 */
function createProduct() {
  MODx.loadPage(
    'resource/create',
    'class_key=MiniShop3\\Model\\msProduct&parent=' + props.categoryId + '&context_key=' + MODx.ctx
  )
}

/**
 * Create new subcategory
 */
function createCategory() {
  MODx.loadPage(
    'resource/create',
    'class_key=MiniShop3\\Model\\msCategory&parent=' + props.categoryId + '&context_key=' + MODx.ctx
  )
}

/**
 * Bulk publish products
 */
async function bulkPublish() {
  const ids = selectedItems.value.map(item => item.id)
  try {
    await request.post(`/api/mgr/categories/${props.categoryId}/products/multiple`, {
      method: 'publish',
      ids,
      ...nestedMutationParams(),
    })
    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('products_published'),
      life: 3000,
    })
    clearSelection()
    await loadProducts()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000,
    })
  }
}

/**
 * Bulk unpublish products
 */
async function bulkUnpublish() {
  const ids = selectedItems.value.map(item => item.id)
  try {
    await request.post(`/api/mgr/categories/${props.categoryId}/products/multiple`, {
      method: 'unpublish',
      ids,
      ...nestedMutationParams(),
    })
    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('products_unpublished'),
      life: 3000,
    })
    clearSelection()
    await loadProducts()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000,
    })
  }
}

/**
 * Format price
 */
function formatPrice(value) {
  if (value === null || value === undefined) return '-'
  return new Intl.NumberFormat('ru-RU', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(value)
}

/**
 * Format weight
 */
function formatWeight(value) {
  if (value === null || value === undefined || value === 0) return '-'
  return new Intl.NumberFormat('ru-RU', {
    style: 'decimal',
    minimumFractionDigits: 0,
    maximumFractionDigits: 3,
  }).format(value)
}

/**
 * Render template field - replaces {field} placeholders with actual values
 */
function renderField(data, column) {
  if (column.template) {
    return column.template.replace(/\{(\w+)\}/g, (match, key) => data[key] ?? '')
  }
  return data[column.name] ?? ''
}

/**
 * Load filters configuration
 */
async function loadFiltersConfig() {
  try {
    const response = await request.get(`/api/mgr/categories/${props.categoryId}/products/filters`)
    filters.value = response.filters || response || {}
    initFilterValues()
  } catch (error) {
    console.error('[CategoryProductsGrid] Failed to load filters config:', error)
    filters.value = {}
  }
}

/**
 * Initialize filter values
 */
function initFilterValues() {
  const newValues = {}
  Object.keys(filters.value).forEach(key => {
    newValues[key] = null
  })
  filterValues.value = newValues
}

/**
 * Apply filters
 */
function applyFilters() {
  first.value = 0
  loadProducts()
}

/**
 * Clear filters
 */
function clearFilters() {
  initFilterValues()
  first.value = 0
  loadProducts()
}

/**
 * Check if any filter has value
 */
const hasActiveFilters = computed(() => {
  return Object.values(filterValues.value).some(v => v !== null && v !== '' && v !== undefined)
})

/**
 * Load grid configuration
 */
async function loadGridConfig() {
  try {
    const response = await request.get('/api/mgr/grid-config/category-products')
    columns.value = response.columns || []
    if (Array.isArray(response.editor_references)) {
      referencePathsByKey.value = Object.fromEntries(
        response.editor_references.map(r => [r.key, r.path])
      )
    } else {
      referencePathsByKey.value = {}
    }
  } catch (error) {
    console.error('[CategoryProductsGrid] Failed to load grid config:', error)
    columns.value = getDefaultColumns()
    referencePathsByKey.value = {}
  }
}

/**
 * Default columns (if API unavailable)
 */
function getDefaultColumns() {
  return [
    { name: 'id', label: 'ID', visible: true, sortable: true, width: '3.75rem', isSystem: true },
    { name: 'thumb', label: _('product_image'), visible: true, type: 'image', width: '3.75rem' },
    {
      name: 'pagetitle',
      label: _('product_pagetitle'),
      visible: true,
      sortable: true,
      filterable: true,
      minWidth: '12.5rem',
      type: 'template',
      template:
        '<span class="product-id">({id})</span> <a href="?a=resource/update&id={id}" target="_blank" class="product-link">{pagetitle}</a>',
    },
    {
      name: 'article',
      label: _('product_article'),
      visible: true,
      sortable: true,
      filterable: true,
      width: '6.25rem',
    },
    {
      name: 'price',
      label: _('product_price'),
      visible: true,
      sortable: true,
      type: 'price',
      width: '6.25rem',
    },
    {
      name: 'weight',
      label: _('product_weight'),
      visible: true,
      sortable: true,
      type: 'weight',
      width: '5rem',
    },
    {
      name: 'published',
      label: _('product_published'),
      visible: true,
      sortable: true,
      type: 'boolean',
      width: '5rem',
    },
    {
      name: 'actions',
      label: _('actions'),
      visible: true,
      isSystem: true,
      frozen: true,
      width: '8.75rem',
      type: 'actions',
      actions: [
        { name: 'view', handler: 'view', icon: 'pi-eye', label: 'view' },
        { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
        {
          name: 'delete',
          handler: 'delete',
          icon: 'pi-trash',
          label: 'delete',
          severity: 'danger',
          confirm: true,
          confirmMessage: 'product_delete_confirm_message',
        },
      ],
    },
  ]
}

/**
 * Get action configuration for column
 */
function getActionsConfig(column) {
  if (!column.actions || column.actions.length === 0) {
    return [
      { name: 'view', handler: 'view', icon: 'pi-eye', label: 'view' },
      { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
      {
        name: 'publish',
        handler: 'publish',
        icon: 'pi-check',
        iconOff: 'pi-times',
        label: 'publish',
        labelOff: 'unpublish',
        toggleField: 'published',
      },
      { name: 'duplicate', handler: 'duplicate', icon: 'pi-copy', label: 'duplicate' },
      {
        name: 'delete',
        handler: 'delete',
        icon: 'pi-trash',
        label: 'delete',
        severity: 'danger',
        confirm: true,
        confirmMessage: 'product_delete_confirm_message',
      },
    ]
  }
  return column.actions
}

/**
 * Toggle product publish status
 */
async function togglePublish(product) {
  try {
    const newStatus = product.published ? 0 : 1
    await request.post(`/api/mgr/categories/${props.categoryId}/products/${product.id}/publish`, {
      published: newStatus,
      ...nestedMutationParams(),
    })
    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: newStatus ? _('product_published') : _('product_unpublished'),
      life: 3000,
    })
    await loadProducts()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000,
    })
  }
}

/**
 * Duplicate product using MODX standard window
 */
function duplicateProduct(product) {
  const w = MODx.load({
    xtype: 'modx-window-resource-duplicate',
    resource: product.id,
    hasChildren: 0,
    listeners: {
      success: {
        fn: function () {
          // Reload resource tree
          const tree = Ext.getCmp('modx-resource-tree')
          if (tree) {
            tree.refresh()
          }
          // Reload products grid
          loadProducts()
          toast.add({
            severity: 'success',
            summary: _('success'),
            detail: _('product_duplicated'),
            life: 3000,
          })
        },
      },
    },
  })
  w.show()
}

/**
 * Handle nested checkbox change
 */
function onNestedChange() {
  first.value = 0
  loadProducts()
}

/**
 * Handle select all checkbox
 */
function onSelectAllChange() {
  if (selectAll.value) {
    selectedItems.value = [...products.value]
  } else {
    selectedItems.value = []
  }
}

/**
 * Handle header click for sorting
 */
function onHeaderClick(column) {
  if (!column.sortable) return

  if (sortField.value === column.name) {
    sortOrder.value = sortOrder.value === 1 ? -1 : 1
  } else {
    sortField.value = column.name
    sortOrder.value = 1
  }

  loadProducts()
}

/**
 * Handle previous page
 */
function onPagePrev() {
  if (first.value > 0) {
    first.value = Math.max(0, first.value - rows.value)
    loadProducts()
  }
}

/**
 * Handle next page
 */
function onPageNext() {
  if (first.value + rows.value < totalRecords.value) {
    first.value = first.value + rows.value
    loadProducts()
  }
}

/**
 * Jump to first page
 */
function onPageFirst() {
  if (first.value > 0) {
    first.value = 0
    loadProducts()
  }
}

/**
 * Jump to last page
 */
function onPageLast() {
  const lastStart = Math.max(0, Math.ceil(totalRecords.value / rows.value) - 1) * rows.value
  if (first.value !== lastStart) {
    first.value = lastStart
    loadProducts()
  }
}

/**
 * Save rows per page to localStorage
 */
function saveRowsPreference(value) {
  try {
    localStorage.setItem(ROWS_STORAGE_KEY, String(value))
  } catch {
    // ignore
  }
}

/**
 * Handle rows per page change: reset to first page, save preference, reload
 */
function onRowsChange() {
  first.value = 0
  saveRowsPreference(rows.value)
  loadProducts()
}

// Watch for category ID changes
watch(
  () => props.categoryId,
  () => {
    first.value = 0
    loadProducts()
  }
)

onMounted(async () => {
  // Initialize nested from system setting
  // Note: ms3 is a global variable (not window.ms3) because it's declared with 'let' in minishop3.js

  const ms3Config = typeof ms3 !== 'undefined' ? ms3.config : null
  nested.value = ms3Config?.show_nested_products ?? false

  // Default rows: localStorage (user choice) > config (admin default) > 20
  const configRows = ms3Config?.category_products_rows
  let saved = null
  try {
    saved = parseInt(localStorage.getItem(ROWS_STORAGE_KEY), 10)
  } catch {
    // ignore (e.g. private mode Safari)
  }
  if (saved && rowsPerPageOptions.includes(saved)) {
    rows.value = saved
  } else if (configRows && rowsPerPageOptions.includes(Number(configRows))) {
    rows.value = Number(configRows)
  } else {
    rows.value = 20
  }

  await Promise.all([loadGridConfig(), loadFiltersConfig()])
  await loadProducts()
})
</script>

<template>
  <div class="category-products-grid">
    <Toast :group="UI_GROUP" />
    <ConfirmDialog :group="UI_GROUP" append-to="self" />

    <Card>
      <template #title>
        <div class="grid-header">
          <div class="grid-header-left">
            <span>{{ _('category_products') }}</span>
            <Button
              :label="_('product_create')"
              icon="pi pi-plus"
              severity="success"
              size="small"
              @click="createProduct"
            />
            <Button
              :label="_('category_create')"
              icon="pi pi-folder-plus"
              severity="secondary"
              size="small"
              @click="createCategory"
            />
          </div>
          <div class="grid-header-right">
            <div class="nested-checkbox">
              <Checkbox
                v-model="nested"
                input-id="nested"
                :binary="true"
                @change="onNestedChange"
              />
              <label for="nested">{{ _('category_show_nested') }}</label>
            </div>
          </div>
        </div>
      </template>

      <template #content>
        <!-- Filters form -->
        <div
          v-if="sortedFilters.length > 0"
          class="filters-form mb-3 p-3 surface-ground"
          style="border-radius: 0.375rem"
        >
          <div class="filters-row">
            <template v-for="filter in sortedFilters" :key="filter.key">
              <!-- Text input filter -->
              <div
                v-if="filter.type === 'text'"
                class="filter-item"
                :style="{ width: filter.width || '12.5rem' }"
              >
                <label :for="`filter-${filter.key}`">{{ _(filter.label) }}</label>
                <InputText
                  :id="`filter-${filter.key}`"
                  v-model="filterValues[filter.key]"
                  :placeholder="_(filter.placeholder || filter.label)"
                  class="w-full"
                  @keyup.enter="applyFilters"
                />
              </div>

              <!-- Select filter -->
              <div
                v-else-if="filter.type === 'select'"
                class="filter-item"
                :style="{ width: filter.width || '9.375rem' }"
              >
                <label :for="`filter-${filter.key}`">{{ _(filter.label) }}</label>
                <Select
                  :id="`filter-${filter.key}`"
                  v-model="filterValues[filter.key]"
                  :options="filter.options || []"
                  option-label="label"
                  option-value="value"
                  :placeholder="_(filter.placeholder || 'all')"
                  :show-clear="true"
                  class="w-full"
                  @change="applyFilters"
                />
              </div>
            </template>
          </div>

          <!-- Filter buttons -->
          <div class="filter-buttons">
            <Button
              :label="_('apply_filters')"
              icon="pi pi-filter"
              size="small"
              @click="applyFilters"
            />
            <Button
              v-if="hasActiveFilters"
              :label="_('clear_filters')"
              icon="pi pi-filter-slash"
              severity="secondary"
              size="small"
              @click="clearFilters"
            />
          </div>
        </div>

        <!-- Bulk actions toolbar -->
        <div v-if="hasSelection" class="bulk-actions-bar mb-3">
          <div class="bulk-info">
            <i class="pi pi-check-square"></i>
            <span>{{ _('selected_count').replace('{count}', selectionCount) }}</span>
          </div>
          <div class="bulk-buttons">
            <Button
              :label="_('clear_selection')"
              icon="pi pi-times"
              severity="secondary"
              size="small"
              text
              @click="clearSelection"
            />
            <Button
              :label="_('publish')"
              icon="pi pi-check"
              severity="success"
              size="small"
              @click="bulkPublish"
            />
            <Button
              :label="_('unpublish')"
              icon="pi pi-times-circle"
              severity="secondary"
              size="small"
              @click="bulkUnpublish"
            />
            <Button
              :label="_('delete_selected')"
              icon="pi pi-trash"
              severity="danger"
              size="small"
              :loading="bulkProcessing"
              @click="confirmBulkDelete"
            />
          </div>
        </div>

        <!-- Drag info -->
        <div v-if="canDrag" class="drag-info mb-2">
          <i class="pi pi-arrows-v"></i>
          <span>{{ _('drag_to_reorder') }}</span>
        </div>

        <!-- Table with drag-drop -->
        <div class="p-datatable p-component p-datatable-striped">
          <div class="p-datatable-wrapper">
            <table class="p-datatable-table">
              <thead class="p-datatable-thead">
                <tr>
                  <th v-if="canDrag" style="width: 3rem"></th>
                  <th style="width: 3rem">
                    <Checkbox v-model="selectAll" :binary="true" @change="onSelectAllChange" />
                  </th>
                  <th
                    v-for="column in columns.filter(c => c.visible)"
                    :key="column.name"
                    :style="{ width: column.width, minWidth: column.minWidth }"
                    :class="{ 'sortable-header': column.sortable }"
                    @click="column.sortable && onHeaderClick(column)"
                  >
                    {{ column.label }}
                    <i
                      v-if="column.sortable && sortField === column.name"
                      :class="
                        sortOrder === 1 ? 'pi pi-sort-amount-up-alt' : 'pi pi-sort-amount-down'
                      "
                      class="sort-icon"
                    ></i>
                  </th>
                </tr>
              </thead>
              <draggable
                v-model="products"
                tag="tbody"
                class="p-datatable-tbody"
                :handle="canDrag ? '.drag-handle' : null"
                :disabled="!canDrag"
                item-key="id"
                :animation="200"
                ghost-class="ghost-row"
                @end="onDragEnd"
              >
                <template #item="{ element: product, index }">
                  <tr :class="{ 'p-row-odd': index % 2 === 1 }">
                    <td v-if="canDrag" class="drag-handle-cell">
                      <i class="pi pi-bars drag-handle"></i>
                    </td>
                    <td>
                      <Checkbox v-model="selectedItems" :value="product" :binary="false" />
                    </td>
                    <template v-for="column in columns.filter(c => c.visible)" :key="column.name">
                      <!-- Actions column -->
                      <td v-if="column.type === 'actions'" :style="{ width: column.width }">
                        <ActionsColumn
                          :data="product"
                          :actions="getActionsConfig(column)"
                          grid-id="category-products"
                          :ui-group="UI_GROUP"
                          @view="viewProduct"
                          @edit="editProduct"
                          @delete="deleteProduct"
                          @publish="togglePublish"
                          @duplicate="duplicateProduct"
                          @refresh="loadProducts"
                        />
                      </td>

                      <!-- Editable column: inline edit mode -->
                      <td
                        v-else-if="column.editable && isEditingCell(product, column)"
                        :style="{ width: column.width, minWidth: column.minWidth }"
                        :class="['inline-edit-cell', { 'inline-edit-saving': inlineEditSaving }]"
                      >
                        <Checkbox
                          v-if="isBooleanColumn(column)"
                          ref="inlineEditInputRef"
                          :model-value="!!inlineEditValue"
                          :binary="true"
                          :disabled="inlineEditSaving"
                          @update:model-value="inlineEditValue = $event ? 1 : 0"
                          @change="saveInlineEdit(product, column)"
                        />
                        <InputText
                          v-else-if="normalizeGridColumnEditorType(column.editor_type) === GridColumnEditorType.TEXT"
                          ref="inlineEditInputRef"
                          v-model="inlineEditValue"
                          class="w-full"
                          :disabled="inlineEditSaving"
                          @blur="saveInlineEdit(product, column)"
                          @keydown.enter.prevent="$event.target.blur()"
                          @keydown.escape="cancelInlineEdit"
                        />
                        <div
                          v-else-if="isSelectLikeEditorType(column.editor_type)"
                          class="inline-edit-input-wrapper w-full"
                          @keydown.enter.capture.prevent="$event.target?.blur?.()"
                          @keydown.escape.capture.prevent="cancelInlineEdit"
                        >
                          <Select
                            ref="inlineEditInputRef"
                            v-model="inlineEditValue"
                            :options="selectOptionsForColumn(column)"
                            option-label="label"
                            option-value="value"
                            class="w-full"
                            :show-clear="selectUsesClear(column)"
                            :disabled="inlineEditSaving"
                            @change="saveInlineEdit(product, column)"
                          />
                        </div>
                        <div
                          v-else
                          class="inline-edit-input-wrapper w-full"
                          @keydown.enter.capture.prevent="$event.target?.blur?.()"
                          @keydown.escape.capture.prevent="cancelInlineEdit"
                        >
                          <InputNumber
                            ref="inlineEditInputRef"
                            v-model="inlineEditValue"
                            class="w-full"
                            :min-fraction-digits="0"
                            :max-fraction-digits="4"
                            :disabled="inlineEditSaving"
                            @blur="saveInlineEdit(product, column)"
                          />
                        </div>
                      </td>

                      <!-- Image column -->
                      <td v-else-if="column.type === 'image'" :style="{ width: column.width }">
                        <img
                          :src="product[column.name] || defaultThumb"
                          :alt="product.pagetitle"
                          class="product-thumb"
                        />
                      </td>

                      <!-- Boolean column -->
                      <td
                        v-else-if="column.type === 'boolean'"
                        :style="{ width: column.width }"
                        :class="{ 'editable-cell': column.editable }"
                        @dblclick="column.editable && startInlineEdit(product, column)"
                      >
                        <Tag
                          :value="product[column.name] ? _('yes') : _('no')"
                          :severity="product[column.name] ? 'success' : 'secondary'"
                        />
                      </td>

                      <!-- Price column -->
                      <td
                        v-else-if="column.type === 'price'"
                        :style="{ width: column.width }"
                        :class="{ 'editable-cell': column.editable }"
                        @dblclick="column.editable && startInlineEdit(product, column)"
                      >
                        {{ formatPrice(product[column.name]) }}
                      </td>

                      <!-- Weight column -->
                      <td
                        v-else-if="column.type === 'weight'"
                        :style="{ width: column.width }"
                        :class="{ 'editable-cell': column.editable }"
                        @dblclick="column.editable && startInlineEdit(product, column)"
                      >
                        {{ formatWeight(product[column.name]) }}
                      </td>

                      <!-- Template column (renders HTML) -->
                      <td
                        v-else-if="column.type === 'template'"
                        :style="{ width: column.width, minWidth: column.minWidth }"
                        :class="{ 'editable-cell': column.editable }"
                        @dblclick="column.editable && startInlineEdit(product, column)"
                      >
                        <div v-if="nested && product.category_name" class="nested-product">
                          <span v-html="renderField(product, column)"></span>
                          <div class="product-category">{{ product.category_name }}</div>
                        </div>
                        <span v-else v-html="renderField(product, column)"></span>
                      </td>

                      <!-- Regular columns -->
                      <td
                        v-else
                        :style="{ width: column.width, minWidth: column.minWidth }"
                        :class="{ 'editable-cell': column.editable }"
                        @dblclick="column.editable && startInlineEdit(product, column)"
                      >
                        {{ product[column.name] }}
                      </td>
                    </template>
                  </tr>
                </template>
              </draggable>
            </table>
          </div>

          <!-- Loading overlay -->
          <div v-if="loading" class="loading-overlay">
            <i class="pi pi-spinner pi-spin"></i>
          </div>

          <!-- Pagination -->
          <div class="p-paginator p-component">
            <span class="p-paginator-current">
              {{ _('showing') }} {{ first + 1 }}-{{ Math.min(first + rows, totalRecords) }}
              {{ _('of') }} {{ totalRecords }}
            </span>
            <Button
              icon="pi pi-angle-double-left"
              :disabled="first === 0"
              text
              :title="_('first_page')"
              @click="onPageFirst"
            />
            <Button icon="pi pi-angle-left" :disabled="first === 0" text @click="onPagePrev" />
            <Button
              icon="pi pi-angle-right"
              :disabled="first + rows >= totalRecords"
              text
              @click="onPageNext"
            />
            <Button
              icon="pi pi-angle-double-right"
              :disabled="first + rows >= totalRecords || totalRecords === 0"
              text
              :title="_('last_page')"
              @click="onPageLast"
            />
            <label class="rows-per-page-label">{{ _('rows_per_page') }}</label>
            <Select
              v-model="rows"
              :options="rowsPerPageOptions"
              class="rows-per-page-select"
              style="min-width: 5rem"
              @change="onRowsChange"
            />
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.category-products-grid {
  padding: 0.625rem;
}

.editable-cell {
  cursor: text;
}

.editable-cell:hover {
  background: var(--ms3-bg-muted, rgba(0 0 0 / 0.04));
}

.inline-edit-cell :deep(input) {
  width: 100%;
  min-width: 0;
}

.inline-edit-saving {
  opacity: 0.8;
}

.inline-edit-saving :deep(input) {
  cursor: wait;
}

.grid-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}

.grid-header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.grid-header-right {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.nested-checkbox {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.nested-checkbox label {
  cursor: pointer;
  font-size: 0.9rem;
  color: var(--ms3-text-muted);
}

.w-full {
  width: 100%;
}

.filters-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 1rem;
}

.filter-item {
  display: flex;
  flex-direction: column;
  min-width: 7.5rem;
}

.filter-item label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  color: var(--ms3-text-muted);
}

.filter-buttons {
  display: flex;
  gap: 0.5rem;
}

/* Bulk actions toolbar */
.bulk-actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: var(--ms3-bg-warning);
  border: var(--ms3-border-width) solid var(--ms3-border-warning);
  border-radius: 0.375rem;
}

.bulk-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  color: var(--ms3-text-warning);
}

.bulk-info i {
  font-size: 1.1rem;
}

.bulk-buttons {
  display: flex;
  gap: 0.5rem;
}

/* Drag info */
.drag-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background: var(--ms3-bg-info);
  border-radius: 0.25rem;
  font-size: 0.85rem;
  color: var(--ms3-text-info-dark);
}

/* Drag and drop styles */
.drag-handle-cell {
  text-align: center;
  vertical-align: middle;
  padding: 0.5rem;
}

.drag-handle {
  cursor: grab;
  color: var(--ms3-text-light);
  font-size: 1.2rem;
  padding: 0.5rem;
  user-select: none;
}

.drag-handle:hover {
  color: var(--ms3-text-muted);
}

.drag-handle:active {
  cursor: grabbing;
}

:deep(.ghost-row) {
  opacity: 0.5;
  background: var(--ms3-bg-muted);
}

:deep(.sortable-drag) {
  opacity: 0.9;
  background: var(--ms3-bg-surface);
  box-shadow: var(--ms3-shadow-dropdown);
}

/* Table styles */
.p-datatable {
  position: relative;
}

.p-datatable-wrapper {
  overflow: auto;
}

.p-datatable-table {
  width: 100%;
  border-collapse: collapse;
}

.p-datatable-thead th {
  text-align: left;
  padding: 0.75rem 1rem;
  border-bottom: var(--ms3-border-width) solid var(--ms3-border-color-alt);
  background: var(--ms3-bg-muted);
  font-weight: 600;
}

.sortable-header {
  cursor: pointer;
}

.sortable-header:hover {
  background: var(--ms3-bg-neutral);
}

.sort-icon {
  margin-left: 0.5rem;
  font-size: 0.8rem;
  color: var(--ms3-text-muted);
}

.p-datatable-tbody td {
  padding: 0.75rem 1rem;
  border-bottom: var(--ms3-border-width) solid var(--ms3-border-color-alt);
  vertical-align: middle;
}

.p-datatable-tbody tr:hover {
  background: var(--ms3-bg-slate-alt);
}

.p-row-odd {
  background: var(--ms3-bg-slate);
}

.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: var(--ms3-bg-overlay);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}

/* Pagination */
.p-paginator {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  padding: 0.75rem 1rem;
  border-top: var(--ms3-border-width) solid var(--ms3-border-color-alt);
  gap: 0.5rem;
}

.p-paginator-current {
  color: var(--ms3-text-muted);
  font-size: 0.9rem;
  margin-right: auto;
}

.rows-per-page-label {
  font-size: 0.9rem;
  color: var(--ms3-text-muted);
  margin-right: 0.5rem;
}

/* Product thumbnail */
.product-thumb {
  width: 3.125rem;
  height: 3.125rem;
  object-fit: contain;
  border-radius: 0.25rem;
}

.no-image {
  color: var(--ms3-text-light);
}

/* Product title */
.product-id {
  color: var(--ms3-text-light);
  font-size: 0.85rem;
  margin-right: 0.25rem;
}

:deep(.product-link) {
  color: var(--ms3-accent-primary);
  text-decoration: none;
}

:deep(.product-link:hover) {
  text-decoration: underline;
}

/* Nested product display */
.nested-product {
  line-height: 1.4;
}

.product-category {
  font-size: 0.8rem;
  color: var(--ms3-text-muted);
}

.product-category a {
  color: var(--ms3-text-muted);
  text-decoration: none;
}

.product-category a:hover {
  color: var(--ms3-accent-primary);
}
</style>
