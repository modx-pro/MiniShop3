<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Checkbox from 'primevue/checkbox'
import Column from 'primevue/column'
import ConfirmDialog from 'primevue/confirmdialog'
import DataTable from 'primevue/datatable'
import Dialog from 'primevue/dialog'
import InputGroup from 'primevue/inputgroup'
import InputGroupAddon from 'primevue/inputgroupaddon'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Toast from 'primevue/toast'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, onMounted, ref } from 'vue'

import { useSelection } from '../composables/useSelection.js'
import request from '../request.js'
import ActionsColumn from './ActionsColumn.vue'

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
  confirmBulkDelete,
} = useSelection({
  entityName: 'customer',
  deleteBulk: async ids => {
    await request.delete('/api/mgr/customers/bulk', { ids })
  },
  onSuccess: () => loadCustomers(),
  getItemName: item => getCustomerDisplayName(item),
})

const columns = ref([])
const directFilterKeys = ref(new Set())
const loading = ref(false)
const customers = ref([])
const totalRecords = ref(0)
const first = ref(0)
const rows = ref(20)
const sortField = ref('id')
const sortOrder = ref(-1)
const filterValues = ref({})
const filterableColumns = computed(() => columns.value.filter(col => col.filterable && col.visible))
const searchQuery = ref('')
const editDialogVisible = ref(false)
const editingCustomer = ref(null)
const saving = ref(false)
const newPassword = ref('')
const showPassword = ref(false)
const addressesDialogVisible = ref(false)
const currentCustomerForAddresses = ref(null)
const addresses = ref([])
const addressesLoading = ref(false)
const editingAddress = ref(null)
const addressFormVisible = ref(false)
const savingAddress = ref(false)

/** Delete row action: aligned with `20251127000002_seed_customers_grid_config` (lexicon keys). */
const CUSTOMER_GRID_DELETE_ACTION = {
  name: 'delete',
  handler: 'delete',
  icon: 'pi-trash',
  label: 'delete',
  severity: 'danger',
  confirm: true,
  confirmTitle: 'customer_delete_confirm_title',
  confirmMessage: 'customer_delete_confirm_message',
  confirmAccept: 'delete',
}

function addFilterParam(params, key, value) {
  if (directFilterKeys.value.has(key)) {
    params[key] = value
    return
  }

  params[`filter_${key}`] = value
}

/**
 * Load customers list
 */
async function loadCustomers() {
  loading.value = true

  try {
    const params = {
      start: first.value,
      limit: rows.value,
      sort: sortField.value,
      dir: sortOrder.value === 1 ? 'ASC' : 'DESC',
    }

    if (searchQuery.value) {
      params.query = searchQuery.value
    }

    Object.keys(filterValues.value).forEach(key => {
      const value = filterValues.value[key]
      if (value !== null && value !== undefined && value !== '') {
        addFilterParam(params, key, value)
      }
    })

    const response = await request.get('/api/mgr/customers', params)

    if (response && response.results) {
      customers.value = response.results
      totalRecords.value = response.total || 0
    } else {
      console.error('[CustomersGrid] Invalid response:', response)
      customers.value = []
      totalRecords.value = 0
    }
  } catch (error) {
    console.error('[CustomersGrid] Error loading customers:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_loading_data'),
      life: 5000,
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
  loadCustomers()
}

/**
 * Handle sort
 */
function onSort(event) {
  sortField.value = event.sortField || 'id'
  sortOrder.value = event.sortOrder ?? -1
  first.value = 0
  loadCustomers()
}

/**
 * Handle search
 */
function onSearch() {
  first.value = 0
  loadCustomers()
}

/**
 * Open edit modal
 */
function editCustomer(customer) {
  editingCustomer.value = { ...customer }
  newPassword.value = ''
  showPassword.value = false
  editDialogVisible.value = true
}

/**
 * Generate random password
 */
function generatePassword() {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'
  let password = ''
  for (let i = 0; i < 12; i++) {
    password += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  newPassword.value = password
  showPassword.value = true
}

/**
 * Save customer changes
 */
async function saveCustomer() {
  if (!editingCustomer.value) return

  saving.value = true
  const queryBeforeSave = searchQuery.value

  try {
    const data = { ...editingCustomer.value }

    if (newPassword.value) {
      data.password = newPassword.value
    }

    await request.put(`/api/mgr/customers/${editingCustomer.value.id}`, data)

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('customer_updated'),
      life: 3000,
    })

    editDialogVisible.value = false
    await nextTick()
    searchQuery.value = queryBeforeSave
    await loadCustomers()
  } catch (error) {
    console.error('[CustomersGrid] Error saving customer:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000,
    })
  } finally {
    saving.value = false
  }
}

/**
 * Delete customer (called after confirmation in ActionsColumn / useActions)
 */
async function deleteCustomer(customer) {
  try {
    await request.delete(`/api/mgr/customers/${customer.id}`)

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('customer_deleted'),
      life: 3000,
    })

    await loadCustomers()
  } catch (error) {
    console.error('[CustomersGrid] Error deleting customer:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_deleting_data'),
      life: 5000,
    })
  }
}

/**
 * Get customer display name (first + last name, or email, or ID)
 */
function getCustomerDisplayName(customer) {
  const fullName = [customer.first_name, customer.last_name].filter(Boolean).join(' ').trim()
  if (fullName) {
    return fullName
  }
  if (customer.email) {
    return customer.email
  }
  return `#${customer.id}`
}

/**
 * Open customer addresses dialog
 */
async function openAddresses(customer) {
  currentCustomerForAddresses.value = customer
  addressesDialogVisible.value = true
  editingAddress.value = null
  addressFormVisible.value = false
  await loadAddresses(customer.id)
}

/**
 * Load customer addresses
 */
async function loadAddresses(customerId) {
  addressesLoading.value = true

  try {
    const response = await request.get(`/api/mgr/customers/${customerId}/addresses`)
    addresses.value = response.results || []
  } catch (error) {
    console.error('[CustomersGrid] Error loading addresses:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_loading_data'),
      life: 5000,
    })
    addresses.value = []
  } finally {
    addressesLoading.value = false
  }
}

/**
 * Open create new address form
 */
function createAddress() {
  editingAddress.value = {
    name: '',
    country: '',
    index: '',
    region: '',
    city: '',
    metro: '',
    street: '',
    building: '',
    entrance: '',
    floor: '',
    room: '',
    comment: '',
    active: true,
  }
  addressFormVisible.value = true
}

/**
 * Open edit address form
 */
function editAddress(address) {
  editingAddress.value = { ...address }
  addressFormVisible.value = true
}

/**
 * Save address (create or update)
 */
async function saveAddress() {
  if (!editingAddress.value || !currentCustomerForAddresses.value) return

  savingAddress.value = true

  try {
    const customerId = currentCustomerForAddresses.value.id

    if (editingAddress.value.id) {
      await request.put(
        `/api/mgr/customers/${customerId}/addresses/${editingAddress.value.id}`,
        editingAddress.value
      )
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('address_updated'),
        life: 3000,
      })
    } else {
      await request.post(`/api/mgr/customers/${customerId}/addresses`, editingAddress.value)
      toast.add({
        severity: 'success',
        summary: _('success'),
        detail: _('address_created'),
        life: 3000,
      })
    }

    addressFormVisible.value = false
    editingAddress.value = null
    await loadAddresses(customerId)
  } catch (error) {
    console.error('[CustomersGrid] Error saving address:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000,
    })
  } finally {
    savingAddress.value = false
  }
}

/**
 * Delete address
 */
function deleteAddress(address) {
  confirm.require({
    message: _('address_delete_confirm_message'),
    header: _('address_delete_confirm_title'),
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: _('delete'),
    rejectLabel: _('cancel'),
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        const customerId = currentCustomerForAddresses.value.id
        await request.delete(`/api/mgr/customers/${customerId}/addresses/${address.id}`)

        toast.add({
          severity: 'success',
          summary: _('success'),
          detail: _('address_deleted'),
          life: 3000,
        })

        await loadAddresses(customerId)
      } catch (error) {
        console.error('[CustomersGrid] Error deleting address:', error)
        toast.add({
          severity: 'error',
          summary: _('error'),
          detail: error.message || _('error_deleting_data'),
          life: 5000,
        })
      }
    },
  })
}

/**
 * Cancel address editing
 */
function cancelAddressEdit() {
  editingAddress.value = null
  addressFormVisible.value = false
}

/**
 * Format date
 */
function formatDate(dateString) {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleString('ru-RU', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

/**
 * Format email status
 */
// eslint-disable-next-line no-unused-vars
function formatEmailStatus(customer) {
  return customer.email_verified_at ? _('verified') : _('not_verified')
}

/**
 * Initialize column filters
 */
function initFilters() {
  const newFilters = {}

  columns.value.forEach(column => {
    if (column.filterable) {
      newFilters[column.name] = ''
    }
  })

  filterValues.value = newFilters
}

/**
 * Apply filters
 */
function applyFilters() {
  first.value = 0
  loadCustomers()
}

/**
 * Clear filters
 */
function clearFilters() {
  initFilters()
  first.value = 0
  loadCustomers()
}

/**
 * Get customer_id from URL params
 */
function getCustomerIdFromUrl() {
  const urlParams = new URLSearchParams(window.location.search)
  const customerId = urlParams.get('customer_id')
  return customerId ? parseInt(customerId, 10) : null
}

/**
 * Load single customer by ID and open edit dialog
 */
async function loadAndOpenCustomer(customerId) {
  try {
    const response = await request.get(`/api/mgr/customers/${customerId}`)
    if (response && response.id) {
      editCustomer(response)
    }
  } catch (error) {
    console.error('[CustomersGrid] Error loading customer:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_loading_data'),
      life: 5000,
    })
  }
}

/**
 * Load grid configuration
 */
async function loadGridConfig() {
  try {
    const response = await request.get('/api/mgr/grid-config/customers')
    columns.value = response.columns || []
    directFilterKeys.value = new Set(response.direct_filter_keys || [])
    initFilters()
  } catch (error) {
    console.error('[CustomersGrid] Failed to load grid config:', error)
    columns.value = getDefaultColumns()
    directFilterKeys.value = new Set()
    initFilters()
  }
}

/**
 * Default columns (if API unavailable)
 */
function getDefaultColumns() {
  return [
    {
      name: 'id',
      label: 'ID',
      visible: true,
      sortable: true,
      frozen: true,
      width: '5rem',
      isSystem: true,
    },
    {
      name: 'customer_name',
      label: _('customer_name'),
      visible: true,
      sortable: false,
      filterable: true,
      type: 'template',
      template: '{first_name} {last_name}',
      minWidth: '12.5rem',
    },
    {
      name: 'email',
      label: _('customer_email'),
      visible: true,
      sortable: true,
      filterable: true,
      type: 'model',
      minWidth: '12.5rem',
    },
    {
      name: 'phone',
      label: _('customer_phone'),
      visible: true,
      filterable: true,
      type: 'model',
      width: '9.375rem',
      minWidth: '7.5rem',
    },
    {
      name: 'is_active',
      label: _('customer_active'),
      visible: true,
      sortable: true,
      filterable: true,
      type: 'boolean',
      width: '6.25rem',
    },
    {
      name: 'created_at',
      label: _('created_at'),
      visible: true,
      sortable: true,
      type: 'model',
      format: 'datetime',
      width: '11.25rem',
      minWidth: '9.375rem',
    },
    {
      name: 'actions',
      label: _('actions'),
      visible: true,
      isSystem: true,
      frozen: true,
      width: '9.375rem',
      type: 'actions',
      actions: [
        { name: 'addresses', handler: 'addresses', icon: 'pi-map-marker', label: 'addresses' },
        { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
        { ...CUSTOMER_GRID_DELETE_ACTION },
      ],
    },
  ]
}

/**
 * Ensure delete action uses customer-specific confirm copy (covers API-loaded grid config).
 */
function applyCustomerDeleteConfirmDefaults(actions) {
  return actions.map(action => {
    const handler = action.handler || action.name
    if (handler !== 'delete') return action
    return {
      ...action,
      confirmTitle: action.confirmTitle || 'customer_delete_confirm_title',
      confirmMessage: action.confirmMessage || 'customer_delete_confirm_message',
      confirmAccept: action.confirmAccept || 'delete',
    }
  })
}

/**
 * Get action configuration for column
 */
function getActionsConfig(column) {
  const fallback = [
    { name: 'addresses', handler: 'addresses', icon: 'pi-map-marker', label: 'addresses' },
    { name: 'edit', handler: 'edit', icon: 'pi-pencil', label: 'edit' },
    { ...CUSTOMER_GRID_DELETE_ACTION },
  ]
  const raw = !column.actions || column.actions.length === 0 ? fallback : column.actions
  return applyCustomerDeleteConfirmDefaults(raw)
}

/**
 * Render column value by template
 */
function renderField(data, column) {
  if (column.template) {
    return column.template.replace(/\{(\w+)\}/g, (match, key) => data[key] || '')
  }
  return data[column.name]
}

onMounted(async () => {
  await loadGridConfig()
  await loadCustomers()

  // Check if customer_id is in URL and open customer dialog
  const customerIdFromUrl = getCustomerIdFromUrl()
  if (customerIdFromUrl) {
    await loadAndOpenCustomer(customerIdFromUrl)
  }
})
</script>

<template>
  <div class="customers-grid">
    <Toast />
    <ConfirmDialog append-to="self" />

    <Card>
      <template #title>
        {{ _('customers_title') }}
      </template>

      <template #content>
        <!-- Search -->
        <div class="p-inputgroup mb-3">
          <InputText
            v-model="searchQuery"
            name="ms3-customers-grid-search"
            autocomplete="off"
            :placeholder="_('search_placeholder')"
            @keyup.enter="onSearch"
          />
          <Button icon="pi pi-search" :label="_('search')" @click="onSearch" />
        </div>

        <!-- Filters form -->
        <div
          v-if="filterableColumns.length > 0"
          class="filters-form mb-3 p-3 surface-ground"
          style="border-radius: 0.375rem"
        >
          <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem">
            <div
              v-for="column in filterableColumns"
              :key="column.name"
              style="flex: 1 1 18.75rem; min-width: 15.625rem"
            >
              <div class="field">
                <label
                  :for="`filter-${column.name}`"
                  style="display: block; margin-bottom: 0.5rem; font-weight: 500"
                  >{{ column.label }}</label
                >
                <InputText
                  :id="`filter-${column.name}`"
                  v-model="filterValues[column.name]"
                  :placeholder="_('filter_by').replace('{field}', column.label)"
                  style="width: 100%"
                  @keyup.enter="applyFilters"
                />
              </div>
            </div>
          </div>
          <div style="display: flex; gap: 0.5rem">
            <Button :label="_('apply_filters')" icon="pi pi-filter" @click="applyFilters" />
            <Button
              :label="_('clear_filters')"
              icon="pi pi-filter-slash"
              severity="secondary"
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
              :label="_('delete_selected')"
              icon="pi pi-trash"
              severity="danger"
              size="small"
              :loading="bulkProcessing"
              @click="confirmBulkDelete"
            />
          </div>
        </div>

        <!-- Table -->
        <DataTable
          v-model:selection="selectedItems"
          :value="customers"
          :loading="loading"
          :paginator="true"
          :rows="rows"
          :total-records="totalRecords"
          :lazy="true"
          :sort-field="sortField"
          :sort-order="sortOrder"
          data-key="id"
          striped-rows
          responsive-layout="scroll"
          @page="onPage"
          @sort="onSort"
        >
          <!-- Selection checkbox column -->
          <Column selection-mode="multiple" header-style="width: 3rem" frozen></Column>

          <!-- Dynamic column rendering -->
          <template v-for="column in columns.filter(c => c.visible)" :key="column.name">
            <!-- Actions column (special handling) -->
            <Column
              v-if="column.type === 'actions'"
              :header="column.label"
              :sortable="column.sortable"
              :frozen="column.frozen"
              :style="{ width: column.width }"
            >
              <template #body="{ data }">
                <ActionsColumn
                  :data="data"
                  :actions="getActionsConfig(column)"
                  grid-id="customers"
                  @edit="editCustomer"
                  @delete="deleteCustomer"
                  @addresses="openAddresses"
                  @refresh="loadCustomers"
                />
              </template>
            </Column>

            <!-- Regular columns -->
            <Column
              v-else
              :field="column.name"
              :header="column.label"
              :sortable="column.sortable"
              :frozen="column.frozen"
              :style="{ width: column.width }"
            >
              <template #body="{ data }">
                <!-- Boolean field (checkbox) -->
                <Checkbox
                  v-if="column.type === 'boolean'"
                  :model-value="Boolean(data[column.name])"
                  :binary="true"
                  disabled
                />
                <!-- Datetime field -->
                <span v-else-if="column.format === 'datetime'">
                  {{ formatDate(data[column.name]) }}
                </span>
                <!-- Template field (e.g.: {first_name} {last_name}) -->
                <span v-else-if="column.template">
                  {{ renderField(data, column) }}
                </span>
                <!-- Regular text field -->
                <span v-else>
                  {{ data[column.name] }}
                </span>
              </template>
            </Column>
          </template>
        </DataTable>
      </template>
    </Card>

    <!-- Edit modal window -->
    <Dialog
      v-model:visible="editDialogVisible"
      :header="_('edit_customer')"
      :modal="true"
      :closable="true"
      :style="{ width: '34.375rem' }"
      :append-to="'self'"
    >
      <form
        v-if="editingCustomer"
        class="customer-form"
        autocomplete="off"
        @submit.prevent="saveCustomer"
      >
        <!-- Row 1: First and Last Name -->
        <div class="form-row">
          <div class="form-col">
            <label for="ms3-customer-first_name">{{ _('customer_first_name') }}</label>
            <InputText
              id="ms3-customer-first_name"
              v-model="editingCustomer.first_name"
              autocomplete="off"
              class="w-full"
            />
          </div>
          <div class="form-col">
            <label for="ms3-customer-last_name">{{ _('customer_last_name') }}</label>
            <InputText
              id="ms3-customer-last_name"
              v-model="editingCustomer.last_name"
              autocomplete="off"
              class="w-full"
            />
          </div>
        </div>

        <!-- Row 2: Email and Phone -->
        <div class="form-row">
          <div class="form-col">
            <label for="ms3-customer-email">{{ _('customer_email') }}</label>
            <InputText
              id="ms3-customer-email"
              v-model="editingCustomer.email"
              type="email"
              autocomplete="off"
              class="w-full"
            />
          </div>
          <div class="form-col">
            <label for="ms3-customer-phone">{{ _('customer_phone') }}</label>
            <InputText
              id="ms3-customer-phone"
              v-model="editingCustomer.phone"
              autocomplete="off"
              class="w-full"
            />
          </div>
        </div>

        <!-- Row 3: New Password -->
        <div class="form-row">
          <div class="form-col-full">
            <label for="ms3-customer-new_password">{{ _('customer_new_password') }}</label>
            <InputGroup>
              <InputText
                id="ms3-customer-new_password"
                v-model="newPassword"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="new-password"
                :placeholder="_('customer_password_placeholder')"
                class="w-full"
              />
              <InputGroupAddon>
                <Button
                  type="button"
                  :icon="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"
                  text
                  :title="showPassword ? _('hide_password') : _('show_password')"
                  @click="showPassword = !showPassword"
                />
              </InputGroupAddon>
              <InputGroupAddon>
                <Button
                  type="button"
                  icon="pi pi-refresh"
                  text
                  :title="_('generate_password')"
                  @click="generatePassword"
                />
              </InputGroupAddon>
            </InputGroup>
            <small class="text-muted">{{ _('customer_password_hint') }}</small>
          </div>
        </div>

        <!-- Row 4: Checkboxes -->
        <div class="checkboxes-row">
          <div class="checkbox-col">
            <Checkbox v-model="editingCustomer.is_active" input-id="is_active" :binary="true" />
            <label for="is_active">{{ _('customer_active') }}</label>
          </div>
          <div class="checkbox-col">
            <Checkbox v-model="editingCustomer.is_blocked" input-id="is_blocked" :binary="true" />
            <label for="is_blocked">{{ _('customer_blocked') }}</label>
          </div>
          <div class="checkbox-col">
            <Checkbox
              input-id="email_verified"
              :model-value="Boolean(editingCustomer.email_verified_at)"
              :binary="true"
              disabled
            />
            <label for="email_verified" class="text-muted">{{
              _('customer_email_verified')
            }}</label>
          </div>
        </div>
      </form>

      <template #footer>
        <Button
          type="button"
          :label="_('cancel')"
          icon="pi pi-times"
          class="p-button-text"
          @click="editDialogVisible = false"
        />
        <Button
          type="button"
          :label="_('save')"
          icon="pi pi-check"
          :loading="saving"
          @click="saveCustomer"
        />
      </template>
    </Dialog>

    <!-- Customer addresses modal window -->
    <Dialog
      v-model:visible="addressesDialogVisible"
      :header="
        currentCustomerForAddresses
          ? _('customer_addresses_title').replace(
              '{name}',
              getCustomerDisplayName(currentCustomerForAddresses)
            )
          : _('addresses')
      "
      :modal="true"
      :closable="true"
      :style="{ width: '50rem' }"
      :append-to="'self'"
    >
      <div class="addresses-content">
        <!-- Addresses list -->
        <div v-if="!addressFormVisible" class="addresses-list">
          <div class="addresses-header">
            <Button :label="_('add_address')" icon="pi pi-plus" @click="createAddress" />
          </div>

          <div v-if="addressesLoading" class="addresses-loading">
            <i class="pi pi-spinner pi-spin"></i> {{ _('loading') }}
          </div>

          <div v-else-if="addresses.length === 0" class="addresses-empty">
            <i class="pi pi-map-marker"></i>
            <p>{{ _('no_addresses') }}</p>
          </div>

          <div v-else class="addresses-items">
            <div
              v-for="address in addresses"
              :key="address.id"
              class="address-card"
              :class="{ 'address-inactive': !address.active }"
            >
              <div class="address-info">
                <div class="address-name">
                  <strong>{{ address.name || _('address_unnamed') }}</strong>
                  <span v-if="!address.active" class="address-badge inactive">{{
                    _('inactive')
                  }}</span>
                </div>
                <div class="address-formatted">{{ address.formatted }}</div>
                <div v-if="address.comment" class="address-comment">
                  <i class="pi pi-comment"></i> {{ address.comment }}
                </div>
              </div>
              <div class="address-actions">
                <Button
                  icon="pi pi-pencil"
                  text
                  severity="secondary"
                  :title="_('edit')"
                  @click="editAddress(address)"
                />
                <Button
                  icon="pi pi-trash"
                  text
                  severity="danger"
                  :title="_('delete')"
                  @click="deleteAddress(address)"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Address edit/create form -->
        <div v-else class="address-form">
          <div class="form-row">
            <div class="form-col-full">
              <label for="addr_name">{{ _('address_name') }}</label>
              <InputText
                id="addr_name"
                v-model="editingAddress.name"
                class="w-full"
                :placeholder="_('address_name_placeholder')"
              />
            </div>
          </div>

          <div class="form-row">
            <div class="form-col">
              <label for="addr_country">{{ _('address_country') }}</label>
              <InputText id="addr_country" v-model="editingAddress.country" class="w-full" />
            </div>
            <div class="form-col">
              <label for="addr_index">{{ _('address_index') }}</label>
              <InputText id="addr_index" v-model="editingAddress.index" class="w-full" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-col">
              <label for="addr_region">{{ _('address_region') }}</label>
              <InputText id="addr_region" v-model="editingAddress.region" class="w-full" />
            </div>
            <div class="form-col">
              <label for="addr_city">{{ _('address_city') }}</label>
              <InputText id="addr_city" v-model="editingAddress.city" class="w-full" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-col">
              <label for="addr_metro">{{ _('address_metro') }}</label>
              <InputText id="addr_metro" v-model="editingAddress.metro" class="w-full" />
            </div>
            <div class="form-col">
              <label for="addr_street">{{ _('address_street') }}</label>
              <InputText id="addr_street" v-model="editingAddress.street" class="w-full" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-col-sm">
              <label for="addr_building">{{ _('address_building') }}</label>
              <InputText id="addr_building" v-model="editingAddress.building" class="w-full" />
            </div>
            <div class="form-col-sm">
              <label for="addr_entrance">{{ _('address_entrance') }}</label>
              <InputText id="addr_entrance" v-model="editingAddress.entrance" class="w-full" />
            </div>
            <div class="form-col-sm">
              <label for="addr_floor">{{ _('address_floor') }}</label>
              <InputText id="addr_floor" v-model="editingAddress.floor" class="w-full" />
            </div>
            <div class="form-col-sm">
              <label for="addr_room">{{ _('address_room') }}</label>
              <InputText id="addr_room" v-model="editingAddress.room" class="w-full" />
            </div>
          </div>

          <div class="form-row">
            <div class="form-col-full">
              <label for="addr_comment">{{ _('address_comment') }}</label>
              <Textarea
                id="addr_comment"
                v-model="editingAddress.comment"
                class="w-full"
                rows="2"
              />
            </div>
          </div>

          <div class="form-row">
            <div class="checkbox-col">
              <Checkbox v-model="editingAddress.active" input-id="addr_active" :binary="true" />
              <label for="addr_active">{{ _('address_active') }}</label>
            </div>
          </div>

          <div class="form-actions">
            <Button
              :label="_('cancel')"
              icon="pi pi-times"
              class="p-button-text"
              @click="cancelAddressEdit"
            />
            <Button
              :label="_('save')"
              icon="pi pi-check"
              :loading="savingAddress"
              @click="saveAddress"
            />
          </div>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
.customers-grid {
  padding: 1.25rem;
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

.text-success {
  color: var(--ms3-text-success);
}

.text-warning {
  color: var(--ms3-text-warning-accent);
}

/* Customer edit form grid */
.customer-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.form-row {
  display: flex;
  gap: 1rem;
}

.form-col {
  flex: 1;
  min-width: 0;
}

.form-col label,
.form-col-full label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
}

.form-col-full {
  flex: 1 1 100%;
}

.form-col-full small.text-muted {
  display: block;
  margin-top: 0.25rem;
  color: var(--ms3-text-muted);
  font-size: 0.75rem;
}

/* Checkboxes row */
.checkboxes-row {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  padding: 0.5rem 0;
}

.checkbox-col {
  flex: 0 0 calc(25% - 0.75rem);
  display: flex;
  align-items: center;
}

.checkbox-col label {
  margin-left: 0.5rem;
  margin-bottom: 0;
  cursor: pointer;
  font-size: 0.875rem;
  user-select: none;
}

.w-full {
  width: 100%;
}

/* Styles for addresses dialog */
.addresses-content {
  min-height: 12.5rem;
}

.addresses-header {
  margin-bottom: 1rem;
}

.addresses-loading,
.addresses-empty {
  text-align: center;
  padding: 2rem;
  color: var(--ms3-text-muted);
}

.addresses-empty i {
  font-size: 2rem;
  margin-bottom: 0.5rem;
  display: block;
}

.addresses-items {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.address-card {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 1rem;
  border: var(--ms3-border-width) solid var(--ms3-border-color);
  border-radius: 0.375rem;
  background: var(--ms3-bg-surface);
}

.address-card.address-inactive {
  opacity: 0.6;
  background: var(--ms3-bg-muted);
}

.address-info {
  flex: 1;
}

.address-name {
  margin-bottom: 0.25rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.address-badge {
  font-size: 0.7rem;
  padding: 0.15rem 0.4rem;
  border-radius: 0.1875rem;
}

.address-badge.inactive {
  background: var(--ms3-border-color);
  color: var(--ms3-text-muted);
}

.address-formatted {
  color: var(--ms3-text-muted);
  font-size: 0.875rem;
}

.address-comment {
  margin-top: 0.5rem;
  font-size: 0.8rem;
  color: var(--ms3-text-light);
  font-style: italic;
}

.address-comment i {
  margin-right: 0.25rem;
}

.address-actions {
  display: flex;
  gap: 0.25rem;
}

/* Address form */
.address-form {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.address-form .form-row {
  display: flex;
  gap: 1rem;
}

.address-form .form-col {
  flex: 1;
  min-width: 0;
}

.address-form .form-col-sm {
  flex: 0 0 calc(25% - 0.75rem);
  min-width: 0;
}

.address-form .form-col-full {
  flex: 1 1 100%;
}

.address-form label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.875rem;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: var(--ms3-border-width) solid var(--ms3-border-color);
}
</style>
