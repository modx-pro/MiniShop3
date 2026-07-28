<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Fieldset from 'primevue/fieldset'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'
import { computed, inject } from 'vue'

import { ORDER_CONTEXT_KEY } from '../../composables/orderContext.js'
import OrderExtraFieldsSection from './OrderExtraFieldsSection.vue'
import OrderFormActionsBar from './OrderFormActionsBar.vue'

const selectedCustomer = defineModel('selectedCustomer', {
  type: Object,
  default: null,
})
const createCustomerFromData = defineModel('createCustomerFromData', {
  type: Boolean,
  default: false,
})

const props = defineProps({
  addressFieldsBySection: { type: Array, required: true },
  addressExtraFields: { type: Array, default: () => [] },
})

const orderCtx = inject(ORDER_CONTEXT_KEY, null)
if (import.meta.env.DEV && !orderCtx) {
  console.error('[OrderAddressTab] Missing inject: orderContext (must be used inside OrderView)')
}
if (!orderCtx) {
  throw new Error('[OrderAddressTab] orderContext is required. Use OrderView as parent.')
}

const {
  order,
  isCreateMode,
  isDraft,
  customerSuggestions,
  searchingCustomers,
  saving,
  formatPrice,
  getFieldWidthClass,
  getAddressFieldCompareField,
  getAddressFieldOptions,
  searchCustomers,
  onCustomerSelect,
  clearCustomer,
  createOrder,
  saveOrder,
  goBack,
  recalculatingCost,
} = orderCtx

const { _ } = useLexicon()

const hasAddressFieldSections = computed(
  () => (props.addressFieldsBySection?.length ?? 0) > 0
)

const hasAddressExtraFields = computed(() => (props.addressExtraFields?.length ?? 0) > 0)

/**
 * Скрыть «Сохранить»/«Отмена», если нет полей адреса и нет сценария с клиентом (#182):
 * create / draft (блок выбора клиента) / есть секции полей.
 */
const showAddressTabActions = computed(
  () =>
    isCreateMode.value ||
    isDraft.value ||
    hasAddressFieldSections.value ||
    hasAddressExtraFields.value
)
</script>

<template>
  <div class="order-address-tab">
    <Fieldset
      v-if="isCreateMode || isDraft"
      :legend="_('order_customer')"
      class="mb-3"
      :toggleable="true"
    >
      <div class="customer-search-content">
        <div class="customer-search-field">
          <AutoComplete
            v-model="selectedCustomer"
            :suggestions="customerSuggestions"
            option-label="display"
            :placeholder="_('ms3_order_search_customer')"
            :loading="searchingCustomers"
            class="w-full"
            :min-length="2"
            @complete="searchCustomers"
            @item-select="onCustomerSelect"
          >
            <template #option="{ option }">
              <div class="customer-suggestion">
                <div class="customer-suggestion-info">
                  <div class="customer-suggestion-name">
                    {{ option.first_name }} {{ option.last_name }}
                  </div>
                  <div class="customer-suggestion-meta">
                    <span v-if="option.email" class="email">{{ option.email }}</span>
                    <span v-if="option.phone" class="phone">{{ option.phone }}</span>
                  </div>
                  <div class="customer-suggestion-stats">
                    <span v-if="option.orders_count"
                      >{{ _('orders') }}: {{ option.orders_count }}</span
                    >
                    <span v-if="option.total_spent"
                      >{{ _('total') }}: {{ formatPrice(option.total_spent) }}</span
                    >
                  </div>
                </div>
              </div>
            </template>
          </AutoComplete>
          <small class="customer-search-hint">{{ _('ms3_order_customer_hint') }}</small>
        </div>

        <div v-if="selectedCustomer && selectedCustomer.id" class="selected-customer-info">
          <div class="selected-customer-badge">
            <i class="pi pi-user"></i>
            <span class="customer-name"
              >{{ selectedCustomer.first_name }} {{ selectedCustomer.last_name }}</span
            >
            <span v-if="selectedCustomer.email" class="customer-email">{{
              selectedCustomer.email
            }}</span>
            <Button
              icon="pi pi-times"
              severity="secondary"
              text
              rounded
              size="small"
              :title="_('ms3_order_clear_customer')"
              @click="clearCustomer"
            />
          </div>
          <small class="text-success">{{ _('ms3_order_customer_selected') }}</small>
        </div>
        <div v-else class="no-customer-hint">
          <i class="pi pi-info-circle"></i>
          <span>{{ _('ms3_order_no_customer') }}</span>
        </div>

        <div class="create-customer-checkbox mt-3">
          <Checkbox
            v-model="createCustomerFromData"
            input-id="createCustomer"
            :binary="true"
            :disabled="!!selectedCustomer?.id"
          />
          <label
            for="createCustomer"
            class="ml-2"
            :class="{ 'text-muted': !!selectedCustomer?.id }"
          >
            {{ _('ms3_order_create_customer_from_data') }}
          </label>
        </div>
      </div>
    </Fieldset>

    <template v-for="section in addressFieldsBySection" :key="section.id || 'no_section'">
      <Fieldset :legend="section.label" class="mb-3" :toggleable="true">
        <div class="fields-grid">
          <template v-for="field in section.fields" :key="field.id">
            <div :class="['field-wrapper', getFieldWidthClass(field)]">
              <div class="field">
                <label>{{ field.label_display || field.label || field.name }}</label>

                <template v-if="field.xtype === 'combo'">
                  <Select
                    v-model="order[getAddressFieldCompareField(field.name)]"
                    :options="getAddressFieldOptions(field.name)"
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

    <OrderExtraFieldsSection
      :extra-fields="addressExtraFields"
      :legend="_('ms3_vue_order_address_extra_fields')"
    />

    <div
      v-if="addressFieldsBySection.length === 0 && !hasAddressExtraFields"
      class="no-fields-message"
    >
      <p>{{ _('ms3_order_tab_address_model_fields_empty_hint') }}</p>
    </div>

    <OrderFormActionsBar
      v-if="showAddressTabActions"
      :is-create-mode="isCreateMode"
      :saving="saving"
      :recalculating-cost="recalculatingCost"
      @create="createOrder"
      @save="saveOrder"
      @cancel="goBack"
    />
  </div>
</template>

<style scoped src="./orderFieldsLayout.css"></style>

<style scoped>
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

.customer-search-content {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.customer-search-field {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.customer-search-hint {
  color: var(--ms3-text-muted);
  font-size: 0.75rem;
}

.customer-suggestion {
  display: flex;
  align-items: center;
  gap: var(--ms3-spacing-3);
  padding: 0.25rem 0;
}

.customer-suggestion-info {
  flex: 1;
}

.customer-suggestion-name {
  font-weight: 500;
  color: var(--ms3-text-darkest);
}

.customer-suggestion-meta {
  font-size: 0.75rem;
  color: var(--ms3-text-muted);
  display: flex;
  gap: var(--ms3-spacing-3);
}

.customer-suggestion-meta .email {
  color: var(--ms3-accent-primary);
}

.customer-suggestion-meta .phone {
  color: var(--ms3-text-muted);
}

.customer-suggestion-stats {
  font-size: 0.7rem;
  color: var(--ms3-text-light);
  display: flex;
  gap: var(--ms3-spacing-3);
  margin-top: 0.25rem;
}

.selected-customer-info {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.selected-customer-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem var(--ms3-spacing-3);
  background: var(--ms3-bg-success);
  border: var(--ms3-border-width) solid var(--ms3-border-success);
  border-radius: 0.5rem;
}

.selected-customer-badge i {
  color: var(--ms3-text-success);
}

.selected-customer-badge .customer-name {
  font-weight: 500;
  color: var(--ms3-text-darkest);
}

.selected-customer-badge .customer-email {
  color: var(--ms3-text-muted);
  font-size: 0.875rem;
}

.no-customer-hint {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem var(--ms3-spacing-3);
  background: var(--ms3-bg-slate);
  border: var(--ms3-border-width) dashed var(--ms3-border-color);
  border-radius: 0.5rem;
  color: var(--ms3-text-muted);
  font-size: 0.875rem;
}

.no-customer-hint i {
  color: var(--ms3-text-light);
}

.text-success {
  color: var(--ms3-text-success);
}

.create-customer-checkbox {
  display: flex;
  align-items: center;
  padding: var(--ms3-spacing-3);
  background: var(--ms3-bg-warning-light);
  border: var(--ms3-border-width) solid var(--ms3-border-warning-alt);
  border-radius: 0.5rem;
}

.create-customer-checkbox label {
  cursor: pointer;
  font-size: 0.875rem;
  color: var(--ms3-text-warning-dark);
}

.create-customer-checkbox label.text-muted {
  color: var(--ms3-text-light);
  cursor: not-allowed;
}
</style>
