<script setup>
import { onMounted, ref, computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
import Fieldset from 'primevue/fieldset'
import TabView from 'primevue/tabview'
import TabPanel from 'primevue/tabpanel'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useToast } from 'primevue/usetoast'
import request from '../request.js'
import { useLexicon } from '../composables/useLexicon.js'

const toast = useToast()
const { _ } = useLexicon()

const loading = ref(true)
const saving = ref(false)
const order = ref(null)
const products = ref([])
const logs = ref([])
const statuses = ref([])
const deliveries = ref([])
const payments = ref([])

const orderId = computed(() => {
  // Try to get from ms3.config first
  if (window.ms3?.config?.order_id) {
    return window.ms3.config.order_id
  }
  // Fallback: get from URL
  const urlParams = new URLSearchParams(window.location.search)
  return parseInt(urlParams.get('id')) || 0
})

/**
 * Load order data
 */
async function loadOrder() {
  if (!orderId.value) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: _('order_id_required'),
      life: 5000
    })
    return
  }

  loading.value = true

  try {
    const response = await request.get(`/api/mgr/orders/${orderId.value}`)
    order.value = response

    await Promise.all([
      loadProducts(),
      loadLogs(),
      loadStatuses(),
      loadDeliveries(),
      loadPayments()
    ])
  } catch (error) {
    console.error('[OrderView] Error loading order:', error)
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
 * Load order products
 */
async function loadProducts() {
  try {
    const response = await request.get(`/api/mgr/orders/${orderId.value}/products`)
    products.value = response.results || response || []
  } catch (error) {
    console.error('[OrderView] Error loading products:', error)
    products.value = []
  }
}

/**
 * Load order logs
 */
async function loadLogs() {
  try {
    const response = await request.get(`/api/mgr/orders/${orderId.value}/logs`)
    logs.value = response.results || response || []
  } catch (error) {
    console.error('[OrderView] Error loading logs:', error)
    logs.value = []
  }
}

/**
 * Load statuses list
 */
async function loadStatuses() {
  try {
    const response = await request.get('/api/mgr/statuses')
    statuses.value = (response.results || response || []).map(s => ({
      value: s.id,
      label: s.name
    }))
  } catch (error) {
    console.error('[OrderView] Error loading statuses:', error)
    statuses.value = []
  }
}

/**
 * Load deliveries list
 */
async function loadDeliveries() {
  try {
    const response = await request.get('/api/mgr/deliveries')
    deliveries.value = (response.results || response || []).map(d => ({
      value: d.id,
      label: d.name
    }))
  } catch (error) {
    console.error('[OrderView] Error loading deliveries:', error)
    deliveries.value = []
  }
}

/**
 * Load payments list
 */
async function loadPayments() {
  try {
    const response = await request.get('/api/mgr/payments')
    payments.value = (response.results || response || []).map(p => ({
      value: p.id,
      label: p.name
    }))
  } catch (error) {
    console.error('[OrderView] Error loading payments:', error)
    payments.value = []
  }
}

/**
 * Save order
 */
async function saveOrder() {
  saving.value = true

  try {
    await request.put(`/api/mgr/orders/${orderId.value}`, {
      status_id: order.value.status_id,
      delivery_id: order.value.delivery_id,
      payment_id: order.value.payment_id,
      order_comment: order.value.order_comment,
      // Address fields
      first_name: order.value.first_name,
      last_name: order.value.last_name,
      phone: order.value.phone,
      email: order.value.email
    })

    toast.add({
      severity: 'success',
      summary: _('success'),
      detail: _('order_saved'),
      life: 3000
    })

    await loadLogs()
  } catch (error) {
    console.error('[OrderView] Error saving order:', error)
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message || _('error_saving_data'),
      life: 5000
    })
  } finally {
    saving.value = false
  }
}

/**
 * Go back to orders list
 */
function goBack() {
  window.location.href = '?a=mgr/orders&namespace=minishop3'
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
    minute: '2-digit'
  })
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
 * Get status severity for tag
 */
function getStatusSeverity(color) {
  if (!color) return 'secondary'
  const colorMap = {
    '#97b94d': 'success',
    '#81d742': 'success',
    'green': 'success',
    '#dd3d36': 'danger',
    'red': 'danger',
    '#f0ad4e': 'warn',
    'yellow': 'warn',
    '#5bc0de': 'info',
    'blue': 'info'
  }
  return colorMap[color?.toLowerCase()] || 'secondary'
}

onMounted(() => {
  loadOrder()
})
</script>

<template>
  <div class="order-view">
    <Toast />
    <ConfirmDialog />

    <!-- Header -->
    <div class="order-header mb-3">
      <Button
        icon="pi pi-arrow-left"
        :label="_('back_to_orders')"
        severity="secondary"
        text
        @click="goBack"
      />
      <h2 v-if="order">{{ _('order') }} #{{ order.num || order.id }}</h2>
    </div>

    <div v-if="loading" class="loading-state">
      <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
      <p>{{ _('loading') }}</p>
    </div>

    <template v-else-if="order">
      <TabView>
        <!-- Order Info Tab -->
        <TabPanel :header="_('order_info')">
          <div class="grid">
            <!-- Left column - main info -->
            <div class="col-12 md:col-6">
              <Card>
                <template #title>{{ _('order_main_info') }}</template>
                <template #content>
                  <div class="field">
                    <label>{{ _('order_num') }}</label>
                    <div class="field-value">{{ order.num || order.id }}</div>
                  </div>

                  <div class="field">
                    <label>{{ _('order_customer') }}</label>
                    <div class="field-value">{{ order.customer || `${order.first_name || ''} ${order.last_name || ''}`.trim() || '-' }}</div>
                  </div>

                  <div class="field">
                    <label>{{ _('order_status') }}</label>
                    <Select
                      v-model="order.status_id"
                      :options="statuses"
                      optionLabel="label"
                      optionValue="value"
                      :placeholder="_('select_status')"
                      class="w-full"
                    />
                  </div>

                  <div class="field">
                    <label>{{ _('order_delivery') }}</label>
                    <Select
                      v-model="order.delivery_id"
                      :options="deliveries"
                      optionLabel="label"
                      optionValue="value"
                      :placeholder="_('select_delivery')"
                      class="w-full"
                    />
                  </div>

                  <div class="field">
                    <label>{{ _('order_payment') }}</label>
                    <Select
                      v-model="order.payment_id"
                      :options="payments"
                      optionLabel="label"
                      optionValue="value"
                      :placeholder="_('select_payment')"
                      class="w-full"
                    />
                  </div>

                  <div class="field">
                    <label>{{ _('order_comment') }}</label>
                    <Textarea
                      v-model="order.order_comment"
                      rows="3"
                      class="w-full"
                    />
                  </div>
                </template>
              </Card>
            </div>

            <!-- Right column - costs and dates -->
            <div class="col-12 md:col-6">
              <Card>
                <template #title>{{ _('order_costs') }}</template>
                <template #content>
                  <div class="costs-grid">
                    <div class="cost-item">
                      <span class="cost-label">{{ _('order_cost') }}</span>
                      <span class="cost-value">{{ order.cost_formatted || formatPrice(order.cost) }}</span>
                    </div>
                    <div class="cost-item">
                      <span class="cost-label">{{ _('order_cart_cost') }}</span>
                      <span class="cost-value">{{ order.cart_cost_formatted || formatPrice(order.cart_cost) }}</span>
                    </div>
                    <div class="cost-item">
                      <span class="cost-label">{{ _('order_delivery_cost') }}</span>
                      <span class="cost-value">{{ order.delivery_cost_formatted || formatPrice(order.delivery_cost) }}</span>
                    </div>
                    <div class="cost-item">
                      <span class="cost-label">{{ _('order_weight') }}</span>
                      <span class="cost-value">{{ order.weight_formatted || order.weight || '-' }}</span>
                    </div>
                  </div>
                </template>
              </Card>

              <Card class="mt-3">
                <template #title>{{ _('order_dates') }}</template>
                <template #content>
                  <div class="field">
                    <label>{{ _('order_createdon') }}</label>
                    <div class="field-value">{{ formatDate(order.createdon) }}</div>
                  </div>
                  <div class="field">
                    <label>{{ _('order_updatedon') }}</label>
                    <div class="field-value">{{ formatDate(order.updatedon) }}</div>
                  </div>
                </template>
              </Card>
            </div>
          </div>

          <!-- Save button -->
          <div class="actions-bar mt-3">
            <Button
              :label="_('save')"
              icon="pi pi-check"
              :loading="saving"
              @click="saveOrder"
            />
            <Button
              :label="_('cancel')"
              icon="pi pi-times"
              severity="secondary"
              @click="goBack"
            />
          </div>
        </TabPanel>

        <!-- Products Tab -->
        <TabPanel :header="_('order_products')">
          <DataTable :value="products" stripedRows responsiveLayout="scroll">
            <Column field="name" :header="_('product_name')">
              <template #body="{ data }">
                <a v-if="data.product_id" :href="`?a=resource/update&id=${data.product_id}`" target="_blank">
                  {{ data.name || data.pagetitle }}
                </a>
                <span v-else>{{ data.name || data.pagetitle || '-' }}</span>
              </template>
            </Column>
            <Column field="article" :header="_('product_article')" style="width: 120px" />
            <Column field="count" :header="_('product_count')" style="width: 80px" />
            <Column field="price" :header="_('product_price')" style="width: 120px">
              <template #body="{ data }">
                {{ formatPrice(data.price) }}
              </template>
            </Column>
            <Column field="cost" :header="_('product_cost')" style="width: 120px">
              <template #body="{ data }">
                {{ formatPrice(data.cost) }}
              </template>
            </Column>
          </DataTable>
        </TabPanel>

        <!-- Address Tab -->
        <TabPanel :header="_('order_address')">
          <Card>
            <template #content>
              <div class="grid">
                <div class="col-12 md:col-6">
                  <div class="field">
                    <label>{{ _('address_first_name') }}</label>
                    <InputText v-model="order.first_name" class="w-full" />
                  </div>
                  <div class="field">
                    <label>{{ _('address_last_name') }}</label>
                    <InputText v-model="order.last_name" class="w-full" />
                  </div>
                  <div class="field">
                    <label>{{ _('address_phone') }}</label>
                    <InputText v-model="order.phone" class="w-full" />
                  </div>
                  <div class="field">
                    <label>{{ _('address_email') }}</label>
                    <InputText v-model="order.email" class="w-full" />
                  </div>
                </div>
                <div class="col-12 md:col-6">
                  <div class="field">
                    <label>{{ _('address_city') }}</label>
                    <InputText v-model="order.city" class="w-full" />
                  </div>
                  <div class="field">
                    <label>{{ _('address_street') }}</label>
                    <InputText v-model="order.street" class="w-full" />
                  </div>
                  <div class="field">
                    <label>{{ _('address_building') }}</label>
                    <InputText v-model="order.building" class="w-full" />
                  </div>
                  <div class="field">
                    <label>{{ _('address_room') }}</label>
                    <InputText v-model="order.room" class="w-full" />
                  </div>
                </div>
              </div>
            </template>
          </Card>
        </TabPanel>

        <!-- History Tab -->
        <TabPanel :header="_('order_history')">
          <DataTable :value="logs" stripedRows responsiveLayout="scroll">
            <Column field="timestamp" :header="_('log_date')" style="width: 180px">
              <template #body="{ data }">
                {{ formatDate(data.timestamp || data.createdon) }}
              </template>
            </Column>
            <Column field="action" :header="_('log_action')" />
            <Column field="user_name" :header="_('log_user')" style="width: 150px" />
            <Column field="entry" :header="_('log_entry')">
              <template #body="{ data }">
                <span v-html="data.entry"></span>
              </template>
            </Column>
          </DataTable>
        </TabPanel>
      </TabView>
    </template>

    <div v-else class="error-state">
      <i class="pi pi-exclamation-triangle" style="font-size: 3rem; color: #f59e0b"></i>
      <p>{{ _('order_not_found') }}</p>
      <Button :label="_('back_to_orders')" @click="goBack" />
    </div>
  </div>
</template>

<style scoped>
.order-view {
  padding: 20px;
}

.order-header {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.order-header h2 {
  margin: 0;
  font-size: 1.5rem;
  color: #1e293b;
}

.loading-state,
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem;
  text-align: center;
}

.field {
  margin-bottom: 1rem;
}

.field label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  color: #64748b;
}

.field-value {
  font-size: 1rem;
  color: #1e293b;
}

.costs-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
}

.cost-item {
  display: flex;
  flex-direction: column;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 8px;
}

.cost-label {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 0.25rem;
}

.cost-value {
  font-size: 1.25rem;
  font-weight: 600;
  color: #1e293b;
}

.actions-bar {
  display: flex;
  gap: 0.5rem;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 8px;
}

.grid {
  display: flex;
  flex-wrap: wrap;
  margin: -0.5rem;
}

.col-12 {
  width: 100%;
  padding: 0.5rem;
}

@media (min-width: 768px) {
  .md\:col-6 {
    width: 50%;
  }
}

.mt-3 {
  margin-top: 1rem;
}

.mb-3 {
  margin-bottom: 1rem;
}

.w-full {
  width: 100%;
}
</style>
