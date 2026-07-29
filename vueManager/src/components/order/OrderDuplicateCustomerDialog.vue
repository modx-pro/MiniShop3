<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'

const { _ } = useLexicon()

const visible = defineModel('visible', { type: Boolean, default: false })

defineProps({
  duplicateCustomer: { type: Object, default: null },
  cancelDuplicateDialog: { type: Function, required: true },
  useDuplicateCustomer: { type: Function, required: true },
  createNewCustomerAnyway: { type: Function, required: true },
})
</script>

<template>
<!-- Duplicate Customer Dialog -->
<Dialog
  v-model:visible="visible"
  :header="_('ms3_customer_duplicate_found')"
  :style="{ width: '31.25rem' }"
  :modal="true"
  :closable="true"
  append-to="self"
  @hide="cancelDuplicateDialog"
>
  <div class="duplicate-customer-dialog">
    <div class="duplicate-warning">
      <i class="pi pi-exclamation-triangle"></i>
      <p>{{ _('ms3_customer_duplicate_message') }}</p>
    </div>

    <div v-if="duplicateCustomer" class="duplicate-customer-info">
      <div class="info-row">
        <span class="info-label">{{ _('customer_name') }}:</span>
        <span class="info-value">
          {{ duplicateCustomer.first_name }} {{ duplicateCustomer.last_name }}
        </span>
      </div>
      <div v-if="duplicateCustomer.email" class="info-row">
        <span class="info-label">Email:</span>
        <span class="info-value">{{ duplicateCustomer.email }}</span>
      </div>
      <div v-if="duplicateCustomer.phone" class="info-row">
        <span class="info-label">{{ _('phone') }}:</span>
        <span class="info-value">{{ duplicateCustomer.phone }}</span>
      </div>
      <div v-if="duplicateCustomer.orders_count" class="info-row">
        <span class="info-label">{{ _('orders') }}:</span>
        <span class="info-value">{{ duplicateCustomer.orders_count }}</span>
      </div>
    </div>
  </div>

  <template #footer>
    <Button
      :label="_('cancel')"
      icon="pi pi-times"
      severity="secondary"
      @click="cancelDuplicateDialog"
    />
    <Button
      :label="_('ms3_customer_use_existing')"
      icon="pi pi-user"
      severity="info"
      @click="useDuplicateCustomer"
    />
    <Button
      :label="_('ms3_customer_create_new')"
      icon="pi pi-plus"
      severity="warning"
      @click="createNewCustomerAnyway"
    />
  </template>
</Dialog>
</template>

<style scoped>
.duplicate-customer-dialog {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.duplicate-warning {
  display: flex;
  align-items: flex-start;
  gap: var(--ms3-spacing-3);
  padding: 1rem;
  background: var(--ms3-bg-warning);
  border-radius: 0.5rem;
}

.duplicate-warning i {
  color: var(--ms3-text-warning-accent);
  font-size: 1.5rem;
  flex-shrink: 0;
}

.duplicate-warning p {
  margin: 0;
  color: var(--ms3-text-warning);
  font-size: 0.875rem;
}

.duplicate-customer-info {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 1rem;
  background: var(--ms3-bg-slate);
  border-radius: 0.5rem;
}

.duplicate-customer-info .info-row {
  display: flex;
  gap: 0.5rem;
}

.duplicate-customer-info .info-label {
  color: var(--ms3-text-muted);
  font-size: 0.875rem;
  min-width: 6.25rem;
}

.duplicate-customer-info .info-value {
  color: var(--ms3-text-darkest);
  font-size: 0.875rem;
  font-weight: 500;
}

</style>
