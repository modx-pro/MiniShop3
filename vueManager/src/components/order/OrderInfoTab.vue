<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import DatePicker from 'primevue/datepicker'
import Fieldset from 'primevue/fieldset'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { computed, inject, watch } from 'vue'

import { ORDER_CONTEXT_KEY } from '../../composables/orderContext.js'
import OrderExtraFieldsSection from './OrderExtraFieldsSection.vue'
import OrderFormActionsBar from './OrderFormActionsBar.vue'

const props = defineProps({
  orderFieldsBySection: { type: Array, required: true },
  orderExtraFields: { type: Array, default: () => [] },
})

const orderCtx = inject(ORDER_CONTEXT_KEY, null)
if (import.meta.env.DEV && !orderCtx) {
  console.error('[OrderInfoTab] Missing inject: orderContext (must be used inside OrderView)')
}
if (!orderCtx) {
  throw new Error('[OrderInfoTab] orderContext is required. Use OrderView as parent.')
}

const {
  order,
  isCreateMode,
  isDraft,
  finalizing,
  saving,
  formatDate,
  formatPrice,
  getFieldWidthClass,
  isFieldEditable,
  getFieldDisplayValue,
  getFieldCompareField,
  getFieldOptions,
  confirmFinalizeOrder,
  createOrder,
  saveOrder,
  goBack,
  recalculateOrderCost,
  recalculatingCost,
  costRecalcWarnings,
  manualDeliveryCost,
  hasUnsavedShippingPaymentChanges,
} = orderCtx

/** Синхронно с константами ManagerOrderCostRecalculator (PHP). */
const CostRecalcWarning = Object.freeze({
  DELIVERY_MANUAL_REQUIRED: 'delivery_manual_required',
  PAYMENT_MANUAL_REQUIRED: 'payment_manual_required',
})

const recalculateBlocked = computed(
  () => saving.value || recalculatingCost.value || hasUnsavedShippingPaymentChanges.value,
)

const showDeliveryManualBlock = computed(() =>
  (costRecalcWarnings.value || []).includes(CostRecalcWarning.DELIVERY_MANUAL_REQUIRED),
)

const showPaymentManualHint = computed(() =>
  (costRecalcWarnings.value || []).includes(CostRecalcWarning.PAYMENT_MANUAL_REQUIRED),
)

watch(
  () => showDeliveryManualBlock.value,
  visible => {
    if (visible && order.value?.delivery_cost !== undefined) {
      manualDeliveryCost.value = parseFloat(order.value.delivery_cost) || 0
    }
  },
)

async function applyManualDeliveryCost() {
  await recalculateOrderCost({
    mode: 'manual',
    manual_delivery_cost: manualDeliveryCost.value,
  })
}

const { _ } = useLexicon()

const hasOrderFieldSections = computed(
  () => (props.orderFieldsBySection?.length ?? 0) > 0
)

const hasOrderExtraFields = computed(() => (props.orderExtraFields?.length ?? 0) > 0)

/** Скрыть «Сохранить»/«Отмена», если нет полей заказа (#182); в режиме создания кнопки нужны. */
const showOrderInfoActions = computed(
  () => isCreateMode.value || hasOrderFieldSections.value || hasOrderExtraFields.value
)
</script>

<template>
  <div class="order-info-tab">
    <Fieldset
      v-if="!isCreateMode"
      :legend="_('order_summary')"
      class="mb-3 order-summary-section"
      :toggleable="false"
    >
      <div class="order-summary-grid">
        <div class="summary-item summary-num">
          <span class="summary-label">{{ _('order_num') }}</span>
          <span class="summary-value summary-value-lg">{{
            order.num ? '#' + order.num : '-'
          }}</span>
        </div>
        <div class="summary-item summary-cost">
          <span class="summary-label">{{ _('order_cost') }}</span>
          <span class="summary-value summary-value-lg summary-value-primary">{{
            order.cost_formatted || formatPrice(order.cost)
          }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">{{ _('order_cart_cost') }}</span>
          <span class="summary-value">{{
            order.cart_cost_formatted || formatPrice(order.cart_cost)
          }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">{{ _('order_delivery_cost') }}</span>
          <span class="summary-value">{{
            order.delivery_cost_formatted || formatPrice(order.delivery_cost)
          }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">{{ _('order_weight') }}</span>
          <span class="summary-value">{{ order.weight_formatted || order.weight || '-' }}</span>
        </div>
        <div class="summary-item">
          <span class="summary-label">{{ _('order_createdon') }}</span>
          <span class="summary-value">{{ formatDate(order.createdon) }}</span>
        </div>
        <div class="summary-item summary-row-actions summary-item-wide">
          <span class="summary-label">{{ _('order_cost_recalculate') }}</span>
          <div class="cost-recalc-controls">
            <Button
              :label="_('order_cost_recalculate')"
              icon="pi pi-sync"
              size="small"
              class="cost-recalc-btn"
              :disabled="recalculateBlocked"
              :loading="recalculatingCost"
              @click="recalculateOrderCost()"
            />
          </div>
        </div>
      </div>
      <Message
        v-if="hasUnsavedShippingPaymentChanges"
        severity="warn"
        :closable="false"
        class="mt-3"
      >
        {{ _('order_cost_recalc_shippay_notice') }} — {{ _('order_cost_recalc_save_first') }}
      </Message>
      <Message v-if="showPaymentManualHint" severity="info" :closable="false" class="mt-2">
        {{ _('order_cost_recalc_payment_manual_hint') }}
      </Message>
      <div v-if="showDeliveryManualBlock" class="manual-delivery-panel mt-2">
        <Message severity="warn" :closable="false" class="mb-2">
          {{ _('order_cost_recalc_delivery_manual_hint') }}
        </Message>
        <div class="manual-delivery-row flex gap-2 align-items-end flex-wrap">
          <div class="manual-delivery-input">
            <label class="block mb-1" for="manual-delivery-cost">{{ _('ms3_order_delivery_cost') }}</label>
            <InputNumber
              v-model="manualDeliveryCost"
              input-id="manual-delivery-cost"
              class="w-full"
              :min-fraction-digits="0"
              :max-fraction-digits="2"
            />
          </div>
          <Button
            :label="_('order_cost_recalc_apply_manual')"
            icon="pi pi-check"
            :loading="recalculatingCost"
            type="button"
            @click="applyManualDeliveryCost()"
          />
        </div>
      </div>
    </Fieldset>

    <template v-for="section in orderFieldsBySection" :key="section.id || 'no_section'">
      <Fieldset :legend="section.label" class="mb-3" :toggleable="true">
        <div class="fields-grid">
          <template v-for="field in section.fields" :key="field.id">
            <div :class="['field-wrapper', getFieldWidthClass(field)]">
              <div class="field">
                <label>{{ field.label_display || field.label || field.name }}</label>

                <template v-if="!isFieldEditable(field.name)">
                  <div class="field-value">
                    {{ getFieldDisplayValue(field, order[field.name]) }}
                  </div>
                </template>

                <template v-else-if="field.xtype === 'combo'">
                  <Select
                    v-model="order[getFieldCompareField(field.name)]"
                    :options="getFieldOptions(field.name)"
                    option-label="label"
                    option-value="value"
                    :placeholder="field.placeholder"
                    class="w-full"
                  />
                </template>

                <template v-else-if="field.xtype === 'textarea'">
                  <Textarea
                    v-model="order[field.name]"
                    :placeholder="field.placeholder"
                    rows="3"
                    class="w-full"
                  />
                </template>

                <template v-else-if="field.xtype === 'numberfield'">
                  <InputNumber
                    v-model="order[field.name]"
                    :placeholder="field.placeholder"
                    class="w-full"
                    :min-fraction-digits="0"
                    :max-fraction-digits="2"
                  />
                </template>

                <template v-else-if="field.xtype === 'datefield'">
                  <DatePicker
                    v-model="order[field.name]"
                    :placeholder="field.placeholder"
                    date-format="dd.mm.yy"
                    show-time
                    hour-format="24"
                    show-icon
                    fluid
                    icon-display="input"
                  />
                </template>

                <template v-else-if="field.xtype === 'checkbox'">
                  <div class="flex align-items-center">
                    <Checkbox v-model="order[field.name]" :binary="true" />
                  </div>
                </template>

                <template v-else>
                  <InputText
                    v-model="order[field.name]"
                    :placeholder="field.placeholder"
                    class="w-full"
                  />
                </template>

                <small v-if="field.description_display" class="field-description">
                  {{ field.description_display }}
                </small>
              </div>
            </div>
          </template>
        </div>
      </Fieldset>
    </template>

    <OrderExtraFieldsSection :extra-fields="orderExtraFields" />

    <div v-if="orderFieldsBySection.length === 0 && !hasOrderExtraFields" class="no-fields-message">
      <p>{{ _('ms3_order_tab_info_model_fields_empty_hint') }}</p>
    </div>

    <Message
      v-if="!isCreateMode && isDraft"
      severity="info"
      :closable="false"
      class="finalize-info-panel mt-3"
    >
      <template #icon>
        <i class="pi pi-info-circle"></i>
      </template>
      <div class="finalize-info-content">
        <p class="finalize-info-text">{{ _('ms3_order_finalize_info') }}</p>
        <Button
          :label="_('ms3_order_finalize_btn')"
          icon="pi pi-check-circle"
          severity="success"
          :loading="finalizing"
          class="finalize-button"
          @click="confirmFinalizeOrder"
        />
      </div>
    </Message>

    <OrderFormActionsBar
      v-if="showOrderInfoActions"
      :is-create-mode="isCreateMode"
      :saving="saving"
      :recalculating-cost="recalculatingCost"
      @create="createOrder"
      @save="saveOrder"
      @cancel="goBack"
    />
  </div>
</template>

<style scoped src="./orderFieldsLayout.css" />

<style scoped>
.order-summary-section :deep(.p-fieldset-legend) {
  background: var(--ms3-accent-primary);
  color: var(--ms3-text-on-primary);
}

.order-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

@media (max-width: 75rem) {
  .order-summary-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (max-width: 48rem) {
  .order-summary-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 30rem) {
  .order-summary-grid {
    grid-template-columns: 1fr;
  }
}

.summary-item {
  display: flex;
  flex-direction: column;
  padding: var(--ms3-spacing-3) 1rem;
  background: var(--ms3-bg-slate);
  border-radius: 0.5rem;
  border-left: var(--ms3-border-width-accent) solid var(--ms3-border-color);
}

.summary-item.summary-num {
  border-left-color: var(--ms3-accent-primary);
}

.summary-item.summary-cost {
  border-left-color: var(--ms3-text-success);
}

.summary-label {
  font-size: 0.75rem;
  color: var(--ms3-text-muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 0.25rem;
}

.summary-value {
  font-size: 1rem;
  font-weight: 500;
  color: var(--ms3-text-darkest);
}

.summary-value-lg {
  font-size: 1.25rem;
  font-weight: 600;
}

.summary-value-primary {
  color: var(--ms3-text-success);
}

.summary-item-wide {
  grid-column: 1 / -1;
}

.cost-recalc-controls {
  margin-top: 0.35rem;
}

.manual-delivery-input {
  flex: 1;
  min-width: 12rem;
  max-width: 20rem;
}

.finalize-info-panel {
  border: var(--ms3-border-width) solid var(--ms3-border-accent);
  background: linear-gradient(135deg, var(--ms3-bg-accent) 0%, var(--ms3-bg-accent-alt) 100%);
  border-radius: 0.5rem;
}

.finalize-info-panel :deep(.p-message-wrapper) {
  padding: 1rem 1.25rem;
}

.finalize-info-panel :deep(.p-message-icon) {
  color: var(--ms3-accent-primary);
  font-size: 1.25rem;
}

.finalize-info-content {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  width: 100%;
}

.finalize-info-text {
  flex: 1;
  min-width: 12.5rem;
  margin: 0;
  color: var(--ms3-text-accent-dark);
  font-size: 0.9rem;
  line-height: 1.5;
}

.finalize-button {
  flex-shrink: 0;
}

@media (max-width: 40rem) {
  .finalize-info-content {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
  }

  .finalize-info-text {
    min-width: auto;
  }

  .finalize-button {
    width: 100%;
  }
}

:deep(.p-fieldset) {
  border: var(--ms3-border-width) solid var(--ms3-border-color);
  border-radius: 0.5rem;
}

:deep(.p-fieldset .p-fieldset-legend) {
  font-size: 0.95rem;
  padding: 0.5rem 1rem;
  background: var(--ms3-bg-slate);
  border-radius: 0.25rem;
}

:deep(.p-fieldset .p-fieldset-content) {
  padding: 1rem;
}
</style>
