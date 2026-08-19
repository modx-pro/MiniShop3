<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Fieldset from 'primevue/fieldset'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'
import { computed, inject, ref, watch } from 'vue'

import { ORDER_CONTEXT_KEY } from '../../composables/orderContext.js'
import { useOrderFormatters } from '../../composables/useOrderFormatters.js'
import request from '../../request.js'

const STATUS_FALLBACK = [
  'preparing',
  'shipped',
  'in_transit',
  'delivered',
  'cancelled',
  'returned',
  'failed',
]

const orderCtx = inject(ORDER_CONTEXT_KEY, null)
if (import.meta.env.DEV && !orderCtx) {
  console.error('[OrderShipmentTab] Missing inject: orderContext (must be used inside OrderView)')
}
if (!orderCtx) {
  throw new Error('[OrderShipmentTab] orderContext is required. Use OrderView as parent.')
}

const { order, isCreateMode } = orderCtx
const { formatDate } = useOrderFormatters()
const { _ } = useLexicon()
const toast = useToast()

function formatUnix(value) {
  if (!value) {
    return '-'
  }
  const numeric = Number(value)
  const ms = numeric > 0 && numeric < 1e12 ? numeric * 1000 : numeric
  return formatDate(ms)
}

const loading = ref(false)
const saving = ref(false)
const shipment = ref(null)
const statuses = ref([...STATUS_FALLBACK])
const formStatus = ref('preparing')
const formTracking = ref('')

const orderId = computed(() => Number(order.value?.id) || 0)

const statusOptions = computed(() =>
  statuses.value.map(value => ({
    value,
    label: _('shipment_status_' + value) || value,
  })),
)

const hasShipment = computed(() => shipment.value != null)

function applyPayload(payload) {
  const next = payload?.shipment ?? null
  shipment.value = next
  statuses.value = Array.isArray(payload?.statuses) && payload.statuses.length ? payload.statuses : [
    ...STATUS_FALLBACK,
  ]
  formStatus.value = next?.status || 'preparing'
  formTracking.value = next?.tracking_number || ''
}

async function loadShipment() {
  if (!orderId.value) {
    shipment.value = null
    return
  }
  loading.value = true
  try {
    applyPayload(await request.get(`/api/mgr/orders/${orderId.value}/shipment`))
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000,
    })
  } finally {
    loading.value = false
  }
}

async function saveShipment(createEmpty = false) {
  if (!orderId.value) {
    return
  }
  saving.value = true
  try {
    const body = createEmpty
      ? {}
      : {
          status: formStatus.value,
          tracking_number: formTracking.value.trim(),
        }
    applyPayload(await request.put(`/api/mgr/orders/${orderId.value}/shipment`, body))
    toast.add({
      severity: 'success',
      summary: _('shipment_saved'),
      life: 3000,
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: _('error'),
      detail: error.message,
      life: 5000,
    })
  } finally {
    saving.value = false
  }
}

watch(orderId, loadShipment, { immediate: true })
</script>

<template>
  <div class="order-shipment-tab">
    <div v-if="loading" class="shipment-state">
      <i class="pi pi-spin pi-spinner" aria-hidden="true"></i>
      <p>{{ _('loading') }}</p>
    </div>

    <div v-else-if="isCreateMode" class="shipment-state">
      <p>{{ _('shipment_empty') }}</p>
    </div>

    <div v-else-if="!hasShipment" class="shipment-state shipment-empty">
      <p>{{ _('shipment_empty') }}</p>
      <Button
        :label="_('shipment_create')"
        icon="pi pi-plus"
        :loading="saving"
        @click="saveShipment(true)"
      />
    </div>

    <Fieldset v-else :legend="_('order_tracking')" :toggleable="false">
      <div class="shipment-form">
        <div class="shipment-field">
          <label class="shipment-label" for="ms3-shipment-status">{{ _('shipment_status') }}</label>
          <Select
            id="ms3-shipment-status"
            v-model="formStatus"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            class="shipment-control"
          />
        </div>
        <div class="shipment-field">
          <label class="shipment-label" for="ms3-shipment-tracking">{{
            _('shipment_tracking_number')
          }}</label>
          <InputText
            id="ms3-shipment-tracking"
            v-model="formTracking"
            class="shipment-control"
            autocomplete="off"
          />
        </div>
        <div v-if="shipment.carrier" class="shipment-meta">
          <span class="shipment-label">{{ _('shipment_carrier') }}</span>
          <span class="shipment-value">{{ shipment.carrier }}</span>
        </div>
        <div v-if="shipment.shipped_at" class="shipment-meta">
          <span class="shipment-label">{{ _('shipment_shipped_at') }}</span>
          <span class="shipment-value">{{ formatUnix(shipment.shipped_at) }}</span>
        </div>
        <div v-if="shipment.delivered_at" class="shipment-meta">
          <span class="shipment-label">{{ _('shipment_delivered_at') }}</span>
          <span class="shipment-value">{{ formatUnix(shipment.delivered_at) }}</span>
        </div>
        <div class="shipment-actions">
          <Button
            :label="_('save')"
            icon="pi pi-check"
            :loading="saving"
            @click="saveShipment(false)"
          />
        </div>
      </div>
    </Fieldset>
  </div>
</template>

<style scoped>
.order-shipment-tab {
  max-width: 40rem;
}

.shipment-state {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 1rem;
  padding: 2rem 0;
  color: var(--ms3-text-muted);
}

.shipment-empty {
  padding: 3rem 0;
}

.shipment-state .pi-spinner {
  font-size: 2rem;
  color: var(--ms3-text-darkest);
}

.shipment-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.shipment-field {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  max-width: 24rem;
}

.shipment-label {
  font-size: 0.75rem;
  color: var(--ms3-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.shipment-control {
  width: 100%;
}

.shipment-meta {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.shipment-value {
  font-size: 1rem;
  font-weight: 500;
  color: var(--ms3-text-darkest);
}

.shipment-actions {
  display: flex;
  gap: 0.75rem;
  margin-top: 0.5rem;
}
</style>
