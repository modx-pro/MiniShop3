<script setup>
import { onMounted, ref, computed, defineProps, watch } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import Tag from 'primevue/tag'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'
import draggable from 'vuedraggable'
import request from '../request.js'
import { useLexicon } from '@vuetools/useLexicon'
import { useSelection } from '../composables/useSelection.js'
import ActionsColumn from './ActionsColumn.vue'

const props = defineProps({
  categoryId: {
    type: Number,
    required: true
  }
})

const toast = useToast()
const confirm = useConfirm()
const { _ } = useLexicon()

// Bulk selection
const {
  selectedItems,
  hasSelection,
  selectionCount,
  processing: bulkProcessing,
  clearSelection,
  confirmBulkDelete
} = useSelection({
  entityName: 'product',
  deleteBulk: async (ids) => {
    await request.post(`/api/mgr/categories/${props.categoryId}/products/multiple`, {
      method: 'delete',
      ids
    })
  },
  onSuccess: () => loadProducts(),
  getItemName: (item) => item.pagetitle || `#${item.id}`
})

const columns = ref([])
const filters = ref({})
const loading = ref(false)
const products = ref([])
const totalRecords = ref(0)
const first = ref(0)
const rows = ref(20)
const filterValues = ref({})
const nested = ref(false)
const dragEnabled = ref(true)
const sortField = ref('menuindex')
const sortOrder = ref(1)
const selectAll = ref(false)

// Default thumbnail from system settings
const defaultThumb = window.ms3?.config?.default_thumb || '/assets/components/minishop3/img/mgr/ms3_small.png'

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

/**
 * Load products list
 */
async function loadProducts() {
  loading.value = true

  try {
    const params = {
      start: first.value,
      limit: rows.value,
      sort: sortField.value,
      dir: sortOrder.value === 1 ? 'ASC' : 'DESC',
      nested: nested.value ? 1 : 0
    }

    // Apply filter values
    Object.keys(filterValues.value).forEach(key => {
      const value = filterValues.value[key]
      if (value !== null && value !== undefined && value !== '') {
        params[key] = value
      }
    })

    const response = await request.get(`/api/mgr/categories/${props.categoryId}/products`, params)

    if (response && response.results) {
      products.value = response.results
      totalRecords.value = response.total || 0
    } else {
      console.error('[CategoryProductsGrid] Invalid response:', response)
      products.value = []
      totalRecords.value = 0
    }
  } catch (error) {
    console.error('[CategoryProductsGrid] Error loading products:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_loading_data'),
      life: 5000
    })
  } finally {
    loading.value = false
  }
}

/**
 * Handle pagination
 */
function onPage(event) {
  first.value = event.first
  rows.value = event.rows
  loadProducts()
}

/**
 * Handle sorting
 */
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
      menuindex: first.value + index
    }))

    await request.post(`/api/mgr/categories/${props.categoryId}/products/sort`, { items })

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('products_reordered'),
      life: 3000
    })
  } catch (error) {
    console.error('[CategoryProductsGrid] Error saving order:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000
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
      ids: [product.id]
    })

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('product_deleted'),
      life: 3000
    })

    await loadProducts()
  } catch (error) {
    console.error('[CategoryProductsGrid] Error deleting product:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_deleting_data'),
      life: 5000
    })
  }
}

/**
 * Create new product
 */
function createProduct() {
  MODx.loadPage('resource/create', 'class_key=MiniShop3\\Model\\msProduct&parent=' + props.categoryId + '&context_key=' + MODx.ctx)
}

/**
 * Create new subcategory
 */
function createCategory() {
  MODx.loadPage('resource/create', 'class_key=MiniShop3\\Model\\msCategory&parent=' + props.categoryId + '&context_key=' + MODx.ctx)
}

/**
 * Bulk publish products
 */
async function bulkPublish() {
  const ids = selectedItems.value.map(item => item.id)
  try {
    await request.post(`/api/mgr/categories/${props.categoryId}/products/multiple`, {
      method: 'publish',
      ids
    })
    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('products_published'),
      life: 3000
    })
    clearSelection()
    await loadProducts()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000
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
      ids
    })
    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('products_unpublished'),
      life: 3000
    })
    clearSelection()
    await loadProducts()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000
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
    maximumFractionDigits: 2
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
    maximumFractionDigits: 3
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
  } catch (error) {
    console.error('[CategoryProductsGrid] Failed to load grid config:', error)
    columns.value = getDefaultColumns()
  }
}

/**
 * Default columns (if API unavailable)
 */
function getDefaultColumns() {
  return [
    { name: 'id', label: 'ID', visible: true, sortable: true, width: '60px', isSystem: true },
    { name: 'thumb', label: _('product_image'), visible: true, type: 'image', width: '60px' },
    { name: 'pagetitle', label: _('product_pagetitle'), visible: true, sortable: true, filterable: true, minWidth: '200px', type: 'template', template: '<span class="product-id">({id})</span> <a href="?a=resource/update&id={id}" target="_blank" class="product-link">{pagetitle}</a>' },
    { name: 'article', label: _('product_article'), visible: true, sortable: true, filterable: true, width: '100px' },
    { name: 'price', label: _('product_price'), visible: true, sortable: true, type: 'price', width: '100px' },
    { name: 'weight', label: _('product_weight'), visible: true, sortable: true, type: 'weight', width: '80px' },
    { name: 'published', label: _('product_published'), visible: true, sortable: true, type: 'boolean', width: '80px' },
    {
      name: 'actions',
      label: _('actions'),
      visible: true,
      isSystem: true,
      frozen: true,
      width: '140px',
      type: 'actions',
      actions: [
        { name: 'view', handler: 'view', icon: 'pi-eye', label: 'view' },
        { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
        { name: 'delete', handler: 'delete', icon: 'pi-trash', label: 'delete', severity: 'danger', confirm: true, confirmMessage: 'product_delete_confirm_message' }
      ]
    }
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
      { name: 'publish', handler: 'publish', icon: 'pi-check', iconOff: 'pi-times', label: 'publish', labelOff: 'unpublish', toggleField: 'published' },
      { name: 'duplicate', handler: 'duplicate', icon: 'pi-copy', label: 'duplicate' },
      { name: 'delete', handler: 'delete', icon: 'pi-trash', label: 'delete', severity: 'danger', confirm: true, confirmMessage: 'product_delete_confirm_message' }
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
      published: newStatus
    })
    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: newStatus ? _('product_published') : _('product_unpublished'),
      life: 3000
    })
    await loadProducts()
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000
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
            life: 3000
          })
        }
      }
    }
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

// Watch for category ID changes
watch(() => props.categoryId, () => {
  first.value = 0
  loadProducts()
})

onMounted(async () => {
  await Promise.all([
    loadGridConfig(),
    loadFiltersConfig()
  ])
  await loadProducts()
})
</script>

<template>
  <div class="category-products-grid">
    <Toast />
    <ConfirmDialog appendTo="self" />

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
                inputId="nested"
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
        <div v-if="sortedFilters.length > 0" class="filters-form mb-3 p-3 surface-ground" style="border-radius: 6px;">
          <div class="filters-row">
            <template v-for="filter in sortedFilters" :key="filter.key">
              <!-- Text input filter -->
              <div v-if="filter.type === 'text'" class="filter-item" :style="{ width: filter.width || '200px' }">
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
              <div v-else-if="filter.type === 'select'" class="filter-item" :style="{ width: filter.width || '150px' }">
                <label :for="`filter-${filter.key}`">{{ _(filter.label) }}</label>
                <Select
                  :id="`filter-${filter.key}`"
                  v-model="filterValues[filter.key]"
                  :options="filter.options || []"
                  optionLabel="label"
                  optionValue="value"
                  :placeholder="_(filter.placeholder || 'all')"
                  :showClear="true"
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
                    <Checkbox
                      v-model="selectAll"
                      :binary="true"
                      @change="onSelectAllChange"
                    />
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
                      :class="sortOrder === 1 ? 'pi pi-sort-amount-up-alt' : 'pi pi-sort-amount-down'"
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
                @end="onDragEnd"
                :animation="200"
                ghost-class="ghost-row"
              >
                <template #item="{ element: product, index }">
                  <tr :class="{ 'p-row-odd': index % 2 === 1 }">
                    <td v-if="canDrag" class="drag-handle-cell">
                      <i class="pi pi-bars drag-handle"></i>
                    </td>
                    <td>
                      <Checkbox
                        v-model="selectedItems"
                        :value="product"
                        :binary="false"
                      />
                    </td>
                    <template v-for="column in columns.filter(c => c.visible)" :key="column.name">
                      <!-- Actions column -->
                      <td v-if="column.type === 'actions'" :style="{ width: column.width }">
                        <ActionsColumn
                          :data="product"
                          :actions="getActionsConfig(column)"
                          grid-id="category-products"
                          @view="viewProduct"
                          @edit="editProduct"
                          @delete="deleteProduct"
                          @publish="togglePublish"
                          @duplicate="duplicateProduct"
                          @refresh="loadProducts"
                        />
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
                      <td v-else-if="column.type === 'boolean'" :style="{ width: column.width }">
                        <Tag
                          :value="product[column.name] ? _('yes') : _('no')"
                          :severity="product[column.name] ? 'success' : 'secondary'"
                        />
                      </td>

                      <!-- Price column -->
                      <td v-else-if="column.type === 'price'" :style="{ width: column.width }">
                        {{ formatPrice(product[column.name]) }}
                      </td>

                      <!-- Weight column -->
                      <td v-else-if="column.type === 'weight'" :style="{ width: column.width }">
                        {{ formatWeight(product[column.name]) }}
                      </td>

                      <!-- Template column (renders HTML) -->
                      <td v-else-if="column.type === 'template'" :style="{ width: column.width, minWidth: column.minWidth }">
                        <div v-if="nested && product.category_name" class="nested-product">
                          <span v-html="renderField(product, column)"></span>
                          <div class="product-category">{{ product.category_name }}</div>
                        </div>
                        <span v-else v-html="renderField(product, column)"></span>
                      </td>

                      <!-- Regular columns -->
                      <td v-else :style="{ width: column.width, minWidth: column.minWidth }">
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
              {{ _('showing') }} {{ first + 1 }}-{{ Math.min(first + rows, totalRecords) }} {{ _('of') }} {{ totalRecords }}
            </span>
            <Button
              icon="pi pi-angle-left"
              :disabled="first === 0"
              text
              @click="onPagePrev"
            />
            <Button
              icon="pi pi-angle-right"
              :disabled="first + rows >= totalRecords"
              text
              @click="onPageNext"
            />
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>

<style scoped>
.category-products-grid {
  padding: 10px;
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
  color: #64748b;
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
  min-width: 120px;
}

.filter-item label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
  color: #64748b;
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
  background: #fef3c7;
  border: 1px solid #fbbf24;
  border-radius: 6px;
}

.bulk-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 500;
  color: #92400e;
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
  background: #e0f2fe;
  border-radius: 4px;
  font-size: 0.85rem;
  color: #0369a1;
}

/* Drag and drop styles */
.drag-handle-cell {
  text-align: center;
  vertical-align: middle;
  padding: 0.5rem;
}

.drag-handle {
  cursor: grab;
  color: #94a3b8;
  font-size: 1.2rem;
  padding: 0.5rem;
  user-select: none;
}

.drag-handle:hover {
  color: #64748b;
}

.drag-handle:active {
  cursor: grabbing;
}

:deep(.ghost-row) {
  opacity: 0.5;
  background: #f8f9fa;
}

:deep(.sortable-drag) {
  opacity: 0.9;
  background: #fff;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
  border-bottom: 1px solid #dee2e6;
  background: #f8f9fa;
  font-weight: 600;
}

.sortable-header {
  cursor: pointer;
}

.sortable-header:hover {
  background: #e9ecef;
}

.sort-icon {
  margin-left: 0.5rem;
  font-size: 0.8rem;
  color: #6c757d;
}

.p-datatable-tbody td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #dee2e6;
  vertical-align: middle;
}

.p-datatable-tbody tr:hover {
  background: #f1f5f9;
}

.p-row-odd {
  background: #f8fafc;
}

.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255,255,255,0.7);
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
  border-top: 1px solid #dee2e6;
  gap: 0.5rem;
}

.p-paginator-current {
  color: #6c757d;
  font-size: 0.9rem;
  margin-right: auto;
}

/* Product thumbnail */
.product-thumb {
  width: 50px;
  height: 50px;
  object-fit: contain;
  border-radius: 4px;
}

.no-image {
  color: #94a3b8;
}

/* Product title */
.product-id {
  color: #94a3b8;
  font-size: 0.85rem;
  margin-right: 0.25rem;
}

:deep(.product-link) {
  color: #3b82f6;
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
  color: #64748b;
}

.product-category a {
  color: #64748b;
  text-decoration: none;
}

.product-category a:hover {
  color: #3b82f6;
}
</style>
