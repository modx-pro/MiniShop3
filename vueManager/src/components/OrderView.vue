<script setup>
import { useLexicon } from '@vuetools/useLexicon'
import Button from 'primevue/button'
import ConfirmDialog from 'primevue/confirmdialog'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import Toast from 'primevue/toast'
import { provide } from 'vue'

import { ORDER_CONTEXT_KEY } from '../composables/orderContext.js'
import { useOrderView } from '../composables/useOrderView.js'
import OrderAddProductDialog from './order/OrderAddProductDialog.vue'
import OrderAddressTab from './order/OrderAddressTab.vue'
import OrderDuplicateCustomerDialog from './order/OrderDuplicateCustomerDialog.vue'
import OrderEditProductDialog from './order/OrderEditProductDialog.vue'
import OrderHistoryTab from './order/OrderHistoryTab.vue'
import OrderInfoTab from './order/OrderInfoTab.vue'
import OrderProductsTab from './order/OrderProductsTab.vue'

const { _ } = useLexicon()

const {
  orderContext,
  registerPluginTab,
  pluginVueProps,
  loading,
  order,
  products,
  productsColumns,
  logs,
  orderFieldsBySection,
  orderExtraFields,
  orderActiveTab,
  orderTabsConfig,
  isCreateMode,
  selectedCustomer,
  createCustomerFromData,
  addressFieldsBySection,
  addressExtraFields,
  goBack,
  formatPrice,
  editProductDialogVisible,
  editingProduct,
  editProductForm,
  savingProduct,
  optionsEditMode,
  optionsTableData,
  productOptionFields,
  optionsJsonText,
  optionsJsonError,
  editedProductCost,
  switchOptionsMode,
  onOptionTypeChange,
  onFieldKeyChange,
  syncTableToJson,
  addOptionRow,
  removeOptionRow,
  saveEditedProduct,
  cancelEditProduct,
  addProductDialogVisible,
  selectedProduct,
  productSuggestions,
  searchingProducts,
  addProductForm,
  savingNewProduct,
  newProductCost,
  searchProducts,
  onProductSelect,
  saveNewProduct,
  cancelAddProduct,
  showDuplicateDialog,
  duplicateCustomer,
  cancelDuplicateDialog,
  useDuplicateCustomer,
  createNewCustomerAnyway,
} = useOrderView()

provide(ORDER_CONTEXT_KEY, orderContext)
defineExpose({ registerPluginTab })
</script>

<template>
  <div class="order-view">
    <Toast />
    <ConfirmDialog append-to="self" />

    <!-- Product dialogs -->
    <OrderEditProductDialog
      v-model:visible="editProductDialogVisible"
      v-model:edit-product-form="editProductForm"
      v-model:options-table-data="optionsTableData"
      v-model:options-json-text="optionsJsonText"
      :editing-product="editingProduct"
      :saving-product="savingProduct"
      :options-edit-mode="optionsEditMode"
      :product-option-fields="productOptionFields"
      :options-json-error="optionsJsonError"
      :edited-product-cost="editedProductCost"
      :format-price="formatPrice"
      :switch-options-mode="switchOptionsMode"
      :on-option-type-change="onOptionTypeChange"
      :on-field-key-change="onFieldKeyChange"
      :sync-table-to-json="syncTableToJson"
      :remove-option-row="removeOptionRow"
      :add-option-row="addOptionRow"
      :cancel-edit-product="cancelEditProduct"
      :save-edited-product="saveEditedProduct"
    />

    <OrderAddProductDialog
      v-model:visible="addProductDialogVisible"
      v-model:selected-product="selectedProduct"
      v-model:add-product-form="addProductForm"
      :product-suggestions="productSuggestions"
      :searching-products="searchingProducts"
      :saving-new-product="savingNewProduct"
      :new-product-cost="newProductCost"
      :format-price="formatPrice"
      :search-products="searchProducts"
      :on-product-select="onProductSelect"
      :cancel-add-product="cancelAddProduct"
      :save-new-product="saveNewProduct"
    />

    <OrderDuplicateCustomerDialog
      v-model:visible="showDuplicateDialog"
      :duplicate-customer="duplicateCustomer"
      :cancel-duplicate-dialog="cancelDuplicateDialog"
      :use-duplicate-customer="useDuplicateCustomer"
      :create-new-customer-anyway="createNewCustomerAnyway"
    />

    <!-- Header -->
    <div class="order-header mb-3">
      <Button
        icon="pi pi-arrow-left"
        :label="_('back_to_orders')"
        severity="secondary"
        text
        @click="goBack"
      />
      <h2 v-if="isCreateMode">{{ _('ms3_order_new') }}</h2>
      <h2 v-else-if="order">{{ _('order') }} {{ order.num ? '#' + order.num : '' }}</h2>
    </div>

    <div v-if="loading" class="loading-state">
      <i class="pi pi-spin pi-spinner" style="font-size: 2rem"></i>
      <p>{{ _('loading') }}</p>
    </div>

    <template v-else-if="order">
      <Tabs v-model:value="orderActiveTab">
        <TabList>
          <Tab v-for="t in orderTabsConfig" :key="t.key" :value="t.key">{{ t.title }}</Tab>
        </TabList>
        <TabPanels>
          <TabPanel v-for="tab in orderTabsConfig" :key="tab.key" :value="tab.key">
            <OrderInfoTab
              v-if="tab.key === 'info'"
              :order-fields-by-section="orderFieldsBySection"
              :order-extra-fields="orderExtraFields"
            />
            <OrderProductsTab
              v-else-if="tab.key === 'products'"
              :products="products"
              :products-columns="productsColumns"
            />
            <OrderAddressTab
              v-else-if="tab.key === 'address'"
              v-model:selected-customer="selectedCustomer"
              v-model:create-customer-from-data="createCustomerFromData"
              :address-fields-by-section="addressFieldsBySection"
              :address-extra-fields="addressExtraFields"
            />
            <OrderHistoryTab v-else-if="tab.key === 'history'" :logs="logs" />
            <template v-else-if="tab.kind === 'plugin' && tab.type === 'vue' && tab.component">
              <component
                :is="tab.component"
                v-if="orderActiveTab === tab.key"
                v-bind="pluginVueProps(tab)"
              />
            </template>
            <div
              v-else-if="tab.kind === 'plugin' && tab.type === 'extjs' && tab.xtype"
              :id="`ms3-order-tab-${tab.key}`"
              class="order-extjs-tab-container"
            />
          </TabPanel>
        </TabPanels>
      </Tabs>
    </template>

    <div v-else class="error-state">
      <i
        class="pi pi-exclamation-triangle"
        style="font-size: 3rem; color: var(--ms3-text-warning-accent)"
      ></i>
      <p>{{ _('order_not_found') }}</p>
      <Button :label="_('back_to_orders')" @click="goBack" />
    </div>
  </div>
</template>

<style scoped>
.order-view {
  padding: 1.25rem;
}

.order-extjs-tab-container {
  min-height: 18.75rem;
  width: 100%;
}

.order-extjs-tab-container :deep(.x-panel) {
  width: 100% !important;
}

.order-extjs-tab-container :deep(.x-panel-body) {
  padding: 0.625rem;
}

.order-header {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.order-header h2 {
  margin: 0;
  font-size: 1.5rem;
  color: var(--ms3-text-darkest);
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

.mb-3 {
  margin-bottom: 1rem;
}
</style>
